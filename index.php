<?php
declare(strict_types=1);

// Home page: films showing now and coming soon.

require __DIR__ . '/includes/bootstrap.php';

$lists = listing_movies();
$showing = $lists['showing'];
$soon = $lists['soon'];

// The cheapest ticket, for the line under the title
$lowestPrice = null;
foreach ($showing as $movie) {
    $price = (int) $movie['price'];
    if ($lowestPrice === null || $price < $lowestPrice) {
        $lowestPrice = $price;
    }
}

// The first row of posters loads first; the rest when scrolled near
$eagerPosters = 4;
$posterCount = 0;

render_head('Cinemax');
render_header(['home' => true, 'current' => 'now-showing']);
?>
  <main>

    <section class="section" id="now-showing">

      <div class="section-title-row">
        <h2 class="section-title">Now Showing</h2>
        <span class="film-count" id="showing-count"><?= e(count_label(count($showing), 'Film', 'Films')) ?></span>
      </div>
      <p class="section-note">Reserve your seat and pre-order your snacks.<?php if ($lowestPrice !== null): ?> Tickets from <?= e(peso($lowestPrice)) ?>.<?php endif; ?></p>

<?php if ($showing === []): ?>
      <p class="empty-state">
        <strong>No films showing right now</strong>
        New screenings appear here as soon as they open.
      </p>
<?php else: ?>
      <div class="movie-grid" id="showing-grid">
<?php foreach ($showing as $movie): ?>
<?php $eager = $posterCount++ < $eagerPosters; ?>

        <div class="movie-card">
          <div class="movie-poster">
            <img src="<?= e(poster_url($movie)) ?>" alt="<?= e($movie['title'] . ' poster') ?>"
                 width="900" height="1200" decoding="async" <?= $eager ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
            <span class="tag-rated"><?= e($movie['rating']) ?></span>
            <span class="tag-time"><?= e(duration_tag((int) $movie['duration_minutes'])) ?></span>
          </div>
          <div class="movie-info">
            <h3 class="movie-title"><?= e($movie['title']) ?></h3>
            <a class="button button-red button-wide" href="<?= e(url('book.php?movie=' . rawurlencode((string) $movie['slug']))) ?>">Book Now</a>
          </div>
        </div>
<?php endforeach; ?>

      </div>
<?php endif; ?>

    </section>

    <section class="section" id="coming-soon">

      <div class="section-title-row">
        <h2 class="section-title">Upcoming Shows</h2>
        <span class="film-count" id="soon-count"><?= e(count_label(count($soon), 'Film', 'Films')) ?></span>
      </div>
      <p class="section-note">Coming to Cinemax soon. Booking opens on release day.</p>

<?php if ($soon === []): ?>
      <p class="empty-state">
        <strong>Nothing announced yet</strong>
        New titles appear here first.
      </p>
<?php else: ?>
      <div class="movie-grid" id="soon-grid">
<?php foreach ($soon as $movie): ?>
<?php $eager = $posterCount++ < $eagerPosters; ?>

        <div class="movie-card">
          <div class="movie-poster">
            <img src="<?= e(poster_url($movie)) ?>" alt="<?= e($movie['title'] . ' poster') ?>"
                 width="900" height="1200" decoding="async" <?= $eager ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
            <span class="tag-rated"><?= e($movie['rating']) ?></span>
            <span class="tag-time"><?= e(duration_tag((int) $movie['duration_minutes'])) ?></span>
          </div>
          <div class="movie-info">
            <h3 class="movie-title"><?= e($movie['title']) ?></h3>
            <span class="button button-grey button-wide"><?= e(movie_opening_note($movie)) ?></span>
          </div>
        </div>
<?php endforeach; ?>

      </div>
<?php endif; ?>

    </section>

  </main>

<?php render_footer();
