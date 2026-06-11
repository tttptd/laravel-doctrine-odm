<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Fixtures\PackageDocuments;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'test_package_pages')]
final class TestPackagePage
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $title = '';
}
