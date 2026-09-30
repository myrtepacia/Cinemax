<?php
declare(strict_types=1);

// Staff Movies page: every film on the listings, its last day, how many
// tickets each has sold, an Extend button that moves the last day later,
// and a Remove button that takes a film off the website.

require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('admin');

if (is_post()) {
    verify_csrf();

    // The id is only a pointer: extend_movie() and remove_movie() check the
    // film is still listed
    $movieId = input_int($_POST, 'movie_id', 1);
    $movie = $movieId !== null ? find_movie($movieId) : null;
    $action = input_string($_POST, 'action', 10);

    if ($movie !== null && $action === 'extend') {
        // A longer run: the same showtimes, on more days. A day that is not
        // allowed (see extend_movie()) changes nothing.
        $newLast = input_date($_POST, 'ends_on');
        if ($newLast !== null) {
            extend_movie((int) $movie['id'], $newLast);
        }
    } elseif ($movie !== null && $action === 'remove') {
        // Taking a film off. Its bookings stay valid; it just cannot be
        // booked any more.
        remove_movie((int) $movie['id']);
    }
    redirect('admin/movies.php');
}

$movies = active_movies();

// The label in the Status column for each place a film can be in
$labels = [
    'showing' => ['class' => 'label-showing', 'text' => 'Now Showing'],
    'soon'    => ['class' => 'label-soon', 'text' => 'Upcoming'],
    'ended'   => ['class' => 'label-ended', 'text' => 'Ended'],
];

render_head('Movies', ['assets/css/admin.css']);
render_header(['staff' => true, 'current' => 'movies']);
?>
  <div class="admin-layout">
    <?php render_staff_sidebar('movies'); ?>

    <div class="admin-head">
      <h1>Movies</h1>
      <p class="admin-intro">Everything on the listings. Take a movie off, or add a new one.</p>
    </div>

    <main class="admin-page">

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
          Add a movie and it shows up here and on the website.
        </p>
<?php else: ?>
        <table class="movie-table">

          <thead>
            <tr>
              <th>Movie</th>
              <th class="hide-small">Status</th>
              <th class="hide-small">Last day</th>
              <th class="right hide-small">Price</th>
              <th class="right">Sold</th>
              <th></th>
            </tr>
          </thead>

          <tbody>
<?php foreach ($movies as $index => $movie): ?>
<?php
    $label = $labels[movie_listing_state($movie)] ?? $labels['ended'];
    $isLast = $index === array_key_last($movies);
    // Only a run with a last day can be extended
    $lastDay = $movie['ends_on'] !== null ? (string) $movie['ends_on'] : null;
    $extendFrom = $lastDay !== null ? movie_extend_from($movie) : null;
    $limit = $lastDay !== null ? movie_extend_limit($movie) : null;
    $canExtend = $lastDay !== null && ($limit === null || $limit['last'] >= $extendFrom);
    $panelId = 'extend-' . (int) $movie['id'];
?>

            <tr class="movie-row<?= $isLast ? ' last-movie' : '' ?>">
              <td>
                <span class="cell-with-poster">
                  <img class="table-poster" src="<?= e(poster_url($movie)) ?>"
                       alt="<?= e($movie['title'] . ' poster') ?>"
                       width="40" height="54" loading="lazy" decoding="async">
                  <span>
                    <span class="movie-name"><?= e($movie['title']) ?></span>
                    <span class="movie-sub"><?= e(movie_details_line($movie, true)) ?></span>
                  </span>
                </span>
              </td>

              <td class="hide-small"><span class="<?= e($label['class']) ?>"><?= e($label['text']) ?></span></td>
              <td class="hide-small last-day-cell"><?= e($lastDay !== null ? format_date_short($lastDay) : 'No last day') ?></td>
              <td class="right hide-small"><?= e(peso((int) $movie['price'])) ?></td>
              <td class="right sold-cell"><?= e(number_format(movie_tickets_sold((int) $movie['id']))) ?></td>
              <td class="right">
                <span class="row-actions">
<?php if ($lastDay !== null): ?>
                  <button class="remove-button extend-toggle" type="button"
                          aria-controls="<?= e($panelId) ?>" aria-expanded="false">Extend</button>
<?php endif; ?>
                  <form method="post" action="<?= e(url('admin/movies.php')) ?>" class="inline-form remove-form"
                        data-title="<?= e($movie['title']) ?>">
                    <?= csrf_field() ?>

                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="movie_id" value="<?= e($movie['id']) ?>">
                    <button class="remove-button" type="submit">Remove</button>
                  </form>
                </span>
              </td>
            </tr>
<?php if ($lastDay !== null): ?>

            <!-- Extend: opened by the row's Extend button (admin-movies.js) -->
            <tr class="extend-row" id="<?= e($panelId) ?>" hidden>
              <td colspan="6">
                <form method="post" action="<?= e(url('admin/movies.php')) ?>" class="extend-form">
                  <?= csrf_field() ?>

                  <input type="hidden" name="action" value="extend">
                  <input type="hidden" name="movie_id" value="<?= e($movie['id']) ?>">
                  <input type="hidden" name="ends_on" value="" class="extend-value">

                  <p class="extend-now">
                    Last day now: <strong><?= e((new DateTimeImmutable($lastDay))->format('D, j M Y')) ?></strong>
                  </p>
<?php if ($canExtend): ?>
                  <div class="extend-controls">
                    <!-- The calendar opens under this box; days outside
                         data-min..data-max cannot be picked -->
                    <div class="date-field extend-date-field"
                         data-min="<?= e((string) $extendFrom) ?>" data-max="<?= e($limit['last'] ?? '') ?>">
                      <button class="date-button extend-date-button" type="button" aria-haspopup="true" aria-expanded="false">
                        <span class="extend-date-text">Choose the new last day</span>
                        <span class="date-arrow">&#9662;</span>
                      </button>
                    </div>
                    <button class="button button-red extend-save" type="submit" disabled>Save</button>
                    <button class="remove-button extend-cancel" type="button">Cancel</button>
                  </div>
<?php endif; ?>
<?php if ($limit !== null): ?>
                  <p class="extend-note"><?= e(movie_extend_limit_text($movie, $limit)) ?></p>
<?php endif; ?>
                </form>
              </td>
            </tr>
<?php endif; ?>
<?php endforeach; ?>

          </tbody>
        </table>
<?php endif; ?>

      </section>

    </main>

  </div>
<?php render_footer(['js' => ['assets/js/admin-movies.js']]);
