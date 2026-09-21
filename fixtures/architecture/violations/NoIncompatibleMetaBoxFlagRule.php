<?php
declare(strict_types=1);

namespace Fixture\Violations\MetaBoxFlag;

add_meta_box('howdah_series', 'Series', 'howdah_render', 'post', 'normal', 'default', ['__back_compat_meta_box' => true]); // EXPECT: mahout.arch.noIncompatibleMetaBoxFlag
