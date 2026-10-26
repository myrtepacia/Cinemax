<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

// The seat hold, worked out as the booking code does
$holdMinutes = max(5, min(60, (int) config('booking_hold_minutes', 10)));

render_head('Terms and Conditions', [], [
    'description' => 'The rules for using CSFlix: accounts, seat bookings, payments, refunds, e-tickets and snack pre-orders.',
    'index'       => true,
]);
render_header();
?>
  <main class="terms-page">

<?php render_policy_intro('Terms and Conditions', 'By creating an account or booking a ticket on CSFlix, you agree to these terms. Please read them before you book.', '2026-09-22'); ?>

    <div class="terms-block">
      <h2>1. Agreement to these terms</h2>
      <p>These Terms and Conditions apply to everyone who uses the CSFlix website to browse films, create an account, book seats or pre-order snacks. If you do not agree with them, please do not use the website.</p>
<?php if (is_student_project()): ?>
      <p>CSFlix is a student project made for school. It is not a registered business, is not connected with any real cinema, and its tickets cannot be used to watch a film anywhere.</p>
<?php endif; ?>
    </div>

    <div class="terms-block">
      <h2>2. Your account</h2>
      <ul>
        <li>Give your real name and a working email address. Your e-ticket is issued under the name on your account.</li>
        <li>Keep your password private. You are responsible for bookings made with your account.</li>
        <li>We may suspend or close an account that is used for fraud, abuse or to hold seats from other customers.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>3. Booking seats</h2>
      <ul>
        <li>Your seats are held for <?= e($holdMinutes) ?> minutes while you pay. If payment is not completed in that time, the booking expires and the seats go back on sale.</li>
        <li>One booking can hold up to <?= e(MAX_SEATS_PER_BOOKING) ?> seats, and you can have up to <?= e(MAX_PENDING_PER_USER) ?> unpaid bookings at a time.</li>
        <li>A booking is confirmed only when our payment provider confirms your payment. Your e-ticket then appears under My Bookings.</li>
        <li>All dates and showtimes are in Philippine time.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>4. Prices and payment</h2>
      <ul>
        <li>All prices are in Philippine Peso (PHP). The total shown before you pay, for tickets and any snacks, is the amount charged.</li>
        <li>Payments are processed by PayMongo, by QR Ph, debit or credit card, or GCash. CSFlix never sees or stores your card details.</li>
        <li>Your bank's or e-wallet's own terms may also apply to your payment.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>5. Cancellations and refunds</h2>
      <ul>
        <li>Paid bookings are final. They cannot be cancelled, changed or moved to another showing.</li>
        <li>An unpaid booking ends by itself when its hold runs out, and nothing is charged.</li>
        <li>If a payment arrives after the hold has run out and the seats are no longer free, the full amount is refunded automatically.</li>
        <li>If CSFlix cancels a showing, you receive a full refund for that booking.</li>
        <li>Refunds go back to the way you paid. How long they take depends on your bank or e-wallet.</li>
      </ul>
      <p>The full rules are in our <a href="<?= e(url('refunds.php')) ?>">Refund Policy</a>.</p>
    </div>

    <div class="terms-block">
      <h2>6. Your e-ticket and entry</h2>
      <ul>
        <li>The QR code on your e-ticket is your proof of purchase. It is valid only for the film, date, showtime, cinema and seats shown on it.</li>
        <li>Each QR code can be scanned once. Do not share it: the first person to show it is let in.</li>
        <li>Films are shown with their age rating. For age-restricted films, staff may ask for a valid ID and may refuse entry if the age rule is not met.</li>
        <li>Recording any part of a film is not allowed.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>7. Snack pre-orders</h2>
      <ul>
        <li>Snacks can only be ordered together with your ticket, before you pay. They cannot be added afterwards.</li>
        <li>Snack prices are fixed at the time you book.</li>
        <li>Show your e-ticket's QR code at the snack counter to start your order, then collect it when your pickup number shows as Ready.</li>
        <li>Please collect your snacks before or after the film, not during it.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>8. Your personal data</h2>
      <ul>
        <li>We collect your name, email address, mobile number (if you give one) and booking details to manage your bookings, issue your tickets and check them at the door.</li>
        <li>Payment details are handled by PayMongo and are not stored by CSFlix.</li>
        <li>We do not sell your personal data. We handle it in line with the Philippine Data Privacy Act of 2012.</li>
      </ul>
      <p>Read the full <a href="<?= e(url('privacy.php')) ?>">Privacy Policy</a>.</p>
    </div>


    <div class="terms-block">
      <h2>9. Films, posters and copyright</h2>
      <ul>
        <li>Film titles, posters, logos and other artwork belong to their owners. They are shown only to identify the films<?= is_student_project() ? ', as part of a non-commercial school project' : '' ?>.</li>
        <li>If you own something shown on CSFlix and want it taken down, email us and we will remove it.</li>
        <li>Do not copy the website's own content or code for another service without permission.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>10. Changes and our responsibility</h2>
      <ul>
        <li>We may change showtimes, prices, the snack menu or these terms. Changes to these terms apply from the date they are posted on this page.</li>
        <li>CSFlix is not responsible for problems caused by your internet connection, your device, or your bank or payment provider.</li>
        <li>Nothing in these terms takes away your rights under Philippine consumer law. These terms are governed by the laws of the Philippines.</li>
      </ul>
    </div>

<?php render_policy_contact(); ?>

  </main>

<?php render_footer();
