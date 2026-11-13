<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Data access for the comments table, joined to each comment's author. */
final class Comment
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /** Every comment in one discussion, oldest first, with author fields. */
    public function forDiscussion(int $discussionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.parent_id, c.body, c.created_at,
                    u.username AS author, u.avatar_filename, u.use_gravatar
               FROM comments c
               JOIN users u ON u.id = c.user_id
              WHERE c.discussion_id = :discussion_id
           ORDER BY c.created_at ASC, c.id ASC'
        );
        $stmt->execute(['discussion_id' => $discussionId]);
        return $stmt->fetchAll();
    }

    /** One comment's parent link, only if it belongs to the given discussion. */
    public function findInDiscussion(int $id, int $discussionId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, parent_id FROM comments WHERE id = :id AND discussion_id = :discussion_id'
        );
        $stmt->execute(['id' => $id, 'discussion_id' => $discussionId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Displayed depth of an existing comment (top level = 0), found by walking
     * parent links upward. Returns null if the walk revisits a comment (a cycle)
     * or exceeds $limit steps, so the caller can refuse the reply.
     */
    public function depthOf(int $id, int $discussionId, int $limit): ?int
    {
        $depth = 0;
        $seen = [];
        $current = $this->findInDiscussion($id, $discussionId);

        while ($current !== null) {
            if (isset($seen[$current['id']]) || $depth > $limit) {
                return null;
            }
            $seen[$current['id']] = true;
            if ($current['parent_id'] === null) {
                return $depth;
            }
            $depth++;
            $current = $this->findInDiscussion((int) $current['parent_id'], $discussionId);
        }

        return null; // broken chain
    }

    /** Inserts a comment; user_id always comes from the session, never the form. */
    public function create(int $discussionId, ?int $parentId, int $userId, string $body): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO comments (discussion_id, parent_id, user_id, body)
             VALUES (:discussion_id, :parent_id, :user_id, :body)'
        );
        $stmt->bindValue('discussion_id', $discussionId, PDO::PARAM_INT);
        $stmt->bindValue('parent_id', $parentId, $parentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('body', $body, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }
}
