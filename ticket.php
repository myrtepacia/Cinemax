<?php
declare(strict_types=1);

// One booking's e-ticket, with the QR code the door scanner reads.
//
// A customer sees only their own bookings; anyone else's reference gives the
// same "not found" page as one that does not exist, so references cannot be
// guessed. Admins may open any booking.

require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

$ref = normalize_reference(input_string($_GET, 'ref', 20));
if ($ref === null) {
    abort(404);
}

$booking = null;
if ($user['role'] === 'customer') {
    $booking = find_user_booking((int) $user['id'], $ref);
} elseif ($user['role'] === 'admin') {
    $booking = find_booking($ref);
}
if ($booking === null) {
    abort(404);
}

$status = (string) $booking['status'];
$ownBooking = (int) $booking['user_id'] === (int) $user['id'];

// Still waiting for payment: the QR Ph page (or the payment page) asks
// PayMongo again and shows the ticket as soon as the money is in.
if ($status === 'pending' && $ownBooking) {
    $paying = paymongo_qrph_only() || !empty($booking['paymongo_intent_id']) ? 'pay.php' : 'payment-success.php';
    redirect($paying . '?ref=' . rawurlencode($ref));
}

// "Booking Confirmed!" is shown once, straight after paying; opened again
// later, the same ticket is simply "Your E-Ticket".
$justPaid = false;
if (($_SESSION['just_paid'] ?? null) === $ref) {
    $justPaid = true;
    unset($_SESSION['just_paid']);
}

// Book Another goes back to the same film while it can still be booked
$movie = find_movie((int) $booking['movie_id']);
$bookAnother = ($movie !== null && movie_booking_window($movie) !== null)
    ? 'book.php?movie=' . rawurlencode((string) $movie['slug'])
    : 'index.php';

$hasTicket = in_array($status, ['paid', 'refunded'], true);

if ($hasTicket) {
    $snackLines = booking_snacks((int) $booking['id']);
    $snacks = snack_summary($snackLines);
    // The order's number for the claim monitor, on a paid ticket only (a
    // refunded one has given its number back)
    $snackNumber = $status === 'paid' && $booking['snack_number'] !== null
        ? '#' . snack_number_label((int) $booking['snack_number'])
        : null;
    $snackStatus = [
        'ordered'   => ['Show this QR code at the snack counter', 'snack-status-ordered'],
        'preparing' => ['Preparing', 'snack-status-preparing'],
        'ready'     => ['Ready at the counter', 'snack-status-ready'],
        'sold'      => ['Picked up', 'snack-status-sold'],
    ][(string) ($booking['snack_status'] ?? '')] ?? null;
}

// The reason a booking has no ticket, for the short page shown instead
$noTicketMessages = [
    'pending'   => 'Booking %s for %s is still waiting for payment. The ticket appears once PayMongo confirms it.',
    'expired'   => 'Booking %s for %s was not paid in time, so no ticket was issued and its seats went back on sale.',
    'cancelled' => 'Booking %s for %s was cancelled before payment, so no ticket was issued and its seats went back on sale.',
];

render_head($hasTicket ? 'Your E-Ticket' : 'Booking ' . $ref, ['assets/css/public.css']);
render_header();
?>
  <main class="ticket-page">

<?php if (!$hasTicket): ?>
    <div class="confirm-box">
      <h1>No Ticket for This Booking</h1>
      <p><?= e(sprintf($noTicketMessages[$status] ?? 'Booking %s for %s has no ticket.', $ref, $booking['title'])) ?></p>
    </div>

    <div class="ticket-actions">
      <a class="button button-outline" href="<?= e(url('index.php')) ?>">Back to Movies</a>
      <a class="button button-red" href="<?= e(url($bookAnother)) ?>">Book Again</a>
    </div>
<?php else: ?>
    <div id="ticket-wrap">

<?php if ($status === 'refunded'): ?>
      <div class="confirm-box">
        <h1>Booking Refunded</h1>
        <p>This ticket is no longer valid.</p>
      </div>
<?php elseif ($justPaid): ?>
      <div class="confirm-box">
        <div class="confirm-tick" aria-hidden="true">&#10003;</div>
        <h1>Booking Confirmed!</h1>
        <p>Your e-ticket is ready below.</p>
      </div>
<?php else: ?>
      <div class="confirm-box">
        <h1>Your E-Ticket</h1>
        <p><?= $booking['scanned_at'] !== null ? 'This ticket has already been used.' : 'Show this QR code at the door.' ?></p>
      </div>
<?php endif; ?>

      <div class="ticket">

        <div class="ticket-header">
          <!-- The brand, with the cinema the film plays in on the right -->
          <div class="ticket-brand-row">
            <p class="ticket-brand">CINEMAX</p>
            <p class="ticket-cinema" id="cinema"><?= e(cinema_label((int) $booking['cinema'])) ?></p>
          </div>
          <h2 id="movie-title"><?= e($booking['title']) ?></h2>
          <p class="ticket-when" id="showtime"><?= e(format_showing((string) $booking['show_date'], (string) $booking['show_time'])) ?></p>
        </div>

        <div class="ticket-tear"></div>

        <div class="ticket-body">

          <div class="ticket-pair">
            <div>
              <p class="ticket-label">Reference No.</p>
              <p class="ticket-value ticket-reference" id="reference"><?= e($booking['reference']) ?></p>
            </div>
            <div>
              <p class="ticket-label">Seat(s)</p>
              <p class="ticket-value" id="seats"><?= e($booking['seat_list']) ?></p>
            </div>
          </div>

          <div class="ticket-pair">
            <div>
              <p class="ticket-label">Name</p>
              <p class="ticket-value ticket-value-small" id="customer-name"><?= e($booking['customer_name']) ?></p>
            </div>
            <div>
              <p class="ticket-label"><?= $status === 'refunded' ? 'Refunded' : 'Total Paid' ?></p>
              <p class="ticket-value" id="total"><?= e(peso((int) $booking['total'])) ?></p>
            </div>
          </div>

<?php if ($snacks !== ''): ?>
          <div class="ticket-pair" id="snack-pair">
            <div>
              <p class="ticket-label">Pickup Number</p>
<?php if ($snackNumber !== null): ?>
              <p class="ticket-value" id="pickup-number"><?= e($snackNumber) ?></p>
<?php else: ?>
              <!-- Refunded (its number was given back), or ordered before numbers were given out -->
              <p class="ticket-value ticket-value-small" id="pickup-number"><?= $status === 'refunded' ? 'Refunded' : 'None' ?></p>
<?php endif; ?>
            </div>
            <div>
              <p class="ticket-label">Snacks</p>
              <p class="ticket-value ticket-value-small" id="snacks"><?= e($snacks) ?></p>
            </div>
          </div>
<?php if ($status === 'paid' && $snackStatus !== null): ?>
          <!-- The snack order's progress, on its own centred line -->
          <p class="snack-status <?= e($snackStatus[1]) ?>" id="snack-status"><?= e($snackStatus[0]) ?></p>
<?php endif; ?>
<?php endif; ?>

<?php if ($status === 'paid'): ?>
          <div class="ticket-qr">
            <img id="qr" class="ticket-qr-code" width="180" height="180"
                 src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
                 alt="QR code for booking <?= e($booking['reference']) ?>"
                 data-qr="<?= e(ticket_qr_payload($booking)) ?>">
            <noscript><p class="ticket-qr-noscript">Turn on JavaScript to show the QR code.</p></noscript>
          </div>
<?php endif; ?>

        </div>
      </div>

<?php if ($status === 'refunded'): ?>
      <p class="form-message form-message-notice ticket-note" id="refund-note">
        This booking was refunded<?= !empty($booking['refunded_at']) ? ' on ' . e(format_datetime((string) $booking['refunded_at'])) : '' ?>. Its QR code can no longer be used at the door.
      </p>
<?php elseif ($booking['scanned_at'] !== null): ?>
      <p class="form-message form-message-notice ticket-note" id="used-note">
        This ticket was scanned at the door on <?= e(format_datetime((string) $booking['scanned_at'])) ?>.
      </p>
<?php endif; ?>

      <div class="ticket-actions">
        <a class="button button-outline" href="<?= e(url('index.php')) ?>">Back to Movies</a>
        <a class="button button-red" id="book-another" href="<?= e(url($bookAnother)) ?>">Book Another</a>
      </div>

    </div>
<?php endif; ?>

  </main>

<?php render_footer($status === 'paid' ? ['js' => ['assets/vendor/qrcode.js', 'assets/js/ticket.js']] : []);
