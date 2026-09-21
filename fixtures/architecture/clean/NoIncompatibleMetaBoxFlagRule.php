<?php
declare(strict_types=1);

namespace Fixture\Clean\MetaBoxFlag;

// EXPECT-NONE: no compatibility flag is carried.
add_meta_box('howdah_series', 'Series', 'howdah_render', 'post', 'normal', 'default', ['priority' => 'high']);
