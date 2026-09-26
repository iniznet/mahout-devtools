<?php
declare(strict_types=1);

namespace Fixture\Clean\DisabledFoundRows;

// EXPECT-NONE

// The hardened default, and the one-past-the-page fetch that answers "is there a
// next page" without counting the whole range.
$args = ['post_type' => 'series', 'no_found_rows' => true, 'posts_per_page' => 25];

$declared = static fn (bool $noFoundRows = true): bool => $noFoundRows;

// A value that is not the literal stays legal: the ban is on a call site opening
// the default, not on passing a variable through.
$passed = ['no_found_rows' => $declared()];
