<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_post();
verify_csrf();

$movieId = input_int($_POST, 'movie_id', 1);
$movie = $movieId !== null ? find_movie($movieId) : null;
if ($movie === null || (int) $movie['is_active'] !== 1) {
    redirect('index.php');
}
$bookPath = 'book.php?movie=' . rawurlencode((string) $movie['slug']);

$signedIn = current_user();
if ($signedIn === null) {
    redirect('signin.php?return=' . rawurlencode(clean_path($bookPath)));
}
if ($signedIn['role'] !== 'customer') {
    redirect($bookPath);
}
$user = $signedIn;

$date = input_date($_POST, 'date');
$time = input_string($_POST, 'time', 8);
if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
    $time = '';
}

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

if (too_many_attempts('checkout', 'user:' . $user['id'], 20, 3600, 60)) {
    redirect($backPath);
}

try {
    $booking = create_pending_booking($user, $movie, $date, $time, $seats, $snacks);
} catch (BookingException $e) {
    redirect($backPath);
}
record_attempt('checkout', 'user:' . $user['id']);

function checkout_give_up(array $booking, Throwable $error, string $backPath): void
{
    error_log(sprintf('[checkout] %s: %s: %s', $booking['reference'], get_class($error), $error->getMessage()));
    try {
        cancel_pending_booking($booking);
    } catch (Throwable $cancelError) {
        error_log('[checkout] could not cancel ' . $booking['reference'] . ': ' . $cancelError->getMessage());
    }
    redirect($backPath);
}

$customer = [
    'name'   => (string) $user['name'],
    'email'  => (string) $user['email'],
    'mobile' => (string) ($user['mobile'] ?? ''),
];

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
    checkout_give_up($booking, $e, $backPath);
}

attach_checkout((int) $booking['id'], $checkout['id']);
$booking['paymongo_checkout_id'] = $checkout['id'];

try {
    redirect_external($checkout['checkout_url']);
} catch (RuntimeException $e) {
    checkout_give_up($booking, $e, $backPath);
}
