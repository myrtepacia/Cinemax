<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$holdMinutes = max(5, min(60, (int) config('booking_hold_minutes', 10)));
$email = site_contact_email();

render_head('Refund Policy', [], [
    'description' => 'When you can get a refund for a CSFlix booking, how refunds are paid, and how to ask for one.',
    'index'       => true,
]);
render_header();
?>
  <main class="terms-page">

<?php render_policy_intro('Refund Policy', 'When you can get your money back for a CSFlix booking, and how.', '2026-09-30'); ?>

<?php if (is_student_project() && !paymongo_is_live()): ?>
    <div class="terms-block">
      <h2>While CSFlix is a school demo</h2>
      <p>Payments on this website run in PayMongo's test mode, so no real money is charged and there is nothing to refund. The rules below show how refunds work in the booking system.</p>
    </div>

<?php endif; ?>
    <div class="terms-block">
      <h2>1. Paid bookings</h2>
      <ul>
        <li>A paid booking is final: it cannot be cancelled, changed or moved to another showing, and is not refunded if you miss the film.</li>
        <li>Snacks are part of the booking and follow the same rule.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>2. When you get a full refund</h2>
      <ul>
        <li><strong>Your payment arrived too late.</strong> Seats are held for <?= e($holdMinutes) ?> minutes. If your payment arrives after that and the seats have been taken, the full amount is refunded automatically and your booking shows as Refunded.</li>
        <li><strong>We cancel the showing.</strong> If CSFlix cancels or cannot screen your showing, we refund the whole booking, tickets and snacks, and tell you by email.</li>
        <li><strong>You were charged wrongly.</strong> If you were charged twice, or more than the total shown before you paid, we refund the extra.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>3. Unpaid bookings</h2>
      <p>If you do not finish paying within <?= e($holdMinutes) ?> minutes, the booking ends by itself, its seats go back on sale, and nothing is charged.</p>
    </div>

    <div class="terms-block">
      <h2>4. How refunds are paid</h2>
      <ul>
        <li>Refunds go back to the way you paid (QR Ph, card or GCash), through PayMongo.</li>
        <li>How long it takes to reach you depends on your bank or e-wallet, usually from a few days up to a few weeks.</li>
        <li>Once refunded, the booking's QR code can no longer be used at the door.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>5. How to ask for a refund</h2>
      <p>Email <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php else: ?>the CSFlix project team<?php endif; ?> with your booking reference (for example CSF-ABC234) and what went wrong. We aim to reply within 7 days.</p>
      <p>This policy does not take away any right you have under the Consumer Act of the Philippines (Republic Act No. 7394).</p>
    </div>

<?php render_policy_contact(); ?>

  </main>

<?php render_footer();
