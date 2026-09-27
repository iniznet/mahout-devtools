<?php
declare(strict_types=1);

namespace Fixture\Violations\RawHookComposed;

// Its own file rather than a second EXPECT line in NoRawHookNameRule.php: the architecture
// test collects expectations per file and unique()s them, so a second occurrence of the same
// identifier in one fixture enforces nothing — confirmed by breaking the rule and watching
// the suite stay green.
//
// A hook name composed from a literal prefix is the same bypass as a literal name: the
// fragment sits in no Hooks class, so the generated reference cannot list it, and the rule
// used to return early because the argument is a Concat rather than a String_.
$pageHook = 'toplevel_page_howdah-status';

add_action('load-' . $pageHook, '__return_true'); // EXPECT: mahout.arch.noRawHookName
