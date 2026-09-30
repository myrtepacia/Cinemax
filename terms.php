<?php
declare(strict_types=1);

// The Terms of Service.

require __DIR__ . '/includes/bootstrap.php';

// How long seats are held while paying, worked out the same way the booking
// code does it, so the terms always say what the site really does.
$holdMinutes = max(5, min(60, (int) config('booking_hold_minutes', 15)));

render_head('Terms of Service');
render_header();
?>
  <main class="terms-page">

    <?php render_flashes(); ?>

    <h1>Terms of Service</h1>
    <p class="terms-intro">These are the terms you agree to when using Cinemax's online booking system.</p>

    <div class="terms-block">
      <span class="terms-kind">Account and Booking</span>
      <h2>Your account and your seats</h2>
      <p>You must provide accurate details when creating an account. Your e-ticket is issued under the name on your account.</p>
      <p>A chosen seat is held for <?= e($holdMinutes) ?> minutes only. Unpaid reservations are automatically released back to the seat map so other customers can book them.</p>
    </div>

    <div class="terms-block">
      <span class="terms-kind">Payment and Cancellation</span>
      <h2>How you pay, and how to cancel</h2>
      <p>Payments are processed securely through PayMongo (GCash, Maya, debit or credit card) in Philippine Peso only. Cinemax does not store your card details.</p>
      <p>Bookings may be cancelled before showtime.</p>
    </div>

    <div class="terms-block">
      <span class="terms-kind">Ticket Use and Privacy</span>
      <h2>Your QR code</h2>
      <p>The QR code issued after payment is the only valid proof of purchase, and it may be scanned once.</p>
      <p>Your personal and booking data is stored securely and used only for reservation and verification.</p>
    </div>

    <div class="terms-block">
      <span class="terms-kind terms-kind-snack">Snacks: Menu and Pricing</span>
      <h2>What you see is what you pay</h2>
      <p>You can view every available concession item and its price before adding it to your order. Prices are locked at the time of purchase.</p>
    </div>

    <div class="terms-block">
      <span class="terms-kind terms-kind-snack">Snacks: Pre-Order</span>
      <h2>Order with your ticket</h2>
      <p>You can choose item size and quantity. Pre-orders must be placed at the time of ticket purchase, not after.</p>
    </div>

    <div class="terms-block">
      <span class="terms-kind terms-kind-snack">Snacks: Pickup</span>
      <h2>Collecting your order</h2>
      <p>Pre-ordered snacks are prepared during the showing and are available for pickup at the concession counter once your QR code is scanned.</p>
      <p>Pickup must happen before or after the movie, not during.</p>
    </div>

  </main>

<?php render_footer();
