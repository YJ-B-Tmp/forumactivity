<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Comment;
use App\Models\Discussion;
use App\Support\Response;
use App\Support\Session;

final class CommentController extends Controller
{
    /** POST /discussions/{id}/comments  (top-level comment or reply) */
    public function store(string $rawId): void
    {
        // 1. Authentication is checked on the server for every write.
        $userId = Session::userId();
        if ($userId === null) {
            Session::flash('error', 'Log in to post a reply.');
            Response::redirect('/login');
            return;
        }

        // 2. CSRF token.
        if (!$this->checkCsrf()) {
            return;
        }

        // 3. Discussion ID from the route.
        $discussionId = $this->parseId($rawId);
        if ($discussionId === null) {
            Response::error(400, 'Bad request', 'A discussion ID must be a positive whole number.');
            return;
        }
        if (!(new Discussion())->exists($discussionId)) {
            Response::error(404, 'Discussion not found', "There is no discussion with ID {$discussionId}.");
            return;
        }

        $back = "/discussions/{$discussionId}";
        $comments = new Comment();

        // 4. Optional parent: must be a comment in THIS discussion, within the depth limit.
        $parentId = null;
        $rawParent = $this->input('parent_id');
        if ($rawParent !== '') {
            $parentId = $this->parseId($rawParent);
            if ($parentId === null || $comments->findInDiscussion($parentId, $discussionId) === null) {
                Response::error(422, 'Reply rejected', 'You can only reply to a comment in the same discussion.');
                return;
            }
            $parentDepth = $comments->depthOf($parentId, $discussionId, $this->config['max_traversal_depth']);
            if ($parentDepth === null || $parentDepth + 1 > $this->config['max_reply_depth']) {
                Session::flash('error', 'That thread is already as deep as replies can go. Reply higher up instead.');
                Response::redirect($back . '#comment-' . $parentId);
                return;
            }
        }

        // 5. Content: trimmed, not empty, not too long, valid UTF-8.
        $body = trim(str_replace("\r\n", "\n", $this->input('body')));
        $max = $this->config['comment_max_length'];
        if ($body === '' || !mb_check_encoding($body, 'UTF-8') || mb_strlen($body) > $max) {
            Session::flash('error', "A comment must contain between 1 and {$max} characters.");
            Response::redirect($back . ($parentId ? '#comment-' . $parentId : '#new-comment'));
            return;
        }

        // 6. Save with the session user ID, then Post/Redirect/Get.
        $newId = $comments->create($discussionId, $parentId, $userId, $body);
        Session::flash('success', $parentId ? 'Reply posted.' : 'Comment posted.');
        Response::redirect($back . '#comment-' . $newId);
    }
}
