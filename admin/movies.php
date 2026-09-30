<?php
declare(strict_types=1);

// Staff Movies page: every film on the listings, how many tickets each has
// sold, and a Remove button that takes a film off the website.

require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('admin');

// Taking a film off. Its bookings stay valid; it just cannot be booked any
// more. The id is only a pointer: remove_movie() checks it is still listed.
if (is_post()) {
    verify_csrf();

    $movieId = input_int($_POST, 'movie_id', 1);
    $movie = $movieId !== null ? find_movie($movieId) : null;

    if ($movie === null) {
        flash('error', 'That movie could not be found.');
    } elseif (remove_movie((int) $movie['id'])) {
        flash('success', $movie['title'] . ' was taken off the listings.');
    } else {
        flash('notice', $movie['title'] . ' is already off the listings.');
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
      <?php render_flashes(); ?>

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
              <th class="right hide-small">Price</th>
              <th class="right">Sold</th>
              <th></th>
            </tr>
          </thead>

          <tbody>
<?php foreach ($movies as $movie): ?>
<?php $label = $labels[movie_listing_state($movie)] ?? $labels['ended']; ?>

            <tr>
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
              <td class="right hide-small"><?= e(peso((int) $movie['price'])) ?></td>
              <td class="right sold-cell"><?= e(number_format(movie_tickets_sold((int) $movie['id']))) ?></td>
              <td class="right">
                <form method="post" action="<?= e(url('admin/movies.php')) ?>" class="inline-form remove-form"
                      data-title="<?= e($movie['title']) ?>">
                  <?= csrf_field() ?>

                  <input type="hidden" name="movie_id" value="<?= e($movie['id']) ?>">
                  <button class="remove-button" type="submit">Remove</button>
                </form>
              </td>
            </tr>
<?php endforeach; ?>

          </tbody>
        </table>
<?php endif; ?>

      </section>

    </main>

  </div>
<?php render_footer(['js' => ['assets/js/admin-movies.js']]);
