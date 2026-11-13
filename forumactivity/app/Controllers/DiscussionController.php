<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Comment;
use App\Models\Discussion;
use App\Support\CommentTree;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

final class DiscussionController extends Controller
{
    /** GET / */
    public function index(): void
    {
        $discussions = array_map(function (array $d): array {
            $time = self::formatUtc($d['created_at']);
            return [
                'id'            => $d['id'],
                'title'         => $d['title'],
                'excerpt'       => mb_strimwidth($d['body'], 0, 140, '…'),
                'author'        => $d['author'],
                'created_iso'   => $time['iso'],
                'created_human' => $time['display'],
                'comment_count' => (int) $d['comment_count'],
                'one_comment'   => (int) $d['comment_count'] === 1,
            ];
        }, (new Discussion())->all());

        View::render('discussions/index', [
            'page_title'  => 'Discussions',
            'discussions' => $discussions,
        ]);
    }

    /** GET /discussions/{id} */
    public function show(string $rawId): void
    {
        // 1. Validate the ID before touching the database.
        $id = $this->parseId($rawId);
        if ($id === null) {
            Response::error(400, 'Bad request', 'A discussion ID must be a positive whole number, such as /discussions/1.');
            return;
        }

        // 2. Fetch the discussion; the comment query runs only if it exists.
        $discussion = (new Discussion())->find($id);
        if ($discussion === null) {
            Response::error(404, 'Discussion not found', "There is no discussion with ID {$id}.");
            return;
        }

        // 3. Fetch comments, group them by parent and build the view tree.
        $rows = (new Comment())->forDiscussion($id);
        $loggedIn = Session::isLoggedIn();
        $maxReplyDepth = $this->config['max_reply_depth'];

        $tree = new CommentTree(
            $this->config['indent_cap'],
            $this->config['max_traversal_depth'],
            $this->config['max_rendered_comments'],
        );
        $comments = $tree->build($rows, function (array $row, int $depth, int $indent) use ($loggedIn, $maxReplyDepth, $id): array {
            $time = self::formatUtc($row['created_at']);
            return [
                'id'             => $row['id'],
                'discussion_id'  => $id,
                'author'         => $row['author'],
                'author_initial' => mb_strtoupper(mb_substr($row['author'], 0, 1)),
                'avatar_url'     => '/assets/img/avatar-default.svg',
                'body'           => $row['body'],
                'created_iso'    => $time['iso'],
                'created_human'  => $time['display'],
                'depth'          => $depth,
                'indent'         => $indent,
                'tone'           => $depth % 4,              // cycles the four palette colours
                'capped'         => $depth > $indent,         // label still shows the true depth
                'can_reply'      => $loggedIn && ($depth + 1) <= $maxReplyDepth,
            ];
        });

        Session::remember('return_to', "/discussions/{$id}");
        $time = self::formatUtc($discussion['created_at']);

        View::render('discussions/show', [
            'page_title'    => $discussion['title'],
            'discussion'    => [
                'id'            => $discussion['id'],
                'title'         => $discussion['title'],
                'body'          => $discussion['body'],
                'author'        => $discussion['author'],
                'created_iso'   => $time['iso'],
                'created_human' => $time['display'],
            ],
            'comments'      => $comments,
            'has_comments'  => $comments !== [],
            'comment_total' => $tree->rendered,
            'thread_height' => $tree->height,
            'unreachable'   => $tree->unreachable,
            'max_length'    => $this->config['comment_max_length'],
            'indent_cap'    => $this->config['indent_cap'],
        ]);
    }
}
