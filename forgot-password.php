<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

redirect_if_signed_in();

$email = '';
$error = '';
$sent = false;

if (is_post()) {
    verify_csrf();

    $email = mb_strtolower(input_string($_POST, 'email', 190));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = 'Enter the email address of your account.';
    } else {
        $user = db_one('SELECT id, name, email, password_hash FROM users WHERE email = ?', [$email]);
        if ($user !== null) {
            $token = account_token($user, 'reset', time() + RESET_LINK_SECONDS);
            // The configured address, never the request's Host header
            $link = rtrim((string) config('app_url'), '/') . '/' . clean_path('reset-password.php?token=' . rawurlencode($token));
            try {
                send_password_reset_email($user, $link);
            } catch (Throwable $e) {
                error_log('[reset] ' . $email . ': ' . $e->getMessage());
            }
        }
        // The same answer whether or not there is such an account
        $sent = true;
    }
}

render_head('Forgot Password');
render_header();
?>
  <main class="form-page">
    <div class="form-card">

      <h1>Forgot password</h1>

<?php if ($sent): ?>
      <p class="form-message form-message-success" role="status">If an account uses <strong><?= e($email) ?></strong>, we have emailed it a link to choose a new password. The link works for <?= e(intdiv(RESET_LINK_SECONDS, 60)) ?> minutes. Check your spam folder too.</p>
      <p class="form-switch"><a href="<?= e(url('signin.php')) ?>">Back to Sign In</a></p>
<?php else: ?>
      <p class="form-intro">Enter your account's email address and we will send you a link to choose a new password.</p>

<?php if ($error !== ''): ?>
      <p class="form-message form-message-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

      <form method="post" action="<?= e(url('forgot-password.php')) ?>">
        <?= csrf_field() ?>

        <div class="form-field">
          <label for="email">Email address</label>
          <input id="email" name="email" type="email" value="<?= e($email) ?>"
                 placeholder="juandelacruz@gmail.com" maxlength="190" autocomplete="email" required autofocus>
        </div>

        <button class="button button-red" type="submit">Send Reset Link</button>

      </form>

      <p class="form-switch">Remembered it? <a href="<?= e(url('signin.php')) ?>">Sign In</a></p>
<?php endif; ?>

    </div>
  </main>

<?php render_footer();
