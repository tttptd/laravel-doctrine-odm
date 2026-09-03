<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Fixtures\PackageDocuments;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\EmbeddedDocument]
final class TestPackageBlock
{
    #[ODM\Field(type: 'string')]
    private string $title = '';
}
