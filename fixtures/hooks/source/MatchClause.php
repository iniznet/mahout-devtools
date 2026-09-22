<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Fixtures\Hooks;

/**
 * A clause builder whose public constant is a database index name — not a
 * hook. The reference must not list it, and its missing @action/@filter tag
 * must not fail the scan.
 */
final class MatchClause
{
    public const INDEX_NAME = 'fixture/search_index';
}
