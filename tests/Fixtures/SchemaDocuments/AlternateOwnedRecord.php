<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Fixtures\SchemaDocuments;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'owned_records')]
class AlternateOwnedRecord
{
    #[ODM\Id]
    public ?string $id = null;
}
