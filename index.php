<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

// Signed out: Book Now goes to sign in first, then on to the film
$signedIn = current_user() !== null;

$lists = listing_movies();
$showing = $lists['showing'];
$soon = $lists['soon'];

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

// Background film strips: the posters repeated to a run of exactly 48 frames
// (5280px, wider than a 4K screen's strip, so its end never shows), then the
// run twice so it can slide round without a gap. The CSS speed counts on 48.
// The second strip slides the other way, with the posters in reverse.
const FILM_STRIP_FRAMES = 48;
$frames = array_map('poster_url', array_merge($showing, $soon));
$stripFrames = [];
while ($frames !== [] && count($stripFrames) < FILM_STRIP_FRAMES) {
    $stripFrames = array_merge($stripFrames, $frames);
}
$stripFrames = array_slice($stripFrames, 0, FILM_STRIP_FRAMES);
$strips = [
    'film-strip film-strip-left'  => $stripFrames,
    'film-strip film-strip-right' => array_reverse($stripFrames),
];

// For search engines: the site, and the films now showing
$filmList = [];
foreach (array_values($showing) as $i => $movie) {
    $filmList[] = ['@type' => 'ListItem', 'position' => $i + 1, 'item' => movie_schema($movie)];
}
render_head('CSFlix', [], [
    'description' => 'See what is showing at CSFlix, reserve your seats and pre-order your snacks online. A student project cinema booking website.',
    'index'       => true,
    'canonical'   => 'index.php',
    'schema'      => [
        '@context' => 'https://schema.org',
        '@graph'   => [
            ['@type' => 'WebSite', 'name' => 'CSFlix', 'url' => public_url('index.php'), 'description' => SITE_DESCRIPTION],
            ['@type' => 'ItemList', 'name' => 'Now Showing', 'itemListElement' => $filmList],
        ],
    ],
]);
render_header(['home' => true, 'current' => 'now-showing']);
?>
<?php if ($stripFrames !== []): ?>
  <div class="film-bg" aria-hidden="true">
<?php foreach ($strips as $class => $run): ?>
    <div class="<?= e($class) ?>">
      <div class="film-track">
<?php foreach (array_merge($run, $run) as $poster): ?>
        <img class="film-frame" src="<?= e($poster) ?>" alt="" width="92" height="122" decoding="async">
<?php endforeach; ?>
      </div>
    </div>
<?php endforeach; ?>
  </div>
<?php endif; ?>
  <main>
    <h1 class="visually-hidden">CSFlix: book cinema seats and pre-order snacks</h1>

    <section class="section" id="now-showing">

      <div class="section-title-row">
        <h2 class="section-title">Now Showing</h2>
        <span class="film-count" id="showing-count"><?= e(count_label(count($showing), 'Film', 'Films')) ?></span>
      </div>
      <p class="section-note">Reserve your seat and pre-order your snacks.</p>

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
            <img class="skeleton" src="<?= e(poster_url($movie)) ?>" alt="<?= e($movie['title'] . ' poster') ?>"
                 width="900" height="1200" decoding="async" <?= $eager ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
            <span class="tag-rated"><?= e($movie['rating']) ?></span>
            <span class="tag-time"><?= e(duration_tag((int) $movie['duration_minutes'])) ?></span>
          </div>
          <div class="movie-info">
            <h3 class="movie-title"><?= e($movie['title']) ?></h3>
<?php $bookPath = 'book.php?movie=' . rawurlencode((string) $movie['slug']); ?>
            <a class="button button-red button-wide" href="<?= e(url($bookPath)) ?>">Book Now</a>
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
      <p class="section-note">Coming to CSFlix soon. Booking opens on release day.</p>

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
            <img class="skeleton" src="<?= e(poster_url($movie)) ?>" alt="<?= e($movie['title'] . ' poster') ?>"
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
