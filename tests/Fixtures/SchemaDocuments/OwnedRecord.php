<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Fixtures\SchemaDocuments;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'owned_records')]
#[ODM\Index(keys: ['title' => 'asc'], options: ['name' => 'owner_title'])]
class OwnedRecord
{
    #[ODM\Id]
    public ?string $id = null;

    #[ODM\Field(type: 'string')]
    public string $title = 'Сохранённая запись';

    #[ODM\Field(type: 'string')]
    public string $requestKey = 'request-1';
}
