<?php
declare(strict_types=1);

// Pay by card. card.js sends the card straight to PayMongo, never to this
// server. A bank check (3-D Secure) comes back here.

// Lets this page talk to PayMongo's API
define('CINEMAX_CARD_FORM', true);

require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

$reference = normalize_reference(input_string($_GET, 'ref', 20));
$booking = $reference !== null ? find_user_booking((int) $user['id'], $reference) : null;
if ($booking === null) {
    abort(404, 'We could not find that booking.');
}

$payPath = 'pay.php?ref=' . rawurlencode($reference);
$elsewhere = 'payment-success.php?ref=' . rawurlencode($reference);

if (!array_key_exists('card', paymongo_other_methods())) {
    redirect($payPath);
}
if (paymongo_is_live() && !in_array('card', paymongo_enabled_methods(), true)) {
    redirect($payPath . '&other=failed');
}

$secondsLeft = booking_seconds_left($booking);
if ($booking['status'] !== 'pending' || $secondsLeft <= 0) {
    redirect($elsewhere);
}

// One card payment per booking, reused for every try; made again if it
// belongs to the other keys
$intentId = (string) ($booking['paymongo_card_intent_id'] ?? '');
$cardError = null;
$confirming = false;
try {
    $intent = null;
    if ($intentId !== '') {
        try {
            $intent = paymongo_get_intent($intentId);
        } catch (PayMongoNotFoundException $e) {
        }
    }
    if ($intent === null) {
        $made = paymongo_create_intent($booking, ['card']);
        attach_intent((int) $booking['id'], $made['id'], 'paymongo_card_intent_id');
        $intentId = $made['id'];
        $clientKey = $made['client_key'];
    } else {
        $status = (string) ($intent['attributes']['status'] ?? '');
        if ($status === 'succeeded') {
            // Paid: the payment page shows the ticket
            redirect($elsewhere);
        }
        $clientKey = (string) ($intent['attributes']['client_key'] ?? '');
        // Back from the bank's check (bank=1): still confirming, or failed
        $confirming = $status === 'processing';
        $fromBank = input_string($_GET, 'bank', 1) === '1';
        if ($status === 'awaiting_payment_method' || ($fromBank && $status === 'awaiting_next_action')) {
            $cardError = paymongo_card_error($intent)
                ?? ($fromBank ? 'Your bank did not confirm the payment, so your card was not charged. Please try again or use another card.' : null);
        }
    }
} catch (PayMongoException $e) {
    error_log('[card] ' . $reference . ': ' . $e->getMessage());
    redirect($payPath . '&other=failed');
}
// pay.js reloads the page if the keys change
remember_payment_keys();

$amount = peso((int) $booking['total']);

// Choose another payment: the QR code, then Maya (the card is this page)
$otherMethods = (paymongo_uses_qrph() || !empty($booking['paymongo_intent_id']) ? ['qrph' => 'QR code'] : [])
    + array_diff_key(paymongo_other_methods(), ['card' => true]);

render_head('Pay by card', ['assets/css/booking.css']);
render_header(['current' => 'account']);
?>
  <main class="ticket-page pay-page">

    <div class="confirm-box confirm-box-tight">
      <h1>Pay by card</h1>
      <p>Enter your debit or credit card. The card details go straight to PayMongo, our payment provider.</p>
    </div>

    <!-- Card on the left, booking on the right -->
    <div class="pay-layout">

      <div class="ticket card-pay" id="card-pay"
           data-status-url="<?= e(url('api/payment-status.php?ref=' . rawurlencode($reference))) ?>"
           data-seconds-left="<?= e($secondsLeft) ?>"
           data-ended-text="Time is up: this booking can no longer be paid."
           data-public-key="<?= e((string) config('paymongo.public_key')) ?>"
           data-intent="<?= e($intentId) ?>"
           data-client-key="<?= e($clientKey) ?>"
           data-return-url="<?= e(absolute_url('card.php?ref=' . rawurlencode($reference))) ?>"
           data-email="<?= e((string) $user['email']) ?>"
           data-phone="<?= e((string) ($user['mobile'] ?? '')) ?>">
        <div class="ticket-body">
          <p class="ticket-label">Amount Due</p>
          <p class="pay-amount"><?= e($amount) ?></p>

          <!-- No name on the card fields, so a card number can never reach
               this server -->
          <form class="card-form" id="card-form" novalidate>
            <noscript>
              <p class="form-message form-message-notice">Paying by card needs JavaScript. Please turn it on, or pay with the QR code.</p>
            </noscript>

            <div class="form-field">
              <label for="card-number">Card number</label>
              <input id="card-number" type="text" inputmode="numeric" autocomplete="cc-number"
                     placeholder="1234 5678 9012 3456" maxlength="23" spellcheck="false" required>
            </div>

            <div class="card-row">
              <div class="form-field">
                <label for="card-expiry">Expiry date</label>
                <input id="card-expiry" type="text" inputmode="numeric" autocomplete="cc-exp"
                       placeholder="MM / YY" maxlength="7" required>
              </div>
              <div class="form-field">
                <label for="card-cvc">CVC</label>
                <input id="card-cvc" type="text" inputmode="numeric" autocomplete="cc-csc"
                       placeholder="123" maxlength="4" required>
              </div>
            </div>

            <div class="form-field">
              <label for="card-name">Name on card</label>
              <input id="card-name" type="text" autocomplete="cc-name" maxlength="100"
                     value="<?= e((string) $user['name']) ?>" required>
            </div>

            <p class="form-message form-message-error" id="card-error" role="alert"<?= $cardError === null ? ' hidden' : '' ?>><?= e($cardError ?? '') ?></p>

            <button class="button button-red button-wide" id="card-submit" type="submit"<?= $confirming ? ' disabled' : '' ?>>Pay <?= e($amount) ?></button>
          </form>

          <p class="pay-timer">Time left to pay <strong id="pay-countdown"><?= e(sprintf('%d:%02d', intdiv($secondsLeft, 60), $secondsLeft % 60)) ?></strong></p>
          <p class="pay-status" id="pay-status" role="status" aria-live="polite"><?= $confirming ? 'Your bank is confirming the payment&hellip;' : 'Your card is charged only when you press Pay.' ?></p>
        </div>
      </div>

      <div class="pay-details">
        <?php render_booking_summary($booking, 'Amount Due'); ?>

        <?php render_other_payments($reference, $otherMethods); ?>

        <p class="status-note">Your seats are held until <?= e(format_time(date('H:i:s', time() + $secondsLeft))) ?>. After that the booking can no longer be paid and the seats go back on sale.</p>
      </div>

    </div>

  </main>
<?php
render_footer(['js' => ['assets/js/pay.js', 'assets/js/card.js']]);
