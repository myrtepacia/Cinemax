<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

redirect_if_signed_in();

// Longer than any allowed password: refused before the slow check
const SIGNIN_PASSWORD_MAX = 128;

/** The password as typed (not trimmed). */
function signin_password(array $source): string
{
    $value = $source['password'] ?? '';
    if (!is_string($value) || mb_strlen($value) > SIGNIN_PASSWORD_MAX) {
        return '';
    }
    return $value;
}

// Where to go after signing in (only paths inside the site)
$return = signin_return_path();

$email = '';
$error = '';

// After a password reset, shown once
$notice = $_SESSION['signin_notice'] ?? null;
unset($_SESSION['signin_notice']);

if (is_post()) {
    verify_csrf();

    $email = input_string($_POST, 'email', 190);
    $password = signin_password($_POST);

    if ($email === '' || ($_POST['password'] ?? '') === '') {
        $error = 'Enter your email address and password.';
    } else {
        $result = attempt_login($email, $password);
        if (!is_array($result)) {
            $error = 'That email and password do not match an account.';
        } else {
            login_user($result);
            redirect($return ?? home_for($result));
        }
    }
}

// A shared film link lands here first (booking needs an account), so its
// link preview shows the film when the preview keeps the session cookie;
// search engines list only the plain page
$returnFilm = $return !== null && preg_match('~^[a-z0-9-]+$~', $return) ? find_movie_by_slug($return) : null;
render_head('Sign In', [], $returnFilm !== null ? movie_meta($returnFilm, false) : [
    'description' => 'Sign in to CSFlix to book your seats and see your e-tickets.',
    'index'       => true,
    'canonical'   => 'signin.php',
]);
render_header();
?>
  <main class="form-page">
    <div class="form-card">

      <h1>Welcome back</h1>
      <p class="form-intro">Sign in to book your seats.</p>

<?php if (is_string($notice) && $notice !== '' && $error === ''): ?>
      <p class="form-message form-message-success" role="status"><?= e($notice) ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
      <p class="form-message form-message-error" id="form-message" role="alert"><?= e($error) ?></p>
<?php endif; ?>

      <form id="signin-form" method="post" action="<?= e(url('signin.php')) ?>">
        <?= csrf_field() ?>

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

        <div class="signin-options">
          <a href="<?= e(url('forgot-password.php')) ?>" id="forgot-link">Forgot password?</a>
        </div>

        <button class="button button-red" type="submit" id="submit">Sign In</button>

      </form>

      <p class="form-switch">Don't have an account? <a href="<?= e(url('signup.php')) ?>" id="signup-link">Create account</a></p>

    </div>
  </main>

<?php render_footer();
