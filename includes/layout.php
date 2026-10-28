<?php
declare(strict_types=1);

// Shared page parts: <head>, top bar, staff sidebar, booking summary and
// footer. No inline scripts or styles (the CSP blocks them): scripts go in
// assets/js and are listed in 'js'.

if (!defined('CSFLIX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * A full address on the site's own configured domain (config app_url), for
 * search engines and link previews. Never built from the request's Host
 * header, which a visitor can fake.
 */
function public_url(string $path): string
{
    return site_origin() . url($path);
}

const SITE_DESCRIPTION = 'Book cinema seats and pre-order snacks online with CSFlix, a student project cinema booking website.';

/** A film as schema.org structured data, for search engines. */
function movie_schema(array $movie): array
{
    $minutes = (int) $movie['duration_minutes'];
    return [
        '@type'         => 'Movie',
        'name'          => (string) $movie['title'],
        'url'           => public_url('book.php?movie=' . rawurlencode((string) $movie['slug'])),
        'image'         => site_origin() . poster_url($movie),
        'genre'         => array_map('trim', explode(',', (string) $movie['genre'])),
        'duration'      => 'PT' . intdiv($minutes, 60) . 'H' . ($minutes % 60) . 'M',
        'contentRating' => (string) $movie['rating'],
    ];
}

/** The tags a link preview shows when a film's page is shared. */
function movie_meta(array $movie, bool $index = true): array
{
    return [
        'share_title' => $movie['title'] . ' · ' . config('site.name', 'CSFlix'),
        'description' => 'Book seats for ' . $movie['title'] . ' (' . movie_details_line($movie) . ') at CSFlix, and pre-order your snacks.',
        'image'       => site_origin() . poster_url($movie),
        'image_alt'   => $movie['title'] . ' poster',
        'type'        => 'video.movie',
        'index'       => $index,
        'canonical'   => 'book.php?movie=' . rawurlencode((string) $movie['slug']),
        'schema'      => ['@context' => 'https://schema.org'] + movie_schema($movie),
    ];
}

/**
 * Opens the page. $css lists extra style sheets. $meta, all optional:
 * 'description', 'image' (a full URL) and 'image_alt', 'type' (Open Graph,
 * default 'website'), 'index' (true lets search engines list the page; off by
 * default, so private pages stay out), 'canonical' (the page's own path) and
 * 'schema' (structured data for search engines).
 */
function render_head(string $title, array $css = [], array $meta = []): void
{
    $siteName = (string) config('site.name', 'CSFlix');
    $fullTitle = $title === $siteName ? $title : $title . ' · ' . $siteName;
    // A link preview may name something else, like the film a sign-in is for
    $shareTitle = (string) ($meta['share_title'] ?? $fullTitle);
    $description = (string) ($meta['description'] ?? SITE_DESCRIPTION);
    $index = !empty($meta['index']);
    $canonical = public_url((string) ($meta['canonical'] ?? strtok(current_path(), '?')));
    $image = (string) ($meta['image'] ?? site_origin() . asset('assets/img/icons/icon-512.png'));
    $imageAlt = (string) ($meta['image_alt'] ?? $siteName . ' logo');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($fullTitle) ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <meta name="robots" content="<?= $index ? 'index, follow' : 'noindex, nofollow' ?>">
<?php if ($index): ?>
  <link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
  <meta name="author" content="<?= e($siteName) ?> project team">
  <meta name="color-scheme" content="light">
  <meta name="format-detection" content="telephone=no">

  <!-- Link previews (Facebook, Messenger, Viber, X) -->
  <meta property="og:site_name" content="<?= e($siteName) ?>">
  <meta property="og:locale" content="en_PH">
  <meta property="og:type" content="<?= e((string) ($meta['type'] ?? 'website')) ?>">
  <meta property="og:title" content="<?= e($shareTitle) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:image" content="<?= e($image) ?>">
  <meta property="og:image:alt" content="<?= e($imageAlt) ?>">
  <meta name="twitter:card" content="<?= isset($meta['image']) ? 'summary_large_image' : 'summary' ?>">
  <meta name="twitter:title" content="<?= e($shareTitle) ?>">
  <meta name="twitter:description" content="<?= e($description) ?>">
  <meta name="twitter:image" content="<?= e($image) ?>">
  <meta name="twitter:image:alt" content="<?= e($imageAlt) ?>">
<?php if (!empty($meta['schema'])): ?>
  <!-- Structured data for search engines (data only, never run as a script) -->
  <script type="application/ld+json"><?= json_encode($meta['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<?php endif; ?>

  <link rel="icon" type="image/png" href="<?= e(asset('assets/img/icons/icon-192.png')) ?>">
  <!-- Installable on phones: manifest, home-screen icon, app colours -->
  <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
  <link rel="apple-touch-icon" href="<?= e(asset('assets/img/icons/apple-touch-icon.png')) ?>">
  <meta name="theme-color" content="#ffffff">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="CSFlix">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('assets/css/csflix.css')) ?>">
<?php foreach ($css as $sheet): ?>
  <link rel="stylesheet" href="<?= e(asset($sheet)) ?>">
<?php endforeach; ?>
</head>
<body>
<?php
}

/** The policy pages, linked from the footer and from each other: [label, path]. */
function policy_links(): array
{
    return [
        ['Terms and Conditions', 'terms.php'],
        ['Privacy Policy', 'privacy.php'],
        ['Refund Policy', 'refunds.php'],
    ];
}

/** Where customers write about privacy, refunds and complaints, or ''. */
function site_contact_email(): string
{
    return (string) config('site.contact_email', '');
}

/** True while CSFlix is a school project rather than a registered business. */
function is_student_project(): bool
{
    return (bool) config('site.student_project', true);
}

/** The opening of a policy page: title, last-updated date ('2026-09-22') and intro. */
function render_policy_intro(string $title, string $intro, string $updated = ''): void
{
    ?>
    <h1><?= e($title) ?></h1>
<?php if ($updated !== ''): ?>
    <p class="terms-updated">Last updated <?= e(format_date_long($updated)) ?></p>
<?php endif; ?>
    <p class="terms-intro"><?= $intro ?></p>
<?php
}

/** The "Questions?" block that ends each policy page. */
function render_policy_contact(): void
{
    $email = site_contact_email();
    ?>
    <div class="terms-block">
      <h2>Contact us</h2>
<?php if ($email !== ''): ?>
      <p>For questions about this page, your personal data, a refund or a complaint, email <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>. Tell us the email on your account and, for a booking, its reference number.</p>
<?php else: ?>
      <p>For questions about this page, your personal data, a refund or a complaint, contact the CSFlix project team.</p>
<?php endif; ?>
      <p>See also:
<?php foreach (policy_links() as $i => [$label, $path]): ?>
        <?= $i > 0 ? '&middot; ' : '' ?><a href="<?= e(url($path)) ?>"><?= e($label) ?></a>
<?php endforeach; ?>
      </p>
    </div>
<?php
}

/** The staff links a user may see: [key, label, path]. */
function staff_links(array $user): array
{
    if ($user['role'] === 'admin') {
        return [
            ['dashboard', 'Dashboard', 'admin/index.php'],
            ['movies', 'Movies', 'admin/movies.php'],
            ['add-movie', 'Add Movie', 'admin/add-movie.php'],
            ['snacks', 'Snacks', 'admin/snacks.php'],
            ['scanner', 'Scanner', 'admin/scanner.php'],
        ];
    }
    if ($user['role'] === 'scanner') {
        return [
            ['scanner', 'Scanner', 'admin/scanner.php'],
        ];
    }
    return [];
}

/**
 * The top bar. Options: 'current' (highlighted link), 'staff' (staff pages),
 * 'home' (home page: links jump down the page).
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
<?php if (is_student_project() && !$onStaffPage): ?>
  <p class="school-banner" role="note"><strong>For school purposes only.</strong> CSFlix is a student project, not a real cinema.</p>
<?php endif; ?>
  <header class="top-bar">
    <div class="top-bar-inner">

      <a class="logo" href="<?= e(url('index.php')) ?>"><img class="logo-image" src="<?= e(asset('assets/img/logo.png')) ?>" alt="CSFlix" width="280" height="120"></a>

      <input class="menu-checkbox" type="checkbox" id="menu-open" aria-label="Menu">
      <label class="menu-toggle" for="menu-open"><span></span></label>

      <div class="bar-nav">
        <nav class="<?= e($menuClass) ?>">
<?php foreach ($links as [$key, $label, $href]): ?>
          <a href="<?= e($href) ?>"<?= $key === $current ? ' class="current"' : '' ?>><span class="menu-label"><?= e($label) ?></span></a>
<?php endforeach; ?>
        </nav>

        <div class="auth-buttons">
<?php if ($user === null): ?>
<?php // Signing in or up comes back to this page (signin_return_path) ?>
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
 * The staff sidebar: a rail of icons on the left; the hamburger at its top
 * shows the names too, over the page (sidebar.js).
 */
function render_staff_sidebar(string $current): void
{
    $user = current_user();
    if (!is_staff($user)) {
        return;
    }
    // One icon per staff page, from Lucide (lucide.dev, ISC licence):
    // layout-dashboard, film, square-plus, popcorn, scan-line
    $icons = [
        'dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/>'
            . '<rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'movies'    => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 3v18"/><path d="M3 7.5h4"/><path d="M3 12h18"/>'
            . '<path d="M3 16.5h4"/><path d="M17 3v18"/><path d="M17 7.5h4"/><path d="M17 16.5h4"/>',
        'add-movie' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M8 12h8"/><path d="M12 8v8"/>',
        'snacks'    => '<path d="M18 8a2 2 0 0 0 0-4 2 2 0 0 0-4 0 2 2 0 0 0-4 0 2 2 0 0 0-4 0 2 2 0 0 0 0 4"/><path d="M10 22 9 8"/>'
            . '<path d="m14 22 1-14"/><path d="M20 8c.5 0 .9.4.8 1l-2.6 12c-.1.5-.7 1-1.2 1H7c-.6 0-1.1-.4-1.2-1L3.2 9c-.1-.6.3-1 .8-1Z"/>',
        'scanner'   => '<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/>'
            . '<path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/>',
    ];
    $svg = '<svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
    ?>
    <aside class="admin-sidebar" id="staff-sidebar">
      <div class="sidebar-top">
        <button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-controls="staff-sidebar" aria-expanded="false" aria-label="Show menu names">
          <?= $svg ?><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/></svg>
        </button>
        <p class="sidebar-title">Staff</p>
      </div>
      <nav class="sidebar-menu" aria-label="Staff">
<?php foreach (staff_links($user) as [$key, $label, $path]): ?>
        <a href="<?= e(url($path)) ?>" title="<?= e($label) ?>"<?= $key === $current ? ' class="current" aria-current="page"' : '' ?>>
          <?= $svg . ($icons[$key] ?? '<circle cx="12" cy="12" r="8"/>') ?></svg>
          <span class="sidebar-label"><?= e($label) ?></span>
        </a>
<?php endforeach; ?>
      </nav>
    </aside>
<?php
}

/** A booking's details for the payment pages. */
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
 * "Choose another payment": one row per other way to pay ($methods: method =>
 * name), each sent to pay-other.php, with its symbol (or logo) and a
 * selection circle.
 */
function render_other_payments(string $reference, array $methods): void
{
    if ($methods === []) {
        return;
    }
    // Line symbols in grey
    $symbols = [
        // Lucide qr-code and credit-card (lucide.dev, ISC licence)
        'qrph'    => '<rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/>'
            . '<rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/>'
            . '<path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/>'
            . '<path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/>',
        'card'    => '<rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>',
    ];
    // Ways to pay shown with their own logo instead
    $logos = ['gcash' => 'assets/img/pay-gcash.svg'];
    ?>
        <details class="pay-other">
          <summary>Choose another payment</summary>
          <form method="post" action="<?= e(url('pay-other.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="ref" value="<?= e($reference) ?>">
<?php foreach ($methods as $method => $name): ?>
            <button class="pay-other-option" type="submit" name="method" value="<?= e($method) ?>">
              <span class="pay-other-name">Pay with <?= e($name) ?></span>
              <span class="pay-other-mark">
<?php if (isset($logos[$method])): ?>
                <img class="pay-other-logo" src="<?= e(asset($logos[$method])) ?>" alt="" width="45" height="18">
<?php elseif (isset($symbols[$method])): ?>
                <svg class="pay-other-symbol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?= $symbols[$method] ?></svg>
<?php endif; ?>
              </span>
              <span class="pay-other-dot" aria-hidden="true"></span>
            </button>
<?php endforeach; ?>
          </form>
        </details>
<?php
}

/** The footer and the page's scripts. Option 'js': scripts under assets/. */
function render_footer(array $options = []): void
{
    // sidebar.js opens and closes the staff sidebar
    // password.js adds the show / hide button to every password box
    // skeleton.js takes the loading shimmer off each picture once it is in
    // pwa.js makes the site installable on phones
    $scripts = array_merge(['assets/js/menu.js', 'assets/js/sidebar.js', 'assets/js/password.js', 'assets/js/skeleton.js', 'assets/js/pwa.js'], $options['js'] ?? []);
    $email = site_contact_email();
    ?>
  <!-- About on the left, the brand and contact in the centre, the policy
       links down the right; below, the copyright. One column on phones -->
  <footer class="site-footer">
    <div class="footer-inner">
      <div class="footer-col">
        <h2 class="footer-heading">About CSFlix</h2>
<?php if (is_student_project()): ?>
        <p class="footer-about">A student project for school. It is not a registered business and does not sell real cinema tickets.</p>
<?php endif; ?>
        <p class="footer-about">Film titles, posters and logos belong to their owners and are shown only to identify the films.</p>
      </div>
      <div class="footer-brand">
        <a class="logo" href="<?= e(url('index.php')) ?>"><img class="logo-image" src="<?= e(asset('assets/img/logo.png')) ?>" alt="CSFlix" width="280" height="120"></a>
        <p class="footer-text">
          Your next movie experience starts here. Book your seats, enjoy your snacks, and let the show begin.
        </p>
<?php if ($email !== ''): ?>
        <p class="footer-text footer-contact">Contact: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
<?php endif; ?>
      </div>
      <nav class="footer-policies" aria-labelledby="footer-policies-heading">
        <h2 class="footer-heading" id="footer-policies-heading">Policies</h2>
        <ul class="footer-links">
<?php foreach (policy_links() as [$label, $path]): ?>
          <li><a href="<?= e(url($path)) ?>"><?= e($label) ?></a></li>
<?php endforeach; ?>
        </ul>
      </nav>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?= e(date('Y')) ?> CSFlix. All rights reserved.</p>
    </div>
  </footer>

<?php foreach ($scripts as $script): ?>
  <script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
