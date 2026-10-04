<?php
declare(strict_types=1);

// The booking form from book.php lands here. Every choice is checked again,
// the seats are held, and the customer is sent to PayMongo's own checkout
// page to pay. Prices come from the database; the form only says which
// seats and how many of each snack.

require __DIR__ . '/includes/bootstrap.php';

require_post();
verify_csrf();

$movieId = input_int($_POST, 'movie_id', 1);
$movie = $movieId !== null ? find_movie($movieId) : null;
if ($movie === null || (int) $movie['is_active'] !== 1) {
    redirect('index.php');
}
$bookPath = 'book.php?movie=' . rawurlencode((string) $movie['slug']);

// Someone signed out (or whose session ran out) is sent to sign in and then
// straight back to the film, rather than to this form-only address.
$signedIn = current_user();
if ($signedIn === null) {
    redirect('signin.php?return=' . rawurlencode($bookPath));
}
// Staff see a greyed-out button; a form sent anyway just goes back to the
// film's page rather than to an error page
if ($signedIn['role'] !== 'customer') {
    redirect($bookPath);
}
$user = $signedIn;

$date = input_date($_POST, 'date');
$time = input_string($_POST, 'time', 8);
if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
    $time = '';
}

/**
 * The film's booking page again, with the date and showtime still picked,
 * so a bounced booking only needs new seats.
 */
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

// Seats: a list of seat codes. Anything else (a single value, nested lists)
// counts as no seats at all. create_pending_booking() checks each code.
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

// Snacks: snack id => how many. A quantity that is not a whole number from
// 0 to the limit means the form was tampered with, so the booking goes back.
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

// Each checkout opens a PayMongo session, so one account cannot start them
// endlessly (holds are already capped by create_pending_booking()).
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
 * PayMongo could not start the payment: let the seats go again at once and
 * send the customer back to try again. Nothing was charged.
 */
function checkout_give_up(array $booking, Throwable $error, string $backPath): void
{
    error_log(sprintf('[checkout] %s: %s: %s', $booking['reference'], get_class($error), $error->getMessage()));
    try {
        cancel_pending_booking($booking);
    } catch (Throwable $cancelError) {
        // The hold still runs out by itself after a few minutes
        error_log('[checkout] could not cancel ' . $booking['reference'] . ': ' . $cancelError->getMessage());
    }
    redirect($backPath);
}

$customer = [
    'name'   => (string) $user['name'],
    'email'  => (string) $user['email'],
    'mobile' => (string) ($user['mobile'] ?? ''),
];

// QR Ph only: the code is made here, valid exactly as long as the seats are
// held, and shown on Cinemax's own page with a countdown
if (paymongo_qrph_only()) {
    try {
        $qr = paymongo_create_qrph($booking, $customer, booking_seconds_left($booking));
    } catch (Throwable $e) {
        checkout_give_up($booking, $e, $backPath);
    }
    attach_intent((int) $booking['id'], $qr['id']);
    $_SESSION['qrph'] = [$booking['reference'] => $qr['qr']];
    redirect('pay.php?ref=' . rawurlencode((string) $booking['reference']));
}

try {
    $checkout = paymongo_create_checkout($booking, booking_line_items($booking), $customer);
} catch (Throwable $e) {
    // PayMongoException mostly; anything else from the call (a missing
    // extension, say) also must not leave the seats held for nothing
    checkout_give_up($booking, $e, $backPath);
}

attach_checkout((int) $booking['id'], $checkout['id']);
$booking['paymongo_checkout_id'] = $checkout['id'];

try {
    redirect_external($checkout['checkout_url']);
} catch (RuntimeException $e) {
    // PayMongo handed back an address that is not PayMongo's own
    checkout_give_up($booking, $e, $backPath);
}
