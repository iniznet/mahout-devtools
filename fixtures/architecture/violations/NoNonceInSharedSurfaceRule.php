<?php
declare(strict_types=1);

namespace Fixture\Violations\NonceShared;

final class SharedSurface
{
    private const string ACTION = 'howdah_save';

    public function cacheability(): string
    {
        return Cacheability::Shared;
    }

    public function form(): string
    {
        return wp_nonce_field(self::ACTION); // EXPECT: mahout.arch.noNonceInSharedSurface
    }
}
