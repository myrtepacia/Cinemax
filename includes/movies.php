<?php
declare(strict_types=1);

// Films, their showtimes and the snacks menu.

if (!defined('CINEMAX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

const MOVIE_RATINGS = ['G', 'PG', 'PG-13', 'R-13', 'R-16', 'R-18'];
const SNACK_CATEGORIES = ['Popcorn', 'Drinks', 'Candy', 'Combos'];

// The building has CINEMA_COUNT cinemas. In each one the films take turns,
// so no two shows in the same cinema may overlap, and after every show it is
// cleaned for CLEANING_MINUTES. Shows start from ROOM_FIRST_SHOW, on the
// SHOWTIME_STEP minutes, and end by ROOM_LAST_END (minutes after midnight).
const CINEMA_COUNT = 2;
const ROOM_FIRST_SHOW = 10 * 60;   // 10:00 AM
const ROOM_LAST_END = 24 * 60;     // midnight
const CLEANING_MINUTES = 10;
const SHOWTIME_STEP = 5;
// Daily shows a newly added film gets, when the cinemas have room for them
const SHOWS_PER_NEW_FILM = 3;
// How far ahead a film with no last day can be booked
const OPEN_ENDED_BOOKING_DAYS = 30;

/**
 * The days a film can be booked for right now, as ['from' => date,
 * 'to' => date], or null when it cannot be booked at all (not open yet,
 * finished, or taken off the listings).
 *
 *   now_showing  today until ends_on
 *   upcoming     nothing until opens_on; from then on, like now_showing
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

/**
 * 'showing', 'soon' or 'ended': where a film belongs on the home page.
 */
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

/**
 * When an upcoming film opens, for its card: 'Opens Oct 17'.
 */
function movie_opening_note(array $movie): string
{
    return 'Opens ' . (new DateTimeImmutable($movie['opens_on']))->format('M j');
}

/**
 * The home page's two lists: ['showing' => [...], 'soon' => [...]].
 */
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

/**
 * Every film still on the listings (for the staff Movies page).
 */
function active_movies(): array
{
    return db_all('SELECT * FROM movies WHERE is_active = 1 ORDER BY id');
}

function find_movie(int $id): ?array
{
    return db_one('SELECT * FROM movies WHERE id = ?', [$id]);
}

/**
 * A film on the listings by its address name, e.g. 'the-reckoning'.
 */
function find_movie_by_slug(string $slug): ?array
{
    if (!preg_match('/^[a-z0-9-]{1,120}$/', $slug)) {
        return null;
    }
    return db_one('SELECT * FROM movies WHERE slug = ? AND is_active = 1', [$slug]);
}

/**
 * A film's daily shows, earliest first, as 'HH:MM:SS' => the cinema it
 * plays in.
 */
function movie_schedule(int $movieId): array
{
    $schedule = [];
    foreach (db_all('SELECT start_time, cinema FROM showtimes WHERE movie_id = ? ORDER BY start_time', [$movieId]) as $row) {
        $schedule[(string) $row['start_time']] = (int) $row['cinema'];
    }
    return $schedule;
}

/**
 * A film's daily showtimes as 'HH:MM:SS', earliest first.
 */
function movie_showtimes(int $movieId): array
{
    return array_map('strval', array_keys(movie_schedule($movieId)));
}

/**
 * The poster's address, or a plain placeholder when the film has none.
 */
function poster_url(array $movie): string
{
    $path = (string) ($movie['poster_path'] ?? '');
    if ($path !== '' && preg_match('~^(assets/img/posters|uploads/posters)/[A-Za-z0-9._-]+$~', $path) && is_file(APP_ROOT . '/' . $path)) {
        return asset($path);
    }
    return asset('assets/img/poster-placeholder.svg');
}

/**
 * 'Crime, Thriller, 2h 2m' — the genre and running time together.
 */
function movie_details_line(array $movie, bool $withRating = false): string
{
    $line = $movie['genre'] . ', ' . duration_label((int) $movie['duration_minutes']);
    return $withRating ? $line . ', ' . $movie['rating'] : $line;
}

/**
 * Seats sold for a film across every showing, counting paid bookings only.
 */
function movie_tickets_sold(int $movieId): int
{
    return (int) db_value(
        "SELECT COALESCE(SUM(ticket_count), 0) FROM bookings WHERE movie_id = ? AND status = 'paid'",
        [$movieId]
    );
}

/**
 * An address name from a title: 'Broken [of] Love' becomes 'broken-of-love',
 * with '-2', '-3'... added if that name is already used.
 */
function unique_movie_slug(string $title): string
{
    $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
    $base = substr($base !== '' ? $base : 'movie', 0, 100);
    $slug = $base;
    $n = 2;
    while (db_value('SELECT 1 FROM movies WHERE slug = ?', [$slug]) !== null) {
        $slug = $base . '-' . $n++;
    }
    return $slug;
}

/**
 * Whether two films are on the listings on at least one same day, so that
 * their shows compete for the cinemas. opens_on null means already showing;
 * ends_on null means no last day.
 */
function movie_runs_overlap(array $a, array $b): bool
{
    $aFrom = $a['opens_on'] ?? null;
    $aTo = $a['ends_on'] ?? null;
    $bFrom = $b['opens_on'] ?? null;
    $bTo = $b['ends_on'] ?? null;
    return ($aFrom === null || $bTo === null || $aFrom <= $bTo)
        && ($bFrom === null || $aTo === null || $bFrom <= $aTo);
}

/**
 * Minutes after midnight for 'HH:MM[:SS]'.
 */
function minutes_of_day(string $time): int
{
    [$hours, $minutes] = array_map('intval', explode(':', $time));
    return $hours * 60 + $minutes;
}

/**
 * Whether a show starting at $start (minutes after midnight) and running
 * $length minutes, plus its cleaning, overlaps any of $shows ([start,
 * length] pairs, each with its own cleaning).
 */
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
 * Up to $count daily shows for a new film, as ['start' => 'HH:MM:SS',
 * 'cinema' => n], at the earliest times a cinema is free on every day the
 * film runs. Fewer, or none, when the cinemas are full. $movie holds
 * duration_minutes, opens_on and ends_on.
 *
 * A cinema is taken by the other films' daily shows on the days they run,
 * and by tickets already sold or held for a show, each followed by its
 * cleaning time. The film's own shows never overlap each other either:
 * a showing's seats are counted per film and time.
 */
function free_showtimes(array $movie, int $count): array
{
    $today = today();
    $from = max($movie['opens_on'] ?? $today, $today);
    $to = $movie['ends_on'] ?? null;

    $taken = array_fill(1, CINEMA_COUNT, []);
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
    // Tickets for a show that is no longer on the timetable (a film taken
    // off the listings, say) still need their cinema at that time
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

/**
 * Adds a film with up to SHOWS_PER_NEW_FILM daily shows at the first free
 * times in the cinemas (or none, when they are full on its days). $movie
 * holds title, genre, duration_minutes, rating, price, status, opens_on,
 * ends_on and poster_path, already checked by the caller. Returns the new id.
 */
function create_movie(array $movie): int
{
    return (int) db_transaction(function (PDO $pdo) use ($movie): int {
        // One film at a time, so two admins cannot both take the same free time
        db_all('SELECT id FROM movies WHERE is_active = 1 FOR UPDATE');
        $shows = free_showtimes($movie, SHOWS_PER_NEW_FILM);

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
 * The earliest new last day a film's run can be extended to: the day after
 * its last day, or today for a run that has already ended.
 */
function movie_extend_from(array $movie): string
{
    $next = (new DateTimeImmutable((string) $movie['ends_on']))->modify('+1 day')->format('Y-m-d');
    return max($next, today());
}

/**
 * How far a film's run can be extended with its shows kept at the same
 * times, as ['last' => 'Y-m-d', 'blocker' => a film's title, 'cinema' => n,
 * 'from' => 'Y-m-d'], or null when nothing is in the way. What stands in
 * the way is another film's show in the same cinema at a clashing time on
 * a day it runs, or tickets already sold or held for one.
 */
function movie_extend_limit(array $movie): ?array
{
    $start = movie_extend_from($movie);
    $own = [];
    foreach (movie_schedule((int) $movie['id']) as $time => $cinema) {
        $own[$cinema][] = [minutes_of_day((string) $time), (int) $movie['duration_minutes']];
    }

    $first = null;
    $inTheWay = static function (string $date, array $show) use (&$first): void {
        if ($first === null || $date < $first['from']) {
            $first = ['from' => $date, 'blocker' => (string) $show['title'], 'cinema' => (int) $show['cinema']];
        }
    };
    $clashes = static function (array $show, string $timeColumn) use ($own): bool {
        return show_clashes($own[(int) $show['cinema']] ?? [], minutes_of_day((string) $show[$timeColumn]), (int) $show['duration_minutes']);
    };

    $shows = db_all(
        'SELECT s.start_time, s.cinema, m.title, m.duration_minutes, m.opens_on, m.ends_on
         FROM showtimes s JOIN movies m ON m.id = s.movie_id
         WHERE m.is_active = 1 AND m.id <> ? AND (m.ends_on IS NULL OR m.ends_on >= ?)',
        [(int) $movie['id'], $start]
    );
    foreach ($shows as $show) {
        $from = max((string) ($show['opens_on'] ?? $start), $start);
        if ($clashes($show, 'start_time') && ($show['ends_on'] === null || $show['ends_on'] >= $from)) {
            $inTheWay($from, $show);
        }
    }
    $sold = db_all(
        "SELECT DISTINCT b.show_date, b.show_time, b.cinema, m.title, m.duration_minutes
         FROM bookings b JOIN movies m ON m.id = b.movie_id
         WHERE b.movie_id <> ? AND b.status IN ('paid', 'pending') AND b.show_date >= ?",
        [(int) $movie['id'], $start]
    );
    foreach ($sold as $show) {
        if ($clashes($show, 'show_time')) {
            $inTheWay((string) $show['show_date'], $show);
        }
    }

    if ($first === null) {
        return null;
    }
    $first['last'] = (new DateTimeImmutable($first['from']))->modify('-1 day')->format('Y-m-d');
    return $first;
}

/**
 * Why a run can go no further, for the Movies page: 'Can run until Fri, 16
 * Oct 2026 at the latest: Broken [of] Love uses Cinema 1 at those times
 * from Sat, 17 Oct.'
 */
function movie_extend_limit_text(array $movie, array $limit): string
{
    $why = $limit['blocker'] . ' uses ' . cinema_label($limit['cinema']) . ' at those times from '
        . (new DateTimeImmutable($limit['from']))->format('D, j M') . '.';
    if ($limit['last'] < movie_extend_from($movie)) {
        return 'This run cannot be extended: ' . $why;
    }
    return 'Can run until ' . (new DateTimeImmutable($limit['last']))->format('D, j M Y') . ' at the latest: ' . $why;
}

/**
 * Moves a film's last day later, keeping its showtimes. Returns null when
 * done, or what is wrong for the admin to read.
 */
function extend_movie(int $movieId, string $newLast): ?string
{
    return db_transaction(function () use ($movieId, $newLast): ?string {
        // One change to the timetable at a time (see create_movie)
        db_all('SELECT id FROM movies WHERE is_active = 1 FOR UPDATE');
        $movie = db_one('SELECT * FROM movies WHERE id = ? AND is_active = 1', [$movieId]);
        if ($movie === null) {
            return 'That movie is no longer on the listings.';
        }
        if ($movie['ends_on'] === null) {
            return $movie['title'] . ' has no last day, so there is nothing to extend.';
        }
        $from = movie_extend_from($movie);
        if ($newLast < $from) {
            return 'Pick a new last day from ' . (new DateTimeImmutable($from))->format('D, j M Y') . ' on.';
        }
        $limit = movie_extend_limit($movie);
        if ($limit !== null && $newLast > $limit['last']) {
            return movie_extend_limit_text($movie, $limit);
        }
        db_exec('UPDATE movies SET ends_on = ? WHERE id = ?', [$newLast, $movieId]);
        return null;
    });
}

/**
 * Takes a film off the listings. Its bookings and tickets stay valid; it
 * simply cannot be booked any more.
 */
function remove_movie(int $movieId): bool
{
    return db_exec('UPDATE movies SET is_active = 0 WHERE id = ? AND is_active = 1', [$movieId]) === 1;
}

/**
 * The snacks on sale, cheapest category first: ['Popcorn' => [...], ...].
 */
function snacks_by_category(): array
{
    $groups = array_fill_keys(SNACK_CATEGORIES, []);
    foreach (db_all('SELECT id, name, detail, category, price FROM snacks WHERE is_active = 1 ORDER BY sort_order, id') as $snack) {
        $groups[$snack['category']][] = $snack;
    }
    return array_filter($groups);
}
