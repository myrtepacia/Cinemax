<?php
declare(strict_types=1);

// Terms and Conditions. The numbers come from the booking settings, so the
// page always matches how the site works.

require __DIR__ . '/includes/bootstrap.php';

// The seat hold, worked out as the booking code does
$holdMinutes = max(5, min(60, (int) config('booking_hold_minutes', 10)));

render_head('Terms and Conditions');
render_header();
?>
  <main class="terms-page">

    <h1>Terms and Conditions</h1>
    <p class="terms-intro">By creating an account or booking a ticket on Cinemax, you agree to these terms. Please read them before you book.</p>

    <div class="terms-block">
      <h2>1. Agreement to these terms</h2>
      <p>These Terms and Conditions apply to everyone who uses the Cinemax website to browse films, create an account, book seats or pre-order snacks. If you do not agree with them, please do not use the website.</p>
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
        <li>Payments are processed by PayMongo, by QR Ph, debit or credit card, or Maya. Cinemax never sees or stores your card details.</li>
        <li>Your bank's or e-wallet's own terms may also apply to your payment.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>5. Cancellations and refunds</h2>
      <ul>
        <li>Paid bookings are final. They cannot be cancelled, changed or moved to another showing.</li>
        <li>An unpaid booking ends by itself when its hold runs out, and nothing is charged.</li>
        <li>If a payment arrives after the hold has run out and the seats are no longer free, the full amount is refunded automatically.</li>
        <li>If Cinemax cancels a showing, you receive a full refund for that booking.</li>
        <li>Refunds go back to the way you paid. How long they take depends on your bank or e-wallet.</li>
      </ul>
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
        <li>Payment details are handled by PayMongo and are not stored by Cinemax.</li>
        <li>We do not sell your personal data. We handle it in line with the Philippine Data Privacy Act of 2012.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>9. Changes and our responsibility</h2>
      <ul>
        <li>We may change showtimes, prices, the snack menu or these terms. Changes to these terms apply from the date they are posted on this page.</li>
        <li>Cinemax is not responsible for problems caused by your internet connection, your device, or your bank or payment provider.</li>
        <li>Nothing in these terms takes away your rights under Philippine consumer law. These terms are governed by the laws of the Philippines.</li>
      </ul>
    </div>

  </main>

<?php render_footer();
