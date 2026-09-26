<?php
declare(strict_types=1);

namespace Fixture\Violations\DisabledFoundRows;

final class Spec
{
    public function __construct(public bool $noFoundRows = true)
    {
    }
}

// EXPECT: mahout.arch.noDisabledFoundRows

$args = ['post_type' => 'series', 'no_found_rows' => false];

$spec = new Spec(noFoundRows: false);

$reader = new Spec();
$reader->withPage(2, noFoundRows: false);
