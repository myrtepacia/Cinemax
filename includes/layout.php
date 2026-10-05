<?php
declare(strict_types=1);

// The parts pages share: <head>, the top bar, the staff sidebar, the
// payment pages' booking summary and the footer.
//
// A customer page:
//   render_head('My Bookings');
//   render_header(['current' => 'account']);
//   ... <main> ...
//   render_footer();
//
// A staff page:
//   render_head('Movies');
//   render_header(['staff' => true, 'current' => 'movies']);
//   echo '<div class="admin-layout">';
//   render_staff_sidebar('movies');
//   ... <div class="admin-head">...</div> <main class="admin-page">...</main> ...
//   echo '</div>';
//   render_footer(['js' => ['assets/js/movies.js']]);
//
// Pages never write <script> or style="" inline: the Content Security
// Policy blocks both. Scripts go in assets/js and are listed in 'js'.

if (!defined('CINEMAX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * Opens the page: doctype, <head> and <body>. $css lists extra style sheets
 * under assets/css, e.g. ['assets/css/booking.css'].
 */
function render_head(string $title, array $css = []): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?></title>
  <link rel="icon" type="image/png" href="<?= e(asset('assets/img/logo.png')) ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;900&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('assets/css/cinemax.css')) ?>">
<?php foreach ($css as $sheet): ?>
  <link rel="stylesheet" href="<?= e(asset($sheet)) ?>">
<?php endforeach; ?>
</head>
<body>
<?php
}

/**
 * The staff links a user may see, as [key, label, path].
 */
function staff_links(array $user): array
{
    if ($user['role'] === 'admin') {
        return [
            ['dashboard', 'Dashboard', 'admin/index.php'],
            ['movies', 'Movies', 'admin/movies.php'],
            ['add-movie', 'Add Movie', 'admin/add-movie.php'],
            ['scanner', 'Ticket Scanner', 'admin/scanner.php'],
            ['snack-scanner', 'Snack Scanner', 'admin/snack-scanner.php'],
        ];
    }
    if ($user['role'] === 'scanner') {
        return [
            ['scanner', 'Ticket Scanner', 'admin/scanner.php'],
            ['snack-scanner', 'Snack Scanner', 'admin/snack-scanner.php'],
        ];
    }
    return [];
}

/**
 * The top bar. Options:
 *   'current' => which link is highlighted: 'now-showing', 'coming-soon',
 *                'account', or a staff key such as 'movies'
 *   'staff'   => true on staff pages: the phone menu lists the staff links
 *   'home'    => true on the home page, so its links jump down the page
 */
function render_header(array $options = []): void
{
    $user = current_user();
    $current = (string) ($options['current'] ?? '');
    $onStaffPage = !empty($options['staff']) && is_staff($user);
    $onHome = !empty($options['home']);

    $links = [];
    $menuClass = 'menu';

    if ($onStaffPage) {
        $menuClass = 'menu menu-staff';
        foreach (staff_links($user) as [$key, $label, $path]) {
            $links[] = [$key, $label, url($path)];
        }
    } else {
        $links[] = ['now-showing', 'Now Showing', $onHome ? '#now-showing' : url('index.php') . '#now-showing'];
        $links[] = ['coming-soon', 'Upcoming Shows', $onHome ? '#coming-soon' : url('index.php') . '#coming-soon'];
        if ($user !== null && $user['role'] === 'customer') {
            $links[] = ['account', 'My Bookings', url('account.php')];
        } elseif (is_staff($user)) {
            $links[] = ['staff', 'Staff Area', url(home_for($user))];
        }
    }
    ?>
  <header class="top-bar">
    <div class="top-bar-inner">

      <a class="logo" href="<?= e(url('index.php')) ?>"><img class="logo-mark" src="<?= e(asset('assets/img/logo.png')) ?>" alt="" width="49" height="44">CINEMAX</a>

      <input class="menu-checkbox" type="checkbox" id="menu-open" aria-label="Menu">
      <label class="menu-toggle" for="menu-open"><span></span></label>

      <div class="bar-nav">
        <nav class="<?= e($menuClass) ?>">
<?php foreach ($links as [$key, $label, $href]): ?>
          <a href="<?= e($href) ?>"<?= $key === $current ? ' class="current"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
        </nav>

        <div class="auth-buttons">
<?php if ($user === null): ?>
          <a class="button button-outline" href="<?= e(url('signin.php')) ?>">Sign In</a>
          <a class="button button-red" href="<?= e(url('signup.php')) ?>">Sign Up</a>
<?php else: ?>
          <form class="inline-form" method="post" action="<?= e(url('signout.php')) ?>">
            <?= csrf_field() ?>
            <button class="button button-red" type="submit">Sign Out</button>
          </form>
<?php endif; ?>
        </div>
      </div>

    </div>
  </header>
<?php
}

/**
 * The staff sidebar, with $current highlighted.
 */
function render_staff_sidebar(string $current): void
{
    $user = current_user();
    if (!is_staff($user)) {
        return;
    }
    ?>
    <aside class="admin-sidebar">
      <nav class="sidebar-menu">
        <p class="sidebar-title">Staff</p>
<?php foreach (staff_links($user) as [$key, $label, $path]): ?>
        <a href="<?= e(url($path)) ?>"<?= $key === $current ? ' class="current"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
      </nav>
    </aside>
<?php
}

/**
 * A booking's details laid out like the e-ticket's body, for the payment
 * pages, under the name of the customer who booked. $amountLabel heads the
 * price: 'Amount Due' or 'Total'.
 */
function render_booking_summary(array $booking, string $amountLabel): void
{
    ?>
    <div class="ticket">
      <div class="ticket-body">
        <div class="ticket-row">
          <p class="ticket-label">Name</p>
          <p class="ticket-value ticket-value-small"><?= e($booking['customer_name']) ?></p>
        </div>
        <div class="ticket-pair">
          <div>
            <p class="ticket-label">Reference No.</p>
            <p class="ticket-value ticket-reference"><?= e($booking['reference']) ?></p>
          </div>
          <div>
            <p class="ticket-label">Seat(s)</p>
            <p class="ticket-value"><?= e($booking['seat_list']) ?></p>
          </div>
        </div>
        <div class="ticket-pair">
          <div>
            <p class="ticket-label">Movie</p>
            <p class="ticket-value ticket-value-small"><?= e($booking['title']) ?></p>
          </div>
          <div>
            <p class="ticket-label"><?= e($amountLabel) ?></p>
            <p class="ticket-value"><?= e(peso((int) $booking['total'])) ?></p>
          </div>
        </div>
        <p class="ticket-label">Showing</p>
        <p class="ticket-value ticket-value-small"><?= e(format_showing((string) $booking['show_date'], (string) $booking['show_time'], (int) $booking['cinema'])) ?></p>
      </div>
    </div>
<?php
}

/**
 * The footer, the page's scripts, and the end of the page. Options:
 *   'js' => scripts under assets/, e.g. ['assets/js/booking.js']
 */
function render_footer(array $options = []): void
{
    $scripts = array_merge(['assets/js/menu.js'], $options['js'] ?? []);
    ?>
  <footer class="site-footer">
    <div class="footer-inner">
      <a class="logo" href="<?= e(url('index.php')) ?>"><img class="logo-mark" src="<?= e(asset('assets/img/logo.png')) ?>" alt="" width="49" height="44">CINEMAX</a>
      <p class="footer-text">
        Your next movie experience starts here. <br>
        Book your seats, enjoy your snacks, and let the show begin.
      </p>
      <p class="footer-copyright">&copy; <?= e(date('Y')) ?> Cinemax. All rights reserved. <a href="<?= e(url('terms.php')) ?>">Terms of Service</a></p>
    </div>
  </footer>

<?php foreach ($scripts as $script): ?>
  <script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
