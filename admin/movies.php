<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('admin');

if (is_post()) {
    verify_csrf();

    // set_movie_active() checks the film is not already that way
    $movieId = input_int($_POST, 'movie_id', 1);
    $movie = $movieId !== null ? find_movie($movieId) : null;
    $status = input_string($_POST, 'status', 10);
    $flash = ['notice' => '', 'error' => ''];

    if ($movie !== null && input_string($_POST, 'action', 10) === 'delete') {
        // Only an inactive film no booking names (delete_movie() checks)
        $flash['error'] = delete_movie((int) $movie['id']);
        $flash['notice'] = $flash['error'] === '' ? $movie['title'] . ' was deleted.' : '';
    } elseif ($movie !== null && in_array($status, ['active', 'inactive'], true)) {
        // Tickets already sold stay valid either way
        set_movie_active((int) $movie['id'], $status === 'active');
    }
    // Shown once, after the redirect
    $_SESSION['movies_flash'] = $flash;
    redirect('admin/movies.php');
}

// Now Showing first, then Upcoming, then Ended: a film moves up by itself on
// its opening day. Same group: the order they were added.
$listingOrder = ['showing' => 0, 'soon' => 1, 'ended' => 2];
$movies = active_movies();
usort($movies, static function (array $a, array $b) use ($listingOrder): int {
    return [$listingOrder[movie_listing_state($a)] ?? 3, (int) $a['id']] <=> [$listingOrder[movie_listing_state($b)] ?? 3, (int) $b['id']];
});
$inactiveMovies = inactive_movies();
$flash = $_SESSION['movies_flash'] ?? ['notice' => '', 'error' => ''];
unset($_SESSION['movies_flash']);

// Status labels
$labels = [
    'showing'  => ['class' => 'pill-green', 'text' => 'Now Showing'],
    'soon'     => ['class' => 'pill-indigo', 'text' => 'Upcoming'],
    'ended'    => ['class' => 'pill-grey', 'text' => 'Ended'],
    'inactive' => ['class' => 'pill-grey', 'text' => 'Inactive'],
];

/**
 * One film's row: poster and details, status, opening day, price, tickets
 * sold, and its status: a dropdown offering the other one (Active / Inactive).
 * An inactive film also gets a trash button, which deletes it for good; it is
 * greyed out when bookings name the film.
 */
$movieRow = static function (array $movie, bool $active) use ($labels): void {
    $label = $active ? ($labels[movie_listing_state($movie)] ?? $labels['ended']) : $labels['inactive'];
    ?>
            <tr class="movie-row<?= $active ? '' : ' movie-row-off' ?>">
              <td>
                <span class="cell-with-poster">
                  <img class="table-poster skeleton" src="<?= e(poster_url($movie)) ?>"
                       alt="<?= e($movie['title'] . ' poster') ?>"
                       width="40" height="54" loading="lazy" decoding="async">
                  <span>
                    <span class="movie-name"><?= e($movie['title']) ?></span>
                    <span class="movie-sub"><?= e(movie_details_line($movie)) ?></span>
                  </span>
                </span>
              </td>

              <td class="hide-small status-cell"><span class="pill <?= e($label['class']) ?>"><?= e($label['text']) ?></span></td>
              <!-- Starting films have no opening day: they were showing from
                   the start -->
              <td class="hide-small opening-day-cell"><?= e($movie['opens_on'] !== null ? format_date_short((string) $movie['opens_on']) : '—') ?></td>
              <td class="right hide-small"><?= e(peso((int) $movie['price'])) ?></td>
              <td class="right sold-cell"><?= e(number_format(movie_tickets_sold((int) $movie['id']))) ?></td>
              <td class="right">
                <span class="row-actions">
<?php if (!$active): ?>
<?php $deletable = movie_sales_booking_count((int) $movie['id']) === 0; ?>
                  <form method="post" action="<?= e(url('admin/movies.php')) ?>" class="inline-form confirm-form"
                        data-confirm="<?= e('Delete ' . $movie['title'] . ' for good? Its showtimes and poster go too. This cannot be undone.') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="movie_id" value="<?= e($movie['id']) ?>">
                    <button class="trash-button" type="submit"<?= $deletable ? '' : ' disabled' ?>
                            title="<?= e($deletable ? 'Delete ' . $movie['title'] : $movie['title'] . ' has bookings, so it cannot be deleted') ?>"
                            aria-label="<?= e($deletable ? 'Delete ' . $movie['title'] : $movie['title'] . ' has bookings, so it cannot be deleted') ?>">
                      <!-- Lucide trash-2 (lucide.dev, ISC licence) -->
                      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                        <path d="M10 11v6"/><path d="M14 11v6"/>
                      </svg>
                    </button>
                  </form>
<?php endif; ?>
                  <!-- The current status; opening it offers the other one -->
                  <details class="status-menu">
                    <summary class="status-current" aria-label="<?= e($movie['title'] . ': ' . ($active ? 'Active' : 'Inactive') . '. Change') ?>">
                      <?= $active ? 'Active' : 'Inactive' ?>
                    </summary>
                    <form method="post" action="<?= e(url('admin/movies.php')) ?>" class="status-menu-list">
                      <?= csrf_field() ?>
                      <input type="hidden" name="movie_id" value="<?= e($movie['id']) ?>">
                      <button class="status-menu-item" type="submit" name="status" value="<?= $active ? 'inactive' : 'active' ?>">
                        <?= $active ? 'Inactive' : 'Active' ?>
                      </button>
                    </form>
                  </details>
                </span>
              </td>
            </tr>
<?php
};

$tableHead = <<<'HTML'
          <thead>
            <tr>
              <th>Movie</th>
              <th class="hide-small status-cell">Status</th>
              <th class="hide-small">Opening day</th>
              <th class="right hide-small">Price</th>
              <th class="right">Sold</th>
              <th></th>
            </tr>
          </thead>
HTML;

render_head('Movies', ['assets/css/admin.css']);
render_header(['staff' => true, 'current' => 'movies']);
?>
  <div class="admin-layout">
    <?php render_staff_sidebar('movies'); ?>

    <div class="admin-head">
      <h1>Movies</h1>
    </div>

    <main class="admin-page">

<?php if ($flash['notice'] !== ''): ?>
      <p class="form-message form-message-success" role="status"><?= e($flash['notice']) ?></p>
<?php endif; ?>
<?php if ($flash['error'] !== ''): ?>
      <p class="form-message form-message-error" role="alert"><?= e($flash['error']) ?></p>
<?php endif; ?>

      <section class="panel">

        <div class="panel-head">
          <div>
            <h2>All movies</h2>
            <p class="panel-note">Everything currently listed on the website.</p>
          </div>
          <a class="button button-red" href="<?= e(url('admin/add-movie.php')) ?>">+ Add movie</a>
        </div>

<?php if (count($movies) === 0): ?>
        <p class="empty-state">
          <strong>No movies on the listings</strong>
          Add a movie, or set one below to Active, and it shows up here and on the website.
        </p>
<?php else: ?>
        <table class="movie-table">

<?= $tableHead ?>

          <tbody>
<?php foreach ($movies as $movie): ?>
<?php $movieRow($movie, true); ?>
<?php endforeach; ?>
          </tbody>
        </table>
<?php endif; ?>

      </section>

<?php if ($inactiveMovies !== []): ?>
      <section class="panel">

        <div class="panel-head">
          <div>
            <h2>Inactive</h2>
            <p class="panel-note">Not on the website. Tickets already sold stay valid. Set one Active to list it again, with its old showtimes.</p>
          </div>
        </div>

        <table class="movie-table">

<?= $tableHead ?>

          <tbody>
<?php foreach ($inactiveMovies as $movie): ?>
<?php $movieRow($movie, false); ?>
<?php endforeach; ?>
          </tbody>
        </table>

      </section>
<?php endif; ?>

    </main>

  </div>
<?php render_footer(['js' => ['assets/js/status-menu.js', 'assets/js/confirm-form.js']]);
