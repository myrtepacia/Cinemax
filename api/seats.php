<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'Only GET is allowed here.'], 405);
}

if (too_many_attempts('seats', '', 240, 60)) {
    json_response(['ok' => false, 'error' => 'Too many requests. Please wait a moment and try again.'], 429);
}
record_attempt('seats');

$movieId = input_int($_GET, 'movie', 1);
$date = input_date($_GET, 'date');
$time = input_string($_GET, 'time', 8);

if ($movieId === null || $date === null || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
    json_response(['ok' => false, 'error' => 'Pick a date and a showtime.'], 422);
}

$movie = find_movie($movieId);
if ($movie === null || (int) $movie['is_active'] !== 1) {
    json_response(['ok' => false, 'error' => 'This movie cannot be booked.'], 404);
}

try {
    validate_showing($movie, $date, $time);
} catch (BookingException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
}

json_response(['ok' => true, 'taken' => taken_seats($movieId, $date, $time)]);
