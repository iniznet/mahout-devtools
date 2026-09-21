<?php
declare(strict_types=1);

namespace Fixture\Violations\ErrorLog;

error_log('booted'); // EXPECT: mahout.arch.errorLogOnlyInDiagnostics
