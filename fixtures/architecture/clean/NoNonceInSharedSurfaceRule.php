<?php
declare(strict_types=1);

namespace Fixture\Clean\NonceShared;

// EXPECT-NONE: a nonce is allowed once the surface is not Shared.
final class PrivateSurface
{
    private const string ACTION = 'howdah_save';

    public function cacheability(): string
    {
        return Cacheability::Private;
    }

    public function form(): string
    {
        return wp_nonce_field(self::ACTION);
    }
}
