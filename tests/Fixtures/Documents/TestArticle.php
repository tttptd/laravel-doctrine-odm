<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Fixtures\Documents;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'test_articles')]
final class TestArticle
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $title = '';
}

