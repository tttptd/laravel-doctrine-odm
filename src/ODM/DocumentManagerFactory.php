<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\ODM;

use Doctrine\Common\EventManager;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Client;

/**
 * Создаёт свежий DocumentManager поверх заранее собранных shared-зависимостей.
 *
 * Почему фабрика нужна отдельно:
 * - Configuration, MongoDB Client, metadata cache и Gedmo listeners дорого инициализировать
 *   на каждый request/job;
 * - сам DocumentManager, наоборот, stateful: он держит identity map и unit of work,
 *   поэтому его lifetime должен быть коротким и совпадать с request/job scope;
 * - long-lived queue worker не должен переиспользовать один и тот же DocumentManager
 *   между job'ами, иначе find() начинает возвращать stale documents из памяти.
 */
final readonly class DocumentManagerFactory
{
    public function __construct(
        private Client $client,
        private Configuration $configuration,
        private EventManager $eventManager,
    ) {
    }

    /**
     * Возвращает новый DocumentManager для текущего lifecycle.
     *
     * Важно: метод всегда создаёт новый manager, а не кэширует его внутри фабрики.
     * Shared остаются только connection/config/listeners, а не identity map.
     */
    public function create(): DocumentManager
    {
        return DocumentManager::create(
            client: $this->client,
            config: $this->configuration,
            eventManager: $this->eventManager,
        );
    }
}
