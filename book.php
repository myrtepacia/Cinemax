<?php
declare(strict_types=1);

// Booking one film: date, showtime, seats and snacks. checkout.php checks
// everything again and works out the price.

require __DIR__ . '/includes/bootstrap.php';

$movie = find_movie_by_slug(input_string($_GET, 'movie', 120));
if ($movie === null) {
    abort(404, 'We could not find that movie. It may have been taken off the listings.');
}

$window = movie_booking_window($movie);
$user = current_user();
$slug = (string) $movie['slug'];
$showtimes = movie_showtimes((int) $movie['id']);
// No showtimes: nothing to book yet
$bookable = $window !== null && $showtimes !== [];

/** The poster with its rating and running time. */
function book_poster(array $movie): void
{
    ?>
<div class="booking-poster-wrap">
          <img class="booking-poster skeleton" src="<?= e(poster_url($movie)) ?>" alt="<?= e($movie['title'] . ' poster') ?>">
          <span class="tag-rated"><?= e($movie['rating']) ?></span>
          <span class="tag-time"><?= e(duration_tag((int) $movie['duration_minutes'])) ?></span>
        </div>
<?php
}

// Bounced back from checkout: keep the date and showtime if still bookable
$initialDate = '';
$initialTime = '';
if ($bookable) {
    $askedDate = input_date($_GET, 'date');
    if ($askedDate !== null && $askedDate >= $window['from'] && $askedDate <= $window['to']) {
        $initialDate = $askedDate;
    }
    $askedTime = input_string($_GET, 'time', 8);
    if (in_array($askedTime, $showtimes, true)) {
        $initialTime = $askedTime;
    }
}

render_head((string) $movie['title'], ['assets/css/booking.css']);
render_header();
?>
  <main class="booking">

    <a class="back-link" href="<?= e(url('index.php')) ?>">&larr; Back to movies</a>

<?php if (!$bookable): ?>
<?php
    // Not bookable: coming soon, ended, or no showtimes
    $opensLater = movie_listing_state($movie) === 'soon';
?>
    <div class="booking-layout">

      <div class="booking-left">
        <?php book_poster($movie); ?>

        <div class="booking-block">
          <h1 class="film-title"><?= e($movie['title']) ?></h1>
          <p class="film-details"><?= e($movie['genre']) ?></p>
        </div>
      </div>

      <div class="seat-area">
<?php if ($window !== null): ?>
        <p class="pick-first">
          <strong>No showtimes yet</strong>
          The showtimes for this movie have not been set. Please check back soon.
        </p>
<?php elseif ($opensLater): ?>
        <p class="pick-first">
          <strong>Opens <?= e(format_date_short((string) $movie['opens_on'])) ?></strong>
          Booking opens on the first day of showing. Come back then to pick your seats and snacks.
        </p>
<?php else: ?>
        <p class="pick-first">
          <strong>No longer showing</strong>
          This movie has finished its run, so it cannot be booked any more.
        </p>
<?php endif; ?>
      </div>

    </div>
<?php else: ?>
<?php
    $ticketPrice = (int) $movie['price'];
    $openEnded = empty($movie['ends_on']);
    $untilLine = $openEnded
        ? 'Booking open up to ' . format_date_short($window['to'])
        : 'Showing until ' . format_date_short($window['to']);
    $afterNote = $openEnded
        ? 'Bookings for this date are not open yet'
        : 'This movie has stopped showing by then';
    $snackGroups = snacks_by_category();
?>
    <noscript>
      <p class="form-message form-message-notice">Booking needs JavaScript. Please turn it on and reload the page.</p>
    </noscript>

    <form method="post" action="<?= e(url('checkout.php')) ?>" id="booking-form"
          data-seats-url="<?= e(url('api/seats.php')) ?>"
          data-movie-id="<?= e((int) $movie['id']) ?>"
          data-from="<?= e($window['from']) ?>"
          data-to="<?= e($window['to']) ?>"
          data-today="<?= e(today()) ?>"
          data-utc-offset="<?= e(date('Z')) ?>"
          data-after-note="<?= e($afterNote) ?>"
          data-ticket-price="<?= e($ticketPrice) ?>"
          data-max-seats="<?= e(MAX_SEATS_PER_BOOKING) ?>"
          data-max-per-snack="<?= e(MAX_PER_SNACK) ?>"
          data-initial-date="<?= e($initialDate) ?>"
          data-initial-time="<?= e($initialTime) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="movie_id" value="<?= e((int) $movie['id']) ?>">

      <div class="booking-layout">

        <div class="booking-left">

          <?php book_poster($movie); ?>

          <div class="booking-block">
            <h1 class="film-title"><?= e($movie['title']) ?></h1>
            <p class="film-details"><?= e($movie['genre']) ?></p>
          </div>

          <div class="booking-block">
            <span class="field-label" id="date-label">Date</span>

            <div class="date-field" id="date-field">

              <button class="dropdown date-button" type="button" id="date-button"
                      aria-haspopup="true" aria-expanded="false" aria-labelledby="date-label date-button">
                <span id="date-text">Choose a date</span>
                <span class="date-arrow">&#9662;</span>
              </button>

              <div class="calendar" id="calendar" role="group" aria-labelledby="date-label" hidden>

                <div class="calendar-head">
                  <button class="calendar-nav" type="button" id="calendar-back" aria-label="Previous month">&#8249;</button>
                  <p class="calendar-month" id="calendar-month">Month</p>
                  <button class="calendar-nav" type="button" id="calendar-next" aria-label="Next month">&#8250;</button>
                </div>

                <div class="calendar-grid calendar-day-names">
                  <span>Sun</span>
                  <span>Mon</span>
                  <span>Tue</span>
                  <span>Wed</span>
                  <span>Thu</span>
                  <span>Fri</span>
                  <span>Sat</span>
                </div>

                <div class="calendar-grid" id="calendar-dates"></div>

                <p class="calendar-hint" id="calendar-until"><?= e($untilLine) ?></p>

              </div>

            </div>

            <input type="hidden" name="date" id="date" value="">
          </div>

          <div class="booking-block">
            <label class="field-label" for="showtime">Showtime</label>
            <!-- booking.js unlocks it once a date is picked -->
            <select class="dropdown" id="showtime" name="time" disabled>
              <option value="" selected disabled hidden>Choose a date first</option>
<?php foreach ($showtimes as $time): ?>
              <option value="<?= e($time) ?>" data-label="<?= e(format_time($time)) ?>"><?= e(format_time($time)) ?></option>
<?php endforeach; ?>
            </select>
          </div>

        </div>

        <div class="seat-area">

          <p class="pick-first" id="pick-first">
            Choose a date and a showtime to see the seats and the snacks
          </p>

          <!-- Hidden until a date and showtime are picked, but keeps its
               space -->
          <div id="seats-and-snacks" class="seats-waiting">

            <h3>Choose Your Seats</h3>

            <div class="screen-curve"></div>
            <p class="screen-word">SCREEN</p>

            <!-- Two aisles: seats 1-3 | 4-7 | 8-10 -->
            <div class="seat-map" id="seat-map">
<?php foreach (SEAT_ROWS as $row): ?>
              <div class="seat-row">
                <span class="row-letter"><?= e($row) ?></span>
<?php for ($number = 1; $number <= SEATS_PER_ROW; $number++): ?>
                <label class="seat<?= in_array($number, [4, 8], true) ? ' seat-after-aisle' : '' ?>"><input type="checkbox" name="seats[]" value="<?= e($row . $number) ?>" aria-label="<?= e('Seat ' . $row . $number) ?>"><span class="seat-box"><?= e($number) ?></span></label>
<?php endfor; ?>
              </div>
<?php endforeach; ?>
            </div>

            <div class="seat-key">
              <div><span class="key-colour key-free"></span> Available</div>
              <div><span class="key-colour key-picked"></span> Selected</div>
              <div><span class="key-colour key-sold"></span> Unavailable</div>
            </div>

            <div class="snacks">

              <h3>Pre-order Your Snacks</h3>
              <p class="snacks-note">
                Add food and drinks now and skip the concession line. Show your QR code at the
                counter and staff hand them over. Snacks must be ordered with your ticket, not after.
              </p>

<?php foreach ($snackGroups as $category => $snacks): ?>
              <p class="snack-heading"><?= e($category) ?></p>
              <div class="snack-list">
<?php foreach ($snacks as $snack): ?>

                <div class="snack" data-price="<?= e((int) $snack['price']) ?>">
                  <div class="snack-box">
                    <span>
                      <span class="snack-name"><?= e($snack['name']) ?></span>
<?php if ((string) $snack['detail'] !== ''): ?>
                      <span class="snack-detail"><?= e($snack['detail']) ?></span>
<?php endif; ?>
                    </span>
                    <span class="snack-side">
                      <span class="snack-price"><?= e(peso((int) $snack['price'])) ?></span>
                      <span class="snack-count">
                        <button class="snack-step snack-less" type="button" data-step="-1" aria-label="<?= e('One less ' . $snack['name']) ?>" disabled>&minus;</button>
                        <span class="snack-quantity">0</span>
                        <button class="snack-step snack-more" type="button" data-step="1" aria-label="<?= e('One more ' . $snack['name']) ?>">+</button>
                      </span>
                    </span>
                  </div>
                  <input type="hidden" class="snack-input" name="snacks[<?= e((int) $snack['id']) ?>]" value="0">
                </div>
<?php endforeach; ?>

              </div>
<?php endforeach; ?>

            </div>

            <div class="order-summary">

              <div class="summary-line">
                <span class="summary-name">Selected seats</span>
                <span id="summary-seats">Tap the seats above</span>
              </div>

              <div class="summary-line">
                <span class="summary-name">Snacks</span>
                <span id="summary-snacks">Use + to add any items above</span>
              </div>

              <div class="summary-line summary-total">
                <span class="summary-name" id="summary-tickets"><?= e('0 tickets × ' . peso($ticketPrice)) ?></span>
                <span class="summary-amount" id="summary-amount"><?= e(peso(0)) ?></span>
              </div>

              <p class="form-message form-message-error" id="booking-error" role="alert" hidden></p>

<?php if ($user === null): ?>
              <a class="button button-red" href="<?= e(url('signin.php?return=' . rawurlencode($slug))) ?>">Sign in to book</a>
<?php elseif (is_staff($user)): ?>
              <button class="button button-grey" type="button" disabled>Confirm Booking</button>
              <p class="summary-note">Staff accounts cannot book tickets. Sign in with a customer account to book.</p>
<?php else: ?>
              <button class="button button-red" type="submit" id="confirm-button">Confirm Booking</button>
              <p class="summary-note">Your seats are held for <?= e(max(5, min(60, (int) config('booking_hold_minutes', 10)))) ?> minutes while you pay. If payment is not finished by then, they go back on sale.</p>
<?php endif; ?>

            </div>

          </div>

        </div>

      </div>
    </form>
<?php endif; ?>

  </main>
<?php
render_footer($bookable ? ['js' => ['assets/js/dates.js', 'assets/js/booking.js']] : []);
