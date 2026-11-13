<?php

declare(strict_types=1);

return [
    'name' => 'Sage & Orchid Forum',
    // Indentation stops growing at this displayed depth; labels stay accurate.
    'indent_cap' => 8,
    // Write handler: the deepest displayed depth a new reply may have.
    'max_reply_depth' => 15,
    // Renderer traversal guard: never walk deeper than this, whatever the data says.
    'max_traversal_depth' => 64,
    // Renderer guard: never render more nodes than this for one discussion.
    'max_rendered_comments' => 2000,
    'comment_max_length' => 2000,
];
