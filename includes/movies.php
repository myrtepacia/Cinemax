<?php
declare(strict_types=1);

if (!defined('CSFLIX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

const MOVIE_RATINGS = ['G', 'PG', 'PG-13', 'R-13', 'R-16', 'R-18'];

// Each cinema shows one film at a time, cleaned between shows. Shows start on
// 5-minute steps from 10 AM and end by midnight.
const CINEMA_COUNT = 2;
const ROOM_FIRST_SHOW = 10 * 60;   // 10:00 AM
const ROOM_LAST_END = 24 * 60;     // midnight
const CLEANING_MINUTES = 10;
const SHOWTIME_STEP = 5;
// Start times staff can pick on the Add Movie page
const SHOWTIME_CHOICE_STEP = 30;
// Daily shows a new film gets, if there is room
const SHOWS_PER_NEW_FILM = 3;
// How far ahead a film can be booked
const OPEN_ENDED_BOOKING_DAYS = 30;

/** A film's cinema has no free time for it. */
class ScheduleException extends RuntimeException
{
}

/**
 * The days a film can be booked now ['from', 'to'], or null if not open,
 * ended or removed.
 */
function movie_booking_window(array $movie): ?array
{
    if ((int) $movie['is_active'] !== 1) {
        return null;
    }
    $today = today();
    $opens = $movie['opens_on'] ?? null;
    if ($opens !== null && $opens > $today) {
        return null;
    }
    $to = $movie['ends_on'] ?? null;
    if ($to === null) {
        $to = (new DateTimeImmutable($today))->modify('+' . OPEN_ENDED_BOOKING_DAYS . ' days')->format('Y-m-d');
    }
    if ($to < $today) {
        return null;
    }
    return ['from' => $today, 'to' => $to];
}

/** 'showing', 'soon' or 'ended', for the home page. */
function movie_listing_state(array $movie): string
{
    if (movie_booking_window($movie) !== null) {
        return 'showing';
    }
    $opens = $movie['opens_on'] ?? null;
    if ((int) $movie['is_active'] === 1 && $opens !== null && $opens > today()) {
        return 'soon';
    }
    return 'ended';
}

/** 'Opens Oct 17'. */
function movie_opening_note(array $movie): string
{
    return 'Opens ' . (new DateTimeImmutable($movie['opens_on']))->format('M j');
}

/** The home page lists: 'showing' and 'soon'. */
function listing_movies(): array
{
    $lists = ['showing' => [], 'soon' => []];
    foreach (db_all('SELECT * FROM movies WHERE is_active = 1 ORDER BY id') as $movie) {
        $state = movie_listing_state($movie);
        if (isset($lists[$state])) {
            $lists[$state][] = $movie;
        }
    }
    usort($lists['soon'], static function (array $a, array $b): int {
        return strcmp((string) $a['opens_on'], (string) $b['opens_on']);
    });
    return $lists;
}

/** Every film still listed. */
function active_movies(): array
{
    return db_all('SELECT * FROM movies WHERE is_active = 1 ORDER BY id');
}

/** Films taken off the listings (Movies page, Inactive). */
function inactive_movies(): array
{
    return db_all('SELECT * FROM movies WHERE is_active = 0 ORDER BY id');
}

function find_movie(int $id): ?array
{
    return db_one('SELECT * FROM movies WHERE id = ?', [$id]);
}

/** A listed film by its slug, e.g. 'the-reckoning'. */
function find_movie_by_slug(string $slug): ?array
{
    if (!preg_match('/^[a-z0-9-]{1,120}$/', $slug)) {
        return null;
    }
    return db_one('SELECT * FROM movies WHERE slug = ? AND is_active = 1', [$slug]);
}

/** A film's daily shows: 'HH:MM:SS' => cinema. */
function movie_schedule(int $movieId): array
{
    $schedule = [];
    foreach (db_all('SELECT start_time, cinema FROM showtimes WHERE movie_id = ? ORDER BY start_time', [$movieId]) as $row) {
        $schedule[(string) $row['start_time']] = (int) $row['cinema'];
    }
    return $schedule;
}

/** A film's daily showtimes, earliest first. */
function movie_showtimes(int $movieId): array
{
    return array_map('strval', array_keys(movie_schedule($movieId)));
}

/** The poster's address, or a placeholder. */
function poster_url(array $movie): string
{
    $path = (string) ($movie['poster_path'] ?? '');
    if ($path !== '' && preg_match('~^assets/img/posters/[A-Za-z0-9._-]+$~', $path) && is_file(APP_ROOT . '/' . $path)) {
        return asset($path);
    }
    return asset('assets/img/poster-placeholder.svg');
}

/** 'Crime, Thriller, 2h 2min, R-16'. */
function movie_details_line(array $movie): string
{
    return $movie['genre'] . ', ' . duration_tag((int) $movie['duration_minutes']) . ', ' . $movie['rating'];
}

/** Tickets sold for a film (paid only). */
function movie_tickets_sold(int $movieId): int
{
    return (int) db_value(
        "SELECT COALESCE(SUM(ticket_count), 0) FROM bookings WHERE movie_id = ? AND status = 'paid'",
        [$movieId]
    );
}

/**
 * A unique slug from a title: 'Broken [of] Love' becomes 'broken-of-love'.
 */
function unique_movie_slug(string $title): string
{
    $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
    $base = substr($base !== '' ? $base : 'movie', 0, 100);
    $slug = $base;
    $n = 2;
    // A film's page is /its-slug, so it may not take a page's name (/terms)
    while (db_value('SELECT 1 FROM movies WHERE slug = ?', [$slug]) !== null
        || is_file(APP_ROOT . '/' . $slug . '.php') || is_dir(APP_ROOT . '/' . $slug)) {
        $slug = $base . '-' . $n++;
    }
    return $slug;
}

/** Whether two films run on at least one same day. */
function movie_runs_overlap(array $a, array $b): bool
{
    $aFrom = $a['opens_on'] ?? null;
    $aTo = $a['ends_on'] ?? null;
    $bFrom = $b['opens_on'] ?? null;
    $bTo = $b['ends_on'] ?? null;
    return ($aFrom === null || $bTo === null || $aFrom <= $bTo)
        && ($bFrom === null || $aTo === null || $bFrom <= $aTo);
}

/** Minutes after midnight for 'HH:MM'. */
function minutes_of_day(string $time): int
{
    [$hours, $minutes] = array_map('intval', explode(':', $time));
    return $hours * 60 + $minutes;
}

/** Whether a show (plus cleaning) overlaps any of $shows. */
function show_clashes(array $shows, int $start, int $length): bool
{
    foreach ($shows as [$otherStart, $otherLength]) {
        if ($start < $otherStart + $otherLength + CLEANING_MINUTES
            && $otherStart < $start + $length + CLEANING_MINUTES) {
            return true;
        }
    }
    return false;
}

/**
 * Times already taken in each cinema, by other films and tickets already sold,
 * as [cinema => [[start minute, length], ...]]. Only the given cinema when one
 * is named.
 */
function taken_showtimes(array $movie, ?int $onlyCinema = null): array
{
    $today = today();
    $from = max($movie['opens_on'] ?? $today, $today);
    $to = $movie['ends_on'] ?? null;

    $taken = $onlyCinema !== null ? [$onlyCinema => []] : array_fill(1, CINEMA_COUNT, []);
    $shows = db_all(
        'SELECT s.start_time, s.cinema, m.duration_minutes, m.opens_on, m.ends_on
         FROM showtimes s JOIN movies m ON m.id = s.movie_id
         WHERE m.is_active = 1 AND (m.ends_on IS NULL OR m.ends_on >= ?)',
        [$today]
    );
    foreach ($shows as $show) {
        if (isset($taken[(int) $show['cinema']]) && movie_runs_overlap($movie, $show)) {
            $taken[(int) $show['cinema']][] = [minutes_of_day((string) $show['start_time']), (int) $show['duration_minutes']];
        }
    }
    // Tickets sold for a removed film still need their cinema
    $sold = db_all(
        "SELECT DISTINCT b.show_time, b.cinema, m.duration_minutes
         FROM bookings b JOIN movies m ON m.id = b.movie_id
         WHERE b.status IN ('paid', 'pending') AND b.show_date >= ?
           AND (? IS NULL OR b.show_date <= ?)",
        [$from, $to, $to]
    );
    foreach ($sold as $show) {
        if (isset($taken[(int) $show['cinema']])) {
            $taken[(int) $show['cinema']][] = [minutes_of_day((string) $show['show_time']), (int) $show['duration_minutes']];
        }
    }
    return $taken;
}

/**
 * Up to $count free daily shows for a film ['start', 'cinema'], avoiding
 * other films and tickets already sold. None when the cinema is full.
 */
function free_showtimes(array $movie, int $count, ?int $onlyCinema = null): array
{
    $taken = taken_showtimes($movie, $onlyCinema);

    $length = (int) $movie['duration_minutes'];
    $picked = [];
    $own = [];
    for ($start = ROOM_FIRST_SHOW; count($picked) < $count && $start + $length <= ROOM_LAST_END; $start += SHOWTIME_STEP) {
        if (show_clashes($own, $start, $length)) {
            continue;
        }
        foreach ($taken as $cinema => $cinemaShows) {
            if (!show_clashes($cinemaShows, $start, $length)) {
                $picked[] = ['start' => sprintf('%02d:%02d:00', intdiv($start, 60), $start % 60), 'cinema' => $cinema];
                $taken[$cinema][] = [$start, $length];
                $own[] = [$start, $length];
                break;
            }
        }
    }
    return $picked;
}

/** The start times staff can choose from: every 30 minutes, 'HH:MM'. */
function showtime_choices(): array
{
    $choices = [];
    for ($minute = ROOM_FIRST_SHOW; $minute < ROOM_LAST_END; $minute += SHOWTIME_CHOICE_STEP) {
        $choices[] = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
    return $choices;
}

/**
 * Checks the start times staff chose ('HH:MM') for a film in its cinema.
 * Returns ['start' => 'HH:MM:00', 'cinema' => n] for each, earliest first.
 * Throws ScheduleException naming the first time that does not fit.
 */
function chosen_showtimes(array $movie, array $times): array
{
    $cinema = (int) $movie['cinema'];
    $length = (int) $movie['duration_minutes'];
    $taken = taken_showtimes($movie, $cinema)[$cinema] ?? [];

    $starts = [];
    foreach ($times as $time) {
        $starts[] = minutes_of_day((string) $time);
    }
    sort($starts);

    $own = [];
    $picked = [];
    foreach ($starts as $start) {
        $label = format_time(sprintf('%02d:%02d:00', intdiv($start, 60), $start % 60));
        if ($start + $length > ROOM_LAST_END) {
            throw new ScheduleException(
                'The ' . $label . ' show would end after midnight. Choose an earlier time for a '
                . duration_tag($length) . ' movie.'
            );
        }
        if (show_clashes($own, $start, $length)) {
            throw new ScheduleException(
                'The ' . $label . ' show starts before the movie\'s previous show is over and the room is cleaned. '
                . 'Leave more time between the shows.'
            );
        }
        if (show_clashes($taken, $start, $length)) {
            throw new ScheduleException(
                cinema_label($cinema) . ' is already busy at ' . $label . '. Choose another time or the other cinema.'
            );
        }
        $own[] = [$start, $length];
        $picked[] = ['start' => sprintf('%02d:%02d:00', intdiv($start, 60), $start % 60), 'cinema' => $cinema];
    }
    return $picked;
}

/**
 * Adds a film with the daily shows in $movie['showtimes'] ('HH:MM' each), or up
 * to SHOWS_PER_NEW_FILM free ones when none are given. Throws
 * ScheduleException if the times do not fit its cinema. Returns the new id.
 */
function create_movie(array $movie): int
{
    return (int) db_transaction(function (PDO $pdo) use ($movie): int {
        // Lock, so two admins cannot take the same free time
        db_all('SELECT id FROM movies WHERE is_active = 1 FOR UPDATE');
        if (!empty($movie['showtimes'])) {
            // Times chosen by staff
            $shows = chosen_showtimes($movie, $movie['showtimes']);
        } else {
            $shows = free_showtimes($movie, SHOWS_PER_NEW_FILM, (int) $movie['cinema']);
        }
        if ($shows === []) {
            throw new ScheduleException(
                cinema_label((int) $movie['cinema']) . ' has no free time for a ' . duration_tag((int) $movie['duration_minutes'])
                . ' movie: films have no last day, so its times stay taken until a movie is removed.'
                . ' Choose the other cinema, or remove a movie on the Movies page first.'
            );
        }

        db_exec(
            'INSERT INTO movies (slug, title, genre, duration_minutes, rating, price, status, opens_on, ends_on, poster_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                unique_movie_slug($movie['title']),
                $movie['title'],
                $movie['genre'],
                (int) $movie['duration_minutes'],
                $movie['rating'],
                (int) $movie['price'],
                $movie['status'],
                $movie['opens_on'],
                $movie['ends_on'],
                $movie['poster_path'],
            ]
        );
        $id = (int) $pdo->lastInsertId();
        foreach ($shows as $show) {
            db_exec('INSERT INTO showtimes (movie_id, cinema, start_time) VALUES (?, ?, ?)', [$id, $show['cinema'], $show['start']]);
        }
        return $id;
    });
}

/**
 * Puts a film on the listings (active) or takes it off (inactive), keeping
 * its showtimes. Tickets already sold stay valid either way.
 */
function set_movie_active(int $movieId, bool $active): bool
{
    return db_exec('UPDATE movies SET is_active = ? WHERE id = ? AND is_active = ?', [$active ? 1 : 0, $movieId, $active ? 0 : 1]) === 1;
}

/** How many bookings of any kind (paid, unpaid, expired...) name a film. */
function movie_booking_count(int $movieId): int
{
    return (int) db_value('SELECT COUNT(*) FROM bookings WHERE movie_id = ?', [$movieId]);
}

/**
 * How many bookings that matter (paid or refunded: real sales) name a film.
 * Unpaid, expired and cancelled bookings never took money, so they do not
 * stop a film from being deleted.
 */
function movie_sales_booking_count(int $movieId): int
{
    return (int) db_value("SELECT COUNT(*) FROM bookings WHERE movie_id = ? AND status IN ('paid', 'refunded')", [$movieId]);
}

/**
 * Deletes an inactive film for good: the film, its showtimes and its poster.
 * A film with paid or refunded bookings stays (its tickets and sales need
 * it); its unpaid, expired or cancelled bookings go with it. Returns
 * an error message, or '' when deleted.
 */
function delete_movie(int $movieId): string
{
    $movie = db_one('SELECT id, title, poster_path, is_active FROM movies WHERE id = ?', [$movieId]);
    if ($movie === null) {
        return 'That movie is already gone.';
    }
    if ((int) $movie['is_active'] === 1) {
        return 'Set ' . $movie['title'] . ' to Inactive before deleting it.';
    }
    if (movie_sales_booking_count($movieId) > 0) {
        return $movie['title'] . ' has bookings, so it cannot be deleted. It stays Inactive.';
    }
    // Leftover unpaid/expired/cancelled bookings go first (their seats and
    // snacks cascade), then the film; showtimes go with it (ON DELETE CASCADE)
    db_transaction(static function () use ($movieId): void {
        db_exec("DELETE FROM bookings WHERE movie_id = ? AND status IN ('pending', 'expired', 'cancelled')", [$movieId]);
        db_exec('DELETE FROM movies WHERE id = ? AND is_active = 0', [$movieId]);
    });

    // Its poster file too, if it is in the posters folder and no other film uses it
    $poster = (string) $movie['poster_path'];
    if (preg_match('~^assets/img/posters/[A-Za-z0-9._-]+$~', $poster)
        && db_value('SELECT 1 FROM movies WHERE poster_path = ?', [$poster]) === null) {
        $file = APP_ROOT . '/' . $poster;
        if (is_file($file) && !@unlink($file)) {
            error_log('[movies] could not delete poster ' . $poster);
        }
    }
    return '';
}

