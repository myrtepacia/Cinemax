<?php
declare(strict_types=1);

// Signing in. The checking, the lockout after too many wrong passwords and
// the even timing all happen in attempt_login(); this page only shows the
// form and sends people on afterwards.

require __DIR__ . '/includes/bootstrap.php';

redirect_if_signed_in();

// Longer than any password sign-up accepts, so it cannot be right. Refused
// before it reaches the (deliberately slow) password check.
const SIGNIN_PASSWORD_MAX = 128;

/**
 * The password exactly as typed. Unlike other fields it is not trimmed,
 * since a space can be part of a password.
 */
function signin_password(array $source): string
{
    $value = $source['password'] ?? '';
    if (!is_string($value) || mb_strlen($value) > SIGNIN_PASSWORD_MAX) {
        return '';
    }
    return $value;
}

// Where to go after signing in, when a page sent the visitor here. Only a
// path inside the site is kept.
$return = safe_return_path(input_string(is_post() ? $_POST : $_GET, 'return', 300));

$email = '';
$error = '';

if (is_post()) {
    verify_csrf();

    $email = input_string($_POST, 'email', 190);
    $password = signin_password($_POST);

    if ($email === '' || ($_POST['password'] ?? '') === '') {
        $error = 'Enter your email address and password.';
    } else {
        $result = attempt_login($email, $password);
        if ($result === 'locked') {
            $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
        } elseif (!is_array($result)) {
            $error = 'That email and password do not match an account.';
        } else {
            login_user($result);
            // A scanner account going to its home page picks Ticket or Snack
            // Scanner first; admin/scanner.php shows the choice on phones
            if ($return === null && $result['role'] === 'scanner') {
                $_SESSION['choose_scanner'] = true;
            }
            redirect($return ?? home_for($result));
        }
    }
}

$signupLink = 'signup.php' . ($return !== null ? '?return=' . rawurlencode($return) : '');

render_head('Sign In');
render_header();
?>
  <main class="form-page">
    <div class="form-card">

      <h1>Welcome back</h1>
      <p class="form-intro">Sign in to book your seats.</p>

<?php if ($error !== ''): ?>
      <p class="form-message form-message-error" id="form-message" role="alert"><?= e($error) ?></p>
<?php endif; ?>

      <form id="signin-form" method="post" action="<?= e(url('signin.php')) ?>">
        <?= csrf_field() ?>
<?php if ($return !== null): ?>
        <input type="hidden" name="return" value="<?= e($return) ?>">
<?php endif; ?>

        <div class="form-field">
          <label for="email">Email address</label>
          <input id="email" name="email" type="email" value="<?= e($email) ?>"
                 placeholder="juandelacruz@gmail.com" maxlength="190"
                 autocomplete="email" required<?= $email === '' ? ' autofocus' : '' ?>>
        </div>

        <div class="form-field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" maxlength="<?= e(SIGNIN_PASSWORD_MAX) ?>"
                 autocomplete="current-password" required<?= $email !== '' ? ' autofocus' : '' ?>>
        </div>

        <button class="button button-red" type="submit" id="submit">Sign In</button>

      </form>

      <p class="form-switch">Don't have an account? <a href="<?= e(url($signupLink)) ?>" id="signup-link">Create account</a></p>

    </div>
  </main>

<?php render_footer();
