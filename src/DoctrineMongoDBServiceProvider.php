<?php
declare(strict_types=1);

namespace Ys\LaravelOdm;

use Doctrine\Common\EventManager;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver;
use Doctrine\ODM\MongoDB\Tools\Console\Helper\DocumentManagerHelper;
use Gedmo\Mapping\Driver\AttributeReader;
use Gedmo\Sluggable\SluggableListener;
use Gedmo\Timestampable\TimestampableListener;
use Illuminate\Support\ServiceProvider;
use MongoDB\Client;
use Symfony\Component\Console\Helper\HelperSet;
use Ys\LaravelOdm\Commands\LaravelClearMetadataCommand;
use Ys\LaravelOdm\Commands\LaravelCreateCommand;
use Ys\LaravelOdm\Commands\LaravelDropCommand;
use Ys\LaravelOdm\Commands\LaravelGenerateHydratorsCommand;
use Ys\LaravelOdm\Commands\LaravelGenerateProxiesCommand;
use Ys\LaravelOdm\Commands\LaravelQueryCommand;
use Ys\LaravelOdm\Commands\LaravelShardCommand;
use Ys\LaravelOdm\Commands\LaravelUpdateCommand;
use Ys\LaravelOdm\ODM\CacheAdapterFactory;
use Ys\LaravelOdm\ODM\DocumentPathRegistry;
use Ys\LaravelOdm\ODM\DocumentManagerFactory;
use function config;
use const PHP_VERSION_ID;

class DoctrineMongoDBServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/mongodb.php' => config_path('mongodb.php'),
        ]);
    }

    public function register()
    {
        $this->mergeConfig();
        $this->registerDocumentPathRegistry();
        $this->registerDocumentManager();
        $this->registerConsoleCommands();

        // Почему именно scoped:
        // - singleton здесь неверен, потому что PersistenceManager держит конкретный
        //   stateful DocumentManager с identity map / unit of work;
        // - bind тоже не подходит как основной lifetime, потому что внутри одного request/job
        //   разные участки кода могли бы получить разные экземпляры PM/DM и потерять единый UoW;
        // - scoped даёт ровно один экземпляр на lifecycle request/job, что соответствует
        //   семантике Doctrine session и при этом автоматически сбрасывается Laravel'ом
        //   между queue job'ами через forgetScopedInstances().
        //
        // Иначе говоря, scoped здесь нужен не для "экономии" или "удобства DI", а для того,
        // чтобы Doctrine жила в естественной для неё модели: один manager на одну рабочую границу.
        $this->app->scoped(
            \Ys\LaravelOdm\ODM\PersistenceManager::class,
            \Ys\LaravelOdm\ODM\PersistenceManagerDoctrine::class,
        );
    }

    private function registerConsoleCommands(): void
    {
        $this->app->singleton(HelperSet::class, function($app) {
            // HelperSet нужен только для console tooling Doctrine.
            // Здесь допустимо один раз разрешить scoped DocumentManager: сам helper
            // используется только внутри текущего console process.
            $documentManager = $app[DocumentManager::class];
            $helperSet = new HelperSet();
            $helperSet->set(new DocumentManagerHelper($documentManager), 'documentManager');

            return $helperSet;
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                LaravelGenerateHydratorsCommand::class,
                LaravelGenerateProxiesCommand::class,
                LaravelQueryCommand::class,
                LaravelClearMetadataCommand::class,
                LaravelCreateCommand::class,
                LaravelDropCommand::class,
                LaravelUpdateCommand::class,
                LaravelShardCommand::class,
            ]);
        }
    }

    protected function mergeConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/mongodb.php', 'mongodb',
        );
    }

    private function registerDocumentPathRegistry(): void
    {
        $this->app->singleton(DocumentPathRegistry::class, static function() {
            $paths = config('mongodb.paths', []);
            $registry = new DocumentPathRegistry();

            foreach ($paths['documents'] ?? [] as $path) {
                if (is_string($path)) {
                    $registry->addDocumentPath($path);
                }
            }

            foreach ($paths['exclude_documents'] ?? [] as $path) {
                if (is_string($path)) {
                    $registry->addExcludePath($path);
                }
            }

            return $registry;
        });
    }

    private function registerDocumentManager(): void
    {
        $this->app->singleton(DocumentManagerFactory::class, static function($app) {
            $appConfig = config('mongodb');
            $documentPathRegistry = $app->make(DocumentPathRegistry::class);

            // Define our global cache backend for the application.
            // For larger applications, you may use multiple cache pools to store cacheable data in different locations.
            $cacheConfig = $appConfig['cache']['metadata'] ?? ['driver' => 'array'];
            $cache = CacheAdapterFactory::create($cacheConfig);
            // dd($cacheConfig);

            // MongoDB ODM appConfig

            $config = new Configuration();

            $paths = $appConfig['paths'];
            $config->setProxyDir($paths['proxies']['path']);
            $config->setProxyNamespace($paths['proxies']['namespace']);
            $config->setHydratorDir($paths['hydrators']['path']);
            $config->setHydratorNamespace($paths['hydrators']['namespace']);
            $metadataDriver = AttributeDriver::create($documentPathRegistry->documentPaths());
            if ($documentPathRegistry->excludePaths() !== []) {
                $metadataDriver->addExcludePaths($documentPathRegistry->excludePaths());
            }
            $config->setMetadataDriverImpl($metadataDriver);
            $config->setAutoGenerateProxyClasses((int)$paths['proxies']['auto_generate']);
            $config->setMetadataCache($cache);
            $config->setUseTransactionalFlush($appConfig['use_transactional_flush'] ?? false);

            $connection = $appConfig['connection'];
            $config->setDefaultDB($connection['options']['db']);
            $client = new Client($connection['server']);


            // Init Gedmo DoctrineExtensions

            // https://github.com/doctrine-extensions/DoctrineExtensions/blob/8d658b4d22977e3b72f02bfe4e68b2df0ba586aa/example/em.php#L73
            $extensionReader = null;
            // For PHP 8, we will provide the extensions an attribute reader, while PHP 7 will require the annotation reader
            // (which will only be created when `doctrine/annotations` is installed)
            if (PHP_VERSION_ID >= 80000) {
                $extensionReader = new AttributeReader();
            }
            $eventManager = new EventManager();
            // Timestampable extension
            $timestampableListener = new TimestampableListener();
            $timestampableListener->setAnnotationReader($extensionReader);
            $timestampableListener->setCacheItemPool($cache);
            $eventManager->addEventSubscriber($timestampableListener);

            // Sluggable extension
            $sluggableListener = new SluggableListener();
            $sluggableListener->setAnnotationReader($extensionReader);
            $eventManager->addEventSubscriber($sluggableListener);

            return new DocumentManagerFactory(
                client: $client,
                configuration: $config,
                eventManager: $eventManager,
            );
        });

        // Почему DocumentManager регистрируется через scoped:
        // - DocumentManager — это не stateless service, а Doctrine session с identity map,
        //   tracked documents и накопленным unit of work;
        // - singleton в long-lived process приводит к stale entities: следующий job/request
        //   может получить объект не из Mongo, а из памяти предыдущего lifecycle;
        // - обычный bind создавал бы новый manager при каждом make(), что ломало бы идею
        //   "один UoW на один request/job": разные сервисы в рамках одной операции могли бы
        //   работать с разными manager'ами и flush'ить несогласованное состояние;
        // - scoped даёт компромисс, который Doctrine и ожидает в PHP-приложении:
        //   один manager на текущий request/job и автоматический reset между job'ами у worker'а.
        //
        // Это особенно важно для queue:work. Worker живёт долго, но сам Laravel перед каждой
        // job очищает scoped instances. Поэтому scoped решает stale-state проблему на уровне
        // контейнера, а не через разрозненные clear() по бизнес-коду.
        $this->app->scoped(DocumentManager::class, static function($app) {
            return $app->make(DocumentManagerFactory::class)->create();
        });
    }
}
