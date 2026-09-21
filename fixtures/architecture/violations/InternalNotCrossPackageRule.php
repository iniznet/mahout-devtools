<?php
declare(strict_types=1);

namespace Iniznet\Mahout\Content\Fixture;

use Iniznet\Mahout\Assets\Internal\AssetUrl;

final class Consumer
{
    public function render(AssetUrl $url): void // EXPECT: mahout.arch.internalNotCrossPackage
    {
    }
}
