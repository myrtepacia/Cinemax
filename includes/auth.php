<?php
declare(strict_types=1);

// Signing in and out, and who may see which page.
//
// Roles:  customer  books tickets and sees their own bookings
//         admin     runs the whole site, including the scanner
//         scanner   checks tickets at the door, nothing else

if (!defined('CINEMAX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

// Five wrong passwords for one email in 15 minutes locks that email for the
// rest of the window; thirty from one address locks the address.
const SIGNIN_MAX_PER_EMAIL = 5;
const SIGNIN_MAX_PER_IP = 30;
const SIGNIN_WINDOW_SECONDS = 900;

const PASSWORD_OPTIONS = ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];

/**
 * The signed-in person, fresh from the database, or null. Reading it again
 * every request means a deleted account or changed role takes effect at once.
 */
function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    $check = $_SESSION['auth_check'] ?? null;
    if (!is_int($id) || !is_string($check)) {
        return null;
    }
    $row = db_one('SELECT id, name, email, mobile, role, password_hash FROM users WHERE id = ?', [$id]);

    // The session must still match the very account it was made for. A
    // different account that later got the same id (after the database is
    // rebuilt) or a changed password both end the old session.
    if ($row === null || !hash_equals(session_auth_check($row), $check)) {
        logout_user();
        return null;
    }
    unset($row['password_hash']);
    $user = $row;
    return $user;
}

/**
 * A fingerprint of the account a session belongs to: its id, email and
 * password hash, run through HMAC so the session holds nothing reusable.
 */
function session_auth_check(array $user): string
{
    return hash_hmac('sha256', $user['id'] . '|' . mb_strtolower((string) $user['email']), (string) $user['password_hash']);
}

function is_staff(?array $user): bool
{
    return $user !== null && in_array($user['role'], ['admin', 'scanner'], true);
}

/**
 * Hashes a new password (Argon2id).
 */
function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_ARGON2ID, PASSWORD_OPTIONS);
}

/**
 * Checks an email and password. Returns the user on success, or one of
 * 'locked' or 'invalid'. The answer takes about as long whether or not the
 * email exists, so timing does not reveal which accounts are real.
 */
function attempt_login(string $email, string $password)
{
    $email = mb_strtolower(trim($email));

    if (too_many_attempts('signin', $email, SIGNIN_MAX_PER_EMAIL, SIGNIN_WINDOW_SECONDS, SIGNIN_MAX_PER_IP)) {
        return 'locked';
    }

    $user = db_one('SELECT id, name, email, role, password_hash FROM users WHERE email = ?', [$email]);

    // Checked against a real hash even when there is no such user
    static $dummy = '$argon2id$v=19$m=65536,t=4,p=1$TTVmcVh4TUZGb2VNVTRKZQ$qZDm6T/UqvdoXzWG4C26WS9dJE7pfs7m2AUhBHUFqxM';
    $hash = $user['password_hash'] ?? $dummy;
    $ok = password_verify($password, $hash) && $user !== null;

    if (!$ok) {
        record_attempt('signin', $email);
        return 'invalid';
    }

    clear_attempts('signin', $email);

    if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID, PASSWORD_OPTIONS)) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password($password), $user['id']]);
    }
    unset($user['password_hash']);
    return $user;
}

/**
 * Signs a user in: a brand-new session id (so an id planted before sign-in
 * is useless afterwards) and a fresh CSRF token.
 */
function login_user(array $user): void
{
    $account = db_one('SELECT id, email, password_hash FROM users WHERE id = ?', [(int) $user['id']]);
    if ($account === null) {
        throw new RuntimeException('Cannot sign in an account that does not exist.');
    }

    session_regenerate_id(true);
    $keepFlashes = $_SESSION['flashes'] ?? [];
    $_SESSION = [];
    $_SESSION['user_id'] = (int) $account['id'];
    $_SESSION['auth_check'] = session_auth_check($account);
    $_SESSION['last_seen'] = time();
    $_SESSION['rotated_at'] = time();
    $_SESSION['flashes'] = $keepFlashes;
    unset($_SESSION['csrf_token']);
    db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int) $user['id']]);
}

/**
 * Signs out completely: the session is emptied, destroyed and its cookie
 * removed from the browser.
 */
function logout_user(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
        session_destroy();
    }
}

/**
 * The page each kind of account lands on after signing in.
 */
function home_for(array $user): string
{
    switch ($user['role']) {
        case 'admin':
            return 'admin/index.php';
        case 'scanner':
            return 'admin/scanner.php';
        default:
            return 'index.php';
    }
}

/**
 * The signed-in user, or a trip to the sign-in page that comes back here.
 */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('notice', 'Please sign in first.');
        $return = safe_return_path(current_path());
        redirect('signin.php' . ($return !== null ? '?return=' . rawurlencode($return) : ''));
    }
    return $user;
}

/**
 * The signed-in user, who must have one of the given roles, or a 403 page.
 * require_role('admin') or require_role('admin', 'scanner').
 */
function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        abort(403);
    }
    return $user;
}

/**
 * For pages only a signed-out visitor needs (sign in, sign up): anyone
 * already signed in is sent to their own home page.
 */
function redirect_if_signed_in(): void
{
    $user = current_user();
    if ($user !== null) {
        redirect(home_for($user));
    }
}
