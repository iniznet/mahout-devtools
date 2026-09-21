<?php
declare(strict_types=1);

namespace Fixture\Violations\Reflection;

$reflection = new \ReflectionClass(\stdClass::class); // EXPECT: mahout.arch.reflectionOnlyInTestsAndRules
