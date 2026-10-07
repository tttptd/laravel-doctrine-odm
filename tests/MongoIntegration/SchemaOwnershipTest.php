<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\MongoIntegration;

use Doctrine\ODM\MongoDB\DocumentManager;
use Illuminate\Contracts\Console\Kernel;
use MongoDB\Driver\Exception\BulkWriteException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Ys\LaravelOdm\Commands\LaravelCreateCommand;
use Ys\LaravelOdm\Commands\LaravelDropCommand;
use Ys\LaravelOdm\Commands\LaravelUpdateCommand;
use Ys\LaravelOdm\ODM\SchemaOwnershipRegistry;
use Ys\LaravelOdm\Tests\Fixtures\PrepareOwnedSchemaCommand;
use Ys\LaravelOdm\Tests\Fixtures\SchemaDocuments\AlternateOwnedRecord;
use Ys\LaravelOdm\Tests\Fixtures\SchemaDocuments\OrdinaryRecord;
use Ys\LaravelOdm\Tests\Fixtures\SchemaDocuments\OwnedRecord;
use Ys\LaravelOdm\Tests\TestCase;

final class SchemaOwnershipTest extends TestCase
{
    private string $databaseName;
    private ?DocumentManager $dm = null;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->databaseName = 'laravel_odm_schema_test_' . getmypid() . '_' . bin2hex(random_bytes(8));
        $app['config']->set('mongodb.connection.server', getenv('ODM_MONGO_TEST_URI') ?: 'mongodb://127.0.0.1:27017');
        $app['config']->set('mongodb.connection.options.db', $this->databaseName);
        $app['config']->set('mongodb.paths.documents', [__DIR__ . '/../Fixtures/SchemaDocuments']);
        $app['config']->set('mongodb.paths.hydrators.auto_generate', 3);
        $app['config']->set('mongodb.use_transactional_flush', false);
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('ODM_MONGO_TESTS') !== '1') {
            self::markTestSkipped('Для изолированной MongoDB-проверки задайте ODM_MONGO_TESTS=1.');
        }
        $this->assertIsolatedDatabase();
        $this->dm = $this->app->make(DocumentManager::class);
        // Имитирует configured names потребителя; атрибуты обоих mapping прежние.
        foreach ([OwnedRecord::class, AlternateOwnedRecord::class] as $class) {
            $this->dm->getClassMetadata($class)->setCollection('configured_owned_records');
        }
        $this->app->make(SchemaOwnershipRegistry::class)->registerDocument(
            'fixture/owner', OwnedRecord::class, 'fixture:schema:prepare',
        );
        $this->app->make(Kernel::class)->registerCommand($this->app->make(PrepareOwnedSchemaCommand::class));
    }

    protected function tearDown(): void
    {
        try {
            if ($this->dm !== null) {
                $this->assertIsolatedDatabase();
                $this->dm->getClient()->selectDatabase($this->databaseName)->drop();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testTwoUpdatesPreserveOwnedDataIndexesValidatorAndRuntime(): void
    {
        $this->prepareOwner();
        $record = new OwnedRecord();
        $this->dm->persist($record);
        $this->dm->flush();
        $ownedBefore = $this->snapshot();

        $ordinary = $this->dm->getDocumentCollection(OrdinaryRecord::class);
        $ordinary->insertOne(['title' => 'Обычная запись']);
        $ordinary->createIndex(['obsolete' => 1], ['name' => 'obsolete_index']);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            self::assertSame(0, $this->runCommand('update', ['--skip-search-indexes' => true])->getStatusCode());
            self::assertSame($ownedBefore, $this->snapshot());
        }
        $names = array_map(static fn($index) => $index->getName(), iterator_to_array($ordinary->listIndexes()));
        self::assertContains('ordinary_title', $names);
        self::assertNotContains('obsolete_index', $names);

        $this->dm->clear();
        self::assertSame('Сохранённая запись', $this->dm->find(OwnedRecord::class, $record->id)->title);
        self::assertSame('configured_owned_records', $this->dm->getClassMetadata(OwnedRecord::class)->getCollection());
        self::assertSame(3, count($this->dm->getMetadataFactory()->getAllMetadata()));

        try {
            $this->dm->getDocumentCollection(OwnedRecord::class)->insertOne(['requestKey' => 'request-1']);
            self::fail('Уникальное ограничение владельца потеряно.');
        } catch (BulkWriteException $exception) {
            self::assertSame(11000, $exception->getCode());
        }
    }

    public function testCreateAndCollectionDropOnlyChangeUnregisteredSchema(): void
    {
        $this->prepareOwner();
        $before = $this->snapshot();
        self::assertSame(0, $this->runCommand('create', ['--skip-search-indexes' => true])->getStatusCode());
        self::assertSame($before, $this->snapshot());
        $ordinary = $this->dm->getDocumentCollection(OrdinaryRecord::class);
        self::assertContains('ordinary_title', array_map(static fn($index) => $index->getName(), iterator_to_array($ordinary->listIndexes())));

        self::assertSame(0, $this->runCommand('drop', ['--index' => true])->getStatusCode());
        self::assertSame($before, $this->snapshot());
        self::assertCount(1, iterator_to_array($ordinary->listIndexes()));
        self::assertSame(0, $this->runCommand('drop', ['--collection' => true])->getStatusCode());
        self::assertSame($before, $this->snapshot());
        self::assertNotContains('ordinary_records', iterator_to_array($this->dm->getDocumentDatabase(OwnedRecord::class)->listCollectionNames()));
    }

    #[DataProvider('targetedCommands')]
    public function testTargetedAliasAndDatabaseDropFailBeforeAnyChange(string $command, array $options): void
    {
        $this->prepareOwner();
        $ordinary = $this->dm->getDocumentCollection(OrdinaryRecord::class);
        $ordinary->insertOne(['title' => 'Запись до отказа']);
        $ordinary->createIndex(['extra' => 1], ['name' => 'must_survive']);
        $before = $this->snapshot();
        $ordinaryBefore = json_encode(iterator_to_array($ordinary->listIndexes()), JSON_THROW_ON_ERROR);

        $tester = $this->runCommand($command, $options);
        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsString('fixture/owner', $tester->getDisplay());
        self::assertStringContainsString('fixture:schema:prepare', $tester->getDisplay());
        self::assertSame($before, $this->snapshot());
        self::assertSame($ordinaryBefore, json_encode(iterator_to_array($ordinary->listIndexes()), JSON_THROW_ON_ERROR));
        self::assertSame(1, $ordinary->countDocuments());
    }

    public static function targetedCommands(): array
    {
        return [
            'update alias' => ['update', ['--class' => AlternateOwnedRecord::class]],
            'create alias' => ['create', ['--class' => AlternateOwnedRecord::class]],
            'drop alias' => ['drop', ['--class' => AlternateOwnedRecord::class, '--collection' => true]],
            'drop DB' => ['drop', ['--db' => true, '--index' => true]],
            'default drop includes DB' => ['drop', ['--skip-search-indexes' => true]],
            'drop ordinary class DB' => ['drop', ['--class' => OrdinaryRecord::class, '--db' => true]],
        ];
    }

    #[DataProvider('allProtectedCommands')]
    public function testEveryModeSkipsProtectedSchemas(string $command, array $options): void
    {
        $this->prepareOwner();
        $this->app->make(SchemaOwnershipRegistry::class)->registerDocument(
            'fixture/ordinary', OrdinaryRecord::class, 'fixture:ordinary:prepare',
        );
        $before = $this->snapshot();
        $tester = $this->runCommand($command, $options);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('fixture/owner', $tester->getDisplay());
        self::assertSame($before, $this->snapshot());
    }

    public static function allProtectedCommands(): array
    {
        return [
            'full update including search and validator' => ['update', []],
            'update without validators' => ['update', ['--disable-validators' => true]],
            'full create including search' => ['create', []],
            'create collection' => ['create', ['--collection' => true]],
            'create index background' => ['create', ['--index' => true, '--background' => true]],
            'create search' => ['create', ['--search-index' => true]],
            'drop collection' => ['drop', ['--collection' => true]],
            'drop index' => ['drop', ['--index' => true]],
            'drop search' => ['drop', ['--search-index' => true]],
        ];
    }

    public function testConflictingPhysicalOwnersFailBeforeUpdatingOrdinaryCollection(): void
    {
        $this->prepareOwner();
        $ordinary = $this->dm->getDocumentCollection(OrdinaryRecord::class);
        $ordinary->createIndex(['extra' => 1], ['name' => 'must_survive']);
        $this->app->make(SchemaOwnershipRegistry::class)->registerDocument(
            'fixture/other', AlternateOwnedRecord::class, 'fixture:other:prepare',
        );
        $before = $this->snapshot();
        $tester = $this->runCommand('update', ['--skip-search-indexes' => true]);
        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Конфликт владения', $tester->getDisplay());
        self::assertSame($before, $this->snapshot());
        self::assertContains('must_survive', array_map(static fn($index) => $index->getName(), iterator_to_array($ordinary->listIndexes())));
    }

    public function testSameCollectionNameInAnotherDatabaseDoesNotProtectOrdinaryMapping(): void
    {
        $this->app->make(SchemaOwnershipRegistry::class)->registerCollection(
            'fixture/foreign', $this->databaseName . '_foreign', 'ordinary_records', 'fixture:foreign:prepare',
        );
        $tester = $this->runCommand('create', ['--class' => OrdinaryRecord::class, '--collection' => true, '--index' => true]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $ordinary = $this->dm->getDocumentCollection(OrdinaryRecord::class);
        self::assertContains('ordinary_title', array_map(static fn($index) => $index->getName(), iterator_to_array($ordinary->listIndexes())));
    }

    public function testWithoutRegistrationNativeSchemaUpdateRetainsItsPreviousBehavior(): void
    {
        $this->prepareOwner();
        $this->app->instance(SchemaOwnershipRegistry::class, new SchemaOwnershipRegistry());
        $tester = $this->runCommand('update', ['--skip-search-indexes' => true]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $collection = $this->dm->getDocumentCollection(OwnedRecord::class);
        self::assertNotContains('additional_index', array_map(static fn($index) => $index->getName(), iterator_to_array($collection->listIndexes())));
    }

    private function prepareOwner(): void
    {
        $this->artisan('fixture:schema:prepare')->assertExitCode(0);
    }

    private function runCommand(string $name, array $options): CommandTester
    {
        $class = match ($name) {
            'create' => LaravelCreateCommand::class,
            'update' => LaravelUpdateCommand::class,
            'drop' => LaravelDropCommand::class,
        };
        $application = new Application();
        $command = $this->app->make($class);
        $application->add($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $tester->execute($options);

        return $tester;
    }

    private function snapshot(): string
    {
        $collection = $this->dm->getDocumentCollection(OwnedRecord::class);
        $options = iterator_to_array($this->dm->getDocumentDatabase(OwnedRecord::class)->listCollections([
            'filter' => ['name' => $collection->getCollectionName()],
        ]))[0]->getOptions();

        return json_encode([
            iterator_to_array($collection->listIndexes()),
            $options,
            iterator_to_array($collection->find()),
        ], JSON_THROW_ON_ERROR);
    }

    private function assertIsolatedDatabase(): void
    {
        if (! preg_match('/^laravel_odm_schema_test_' . getmypid() . '_[a-f0-9]{16}$/D', $this->databaseName)) {
            throw new \RuntimeException('Отказ операции над базой вне изолированной fixture.');
        }
    }
}
