<?php
declare(strict_types=1);

namespace Fixture\Clean\SecurityHeader;

// EXPECT-NONE: core owns the frame and content-type options headers.
header('Content-Type: text/html');
