<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Fixtures\SchemaDocuments;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'ordinary_records')]
#[ODM\Index(keys: ['title' => 'asc'], options: ['name' => 'ordinary_title'])]
class OrdinaryRecord
{
    #[ODM\Id]
    public ?string $id = null;

    #[ODM\Field(type: 'string')]
    public string $title = 'Обычная запись';
}
