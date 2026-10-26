<?php
declare(strict_types=1);


if (!defined('CSFLIX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

const PASSWORD_OPTIONS = ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_LENGTH = 128;

// How long a "forgot password" link works
const RESET_LINK_SECONDS = 3600;

// Sign-up email check: a 6-digit code (api/signup-otp.php), kept in the
// session. It works for 10 minutes and 5 tries; a new one after 1 minute.
const SIGNUP_CODE_SECONDS = 600;
const SIGNUP_CODE_TRIES = 5;
const SIGNUP_CODE_RESEND_SECONDS = 60;

/** True once this browser proved it owns $email with the emailed code. */
function signup_email_verified(string $email): bool
{
    $verified = $_SESSION['signup_verified_email'] ?? null;
    return is_string($verified) && $email !== '' && hash_equals($verified, mb_strtolower($email));
}

/** Why a new password is not accepted, by field ('password', 'confirm'). */
function password_problems(string $password, string $confirm): array
{
    $length = mb_strlen($password);
    if ($password === '') {
        return ['password' => 'Choose a password.'];
    }
    if (!preg_match('//u', $password)) {
        return ['password' => 'Your password has characters we cannot read. Please choose another.'];
    }
    if ($length < PASSWORD_MIN_LENGTH || $length > PASSWORD_MAX_LENGTH) {
        return ['password' => 'Your password should be ' . PASSWORD_MIN_LENGTH . ' to ' . PASSWORD_MAX_LENGTH . ' characters long.'];
    }
    if (!preg_match('/\p{L}/u', $password) || !preg_match('/[0-9]/', $password)) {
        return ['password' => 'Your password needs at least one letter and one number.'];
    }
    if ($confirm === '') {
        return ['confirm' => 'Type your password again.'];
    }
    if (!hash_equals($password, $confirm)) {
        return ['confirm' => 'The two passwords do not match.'];
    }
    return [];
}

/**
 * The site's own secret for signing links: made once, kept in
 * storage/ (never served).
 */
function app_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $file = APP_ROOT . '/storage/app.key';
    if (!is_file($file)) {
        $temp = $file . '.' . bin2hex(random_bytes(4));
        file_put_contents($temp, bin2hex(random_bytes(32)), LOCK_EX);
        rename($temp, $file);
    }
    $secret = trim((string) file_get_contents($file));
    if (strlen($secret) < 64) {
        throw new RuntimeException('storage/app.key is damaged: delete it and it is made again.');
    }
    return $secret;
}

/**
 * A signed token 'id.expires.signature' for one purpose (such as
 * 'reset'). It stops working once the password changes.
 */
function account_token(array $user, string $purpose, int $expires): string
{
    $signature = hash_hmac('sha256', $purpose . '|' . $user['id'] . '|' . $expires, app_secret() . '|' . $user['password_hash']);
    return $user['id'] . '.' . $expires . '.' . $signature;
}

/** The account of a genuine, unexpired token, or null. */
function account_from_token(string $token, string $purpose): ?array
{
    if (!preg_match('/^(\d{1,10})\.(\d{10})\.[a-f0-9]{64}$/D', $token, $m) || (int) $m[2] < time()) {
        return null;
    }
    $user = db_one('SELECT id, name, email, role, password_hash FROM users WHERE id = ?', [(int) $m[1]]);
    if ($user === null || !hash_equals(account_token($user, $purpose, (int) $m[2]), $token)) {
        return null;
    }
    return $user;
}

/**
 * "Keep me signed in" was removed: a browser that still holds its old
 * cookie is told to delete it. Runs at the start of each request.
 */
function clear_old_remember_cookie(): void
{
    if (!isset($_COOKIE['CSFLIXREMEMBER']) || headers_sent()) {
        return;
    }
    setcookie('CSFLIXREMEMBER', '', [
        'expires'  => time() - 42000,
        'path'     => app_path() === '' ? '/' : app_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** The signed-in user, read fresh each request, or null. */
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

    // The session must still match its account (same id, email and password)
    if ($row === null || !hash_equals(session_auth_check($row), $check)) {
        logout_user();
        return null;
    }
    unset($row['password_hash']);
    $user = $row;
    return $user;
}

/** An HMAC fingerprint of the account, kept in the session. */
function session_auth_check(array $user): string
{
    return hash_hmac('sha256', $user['id'] . '|' . mb_strtolower((string) $user['email']), (string) $user['password_hash']);
}

function is_staff(?array $user): bool
{
    return $user !== null && in_array($user['role'], ['admin', 'scanner'], true);
}

/** Hashes a new password (Argon2id). */
function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_ARGON2ID, PASSWORD_OPTIONS);
}

/**
 * Checks an email and password. Returns the user or 'invalid', in
 * about the same time either way.
 */
function attempt_login(string $email, string $password)
{
    $email = mb_strtolower(trim($email));

    $user = db_one('SELECT id, name, email, role, password_hash FROM users WHERE email = ?', [$email]);

    // Checked against a real hash even with no such user
    static $dummy = '$argon2id$v=19$m=65536,t=4,p=1$TTVmcVh4TUZGb2VNVTRKZQ$qZDm6T/UqvdoXzWG4C26WS9dJE7pfs7m2AUhBHUFqxM';
    $hash = $user['password_hash'] ?? $dummy;
    $ok = password_verify($password, $hash) && $user !== null;

    if (!$ok) {
        return 'invalid';
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID, PASSWORD_OPTIONS)) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password($password), $user['id']]);
    }
    unset($user['password_hash']);
    return $user;
}

/** Signs a user in with a new session id and CSRF token. */
function login_user(array $user): void
{
    $account = db_one('SELECT id, email, password_hash FROM users WHERE id = ?', [(int) $user['id']]);
    if ($account === null) {
        throw new RuntimeException('Cannot sign in an account that does not exist.');
    }

    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['user_id'] = (int) $account['id'];
    $_SESSION['auth_check'] = session_auth_check($account);
    $_SESSION['last_seen'] = time();
    $_SESSION['rotated_at'] = time();
    db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int) $user['id']]);
}

/** Signs out: empties and destroys the session and its cookie. */
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

/** The page each kind of account lands on after signing in. */
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

/** The signed-in user, or off to sign in and back. */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect_to_signin(current_path());
    }
    return $user;
}

/**
 * Off to the plain sign-in page; the page to come back to is kept in the
 * session, not the address bar.
 */
function redirect_to_signin(string $return, string $page = 'signin.php'): void
{
    $_SESSION['signin_return'] = safe_return_path($return);
    $_SESSION['signin_return_fresh'] = true;
    // The browser sends sign-in this same Referer, also when it is reloaded
    $_SESSION['signin_return_from'] = referer_path();
    redirect($page);
}

/**
 * Where signing in or up goes back to. Sent by a page that needs an account:
 * that page, also after a reload. Opened from another page here (the Sign In
 * button): that page. Between Sign In, Sign Up and the password pages: kept.
 * Typed in or bookmarked: none. Read it before login_user(), which empties
 * the session.
 */
function signin_return_path(): ?string
{
    if (!is_post()) {
        // An older link with ?return=: keep it, then show the plain address
        $asked = $_GET['return'] ?? null;
        if (is_string($asked)) {
            redirect_to_signin($asked, (string) strtok(current_path(), '?'));
        }
        $from = referer_path();
        if (!empty($_SESSION['signin_return_fresh'])) {
            unset($_SESSION['signin_return_fresh']);
        } elseif ($from !== null && preg_match('~^(?:sign(?:in|up)|forgot-password|reset-password)(?:\.php)?(?:[?/]|$)~', $from)) {
            // Moving between the sign-in pages: kept
        } elseif (array_key_exists('signin_return_from', $_SESSION) && $from === $_SESSION['signin_return_from']) {
            // Sent here by a page that needs an account, then reloaded: kept
        } elseif ($from === null) {
            unset($_SESSION['signin_return'], $_SESSION['signin_return_from']);
        } else {
            $_SESSION['signin_return'] = safe_return_path($from) ?? safe_return_path((string) strtok($from, '?'));
            unset($_SESSION['signin_return_from']);
        }
    }
    return safe_return_path($_SESSION['signin_return'] ?? null);
}

/** The signed-in user with one of the given roles, or a 403 page. */
function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        abort(403);
    }
    return $user;
}

/** For admin background requests: only a GET from a signed-in admin. */
function require_admin_get(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        header('Allow: GET');
        json_response(['ok' => false, 'error' => 'Only GET requests are accepted here.'], 405);
    }
    $user = current_user();
    if ($user === null) {
        json_response(['ok' => false, 'error' => 'Please sign in again.'], 401);
    }
    if ($user['role'] !== 'admin') {
        json_response(['ok' => false, 'error' => 'Not allowed.'], 403);
    }
    return $user;
}

/** For sign in and sign up: signed-in users go to their home page. */
function redirect_if_signed_in(): void
{
    $user = current_user();
    if ($user !== null) {
        redirect(home_for($user));
    }
}
