<?php
declare(strict_types=1);

if (!defined('CINEMAX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

const SEAT_ROWS = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
const SEATS_PER_ROW = 10;
const MAX_SEATS_PER_BOOKING = 10;
const MAX_PER_SNACK = 10;
const MAX_PENDING_PER_USER = 3;
const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
const BOOKING_INTENT_COLUMNS = ['paymongo_intent_id', 'paymongo_card_intent_id', 'paymongo_wallet_intent_id'];

class BookingException extends RuntimeException
{
}

function is_valid_seat(string $code): bool
{
    return (bool) preg_match('/^[A-G](?:[1-9]|10)$/', $code);
}

function sort_seats(array $seats): array
{
    usort($seats, static function (string $a, string $b): int {
        return [$a[0], (int) substr($a, 1)] <=> [$b[0], (int) substr($b, 1)];
    });
    return $seats;
}

function release_expired_holds(): void
{
    $runOut = db_all(
        "SELECT id, reference, paymongo_checkout_id, " . implode(', ', BOOKING_INTENT_COLUMNS) . " FROM bookings
         WHERE status = 'pending' AND expires_at < NOW()
         ORDER BY expires_at LIMIT 20"
    );
    $remoteCalls = 0;
    foreach ($runOut as $hold) {
        $bookingId = (int) $hold['id'];
        $checkoutId = (string) ($hold['paymongo_checkout_id'] ?? '');
        $askPayMongo = ($checkoutId !== '' || booking_has_intent($hold)) && $remoteCalls < 3;

        if ($askPayMongo) {
            $remoteCalls++;
            try {
                $payment = booking_paid_payment($hold, 5);
                if ($payment !== null
                    && in_array(mark_booking_paid($bookingId, $payment['id'], $payment['amount']), ['paid', 'already'], true)) {
                    continue;
                }
            } catch (PayMongoException $e) {
                error_log('[hold] could not check ' . $hold['reference'] . ': ' . $e->getMessage());
            }
        }

        $expired = db_exec("UPDATE bookings SET status = 'expired' WHERE id = ? AND status = 'pending'", [$bookingId]) === 1;
        if ($expired && $askPayMongo && $checkoutId !== '') {
            paymongo_expire_checkout($checkoutId, 5);
        }
    }

    db_exec(
        "DELETE bs FROM booking_seats bs
         JOIN bookings b ON b.id = bs.booking_id
         WHERE b.status IN ('expired', 'cancelled', 'refunded')"
    );
}

function taken_seats(int $movieId, string $date, string $time): array
{
    release_expired_holds();
    return array_map(static function (array $row): string {
        return (string) $row['seat_code'];
    }, db_all(
        'SELECT seat_code FROM booking_seats WHERE movie_id = ? AND show_date = ? AND show_time = ?',
        [$movieId, $date, $time]
    ));
}

function showing_has_started(string $date, string $time): bool
{
    return new DateTimeImmutable($date . ' ' . $time) <= new DateTimeImmutable('now');
}

function validate_showing(array $movie, string $date, string $time): void
{
    $window = movie_booking_window($movie);
    if ($window === null) {
        throw new BookingException('This movie cannot be booked right now.');
    }
    if ($date < $window['from'] || $date > $window['to']) {
        throw new BookingException('Pick a date between ' . format_date_short($window['from'])
            . ' and ' . format_date_short($window['to']) . '.');
    }
    if (!in_array($time, movie_showtimes((int) $movie['id']), true)) {
        throw new BookingException('Pick one of the listed showtimes.');
    }
    if (showing_has_started($date, $time)) {
        throw new BookingException('That showtime has already started. Please pick a later one.');
    }
}

function generate_reference(): string
{
    $last = strlen(REFERENCE_ALPHABET) - 1;
    for ($try = 0; $try < 20; $try++) {
        $code = 'CMX-';
        for ($i = 0; $i < 6; $i++) {
            $code .= REFERENCE_ALPHABET[random_int(0, $last)];
        }
        if (db_value('SELECT 1 FROM bookings WHERE reference = ?', [$code]) === null) {
            return $code;
        }
    }
    throw new RuntimeException('Could not find a free reference number.');
}

function normalize_reference(string $text): ?string
{
    $code = strtoupper((string) preg_replace('/[\s-]+/', '', $text));
    if (strpos($code, 'CMX') === 0) {
        $code = substr($code, 3);
    }
    if (!preg_match('/^[' . REFERENCE_ALPHABET . ']{6}$/', $code)) {
        return null;
    }
    return 'CMX-' . $code;
}

function create_pending_booking(array $user, array $movie, string $date, string $time, array $seats, array $snackQuantities): array
{
    validate_showing($movie, $date, $time);

    $clean = [];
    foreach ($seats as $seat) {
        if (!is_string($seat) || !is_valid_seat($seat)) {
            throw new BookingException('One of the seats picked does not exist. Please pick again.');
        }
        $clean[$seat] = true;
    }
    $seats = sort_seats(array_keys($clean));
    if (count($seats) === 0) {
        throw new BookingException('Pick at least one seat.');
    }
    if (count($seats) > MAX_SEATS_PER_BOOKING) {
        throw new BookingException('One booking can hold up to ' . MAX_SEATS_PER_BOOKING . ' seats.');
    }

    $snackLines = [];
    $wanted = [];
    foreach ($snackQuantities as $id => $quantity) {
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            throw new BookingException('One of the snacks picked is not on the menu.');
        }
        if (!is_int($quantity) || $quantity < 0 || $quantity > MAX_PER_SNACK) {
            throw new BookingException('You can add up to ' . MAX_PER_SNACK . ' of each snack.');
        }
        if ($quantity > 0) {
            $wanted[(int) $id] = $quantity;
        }
    }
    $snacksTotal = 0;
    if ($wanted !== []) {
        $marks = implode(',', array_fill(0, count($wanted), '?'));
        $menu = db_all("SELECT id, name, price FROM snacks WHERE is_active = 1 AND id IN ($marks)", array_keys($wanted));
        if (count($menu) !== count($wanted)) {
            throw new BookingException('One of the snacks picked is not on the menu any more.');
        }
        foreach ($menu as $snack) {
            $qty = $wanted[(int) $snack['id']];
            $snackLines[] = ['id' => (int) $snack['id'], 'quantity' => $qty, 'price' => (int) $snack['price']];
            $snacksTotal += $qty * (int) $snack['price'];
        }
    }

    $ticketPrice = (int) $movie['price'];
    $total = count($seats) * $ticketPrice + $snacksTotal;
    $cinema = movie_schedule((int) $movie['id'])[$time];

    release_expired_holds();

    $waiting = (int) db_value(
        "SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'pending'",
        [(int) $user['id']]
    );
    if ($waiting >= MAX_PENDING_PER_USER) {
        throw new BookingException('You already have bookings waiting for payment. Finish or cancel those first.');
    }

    $holdMinutes = max(5, min(60, (int) config('booking_hold_minutes', 10)));

    try {
        $bookingId = db_transaction(function (PDO $pdo) use ($user, $movie, $date, $time, $cinema, $seats, $snackLines, $ticketPrice, $snacksTotal, $total, $holdMinutes): int {
            db_exec(
                "INSERT INTO bookings
                   (reference, qr_token, user_id, movie_id, show_date, show_time, cinema, ticket_count, seat_list,
                    ticket_price, snacks_total, total, status, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW() + INTERVAL ? MINUTE)",
                [
                    generate_reference(),
                    bin2hex(random_bytes(16)),
                    (int) $user['id'],
                    (int) $movie['id'],
                    $date,
                    $time,
                    $cinema,
                    count($seats),
                    implode(', ', $seats),
                    $ticketPrice,
                    $snacksTotal,
                    $total,
                    $holdMinutes,
                ]
            );
            $id = (int) $pdo->lastInsertId();
            foreach ($seats as $seat) {
                db_exec(
                    'INSERT INTO booking_seats (booking_id, movie_id, show_date, show_time, seat_code) VALUES (?, ?, ?, ?, ?)',
                    [$id, (int) $movie['id'], $date, $time, $seat]
                );
            }
            foreach ($snackLines as $line) {
                db_exec(
                    'INSERT INTO booking_snacks (booking_id, snack_id, quantity, unit_price) VALUES (?, ?, ?, ?)',
                    [$id, $line['id'], $line['quantity'], $line['price']]
                );
            }
            return $id;
        });
    } catch (PDOException $e) {
        if (is_duplicate_key($e)) {
            throw new BookingException('Someone else just took one of those seats. Please pick again.');
        }
        throw $e;
    }

    return find_booking_by_id($bookingId);
}

function booking_line_items(array $booking): array
{
    $items = [[
        'name'     => $booking['title'] . ' ticket, ' . format_showing($booking['show_date'], $booking['show_time'], (int) $booking['cinema']),
        'amount'   => (int) $booking['ticket_price'],
        'quantity' => (int) $booking['ticket_count'],
    ]];
    foreach (booking_snacks((int) $booking['id']) as $snack) {
        $items[] = [
            'name'     => $snack['name'],
            'amount'   => (int) $snack['unit_price'],
            'quantity' => (int) $snack['quantity'],
        ];
    }
    return $items;
}

function booking_seconds_left(array $booking): int
{
    $expires = strtotime((string) $booking['expires_at']);
    return $expires === false ? 0 : max(0, $expires - time());
}

function remember_qr(string $reference, string $qr): void
{
    $_SESSION['qrph'] = [$reference => ['qr' => $qr, 'keys' => paymongo_keys_id()]];
}

function remembered_qr(string $reference): ?string
{
    $kept = $_SESSION['qrph'][$reference] ?? null;
    if (!is_array($kept) || ($kept['keys'] ?? '') !== paymongo_keys_id() || !is_string($kept['qr'] ?? null)) {
        return null;
    }
    return $kept['qr'];
}

function remember_payment_keys(): void
{
    $_SESSION['payment_keys'] = paymongo_keys_id();
}

function payment_keys_changed(): bool
{
    return isset($_SESSION['payment_keys']) && $_SESSION['payment_keys'] !== paymongo_keys_id();
}

function attach_intent(int $bookingId, string $intentId, string $column = 'paymongo_intent_id'): void
{
    if (!in_array($column, BOOKING_INTENT_COLUMNS, true)) {
        throw new InvalidArgumentException('Not a payment column: ' . $column);
    }
    db_exec(
        "UPDATE bookings SET $column = ? WHERE id = ? AND status = 'pending'",
        [$intentId, $bookingId]
    );
}

function booking_has_intent(array $booking): bool
{
    foreach (BOOKING_INTENT_COLUMNS as $column) {
        if (!empty($booking[$column])) {
            return true;
        }
    }
    return false;
}

function attach_checkout(int $bookingId, string $checkoutId): void
{
    db_exec(
        "UPDATE bookings SET paymongo_checkout_id = ? WHERE id = ? AND status = 'pending'",
        [$checkoutId, $bookingId]
    );
}

const BOOKING_SELECT = "SELECT b.*, m.title, m.slug, m.genre, m.rating, m.duration_minutes, m.poster_path,
                               u.name AS customer_name, u.email AS customer_email, u.mobile AS customer_mobile
                        FROM bookings b
                        JOIN movies m ON m.id = b.movie_id
                        JOIN users u ON u.id = b.user_id";

function find_booking_by_id(int $id): ?array
{
    return db_one(BOOKING_SELECT . ' WHERE b.id = ?', [$id]);
}

function find_booking(string $reference): ?array
{
    return db_one(BOOKING_SELECT . ' WHERE b.reference = ?', [$reference]);
}

function find_booking_by_checkout(string $checkoutId): ?array
{
    return db_one(BOOKING_SELECT . ' WHERE b.paymongo_checkout_id = ?', [$checkoutId]);
}

function find_booking_by_intent(string $intentId): ?array
{
    $where = implode(' OR ', array_map(static function (string $column): string {
        return 'b.' . $column . ' = ?';
    }, BOOKING_INTENT_COLUMNS));
    return db_one(BOOKING_SELECT . ' WHERE ' . $where, array_fill(0, count(BOOKING_INTENT_COLUMNS), $intentId));
}

function booking_paid_payment(array $booking, int $timeoutSeconds = 30): ?array
{
    foreach (BOOKING_INTENT_COLUMNS as $column) {
        $intentId = (string) ($booking[$column] ?? '');
        if ($intentId === '') {
            continue;
        }
        try {
            $payment = paymongo_paid_payment(paymongo_get_intent($intentId, $timeoutSeconds));
        } catch (PayMongoNotFoundException $e) {
            continue;
        }
        if ($payment !== null) {
            return $payment;
        }
    }
    $checkoutId = (string) ($booking['paymongo_checkout_id'] ?? '');
    if ($checkoutId !== '') {
        try {
            return paymongo_paid_payment(paymongo_get_checkout($checkoutId, $timeoutSeconds));
        } catch (PayMongoNotFoundException $e) {
        }
    }
    return null;
}

function find_user_booking(int $userId, string $reference): ?array
{
    return db_one(BOOKING_SELECT . ' WHERE b.reference = ? AND b.user_id = ?', [$reference, $userId]);
}

function booking_film_path(array $booking): string
{
    return find_movie_by_slug((string) $booking['slug']) !== null
        ? 'book.php?movie=' . rawurlencode((string) $booking['slug'])
        : 'index.php';
}

function booking_snacks(int $bookingId): array
{
    return db_all(
        'SELECT s.name, bs.quantity, bs.unit_price
         FROM booking_snacks bs JOIN snacks s ON s.id = bs.snack_id
         WHERE bs.booking_id = ? ORDER BY s.sort_order, s.id',
        [$bookingId]
    );
}

function snack_summary(array $lines): string
{
    $parts = [];
    foreach ($lines as $line) {
        $parts[] = $line['name'] . ((int) $line['quantity'] > 1 ? " \u{00D7}" . (int) $line['quantity'] : '');
    }
    return implode(', ', $parts);
}

function snack_number_label(int $number): string
{
    return str_pad((string) $number, 4, '0', STR_PAD_LEFT);
}

function next_snack_number(): int
{
    $held = db_all(
        "SELECT DISTINCT snack_number FROM bookings
         WHERE status = 'paid' AND snack_status IN ('ordered', 'preparing', 'ready') AND snack_number IS NOT NULL
         ORDER BY snack_number"
    );
    $next = 1;
    foreach ($held as $row) {
        if ((int) $row['snack_number'] !== $next) {
            break;
        }
        $next++;
    }
    return $next;
}

function mark_booking_paid(int $bookingId, string $paymentId, int $amountPaid): string
{
    if ((int) db_value("SELECT GET_LOCK('cinemax_snack_numbers', 10)") !== 1) {
        error_log('[payment] snack numbers lock was busy for booking ' . $bookingId);
    }
    try {
        return mark_booking_paid_now($bookingId, $paymentId, $amountPaid);
    } finally {
        db_value("SELECT RELEASE_LOCK('cinemax_snack_numbers')");
    }
}

function mark_booking_paid_now(int $bookingId, string $paymentId, int $amountPaid): string
{
    return (string) db_transaction(function () use ($bookingId, $paymentId, $amountPaid): string {
        $booking = db_one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
        if ($booking === null) {
            throw new RuntimeException('No booking ' . $bookingId);
        }
        if (in_array($booking['status'], ['paid', 'refunded'], true)) {
            return 'already';
        }
        if ($amountPaid !== (int) $booking['total']) {
            error_log(sprintf('[payment] booking %s paid %d but total is %d', $booking['reference'], $amountPaid, $booking['total']));
            return 'mismatch';
        }

        if ($booking['status'] !== 'pending') {
            db_exec('DELETE FROM booking_seats WHERE booking_id = ?', [$bookingId]);
            try {
                foreach (explode(', ', (string) $booking['seat_list']) as $seat) {
                    db_exec(
                        'INSERT INTO booking_seats (booking_id, movie_id, show_date, show_time, seat_code) VALUES (?, ?, ?, ?, ?)',
                        [$bookingId, $booking['movie_id'], $booking['show_date'], $booking['show_time'], $seat]
                    );
                }
            } catch (PDOException $e) {
                if (is_duplicate_key($e)) {
                    return 'seats_lost';
                }
                throw $e;
            }
        }

        $hasSnacks = (int) $booking['snacks_total'] > 0;
        db_exec(
            "UPDATE bookings
             SET status = 'paid', paid_at = NOW(), paymongo_payment_id = ?,
                 snack_status = ?, snack_number = ?
             WHERE id = ?",
            [$paymentId, $hasSnacks ? 'ordered' : null, $hasSnacks ? next_snack_number() : null, $bookingId]
        );
        return 'paid';
    });
}

function settle_booking(array $booking): string
{
    if ($booking['status'] === 'paid') {
        return 'paid';
    }
    if ($booking['status'] === 'refunded') {
        return 'refunded';
    }

    $payment = booking_paid_payment($booking);
    if ($payment === null) {
        return 'unpaid';
    }

    $result = mark_booking_paid((int) $booking['id'], $payment['id'], $payment['amount']);
    if ($result === 'seats_lost') {
        $refundId = paymongo_refund($payment['id'], $payment['amount'], 'others', 'Seats no longer available: ' . $booking['reference']);
        db_exec(
            "UPDATE bookings SET status = 'refunded', refunded_at = NOW(), paymongo_payment_id = ?,
                    paymongo_refund_id = ?, snack_status = NULL WHERE id = ?",
            [$payment['id'], $refundId, (int) $booking['id']]
        );
        return 'refunded';
    }
    if ($result === 'mismatch') {
        return 'review';
    }
    return 'paid';
}

function cancel_pending_booking(array $booking): void
{
    $changed = db_exec("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND status IN ('pending', 'expired')", [(int) $booking['id']]);
    if ($changed === 1) {
        db_exec('DELETE FROM booking_seats WHERE booking_id = ?', [(int) $booking['id']]);
        if (!empty($booking['paymongo_checkout_id'])) {
            paymongo_expire_checkout((string) $booking['paymongo_checkout_id']);
        }
    }
}

function ticket_qr_payload(array $booking): string
{
    return 'CINEMAX|' . $booking['reference'] . '|' . $booking['qr_token'];
}

function check_ticket(string $payload): array
{
    $booking = find_booking_by_qr($payload);
    if ($booking === null) {
        return ['status' => 'invalid', 'booking' => null];
    }
    switch ($booking['status']) {
        case 'paid':
            break;
        case 'refunded':
            return ['status' => 'refunded', 'booking' => $booking];
        default:
            return ['status' => 'invalid', 'booking' => null];
    }
    if ($booking['scanned_at'] !== null) {
        return ['status' => 'used', 'booking' => $booking];
    }
    if ($booking['show_date'] !== today()) {
        return ['status' => 'wrong_day', 'booking' => $booking];
    }
    return ['status' => 'valid', 'booking' => $booking];
}

function find_booking_by_qr(string $payload): ?array
{
    if (!preg_match('/^CINEMAX\|(CMX-[' . REFERENCE_ALPHABET . ']{6})\|([a-f0-9]{32})$/', trim($payload), $m)) {
        return null;
    }
    $booking = find_booking($m[1]);
    if ($booking === null || !hash_equals((string) $booking['qr_token'], $m[2])) {
        return null;
    }
    return $booking;
}

function check_snack_order(string $payload): array
{
    $booking = find_booking_by_qr($payload);
    if ($booking === null || !in_array($booking['status'], ['paid', 'refunded'], true)) {
        return ['status' => 'invalid', 'booking' => null, 'items' => []];
    }
    $items = booking_snacks((int) $booking['id']);
    if ($booking['status'] === 'refunded') {
        return ['status' => 'refunded', 'booking' => $booking, 'items' => $items];
    }
    if ($booking['snack_status'] === null || $items === []) {
        return ['status' => 'no_snacks', 'booking' => $booking, 'items' => []];
    }
    if ($booking['snack_status'] === 'sold') {
        return ['status' => 'collected', 'booking' => $booking, 'items' => $items];
    }
    if ($booking['snack_status'] !== 'ordered') {
        return ['status' => 'in_progress', 'booking' => $booking, 'items' => $items];
    }
    return ['status' => 'waiting', 'booking' => $booking, 'items' => $items];
}

function confirm_snack_order(int $bookingId): bool
{
    return db_exec(
        "UPDATE bookings SET snack_status = 'preparing', snack_scanned_at = NOW()
         WHERE id = ? AND status = 'paid' AND snack_status = 'ordered'",
        [$bookingId]
    ) === 1;
}

function admit_ticket(int $bookingId, int $staffId): bool
{
    return db_exec(
        "UPDATE bookings SET scanned_at = NOW(), scanned_by = ?
         WHERE id = ? AND status = 'paid' AND scanned_at IS NULL AND show_date = CURDATE()",
        [$staffId, $bookingId]
    ) === 1;
}

function settle_recent_bookings(int $userId): void
{
    $waiting = db_all(
        "SELECT id FROM bookings
         WHERE user_id = ? AND status IN ('pending', 'expired')
           AND (paymongo_checkout_id IS NOT NULL OR " . implode(' IS NOT NULL OR ', BOOKING_INTENT_COLUMNS) . " IS NOT NULL)
           AND created_at > NOW() - INTERVAL 1 DAY
         ORDER BY id DESC LIMIT 5",
        [$userId]
    );
    foreach ($waiting as $row) {
        $booking = find_booking_by_id((int) $row['id']);
        if ($booking === null) {
            continue;
        }
        try {
            settle_booking($booking);
        } catch (PayMongoException $e) {
            error_log('[settle] ' . $booking['reference'] . ': ' . $e->getMessage());
        }
    }
}

function user_bookings(int $userId): array
{
    release_expired_holds();
    return db_all(
        "SELECT b.*, m.title, m.poster_path
         FROM bookings b JOIN movies m ON m.id = b.movie_id
         WHERE b.user_id = ? AND b.status IN ('pending', 'paid', 'refunded')
         ORDER BY b.show_date DESC, b.show_time DESC, b.id DESC",
        [$userId]
    );
}

function dashboard_stats(): array
{
    $row = db_one(
        "SELECT COALESCE(SUM(ticket_count * ticket_price), 0) AS ticket_revenue,
                COALESCE(SUM(ticket_count), 0) AS tickets_sold,
                COALESCE(SUM(snacks_total), 0) AS snack_revenue
         FROM bookings WHERE status = 'paid'"
    );
    $snacksSold = (int) db_value(
        "SELECT COALESCE(SUM(bs.quantity), 0)
         FROM booking_snacks bs JOIN bookings b ON b.id = bs.booking_id
         WHERE b.status = 'paid'"
    );
    $refunds = db_one(
        "SELECT COUNT(*) AS refund_count, COALESCE(SUM(total), 0) AS refunded_total
         FROM bookings WHERE status = 'refunded'"
    );
    return [
        'total_revenue'  => (int) $row['ticket_revenue'] + (int) $row['snack_revenue'],
        'ticket_revenue' => (int) $row['ticket_revenue'],
        'tickets_sold'   => (int) $row['tickets_sold'],
        'snack_revenue'  => (int) $row['snack_revenue'],
        'snacks_sold'    => $snacksSold,
        'refund_count'   => (int) $refunds['refund_count'],
        'refunded_total' => (int) $refunds['refunded_total'],
    ];
}

function snack_queue(): array
{
    $queue = ['preparing' => [], 'ready' => [], 'sold' => []];
    $columns = 'b.id, b.reference, b.snack_number, b.snack_status, b.snacks_total, u.name AS customer_name
                FROM bookings b JOIN users u ON u.id = b.user_id';
    $orders = array_merge(
        db_all(
            "SELECT $columns
             WHERE b.status = 'paid' AND b.snack_status IN ('preparing', 'ready')
             ORDER BY b.show_date, b.show_time, b.id"
        ),
        db_all(
            "SELECT $columns
             WHERE b.status = 'paid' AND b.snack_status = 'sold'
             ORDER BY b.snack_sold_at DESC, b.id DESC LIMIT 20"
        )
    );
    foreach ($orders as $order) {
        $lines = booking_snacks((int) $order['id']);
        $order['items'] = snack_summary($lines);
        $order['item_count'] = array_sum(array_map(static function (array $line): int {
            return (int) $line['quantity'];
        }, $lines));
        $queue[$order['snack_status']][] = $order;
    }
    return $queue;
}

function claim_monitor_orders(): array
{
    $lists = ['preparing' => [], 'ready' => []];
    $rows = db_all(
        "SELECT reference, snack_status, snack_number FROM bookings
         WHERE status = 'paid' AND snack_status IN ('preparing', 'ready')
         ORDER BY snack_number IS NULL, snack_number, id"
    );
    foreach ($rows as $row) {
        $lists[$row['snack_status']][] = $row['snack_number'] !== null
            ? snack_number_label((int) $row['snack_number'])
            : (string) $row['reference'];
    }
    return $lists;
}

function dashboard_version(array $stats, array $queue): string
{
    $orders = [];
    foreach ($queue as $stage => $list) {
        foreach ($list as $order) {
            $orders[] = $stage . ':' . $order['id'] . ':' . $order['item_count'];
        }
    }
    return substr(hash('sha256', json_encode([$stats, $orders])), 0, 16);
}

function advance_snack_order(int $bookingId, string $from): bool
{
    if ($from === 'preparing') {
        $sql = "UPDATE bookings SET snack_status = 'ready' WHERE id = ? AND status = 'paid' AND snack_status = 'preparing'";
    } elseif ($from === 'ready') {
        $sql = "UPDATE bookings SET snack_status = 'sold', snack_sold_at = NOW() WHERE id = ? AND status = 'paid' AND snack_status = 'ready'";
    } else {
        return false;
    }
    return db_exec($sql, [$bookingId]) === 1;
}

function search_sold_snacks(string $typed): array
{
    $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $typed));
    if (strpos($code, 'CMX') === 0) {
        $code = substr($code, 3);
    }
    $code = substr($code, 0, 6);
    if ($code === '') {
        return ['orders' => [], 'other' => null];
    }

    $orders = db_all(
        "SELECT b.id, b.reference, b.snack_number, b.snacks_total, b.snack_scanned_at, b.snack_sold_at, u.name AS customer_name
         FROM bookings b JOIN users u ON u.id = b.user_id
         WHERE b.status = 'paid' AND b.snack_status = 'sold' AND b.reference LIKE ?
         ORDER BY b.snack_sold_at DESC, b.id DESC LIMIT 10",
        ['%' . $code . '%']
    );
    foreach ($orders as &$order) {
        $lines = booking_snacks((int) $order['id']);
        $order['items'] = snack_summary($lines);
        $order['item_count'] = array_sum(array_map(static function (array $line): int {
            return (int) $line['quantity'];
        }, $lines));
    }
    unset($order);

    $other = null;
    if ($orders === [] && strlen($code) === 6) {
        $booking = find_booking('CMX-' . $code);
        if ($booking !== null) {
            $stage = $booking['status'] === 'refunded' ? 'refunded'
                : ($booking['status'] !== 'paid' ? 'unpaid' : ($booking['snack_status'] ?? 'none'));
            $other = ['reference' => (string) $booking['reference'], 'stage' => $stage];
        }
    }
    return ['orders' => $orders, 'other' => $other];
}
