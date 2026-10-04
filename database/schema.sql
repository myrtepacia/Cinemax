-- Cinemax database schema (MariaDB 10.4+ / MySQL 8+)
--
-- Every amount of money is a whole number of pesos. PayMongo counts in
-- centavos, so the PayMongo code multiplies by 100 on the way out.

SET NAMES utf8mb4;
SET time_zone = '+08:00';

-- People who can sign in. The role decides what they may do and is never
-- taken from a form: customers sign up as 'customer', staff are made here.
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  mobile        VARCHAR(20)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer', 'admin', 'scanner') NOT NULL DEFAULT 'customer',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed sign-ins, kept so that guessing passwords is slowed to a crawl.
-- Also used to rate-limit sign-ups and ticket scans per address.
CREATE TABLE login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket       VARCHAR(40)  NOT NULL,           -- 'signin', 'signup', 'scan'
  identifier   VARCHAR(190) NOT NULL,           -- email, or '' when by address only
  ip_address   VARCHAR(45)  NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_lookup (bucket, identifier, attempted_at),
  KEY idx_attempts_ip (bucket, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The films. A 'now_showing' film runs until ends_on. An 'upcoming' film
-- opens on opens_on and can be booked from that day on.
CREATE TABLE movies (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug             VARCHAR(120) NOT NULL,
  title            VARCHAR(150) NOT NULL,
  genre            VARCHAR(100) NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  rating           ENUM('G', 'PG', 'PG-13', 'R-13', 'R-16', 'R-18') NOT NULL,
  price            INT UNSIGNED NOT NULL,        -- pesos per ticket
  status           ENUM('now_showing', 'upcoming') NOT NULL,
  opens_on         DATE NULL,
  ends_on          DATE NULL,
  poster_path      VARCHAR(255) NULL,            -- relative to the site root
  is_active        TINYINT(1) NOT NULL DEFAULT 1, -- 0 once taken off the listings
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_movies_slug (slug),
  KEY idx_movies_listing (is_active, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The times each film plays every day it is showing, and in which of the
-- cinemas (1 or 2).
CREATE TABLE showtimes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  movie_id   INT UNSIGNED NOT NULL,
  cinema     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  start_time TIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_showtimes (movie_id, start_time),
  CONSTRAINT fk_showtimes_movie FOREIGN KEY (movie_id) REFERENCES movies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Food and drink sold with a ticket.
CREATE TABLE snacks (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(100) NOT NULL,
  detail     VARCHAR(150) NOT NULL DEFAULT '',
  category   ENUM('Popcorn', 'Drinks', 'Candy', 'Combos') NOT NULL,
  price      INT UNSIGNED NOT NULL,              -- pesos
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One booking: some seats for one showing, maybe some snacks, one payment.
--
-- status:
--   pending   seats are held while the customer pays (until expires_at)
--   paid      money received; this is a real ticket
--   expired   the hold ran out before payment
--   cancelled the customer backed out at PayMongo
--   refunded  the money was sent back; the seats are free again
CREATE TABLE bookings (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference            CHAR(10) NOT NULL,       -- CMX-XXXXXX
  qr_token             CHAR(32) NOT NULL,       -- secret half of the QR code
  user_id              INT UNSIGNED NOT NULL,
  movie_id             INT UNSIGNED NOT NULL,
  show_date            DATE NOT NULL,
  show_time            TIME NOT NULL,
  cinema               TINYINT UNSIGNED NOT NULL DEFAULT 1, -- the room, printed on the ticket
  ticket_count         TINYINT UNSIGNED NOT NULL,
  seat_list            VARCHAR(80) NOT NULL,     -- 'C5, C6', kept after the seats are freed
  ticket_price         INT UNSIGNED NOT NULL,   -- pesos, fixed at booking time
  snacks_total         INT UNSIGNED NOT NULL DEFAULT 0,
  total                INT UNSIGNED NOT NULL,
  status               ENUM('pending', 'paid', 'expired', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
  -- The snack order: 'ordered' once paid, until staff scan the ticket at the
  -- snack counter; then 'preparing', 'ready', and 'sold' when picked up.
  -- NULL when the booking has no snacks.
  snack_status         ENUM('ordered', 'preparing', 'ready', 'sold') NULL,
  -- The snack order's number (#001), given when it is paid: the lowest one
  -- not held by another order still waiting to be picked up, so numbers
  -- are used again once their orders are collected.
  snack_number         SMALLINT UNSIGNED NULL,
  snack_scanned_at     DATETIME NULL,           -- confirmed at the snack counter
  snack_sold_at        DATETIME NULL,           -- picked up
<<<<<<< HEAD
  paymongo_checkout_id VARCHAR(64) NULL,       -- PayMongo's checkout page (cs_...)
  paymongo_intent_id   VARCHAR(64) NULL,       -- or a QR Ph payment shown on pay.php (pi_...)
=======
  paymongo_checkout_id VARCHAR(64) NULL,
>>>>>>> 7c9a0f9974903781a2848a4882ebb68cc0b70671
  paymongo_payment_id  VARCHAR(64) NULL,
  paymongo_refund_id   VARCHAR(64) NULL,
  expires_at           DATETIME NOT NULL,
  paid_at              DATETIME NULL,
  scanned_at           DATETIME NULL,
  scanned_by           INT UNSIGNED NULL,
  refunded_at          DATETIME NULL,
  refunded_by          INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bookings_reference (reference),
  UNIQUE KEY uq_bookings_checkout (paymongo_checkout_id),
  UNIQUE KEY uq_bookings_intent (paymongo_intent_id),
  KEY idx_bookings_user (user_id, created_at),
  KEY idx_bookings_status (status, expires_at),
  KEY idx_bookings_snacks (status, snack_status),
  CONSTRAINT fk_bookings_user  FOREIGN KEY (user_id)  REFERENCES users (id),
  CONSTRAINT fk_bookings_movie FOREIGN KEY (movie_id) REFERENCES movies (id),
  CONSTRAINT fk_bookings_scanned_by  FOREIGN KEY (scanned_by)  REFERENCES users (id),
  CONSTRAINT fk_bookings_refunded_by FOREIGN KEY (refunded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The seats a booking holds. The unique key is what makes double-booking
-- impossible: two customers racing for C5 at the same showing cannot both
-- insert it. Rows are deleted when a booking expires, is cancelled or is
-- refunded, which frees the seat again.
CREATE TABLE booking_seats (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  movie_id   INT UNSIGNED NOT NULL,
  show_date  DATE NOT NULL,
  show_time  TIME NOT NULL,
  seat_code  VARCHAR(3) NOT NULL,              -- 'A1' .. 'G10'
  PRIMARY KEY (id),
  UNIQUE KEY uq_seat_per_showing (movie_id, show_date, show_time, seat_code),
  KEY idx_booking_seats_booking (booking_id),
  CONSTRAINT fk_booking_seats_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snacks on a booking, priced as they were when it was made.
CREATE TABLE booking_snacks (
  booking_id INT UNSIGNED NOT NULL,
  snack_id   INT UNSIGNED NOT NULL,
  quantity   TINYINT UNSIGNED NOT NULL,
  unit_price INT UNSIGNED NOT NULL,
  PRIMARY KEY (booking_id, snack_id),
  CONSTRAINT fk_booking_snacks_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_snacks_snack   FOREIGN KEY (snack_id)   REFERENCES snacks (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PayMongo webhook events already handled, so a replayed or repeated
-- delivery is ignored instead of being applied twice.
CREATE TABLE webhook_events (
  event_id    VARCHAR(64) NOT NULL,
  event_type  VARCHAR(80) NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
