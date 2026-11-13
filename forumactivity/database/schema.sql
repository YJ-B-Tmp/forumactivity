-- Nested Forum Lab: schema and fictional seed data
-- Target: MySQL 8.0+ or MariaDB 10.6+ (utf8mb4, InnoDB).
-- Run:   mysql -u root -p < database/schema.sql
-- All users below are fictional. Seed users have NULL password hashes,
-- so they can never log in; register a new account to post.

DROP DATABASE IF EXISTS nested_forum;
CREATE DATABASE nested_forum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nested_forum;

-- Store and read every TIMESTAMP in UTC for this script's session.
SET time_zone = '+00:00';

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(30)  NOT NULL,
    email           VARCHAR(254) NOT NULL,
    -- NULL = account cannot log in (fictional seed users).
    password_hash   VARCHAR(255) NULL,
    -- Optional avatar extension fields (not used by the required lab).
    avatar_filename VARCHAR(64)  NULL,
    use_gravatar    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_username UNIQUE (username),
    CONSTRAINT uq_users_email    UNIQUE (email)
) ENGINE=InnoDB;

CREATE TABLE discussions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(150) NOT NULL,
    body        TEXT         NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_discussions_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB;

CREATE TABLE comments (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    discussion_id  INT UNSIGNED NOT NULL,
    -- NULL = top-level comment (an edge from the discussion root).
    parent_id      INT UNSIGNED NULL,
    user_id        INT UNSIGNED NOT NULL,
    body           TEXT         NOT NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Target for the composite foreign key below.
    CONSTRAINT uq_comments_discussion_id UNIQUE (discussion_id, id),
    KEY idx_comments_parent (discussion_id, parent_id),
    CONSTRAINT fk_comments_discussion FOREIGN KEY (discussion_id) REFERENCES discussions (id),
    CONSTRAINT fk_comments_user       FOREIGN KEY (user_id)       REFERENCES users (id),
    -- A parent must exist in the SAME discussion. When parent_id is NULL the
    -- constraint is not checked (MATCH SIMPLE), so top-level comments pass.
    CONSTRAINT fk_comments_parent_same_discussion
        FOREIGN KEY (discussion_id, parent_id) REFERENCES comments (discussion_id, id)
) ENGINE=InnoDB;

-- Parent links are immutable: a reply can only point at a row that already
-- exists, and no row may later be re-parented, so no cycle can ever form.
DELIMITER //
CREATE TRIGGER trg_comments_parent_immutable
BEFORE UPDATE ON comments
FOR EACH ROW
BEGIN
    IF NOT (NEW.parent_id <=> OLD.parent_id) OR NEW.discussion_id <> OLD.discussion_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'comments.parent_id and discussion_id are immutable';
    END IF;
END//
DELIMITER ;

-- ---------------------------------------------------------------------------
-- Fictional seed data
-- ---------------------------------------------------------------------------
INSERT INTO users (id, username, email, password_hash, created_at) VALUES
    (1, 'Alice', 'alice@example.test', NULL, '2026-11-01 01:00:00'),
    (2, 'Ben',   'ben@example.test',   NULL, '2026-11-01 01:05:00'),
    (3, 'Cara',  'cara@example.test',  NULL, '2026-11-01 01:10:00'),
    (4, 'Dee',   'dee@example.test',   NULL, '2026-11-01 01:15:00'),
    (5, 'Eli',   'eli@example.test',   NULL, '2026-11-01 01:20:00');

INSERT INTO discussions (id, user_id, title, body, created_at) VALUES
    (1, 1, 'How do I learn PHP?', 'Share your learning tips.', '2026-11-02 02:00:00'),
    (2, 3, 'Deep thread: indentation cap demo',
        'Replies here go past depth 8 so you can see indentation stop growing while the depth labels keep counting.',
        '2026-11-03 03:00:00');

-- Discussion 1: the reply tree from the lab handout (ids 1-7).
INSERT INTO comments (id, discussion_id, parent_id, user_id, body, created_at) VALUES
    (1, 1, NULL, 1, 'Start with the PHP manual.',    '2026-11-02 02:10:00'),
    (2, 1, 1,    2, 'Try the examples as you read.', '2026-11-02 02:20:00'),
    (3, 1, 2,    3, 'Build a small project too.',    '2026-11-02 02:30:00'),
    (4, 1, 1,    4, 'Which manual section first?',   '2026-11-02 02:40:00'),
    (5, 1, NULL, 5, 'Practice SQL as well.',         '2026-11-02 02:50:00'),
    (6, 1, 3,    2, 'Start with a forum.',           '2026-11-02 03:00:00'),
    (7, 1, 6,    5, 'Add replies next.',             '2026-11-02 03:10:00');

-- Discussion 2: one chain from displayed depth 0 to depth 10 (ids 8-18).
INSERT INTO comments (id, discussion_id, parent_id, user_id, body, created_at) VALUES
    (8,  2, NULL, 3, 'Depth 0: the chain starts here.',          '2026-11-03 03:05:00'),
    (9,  2, 8,    1, 'Depth 1.',                                 '2026-11-03 03:06:00'),
    (10, 2, 9,    2, 'Depth 2.',                                 '2026-11-03 03:07:00'),
    (11, 2, 10,   4, 'Depth 3.',                                 '2026-11-03 03:08:00'),
    (12, 2, 11,   5, 'Depth 4.',                                 '2026-11-03 03:09:00'),
    (13, 2, 12,   3, 'Depth 5.',                                 '2026-11-03 03:10:00'),
    (14, 2, 13,   1, 'Depth 6.',                                 '2026-11-03 03:11:00'),
    (15, 2, 14,   2, 'Depth 7.',                                 '2026-11-03 03:12:00'),
    (16, 2, 15,   4, 'Depth 8: indentation stops growing here.', '2026-11-03 03:13:00'),
    (17, 2, 16,   5, 'Depth 9: same indent, accurate label.',    '2026-11-03 03:14:00'),
    (18, 2, 17,   3, 'Depth 10: still the same indent.',         '2026-11-03 03:15:00');

ALTER TABLE users       AUTO_INCREMENT = 100;
ALTER TABLE discussions AUTO_INCREMENT = 100;
ALTER TABLE comments    AUTO_INCREMENT = 100;
