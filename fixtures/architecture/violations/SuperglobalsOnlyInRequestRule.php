<?php
declare(strict_types=1);

namespace Fixture\Violations\Superglobals;

$genre = $_GET['genre'] ?? null; // EXPECT: mahout.arch.superglobalsOnlyInRequest
