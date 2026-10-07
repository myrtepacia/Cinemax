<?php
declare(strict_types=1);

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

$notice = null;
if (input_string($_GET, 'other', 10) === 'failed') {
    $notice = ['error', 'That way to pay is not available right now. Please scan the QR code instead.'];
}

$wallet = input_string($_GET, 'wallet', 20);
$walletIntent = (string) ($booking['paymongo_wallet_intent_id'] ?? '');
if (in_array($wallet, PAYMONGO_WALLETS, true) && $walletIntent !== '') {
    $walletName = paymongo_other_methods()[$wallet] ?? 'e-wallet';
    try {
        $status = (string) (paymongo_get_intent($walletIntent)['attributes']['status'] ?? '');
        if ($status === 'succeeded') {
            redirect($elsewhere);
        }
        $notice = $status === 'processing'
            ? ['notice', $walletName . ' is confirming your payment. Your ticket opens as soon as it is done.']
            : ['error', 'Your ' . $walletName . ' payment did not go through, so nothing was charged. Please try again or scan the QR code instead.'];
    } catch (PayMongoNotFoundException $e) {
    } catch (PayMongoException $e) {
        error_log('[pay] ' . $reference . ' (' . $wallet . '): ' . $e->getMessage());
    }
}

$customer = [
    'name'   => (string) $user['name'],
    'email'  => (string) $user['email'],
    'mobile' => (string) ($user['mobile'] ?? ''),
];

$qr = remembered_qr($reference);
if ($qr === null) {
    try {
        $intent = null;
        if ($intentId !== '') {
            try {
                $intent = paymongo_get_intent($intentId);
            } catch (PayMongoNotFoundException $e) {
            }
        }
        if ($intent === null) {
            $made = paymongo_create_qrph($booking, $customer, $secondsLeft);
            attach_intent((int) $booking['id'], $made['id']);
            $qr = $made['qr'];
        } else {
            if (($intent['attributes']['status'] ?? '') === 'succeeded') {
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
remember_payment_keys();

render_head('Scan to pay', ['assets/css/booking.css']);
render_header(['current' => 'account']);
?>
  <main class="ticket-page pay-page">

    <div class="confirm-box confirm-box-tight">
      <h1>Scan to pay</h1>
      <p>Open GCash, Maya or your bank&rsquo;s app, choose Scan or QR Ph, and scan this code.</p>
    </div>

    <div class="pay-layout">

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

        <?php render_other_payments($reference, paymongo_other_methods()); ?>

<?php if ($notice !== null): ?>
        <p class="form-message form-message-<?= e($notice[0]) ?> pay-notice" role="<?= $notice[0] === 'error' ? 'alert' : 'status' ?>"><?= e($notice[1]) ?></p>
<?php endif; ?>

        <p class="status-note">Your seats are held until <?= e(format_time(date('H:i:s', time() + $secondsLeft))) ?>. After that this code stops working and the seats go back on sale.</p>
      </div>

    </div>

  </main>
<?php
render_footer(['js' => ['assets/js/pay.js']]);
