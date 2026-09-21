<?php
declare(strict_types=1);

namespace Fixture\Clean\QuickEdit;

const SAVE_POST = 'save_post';

// EXPECT-NONE: save_post carries the save lifecycle, named by a constant.
add_action(SAVE_POST, 'howdah_save');
