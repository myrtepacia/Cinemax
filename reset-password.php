<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

// The link from the "forgot password" email
$token = input_string(is_post() ? $_POST : $_GET, 'token', 200);
$user = $token !== '' ? account_from_token($token, 'reset') : null;
$errors = [];

if ($user !== null && is_post()) {
    verify_csrf();

    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';
    $errors = password_problems($password, $confirm);

    if ($errors === []) {
        // The new hash also ends this link, every session and every "keep me
        // signed in" made with the old password
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password($password), (int) $user['id']]);

        logout_user();
        start_session();
        $_SESSION['signin_notice'] = 'Your password was changed. Sign in with your new password.';
        redirect('signin.php');
    }
}

render_head('Choose a New Password');
render_header();
?>
  <main class="form-page">
    <div class="form-card">

<?php if ($user === null): ?>
      <h1>Link not working</h1>
      <p class="form-intro">This reset link has expired or was already used. Links work once, for <?= e(intdiv(RESET_LINK_SECONDS, 60)) ?> minutes.</p>
      <a class="button button-red" href="<?= e(url('forgot-password.php')) ?>">Send a New Link</a>
<?php else: ?>
      <h1>Choose a new password</h1>
      <p class="form-intro">For <?= e($user['email']) ?>. Use at least <?= e(PASSWORD_MIN_LENGTH) ?> characters, with a letter and a number.</p>

<?php foreach ($errors as $message): ?>
      <p class="form-message form-message-error" role="alert"><?= e($message) ?></p>
<?php endforeach; ?>

      <form method="post" action="<?= e(url('reset-password.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="form-field">
          <label for="password">New password</label>
          <input id="password" name="password" type="password" autocomplete="new-password"
                 minlength="<?= e(PASSWORD_MIN_LENGTH) ?>" maxlength="<?= e(PASSWORD_MAX_LENGTH) ?>" required autofocus>
        </div>

        <div class="form-field">
          <label for="confirm">Confirm new password</label>
          <input id="confirm" name="confirm" type="password" autocomplete="new-password"
                 maxlength="<?= e(PASSWORD_MAX_LENGTH) ?>" required>
        </div>

        <button class="button button-red" type="submit">Save New Password</button>

      </form>
<?php endif; ?>

    </div>
  </main>

<?php render_footer();
