<?php
declare(strict_types=1);

namespace Fixture\Violations\PostsPerPage;

$query = new \WP_Query(['posts_per_page' => -1]); // EXPECT: mahout.arch.noUnboundedPostsPerPage
