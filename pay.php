<?php
declare(strict_types=1);

// Paying for a booking by QR Ph. The code PayMongo made for it is shown here,
// on Cinemax's own page, with a countdown to the end of the seat hold (10
// minutes): the code stops working at the same moment. pay.js asks
// api/payment-status.php every few seconds and opens the e-ticket as soon as
// PayMongo has the money.
//
// Used when QR Ph is the only way to pay (config: payment_methods ['qrph']).
// Anything else (already paid, the hold over, another way to pay) goes to
// payment-success.php, which says where the booking stands.

require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

$reference = normalize_reference(input_string($_GET, 'ref', 20));
$booking = $reference !== null ? find_user_booking((int) $user['id'], $reference) : null;
if ($booking === null) {
    abort(404, 'We could not find that booking.');
}

$secondsLeft = booking_seconds_left($booking);
$intentId = (string) ($booking['paymongo_intent_id'] ?? '');
$elsewhere = 'payment-success.php?ref=' . rawurlencode($reference);
if ($booking['status'] !== 'pending' || $secondsLeft <= 0 || ($intentId === '' && !paymongo_qrph_only())) {
    redirect($elsewhere);
}

$customer = [
    'name'   => (string) $user['name'],
    'email'  => (string) $user['email'],
    'mobile' => (string) ($user['mobile'] ?? ''),
];

// The code: kept from when it was made, or else asked from PayMongo again
// (opened in another browser, say). A booking made before QR Ph was the only
// way to pay gets one now.
$qr = $_SESSION['qrph'][$reference] ?? null;
if (!is_string($qr)) {
    try {
        if ($intentId === '') {
            $made = paymongo_create_qrph($booking, $customer, $secondsLeft);
            attach_intent((int) $booking['id'], $made['id']);
            $qr = $made['qr'];
        } else {
            $intent = paymongo_get_intent($intentId);
            if (($intent['attributes']['status'] ?? '') === 'succeeded') {
                // Paid already: the payment page records it and shows the ticket
                redirect($elsewhere);
            }
            $qr = paymongo_qr_image($intent)
                ?? paymongo_attach_qrph($intentId, (string) ($intent['attributes']['client_key'] ?? ''), $customer, $secondsLeft);
        }
        $_SESSION['qrph'] = [$reference => $qr];
    } catch (PayMongoException $e) {
        error_log('[pay] ' . $reference . ': ' . $e->getMessage());
        redirect($elsewhere);
    }
}

render_head('Scan to pay', ['assets/css/booking.css']);
render_header(['current' => 'account']);
?>
  <main class="ticket-page pay-page">

    <div class="confirm-box confirm-box-tight">
      <h1>Scan to pay</h1>
      <p>Open GCash, Maya or your bank&rsquo;s app, choose Scan or QR Ph, and scan this code.</p>
    </div>

    <!-- The code on the left and the booking on the right; on a phone the
         code comes first -->
    <div class="pay-layout">

      <!-- pay.js counts down from data-seconds-left and asks data-status-url
           whether the booking is paid -->
      <div class="ticket qr-pay" id="qr-pay"
           data-status-url="<?= e(url('api/payment-status.php?ref=' . rawurlencode($reference))) ?>"
           data-seconds-left="<?= e($secondsLeft) ?>">
        <div class="ticket-body">
          <p class="ticket-label">Amount Due</p>
          <p class="qr-pay-amount"><?= e(peso((int) $booking['total'])) ?></p>
          <img class="qr-pay-code" id="qr-code" src="<?= e($qr) ?>" width="240" height="240"
               alt="<?= e('QR Ph code to pay for booking ' . $reference) ?>">
          <p class="qr-pay-timer">Time left to pay <strong id="qr-countdown"><?= e(sprintf('%d:%02d', intdiv($secondsLeft, 60), $secondsLeft % 60)) ?></strong></p>
          <p class="qr-pay-status" id="qr-status" role="status" aria-live="polite">Waiting for your payment&hellip;</p>
          <div class="qr-pay-save">
            <a class="button button-outline button-small" href="<?= e($qr) ?>" download="<?= e('cinemax-' . $reference . '-qrph.png') ?>">Save QR code</a>
            <p>Paying on this phone? Save the code, then choose Upload QR in your app.</p>
          </div>
        </div>
      </div>

      <div class="pay-details">
        <?php render_booking_summary($booking, 'Amount Due'); ?>

        <div class="ticket-actions">
          <form class="inline-form" method="post" action="<?= e(url('payment-cancel.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="ref" value="<?= e($reference) ?>">
            <button class="button button-outline" type="submit">Cancel booking</button>
          </form>
        </div>

        <p class="status-note">Your seats are held until <?= e(format_time(date('H:i:s', time() + $secondsLeft))) ?>. After that this code stops working and the seats go back on sale.</p>
      </div>

    </div>

  </main>
<?php
render_footer(['js' => ['assets/js/pay.js']]);
