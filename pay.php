<?php
declare(strict_types=1);

// Paying for a booking by QR Ph. The code PayMongo made for it is shown here,
// on Cinemax's own page, with a countdown to the end of the seat hold (10
// minutes): the code stops working at the same moment. pay.js asks
// api/payment-status.php every few seconds and opens the e-ticket as soon as
// PayMongo has the money.
//
// Used when QR Ph is one of the ways to pay (config: payment_methods). The
// others listed there (GCash, card) are offered under the booking ("Choose
// another payment", pay-other.php), and GCash sends the customer back here
// with wallet=gcash. Anything else (already paid, the hold over, QR Ph not
// used) goes to payment-success.php, which says where the booking stands.

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
if ($booking['status'] !== 'pending' || $secondsLeft <= 0 || ($intentId === '' && !paymongo_uses_qrph())) {
    redirect($elsewhere);
}

// A message under Cancel booking: ['error' or 'notice', text], or null
$notice = null;
if (input_string($_GET, 'other', 10) === 'failed') {
    // Back from pay-other.php because that way to pay could not be opened
    $notice = ['error', 'That way to pay is not available right now. Please scan the QR code instead.'];
}

// Back from GCash (or another e-wallet): paid, still being confirmed, or not
// done (failed, or the customer went back)
$wallet = input_string($_GET, 'wallet', 20);
$walletIntent = (string) ($booking['paymongo_wallet_intent_id'] ?? '');
if (in_array($wallet, PAYMONGO_WALLETS, true) && $walletIntent !== '') {
    $walletName = paymongo_other_methods()[$wallet] ?? 'e-wallet';
    try {
        $status = (string) (paymongo_get_intent($walletIntent)['attributes']['status'] ?? '');
        if ($status === 'succeeded') {
            // The payment page records it and shows the ticket
            redirect($elsewhere);
        }
        $notice = $status === 'processing'
            ? ['notice', $walletName . ' is confirming your payment. Your ticket opens as soon as it is done.']
            : ['error', 'Your ' . $walletName . ' payment did not go through, so nothing was charged. Please try again or scan the QR code instead.'];
    } catch (PayMongoNotFoundException $e) {
        // Made with the other keys (switched between test and live since)
    } catch (PayMongoException $e) {
        // pay.js keeps asking whether it was paid
        error_log('[pay] ' . $reference . ' (' . $wallet . '): ' . $e->getMessage());
    }
}

$customer = [
    'name'   => (string) $user['name'],
    'email'  => (string) $user['email'],
    'mobile' => (string) ($user['mobile'] ?? ''),
];

// The code: kept from when it was made, or else asked from PayMongo again
// (opened in another browser, say). A booking made before QR Ph was a way to
// pay gets one now, and so does one whose code was made with the other keys
// (switched between test and live since): that code cannot be paid now.
$qr = remembered_qr($reference);
if ($qr === null) {
    try {
        $intent = null;
        if ($intentId !== '') {
            try {
                $intent = paymongo_get_intent($intentId);
            } catch (PayMongoNotFoundException $e) {
                // Made with the other keys
            }
        }
        if ($intent === null) {
            $made = paymongo_create_qrph($booking, $customer, $secondsLeft);
            attach_intent((int) $booking['id'], $made['id']);
            $qr = $made['qr'];
        } else {
            if (($intent['attributes']['status'] ?? '') === 'succeeded') {
                // Paid already: the payment page records it and shows the ticket
                redirect($elsewhere);
            }
            $qr = paymongo_qr_image($intent)
                ?? paymongo_attach_qrph($intentId, (string) ($intent['attributes']['client_key'] ?? ''), $customer, $secondsLeft);
        }
        remember_qr($reference, $qr);
    } catch (PayMongoException $e) {
        error_log('[pay] ' . $reference . ': ' . $e->getMessage());
        redirect($elsewhere);
    }
}
// pay.js reloads the page if the keys are switched while it is open
remember_payment_keys();

// GCash, card: the other ways listed in the config (none hides the choice)
$otherMethods = paymongo_other_methods();
$methodLogos = ['gcash' => 'assets/img/pay-gcash.svg', 'card' => 'assets/img/pay-card.svg'];

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
           data-seconds-left="<?= e($secondsLeft) ?>"
           data-ended-text="Time is up: this code no longer works.">
        <div class="ticket-body">
          <p class="ticket-label">Amount Due</p>
          <p class="pay-amount"><?= e(peso((int) $booking['total'])) ?></p>
          <img class="qr-pay-code" id="qr-code" src="<?= e($qr) ?>" width="240" height="240"
               alt="<?= e('QR Ph code to pay for booking ' . $reference) ?>">
          <p class="pay-timer">Time left to pay <strong id="pay-countdown"><?= e(sprintf('%d:%02d', intdiv($secondsLeft, 60), $secondsLeft % 60)) ?></strong></p>
          <p class="pay-status" id="pay-status" role="status" aria-live="polite">Waiting for your payment&hellip;</p>
          <div class="qr-pay-save">
            <a class="button button-outline button-small" href="<?= e($qr) ?>" download="<?= e('cinemax-' . $reference . '-qrph.png') ?>">Save QR code</a>
            <p>Paying on this phone? Save the code, then choose Upload QR in your app.</p>
          </div>
        </div>
      </div>

      <div class="pay-details">
        <?php render_booking_summary($booking, 'Amount Due'); ?>

<?php if ($otherMethods !== []): ?>
        <!-- Opens to one row per way to pay; tapping one goes to PayMongo's page -->
        <details class="pay-other">
          <summary>Choose another payment</summary>
          <form method="post" action="<?= e(url('pay-other.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="ref" value="<?= e($reference) ?>">
<?php foreach ($otherMethods as $method => $name): ?>
            <button class="pay-other-option" type="submit" name="method" value="<?= e($method) ?>">
              <span class="pay-other-name">Pay with <?= e($name) ?></span>
<?php if (isset($methodLogos[$method])): ?>
              <img class="pay-other-logo" src="<?= e(asset($methodLogos[$method])) ?>" alt="">
<?php endif; ?>
            </button>
<?php endforeach; ?>
          </form>
        </details>
<?php endif; ?>

        <div class="ticket-actions">
          <form class="inline-form" method="post" action="<?= e(url('payment-cancel.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="ref" value="<?= e($reference) ?>">
            <button class="button button-outline" type="submit">Cancel booking</button>
          </form>
        </div>

<?php if ($notice !== null): ?>
        <p class="form-message form-message-<?= e($notice[0]) ?> pay-notice" role="<?= $notice[0] === 'error' ? 'alert' : 'status' ?>"><?= e($notice[1]) ?></p>
<?php endif; ?>

        <p class="status-note">Your seats are held until <?= e(format_time(date('H:i:s', time() + $secondsLeft))) ?>. After that this code stops working and the seats go back on sale.</p>
      </div>

    </div>

  </main>
<?php
render_footer(['js' => ['assets/js/pay.js']]);
