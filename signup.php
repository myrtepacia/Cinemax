<?php
declare(strict_types=1);

// Sign up. Everyone who signs up here is a customer (never read from the
// form).

require __DIR__ . '/includes/bootstrap.php';

redirect_if_signed_in();

// Sign-ups allowed per address per hour
const SIGNUP_MAX_PER_IP = 10;
const SIGNUP_WINDOW_SECONDS = 3600;

const SIGNUP_PASSWORD_MIN = 8;
const SIGNUP_PASSWORD_MAX = 128;

/** The password as typed (not trimmed), or ''. */
function signup_password(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? $value : '';
}

/** Checks the form. Returns [clean values, errors by field]. */
function signup_validate(array $post): array
{
    $errors = [];

    // Names: letters in any language, spaces and . ' -
    $name = (string) preg_replace('/\s+/u', ' ', input_string($post, 'fullname', 200));
    $nameLength = mb_strlen($name);
    if ($name === '') {
        $errors['fullname'] = 'Enter your full name.';
    } elseif ($nameLength < 2 || $nameLength > 100) {
        $errors['fullname'] = 'Your name should be 2 to 100 characters long.';
    } elseif (!preg_match('/^\p{L}[\p{L}\p{M} .\'\-]*$/uD', $name)) {
        $errors['fullname'] = 'Use only letters, spaces, periods, apostrophes and hyphens in your name.';
    }

    $email = mb_strtolower(input_string($post, 'email', 250));
    if ($email === '') {
        $errors['email'] = 'Enter your email address.';
    } elseif (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address, like juandelacruz@gmail.com.';
    }

    // Mobile is optional; stored as digits
    $mobileTyped = input_string($post, 'mobile', 30);
    $mobile = null;
    if ($mobileTyped !== '') {
        $digits = (string) preg_replace('/\D/', '', $mobileTyped);
        if (!preg_match('/^\+?[0-9 ]+$/', $mobileTyped) || strlen($digits) < 10 || strlen($digits) > 13) {
            $errors['mobile'] = 'Enter a mobile number like 0917 123 4567 or +63 917 123 4567.';
        } else {
            $mobile = ($mobileTyped[0] === '+' ? '+' : '') . $digits;
        }
    }

    $password = signup_password($post, 'password');
    $confirm = signup_password($post, 'confirm');
    $passwordLength = mb_strlen($password);
    if ($password === '') {
        $errors['password'] = 'Choose a password.';
    } elseif (!preg_match('//u', $password)) {
        $errors['password'] = 'Your password has characters we cannot read. Please choose another.';
    } elseif ($passwordLength < SIGNUP_PASSWORD_MIN || $passwordLength > SIGNUP_PASSWORD_MAX) {
        $errors['password'] = 'Your password should be ' . SIGNUP_PASSWORD_MIN . ' to ' . SIGNUP_PASSWORD_MAX . ' characters long.';
    } elseif (!preg_match('/\p{L}/u', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Your password needs at least one letter and one number.';
    } elseif ($confirm === '') {
        $errors['confirm'] = 'Type your password again.';
    } elseif (!hash_equals($password, $confirm)) {
        $errors['confirm'] = 'The two passwords do not match.';
    }

    if (input_string($post, 'agree', 5) === '') {
        $errors['agree'] = 'Please agree to the Terms and Conditions.';
    }

    return [
        ['name' => $name, 'email' => $email, 'mobile' => $mobile, 'password' => $password],
        $errors,
    ];
}

function signup_email_taken(string $email): bool
{
    return db_value('SELECT 1 FROM users WHERE email = ?', [$email]) !== null;
}

/** Extra attributes for a field with an error. */
function signup_invalid_attrs(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return ' class="invalid" aria-invalid="true" aria-describedby="' . e($field . '-error') . '"';
}

/** The error line under a field. */
function signup_field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<span class="field-error" id="' . e($field . '-error') . '">' . e($errors[$field]) . '</span>';
}

$return = safe_return_path(input_string(is_post() ? $_POST : $_GET, 'return', 300));

// Shown again if the form comes back (never the password)
$typed = ['fullname' => '', 'email' => '', 'mobile' => ''];
$agreed = false;
$errors = [];
$formError = '';
$emailTaken = false;

if (is_post()) {
    verify_csrf();

    $typed = [
        'fullname' => input_string($_POST, 'fullname', 200),
        'email'    => input_string($_POST, 'email', 250),
        'mobile'   => input_string($_POST, 'mobile', 30),
    ];
    $agreed = input_string($_POST, 'agree', 5) !== '';

    // Every try counts, even refused ones
    $limited = too_many_attempts('signup', '', SIGNUP_MAX_PER_IP, SIGNUP_WINDOW_SECONDS);
    record_attempt('signup');

    if ($limited) {
        $formError = 'Too many accounts have been made from your connection recently. Please try again in an hour.';
    } else {
        [$clean, $errors] = signup_validate($_POST);

        if ($errors === [] && signup_email_taken($clean['email'])) {
            $emailTaken = true;
        }

        if ($errors === [] && !$emailTaken) {
            try {
                db_exec(
                    "INSERT INTO users (name, email, mobile, password_hash, role) VALUES (?, ?, ?, ?, 'customer')",
                    [$clean['name'], $clean['email'], $clean['mobile'], hash_password($clean['password'])]
                );
                $userId = (int) db()->lastInsertId();
            } catch (PDOException $e) {
                // Same email at the same moment: the unique key lets only one
                // through
                if (!is_duplicate_key($e)) {
                    throw $e;
                }
                $emailTaken = true;
            }

            if (!$emailTaken) {
                login_user(['id' => $userId]);
                redirect($return ?? 'index.php');
            }
        }

        if ($emailTaken) {
            $errors['email'] = 'This email is already registered.';
        } elseif ($errors !== []) {
            $formError = 'Please check the details below.';
        }
    }
}

$signinLink = 'signin.php' . ($return !== null ? '?return=' . rawurlencode($return) : '');

render_head('Sign Up');
render_header();
?>
  <main class="form-page">
    <div class="form-card">

      <h1>Join CINEMAX</h1>
      <p class="form-intro">Create an account to start booking.</p>

<?php if ($emailTaken): ?>
      <p class="form-message form-message-error" id="form-message" role="alert">
        An account with that email already exists. <a href="<?= e(url($signinLink)) ?>">Sign in</a> instead.
      </p>
<?php elseif ($formError !== ''): ?>
      <p class="form-message form-message-error" id="form-message" role="alert"><?= e($formError) ?></p>
<?php endif; ?>

      <form id="signup-form" method="post" action="<?= e(url('signup.php')) ?>">
        <?= csrf_field() ?>
<?php if ($return !== null): ?>
        <input type="hidden" name="return" value="<?= e($return) ?>">
<?php endif; ?>

        <div class="form-field">
          <label for="fullname">Full name</label>
          <input id="fullname" name="fullname" type="text" value="<?= e($typed['fullname']) ?>"
                 placeholder="Juan Dela Cruz" autocomplete="name" maxlength="100" required<?= signup_invalid_attrs($errors, 'fullname') ?>>
          <?= signup_field_error($errors, 'fullname') ?>
        </div>

        <div class="form-field">
          <label for="email">Email address</label>
          <input id="email" name="email" type="email" value="<?= e($typed['email']) ?>"
                 placeholder="juandelacruz@gmail.com" autocomplete="email" maxlength="190" required<?= signup_invalid_attrs($errors, 'email') ?>>
          <?= signup_field_error($errors, 'email') ?>
        </div>

        <div class="form-field">
          <label for="mobile">Mobile number</label>
          <input id="mobile" name="mobile" type="tel" value="<?= e($typed['mobile']) ?>"
                 placeholder="0917 123 4567" autocomplete="tel" maxlength="20"<?= signup_invalid_attrs($errors, 'mobile') ?>>
          <?= signup_field_error($errors, 'mobile') ?>
        </div>

        <div class="form-field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password"
                 placeholder="At least <?= e(SIGNUP_PASSWORD_MIN) ?> characters" autocomplete="new-password"
                 minlength="<?= e(SIGNUP_PASSWORD_MIN) ?>" maxlength="<?= e(SIGNUP_PASSWORD_MAX) ?>" required<?= signup_invalid_attrs($errors, 'password') ?>>
          <?= signup_field_error($errors, 'password') ?>
        </div>

        <div class="form-field">
          <label for="confirm">Confirm password</label>
          <input id="confirm" name="confirm" type="password"
                 placeholder="Re-type your password" autocomplete="new-password"
                 maxlength="<?= e(SIGNUP_PASSWORD_MAX) ?>" required<?= signup_invalid_attrs($errors, 'confirm') ?>>
          <?= signup_field_error($errors, 'confirm') ?>
        </div>

        <label class="agree-line">
          <input type="checkbox" id="agree" name="agree" value="1" required<?= $agreed ? ' checked' : '' ?><?= isset($errors['agree']) ? ' aria-invalid="true" aria-describedby="agree-error"' : '' ?>>
          <span>I agree to the <a href="<?= e(url('terms.php')) ?>" target="_blank" rel="noopener">Terms and Conditions</a>.<?= signup_field_error($errors, 'agree') ?></span>
        </label>

        <button class="button button-red" type="submit" id="submit">Create Account</button>

      </form>

      <p class="form-switch">Already have an account? <a href="<?= e(url($signinLink)) ?>" id="signin-link">Sign In</a></p>

    </div>
  </main>

<?php render_footer();
