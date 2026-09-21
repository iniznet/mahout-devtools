<?php
declare(strict_types=1);

namespace Fixture\Clean\PostsPerPage;

// EXPECT-NONE: the query is paged.
$query = new \WP_Query(['posts_per_page' => 20]);
