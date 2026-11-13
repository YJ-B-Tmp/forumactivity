<?php

declare(strict_types=1);

/*
 * Test runner (no extra dependencies).
 *
 *   1. Start the app:  php -S 127.0.0.1:8000 -t public public/index.php
 *   2. Run:            php tests/run.php            (or: composer test)
 *
 * Part A checks CommentTree with in-memory rows (no server needed).
 * Part B sends real HTTP requests to the running app and checks the database.
 * Set FORUM_BASE_URL to test a different address.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Database;
use App\Support\CommentTree;

$base = rtrim(getenv('FORUM_BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    printf("  [%s] %s%s\n", $ok ? 'PASS' : 'FAIL', $name, (!$ok && $detail !== '') ? "  ($detail)" : '');
}

// ---------------------------------------------------------------------------
echo "A. CommentTree (unit)\n";
// ---------------------------------------------------------------------------
$rows = [
    ['id' => 1, 'parent_id' => null], ['id' => 2, 'parent_id' => 1], ['id' => 3, 'parent_id' => 2],
    ['id' => 4, 'parent_id' => 1],    ['id' => 5, 'parent_id' => null], ['id' => 6, 'parent_id' => 3],
    ['id' => 7, 'parent_id' => 6],
];
$tree = new CommentTree(8, 64, 2000);
$order = [];
$walk = function (array $nodes) use (&$walk, &$order): void {
    foreach ($nodes as $n) {
        $order[] = $n['id'] . '@' . $n['depth'];
        $walk($n['children']);
    }
};
$walk($tree->build($rows, fn ($r, $d, $i) => ['id' => $r['id'], 'depth' => $d, 'indent' => $i]));
check('handout tree order and displayed depths', implode(' ', $order) === '1@0 2@1 3@2 6@3 7@4 4@1 5@0', implode(' ', $order));
check('tree height (displayed) is 4', $tree->height === 4);
check('groupByParent puts 2 top-level rows under root', count(CommentTree::groupByParent($rows)['root']) === 2);

$cycle = [['id' => 1, 'parent_id' => null], ['id' => 2, 'parent_id' => 3], ['id' => 3, 'parent_id' => 2]];
$tree->build($cycle, fn ($r, $d, $i) => ['id' => $r['id']]);
check('a disconnected cycle is not rendered and does not loop', $tree->rendered === 1 && $tree->unreachable === 2);

$chain = [['id' => 1, 'parent_id' => null]];
for ($i = 2; $i <= 12; $i++) {
    $chain[] = ['id' => $i, 'parent_id' => $i - 1];
}
$indents = [];
$walkIndent = function (array $nodes) use (&$walkIndent, &$indents): void {
    foreach ($nodes as $n) {
        $indents[] = $n['depth'] . ':' . $n['indent'];
        $walkIndent($n['children']);
    }
};
$walkIndent($tree->build($chain, fn ($r, $d, $i) => ['depth' => $d, 'indent' => $i]));
check('indent caps at 8 while depth label keeps counting', end($indents) === '11:8', end($indents));

$limited = new CommentTree(8, 3, 2000);
$limited->build($chain, fn ($r, $d, $i) => []);
check('traversal depth limit stops the walk', $limited->rendered === 4, (string) $limited->rendered);

// ---------------------------------------------------------------------------
echo "B. HTTP + database (integration against $base)\n";
// ---------------------------------------------------------------------------

/** Minimal HTTP client with its own cookie jar. */
final class Client
{
    private string $jar;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'forum-test-');
    }

    public function request(string $method, string $path, array $form = []): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            fwrite(STDERR, "Cannot reach {$this->base}. Is the server running?\n");
            exit(2);
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [
            'status'  => $status,
            'headers' => substr($raw, 0, $headerSize),
            'body'    => substr($raw, $headerSize),
        ];
    }

    public function get(string $path): array { return $this->request('GET', $path); }
    public function post(string $path, array $form): array { return $this->request('POST', $path, $form); }

    public function csrf(string $path): string
    {
        $page = $this->get($path)['body'];
        preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $page, $m);
        return $m[1] ?? '';
    }

    public function sessionId(): string
    {
        $jar = (string) file_get_contents($this->jar);
        return preg_match('/forum_session\s+(\S+)/', $jar, $m) ? $m[1] : '';
    }
}

$db = Database::connection();
$count = fn (): int => (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn();
$location = fn (array $r): string => preg_match('/^Location:\s*(\S+)/mi', $r['headers'], $m) ? $m[1] : '';

$commentId = fn (array $r): int => preg_match('/#comment-(\d+)$/', $location($r), $m) ? (int) $m[1] : 0;

$web = new Client($base);

// Reading and ID handling
check('GET /discussions/1 is public (200)', $web->get('/discussions/1')['status'] === 200);
check('malformed ID /discussions/abc returns 400', $web->get('/discussions/abc')['status'] === 400);
check('malformed ID /discussions/-1 returns 400', $web->get('/discussions/-1')['status'] === 400);
check('valid but missing /discussions/999999 returns 404', $web->get('/discussions/999999')['status'] === 404);

$cookieHeader = (new Client($base))->get('/login')['headers'];
check('session cookie is HttpOnly and SameSite=Lax',
    (bool) preg_match('/^Set-Cookie: forum_session=[^\r\n]*HttpOnly[^\r\n]*SameSite=Lax/mi', $cookieHeader));

// Registration
$name = 'tester_' . substr(bin2hex(random_bytes(4)), 0, 8);
$password = 'correct horse ' . bin2hex(random_bytes(3));
$r = $web->post('/register', ['_csrf' => 'not-the-token', 'username' => $name, 'email' => "$name@example.test",
    'password' => $password, 'password_confirm' => $password]);
check('register with an invalid CSRF token is rejected (403)', $r['status'] === 403);

$token = $web->csrf('/register');
$r = $web->post('/register', ['_csrf' => $token, 'username' => 'x', 'email' => 'not-an-email',
    'password' => 'short', 'password_confirm' => 'other']);
check('register validates username, email and password (422)', $r['status'] === 422);

$token = $web->csrf('/register');
$r = $web->post('/register', ['_csrf' => $token, 'username' => $name, 'email' => "$name@example.test",
    'password' => $password, 'password_confirm' => $password]);
check('register a new account (303 redirect)', $r['status'] === 303);
$stored = $db->prepare('SELECT password_hash FROM users WHERE username = ?');
$stored->execute([$name]);
$hash = (string) $stored->fetchColumn();
check('password is stored as a hash, not plaintext', str_starts_with($hash, '$2y$') && !str_contains($hash, $password));

$token = $web->csrf('/');
$web->post('/logout', ['_csrf' => $token]);

$dup = new Client($base);
$token = $dup->csrf('/register');
$r = $dup->post('/register', ['_csrf' => $token, 'username' => $name, 'email' => "$name@example.test",
    'password' => $password, 'password_confirm' => $password]);
check('duplicate username/email is rejected (422)', $r['status'] === 422 && str_contains($r['body'], 'already'));

// Login
$token = $web->csrf('/login');
$r = $web->post('/login', ['_csrf' => $token, 'username' => $name, 'password' => 'wrong password']);
check('incorrect password is rejected (401)', $r['status'] === 401);

$token = $web->csrf('/login');
$r = $web->post('/login', ['_csrf' => $token, 'username' => 'Alice', 'password' => '']);
check('seed user with NULL password hash cannot log in', $r['status'] === 401);

$token = $web->csrf('/login');
$r = $web->post('/login', ['_csrf' => 'x' . $token, 'username' => $name, 'password' => $password]);
check('login with an invalid CSRF token is rejected (403)', $r['status'] === 403);

$before = $web->sessionId();
$token = $web->csrf('/login');
$r = $web->post('/login', ['_csrf' => $token, 'username' => $name, 'password' => $password]);
check('correct login redirects (303)', $r['status'] === 303);
check('session ID is regenerated after login', $web->sessionId() !== '' && $web->sessionId() !== $before);

$userId = (int) $db->query('SELECT id FROM users WHERE username = ' . $db->quote($name))->fetchColumn();

// Authenticated replies
$token = $web->csrf('/discussions/1');
$n = $count();
$r = $web->post('/discussions/1/comments', ['_csrf' => $token, 'parent_id' => '7',
    'body' => 'Depth-5 reply from the test run.', 'user_id' => '1']);
$newId = $commentId($r);
$row = $db->query("SELECT parent_id, user_id, discussion_id FROM comments WHERE id = $newId")->fetch();
check('reply under comment 7 is saved (303 + one row)', $r['status'] === 303 && $count() === $n + 1);
check('reply author comes from the session, not the form', $row && (int) $row['user_id'] === $userId);
check('reply is placed under comment 7 in discussion 1', $row && (int) $row['parent_id'] === 7 && (int) $row['discussion_id'] === 1);
$page = $web->get('/discussions/1')['body'];
check('thread page shows the new reply at depth 5', (bool) preg_match('/id="comment-' . $newId . '".*?Depth 5/s', $page));

$r = $web->post('/discussions/1/comments', ['_csrf' => $token, 'body' => 'Second top-level comment from the test run.']);
$topId = $commentId($r);
$top = $db->query("SELECT parent_id FROM comments WHERE id = $topId")->fetch();
check('second top-level comment is saved with NULL parent', $r['status'] === 303 && $top && $top['parent_id'] === null);

$n = $count();
$r = $web->post('/discussions/1/comments', ['_csrf' => $token, 'parent_id' => '8', 'body' => 'Cross-discussion parent']);
check('cross-discussion parent is rejected (422, no row)', $r['status'] === 422 && $count() === $n);

$r = $web->post('/discussions/999999/comments', ['_csrf' => $token, 'body' => 'Missing discussion']);
check('posting to a missing discussion returns 404 (no row)', $r['status'] === 404 && $count() === $n);

$r = $web->post('/discussions/1/comments', ['_csrf' => 'bad-token', 'body' => 'Bad token']);
check('reply with an invalid CSRF token is rejected (403, no row)', $r['status'] === 403 && $count() === $n);

$r = $web->post('/discussions/1/comments', ['_csrf' => $token, 'body' => '   ']);
check('blank comment is refused (no row)', $r['status'] === 303 && $count() === $n);

$r = $web->post('/discussions/1/comments', ['_csrf' => $token, 'body' => str_repeat('a', 2001)]);
check('over-long comment is refused (no row)', $count() === $n);

$xss = '<script>alert("xss")</script> <b>bold?</b> & "quotes"';
$web->post('/discussions/1/comments', ['_csrf' => $token, 'body' => $xss]);
$page = $web->get('/discussions/1')['body'];
check('HTML/script-like comment is shown escaped', str_contains($page, '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'));
check('no raw <script>alert in the page', !str_contains($page, '<script>alert'));

// Depth limit: the deep demo thread ends at depth 10; keep replying down to the limit.
$parent = 18;
$limitHit = false;
for ($i = 0; $i < 10; $i++) {
    $before = $count();
    $r = $web->post('/discussions/2/comments', ['_csrf' => $token, 'parent_id' => (string) $parent, 'body' => "deeper $i"]);
    if ($count() === $before) {
        $limitHit = true;
        break;
    }
    $parent = $commentId($r);
}
check('server refuses replies beyond max_reply_depth (15)', $limitHit);

// Guests
$token = $web->csrf('/discussions/1');
$web->post('/logout', ['_csrf' => $token]);
$n = $count();
$r = $web->post('/discussions/1/comments', ['_csrf' => $token, 'body' => 'Guest reply attempt', 'parent_id' => '1']);
check('guest POST after logout is redirected to /login, no row', $r['status'] === 303 && $location($r) === '/login' && $count() === $n);

$stranger = new Client($base);
$r = $stranger->post('/discussions/1/comments', ['body' => 'Direct POST with no session', 'user_id' => '1']);
check('direct POST with no session creates no row', $count() === $n && $r['status'] === 303);

$page = $stranger->get('/discussions/1')['body'];
check('guests see no reply forms', !str_contains($page, 'name="parent_id"'));

printf("\n%d passed, %d failed\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
