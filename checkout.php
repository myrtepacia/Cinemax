<?php
declare(strict_types=1);

// The booking form lands here: everything is checked again, seats are held,
// then on to payment. Prices come from the database.

require __DIR__ . '/includes/bootstrap.php';

require_post();
verify_csrf();

$movieId = input_int($_POST, 'movie_id', 1);
$movie = $movieId !== null ? find_movie($movieId) : null;
if ($movie === null || (int) $movie['is_active'] !== 1) {
    redirect('index.php');
}
$bookPath = 'book.php?movie=' . rawurlencode((string) $movie['slug']);

// Signed out: sign in, then back to the film
$signedIn = current_user();
if ($signedIn === null) {
    redirect('signin.php?return=' . rawurlencode($bookPath));
}
// Staff cannot book: back to the film
if ($signedIn['role'] !== 'customer') {
    redirect($bookPath);
}
$user = $signedIn;

$date = input_date($_POST, 'date');
$time = input_string($_POST, 'time', 8);
if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
    $time = '';
}

/** The film's page again, with the date and showtime kept. */
function checkout_back_path(string $bookPath, ?string $date, string $time): string
{
    $path = $bookPath;
    if ($date !== null) {
        $path .= '&date=' . rawurlencode($date);
    }
    if ($time !== '') {
        $path .= '&time=' . rawurlencode($time);
    }
    return $path;
}

$backPath = checkout_back_path($bookPath, $date, $time);

// Seats: a list of seat codes
$seats = [];
$rawSeats = $_POST['seats'] ?? [];
if (is_array($rawSeats)) {
    foreach ($rawSeats as $seat) {
        if (!is_string($seat)) {
            $seats = [];
            break;
        }
        $seats[] = $seat;
    }
}

// Snacks: id => how many; anything odd sends the booking back
$snacks = [];
$snackProblem = false;
$rawSnacks = $_POST['snacks'] ?? [];
if (is_array($rawSnacks)) {
    foreach ($rawSnacks as $snackId => $unused) {
        $key = (string) $snackId;
        $quantity = preg_match('/^\d{1,10}$/', $key) ? input_int($rawSnacks, $key, 0, MAX_PER_SNACK) : null;
        if ($quantity === null) {
            $snackProblem = true;
            break;
        }
        if ($quantity > 0) {
            $snacks[(int) $key] = $quantity;
        }
    }
} else {
    $snackProblem = true;
}

if ($date === null || $time === '') {
    redirect($backPath);
}
if ($snackProblem) {
    redirect($backPath);
}

// Limit how many payments one account can start
if (too_many_attempts('checkout', 'user:' . $user['id'], 20, 3600, 60)) {
    redirect($backPath);
}

try {
    $booking = create_pending_booking($user, $movie, $date, $time, $seats, $snacks);
} catch (BookingException $e) {
    redirect($backPath);
}
record_attempt('checkout', 'user:' . $user['id']);

/**
 * PayMongo failed: free the seats and send the customer back. Nothing was
 * charged.
 */
function checkout_give_up(array $booking, Throwable $error, string $backPath): void
{
    error_log(sprintf('[checkout] %s: %s: %s', $booking['reference'], get_class($error), $error->getMessage()));
    try {
        cancel_pending_booking($booking);
    } catch (Throwable $cancelError) {
        // The hold runs out by itself anyway
        error_log('[checkout] could not cancel ' . $booking['reference'] . ': ' . $cancelError->getMessage());
    }
    redirect($backPath);
}

$customer = [
    'name'   => (string) $user['name'],
    'email'  => (string) $user['email'],
    'mobile' => (string) ($user['mobile'] ?? ''),
];

// QR Ph: the code is shown on our own page
if (paymongo_uses_qrph()) {
    try {
        $qr = paymongo_create_qrph($booking, $customer, booking_seconds_left($booking));
    } catch (Throwable $e) {
        checkout_give_up($booking, $e, $backPath);
    }
    attach_intent((int) $booking['id'], $qr['id']);
    remember_qr((string) $booking['reference'], $qr['qr']);
    redirect('pay.php?ref=' . rawurlencode((string) $booking['reference']));
}

try {
    $checkout = paymongo_create_checkout($booking, booking_line_items($booking), $customer);
} catch (Throwable $e) {
    // Anything going wrong must not keep the seats held
    checkout_give_up($booking, $e, $backPath);
}

attach_checkout((int) $booking['id'], $checkout['id']);
$booking['paymongo_checkout_id'] = $checkout['id'];

try {
    redirect_external($checkout['checkout_url']);
} catch (RuntimeException $e) {
    // Not a PayMongo address
    checkout_give_up($booking, $e, $backPath);
}
