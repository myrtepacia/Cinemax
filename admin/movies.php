<?php
declare(strict_types=1);

// Staff Movies page: films, opening day, tickets sold, and Remove. Films have
// no last day: one stays until removed here.

require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('admin');

if (is_post()) {
    verify_csrf();

    // remove_movie() checks the film is still listed
    $movieId = input_int($_POST, 'movie_id', 1);
    $movie = $movieId !== null ? find_movie($movieId) : null;
    $action = input_string($_POST, 'action', 10);

    if ($movie !== null && $action === 'remove') {
        // Tickets already sold stay valid
        remove_movie((int) $movie['id']);
    }
    redirect('admin/movies.php');
}

$movies = active_movies();

// Status labels
$labels = [
    'showing' => ['class' => 'pill-green', 'text' => 'Now Showing'],
    'soon'    => ['class' => 'pill-indigo', 'text' => 'Upcoming'],
    'ended'   => ['class' => 'pill-grey', 'text' => 'Ended'],
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
              <th class="hide-small">Opening day</th>
              <th class="right hide-small">Price</th>
              <th class="right">Sold</th>
              <th></th>
            </tr>
          </thead>

          <tbody>
<?php foreach ($movies as $movie): ?>
<?php $label = $labels[movie_listing_state($movie)] ?? $labels['ended']; ?>

            <tr class="movie-row">
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

              <td class="hide-small"><span class="pill <?= e($label['class']) ?>"><?= e($label['text']) ?></span></td>
              <!-- Starting films have no opening day: they were showing from
                   the start -->
              <td class="hide-small opening-day-cell"><?= e($movie['opens_on'] !== null ? format_date_short((string) $movie['opens_on']) : '—') ?></td>
              <td class="right hide-small"><?= e(peso((int) $movie['price'])) ?></td>
              <td class="right sold-cell"><?= e(number_format(movie_tickets_sold((int) $movie['id']))) ?></td>
              <td class="right">
                <span class="row-actions">
                  <form method="post" action="<?= e(url('admin/movies.php')) ?>" class="inline-form remove-form"
                        data-title="<?= e($movie['title']) ?>">
                    <?= csrf_field() ?>

                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="movie_id" value="<?= e($movie['id']) ?>">
                    <button class="button button-outline button-small" type="submit">Remove</button>
                  </form>
                </span>
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
