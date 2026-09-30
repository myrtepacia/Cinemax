<?php
declare(strict_types=1);

// The Ticket Scanner, for checking tickets at the door. Two ways in:
//
//   Find a ticket  type the reference number from the e-ticket to see the
//                  booking and whether it was used. Admins can refund an
//                  unused ticket from here.
//   The camera     reads the QR code on a ticket; scanner.js asks
//                  api/scan.php whether it is good, and staff confirm the
//                  booking to let the customer in.
//
// Searching is a GET and only ever reads. A refund is a POST with a CSRF
// token, for admins only, and everything about it (the amount, whether it is
// allowed) is decided from the database by refund_booking(), never from the
// form. The refund's confirm form is printed open with the Refund button
// hidden, so a browser without scripts can still refund; tickets.js swaps
// them round.

require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('admin', 'scanner');
$canRefund = $user['role'] === 'admin';

/**
 * What staff typed, tidied the way a reference is written, so the "no
 * booking matches" message shows it the way it is printed on a ticket:
 * 'cmx 8f41k2' becomes 'CMX-8F41K2'.
 */
function scanner_tidy_code(string $text): string
{
    $code = mb_strtoupper((string) preg_replace('/[\s-]+/u', '', $text));
    if (strpos($code, 'CMX') === 0) {
        $code = substr($code, 3);
    }
    return 'CMX-' . mb_substr($code, 0, 20);
}

/**
 * True while a booking's seats are still held for a customer who is paying.
 * A 'pending' booking whose hold has run out is only marked 'expired' the
 * next time the holds are tidied, so the time is checked here as well.
 */
function scanner_awaiting_payment(array $booking): bool
{
    return $booking['status'] === 'pending'
        && new DateTimeImmutable((string) $booking['expires_at']) > new DateTimeImmutable('now');
}

/**
 * The badge on a booking: [label, css class].
 */
function scanner_status(array $booking): array
{
    switch ($booking['status']) {
        case 'paid':
            return $booking['scanned_at'] === null
                ? ['Not scanned yet', 'status-unused']
                : ['Already scanned', 'status-used'];
        case 'refunded':
            return ['Refunded', 'status-refunded'];
        default:
            // Expired, cancelled, or a hold that has run out: never paid
            return scanner_awaiting_payment($booking)
                ? ['Awaiting payment', 'status-refunded']
                : ['Not paid', 'status-refunded'];
    }
}

/**
 * A day as the refund note says it: 'Friday, September 25, 2026'.
 */
function scanner_long_date(string $datetime): string
{
    return (new DateTimeImmutable($datetime))->format('l, F j, Y');
}

// The refund, for admins only. The booking id only says which booking;
// refund_booking() locks it and checks it is paid and unused before any
// money moves.
if (is_post()) {
    verify_csrf();
    if (!$canRefund) {
        abort(403, 'Only an admin can refund tickets.');
    }

    $bookingId = input_int($_POST, 'booking_id', 1);
    $target = $bookingId !== null ? find_booking_by_id($bookingId) : null;
    if ($target === null) {
        redirect('admin/scanner.php');
    }

    try {
        refund_booking((int) $target['id'], (int) $user['id']);
    } catch (BookingException $e) {
        // Cannot be refunded (already refunded or used, say): nothing changes,
        // and the booking shown next says what it is
    }
    redirect('admin/scanner.php?ref=' . rawurlencode((string) $target['reference']));
}

// The search
$searched = isset($_GET['ref']);
$typed = input_string($_GET, 'ref', 40);
$shownCode = '';
$searchError = '';
$booking = null;

if ($searched) {
    if ($typed === '') {
        $searchError = 'Type a reference number first.';
    } else {
        $reference = normalize_reference($typed);
        $shownCode = $reference ?? scanner_tidy_code($typed);
        $booking = $reference !== null ? find_booking($reference) : null;
        if ($booking === null) {
            $searchError = 'No booking matches ' . $shownCode . '. Check the number on the e-ticket.';
        }
    }
}

if ($booking !== null) {
    [$statusLabel, $statusClass] = scanner_status($booking);
    $total = (int) $booking['total'];
    $isPaid = $booking['status'] === 'paid';
    $isScanned = $booking['scanned_at'] !== null;
    // Snacks confirmed at the snack counter are being made or were handed
    // over, so the booking counts as used, the same as a scanned ticket
    $snacksClaimed = in_array($booking['snack_status'], ['preparing', 'ready', 'sold'], true);
    $wasPaid = in_array($booking['status'], ['paid', 'refunded'], true);
    $seatList = (string) $booking['seat_list'];
    $oneSeat = strpos($seatList, ',') === false;
}

render_head('Ticket Scanner');
render_header(['staff' => true, 'current' => 'scanner']);
?>
  <div class="admin-layout">

    <?php render_staff_sidebar('scanner'); ?>

    <div class="admin-head">
      <h1>Ticket Scanner</h1>
    </div>

    <main class="admin-page">

      <div class="panel camera-panel" id="scanner" data-api-url="<?= e(url('api/scan.php')) ?>">

        <!-- Find a ticket by its reference number -->
        <section class="ticket-lookup">

          <h2>Find a ticket</h2>
          <p class="panel-note">The reference number is on the customer&rsquo;s e-ticket, under the movie title.</p>

          <form class="search-row" id="ticket-search" method="get" action="<?= e(url('admin/scanner.php')) ?>" novalidate>
            <div class="form-field search-field">
              <label for="ticket-code">Reference No.</label>
              <input id="ticket-code" name="ref" type="text" placeholder="CMX-8F41K2" maxlength="40"
                     value="<?= e($shownCode) ?>" autocomplete="off" spellcheck="false">
            </div>
            <button class="button button-red" type="submit">Search</button>
          </form>

<?php if ($searchError !== ''): ?>
          <p class="form-message form-message-error" id="search-message" role="alert"><?= e($searchError) ?></p>
<?php endif; ?>

<?php if ($booking !== null): ?>
          <div class="ticket-result" id="ticket-result">

            <div class="ticket-result-head">
              <div>
                <p class="ticket-result-code"><?= e($booking['reference']) ?></p>
                <h3 class="ticket-result-movie"><?= e($booking['title']) ?></h3>
              </div>
              <span class="status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
            </div>

            <dl class="ticket-facts">
              <div>
                <dt>Name</dt>
                <dd><?= e($booking['customer_name']) ?></dd>
              </div>
              <div>
                <dt>Show</dt>
                <dd><?= e(format_showing((string) $booking['show_date'], (string) $booking['show_time'], (int) $booking['cinema'])) ?></dd>
              </div>
              <div>
                <dt>Seats</dt>
                <dd><?= e($seatList) ?></dd>
              </div>
              <div>
                <dt>Total paid</dt>
                <dd><?= e($wasPaid ? peso($total) : 'Not paid (' . peso($total) . ')') ?></dd>
              </div>
              <div>
                <dt>Scanned</dt>
                <dd><?= e($isScanned ? format_datetime((string) $booking['scanned_at']) : 'Not yet') ?></dd>
              </div>
            </dl>

<?php if ($isPaid && !$isScanned && !$snacksClaimed && $canRefund): ?>
            <div class="refund-row" id="refund-row">
              <p class="refund-note">This ticket has not been used, so it can be refunded.</p>
              <button class="button button-red hidden" type="button" id="refund-button">Refund <?= e(peso($total)) ?></button>
            </div>

            <form class="refund-row refund-confirm" id="refund-confirm" method="post" action="<?= e(url('admin/scanner.php')) ?>">
              <?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= e((int) $booking['id']) ?>">
              <p class="refund-note">Refund <?= e(peso($total)) ?> to <?= e($booking['customer_name']) ?>? This cancels the booking and frees <?= e($oneSeat ? 'seat' : 'seats') ?> <?= e($seatList) ?>.</p>
              <div class="refund-buttons">
                <button class="button button-outline hidden" type="button" id="refund-cancel">Keep ticket</button>
                <button class="button button-red" type="submit" id="refund-yes">Confirm refund</button>
              </div>
            </form>
<?php elseif ($isPaid && !$isScanned && !$snacksClaimed): ?>
            <div class="refund-row" id="refund-row">
              <p class="refund-note">This ticket has not been used. Only an admin can refund it.</p>
            </div>
<?php elseif ($isPaid): ?>
            <div class="refund-row" id="refund-row">
              <p class="refund-note"><?= e($isScanned
                  ? 'This ticket was already used at the door, so it cannot be refunded.'
                  : 'The snacks on this booking were already confirmed at the snack counter, so it cannot be refunded.') ?></p>
<?php if ($canRefund): ?>
              <button class="button button-grey" type="button" disabled>Refund <?= e(peso($total)) ?></button>
<?php endif; ?>
            </div>
<?php elseif ($booking['status'] === 'refunded'): ?>
            <div class="refund-row" id="refund-row">
              <p class="refund-note"><?= e(peso($total)) ?> was refunded<?= $booking['refunded_at'] !== null ? ' on ' . e(scanner_long_date((string) $booking['refunded_at'])) : '' ?>. The seats are open for booking again.</p>
            </div>
<?php elseif (scanner_awaiting_payment($booking)): ?>
            <div class="refund-row" id="refund-row">
              <p class="refund-note">This booking is still waiting for payment, so there is nothing to refund.</p>
            </div>
<?php else: ?>
            <div class="refund-row" id="refund-row">
              <p class="refund-note">This booking was never paid, so there is nothing to refund.</p>
            </div>
<?php endif; ?>

          </div>
<?php endif; ?>

        </section>

        <!-- Scan the QR code -->
        <p class="panel-note">Open the camera to scan the QR code on the ticket</p>

        <div class="camera-video" id="camera-video"></div>

        <p class="scan-result scan-result-good hidden" id="scan-result" role="status" aria-live="polite">
          <span id="scan-result-title"></span>
          <span class="scan-result-who" id="scan-result-who"></span>
        </p>

        <div class="scan-actions hidden" id="scan-actions">
          <button class="button button-red" id="confirm-booking" type="button">Confirm booking</button>
          <button class="button button-outline" id="cancel-booking" type="button">Cancel</button>
        </div>

        <p class="camera-message" id="camera-message" aria-live="polite"></p>
        <noscript><p class="camera-message">The scanner needs JavaScript. Turn it on in this browser, then reload the page.</p></noscript>

        <button class="button button-red" id="start-camera" type="button">Open camera</button>
        <button class="button button-outline hidden" id="close-camera" type="button">Close camera</button>

      </div>

    </main>

  </div>

<?php render_footer(['js' => ['assets/vendor/jsQR.js', 'assets/js/scanner.js', 'assets/js/tickets.js']]);
