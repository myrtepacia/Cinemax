SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('customer','admin','scanner') NOT NULL DEFAULT 'customer',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=248 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `users` WRITE;
INSERT INTO `users` VALUES (1,'Cinemax Admin','admin@gmail.com',NULL,'$argon2id$v=19$m=65536,t=4,p=1$eUdrZHFzVWg2NTE1WUkzcg$zDo9ZNprzS9DY82fcQ1ll7ln13YKxPJU3HpSk9mh6Gk','admin','2026-09-28 01:06:42','2026-10-06 21:13:59'),(2,'Ticket Scanner','scanner@gmail.com',NULL,'$argon2id$v=19$m=65536,t=4,p=1$cElQWXJZOHpnQmJ5VnV6dA$wRw2G5rfEmpl1AVrpsbxB5ehIYtb0GUSB7VnYGPy8A4','scanner','2026-09-28 01:06:42','2026-10-02 20:42:24');
UNLOCK TABLES;

DROP TABLE IF EXISTS `movies`;
CREATE TABLE `movies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(120) NOT NULL,
  `title` varchar(150) NOT NULL,
  `genre` varchar(100) NOT NULL,
  `duration_minutes` smallint(5) unsigned NOT NULL,
  `rating` enum('G','PG','PG-13','R-13','R-16','R-18') NOT NULL,
  `price` int(10) unsigned NOT NULL,
  `status` enum('now_showing','upcoming') NOT NULL,
  `opens_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `poster_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_movies_slug` (`slug`),
  KEY `idx_movies_listing` (`is_active`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `movies` WRITE;
INSERT INTO `movies` VALUES (1,'eternal-sunshine','Eternal Sunshine of the Spotless Mind','Romance, Drama',108,'PG-13',220,'now_showing',NULL,NULL,'assets/img/posters/eternal-sunshine.jpg',1,'2026-09-28 01:06:42'),(2,'pieces-of-us','Pieces of Us','Comedy, Romance, Drama',112,'PG-13',220,'now_showing',NULL,NULL,'assets/img/posters/Pieces-of-us.jpg',1,'2026-09-28 01:06:42'),(3,'the-reckoning','The Reckoning','Action, Thriller',122,'R-16',220,'now_showing',NULL,NULL,'assets/img/posters/The-Reckoning.jpg',1,'2026-09-28 01:06:42'),(4,'movie-test','Movie Test','',0,'R-16',1,'now_showing',NULL,NULL,'',1,'2026-09-28 01:06:42'),(5,'hush','Hush','Horror, Thriller',82,'R-16',220,'upcoming','2026-10-09',NULL,'assets/img/posters/Hush.jpg',1,'2026-09-28 01:06:42'),(6,'broken-of-love','Broken [of] Love','Romance, Drama',105,'PG-13',220,'upcoming','2026-10-17',NULL,'assets/img/posters/Broken-of-Love.jpg',1,'2026-09-28 01:06:42'),(7,'maleficent','Maleficent','Dark Fantasy, Adventure, Drama',153,'PG',220,'upcoming','2026-11-18',NULL,'assets/img/posters/Maleficent.jpg',1,'2026-10-02 13:34:15');
UNLOCK TABLES;

DROP TABLE IF EXISTS `showtimes`;
CREATE TABLE `showtimes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `movie_id` int(10) unsigned NOT NULL,
  `cinema` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `start_time` time NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_showtimes` (`movie_id`,`start_time`),
  CONSTRAINT `fk_showtimes_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=153 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `showtimes` WRITE;
INSERT INTO `showtimes` VALUES (36,2,1,'12:00:00'),(37,2,1,'16:05:00'),(38,2,1,'20:10:00'),(39,5,1,'22:15:00'),(43,3,2,'10:00:00'),(44,3,2,'14:25:00'),(45,3,2,'18:50:00'),(46,4,2,'12:15:00'),(47,4,2,'16:40:00'),(48,4,2,'21:05:00'),(146,1,1,'10:00:00'),(147,1,1,'14:05:00'),(148,1,1,'18:10:00'),(149,6,2,'12:25:00'),(150,6,2,'16:50:00'),(151,6,2,'21:15:00');
UNLOCK TABLES;

DROP TABLE IF EXISTS `snacks`;
CREATE TABLE `snacks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `detail` varchar(150) NOT NULL DEFAULT '',
  `category` enum('Popcorn','Drinks','Candy','Combos') NOT NULL,
  `price` int(10) unsigned NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `snacks` WRITE;
INSERT INTO `snacks` VALUES (1,'Test Snack','','Popcorn',1,1,1),(2,'Popcorn, Large','Cheese or Caramel, 85 oz','Popcorn',160,2,1),(3,'Soda, Regular','16 oz','Drinks',70,3,1),(4,'Soda, Large','22 oz, free refill','Drinks',95,4,1),(5,'Chocolate Bar','90 g','Candy',60,5,1),(6,'Gummy Candy','Assorted, 100 g','Candy',55,6,1),(7,'Solo Combo','Regular popcorn and regular soda','Combos',180,7,1),(8,'Barkada Combo','2 large popcorn and 2 large sodas','Combos',480,8,1);
UNLOCK TABLES;

DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference` char(10) NOT NULL,
  `qr_token` char(32) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `movie_id` int(10) unsigned NOT NULL,
  `show_date` date NOT NULL,
  `show_time` time NOT NULL,
  `cinema` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `ticket_count` tinyint(3) unsigned NOT NULL,
  `seat_list` varchar(80) NOT NULL,
  `ticket_price` int(10) unsigned NOT NULL,
  `snacks_total` int(10) unsigned NOT NULL DEFAULT 0,
  `total` int(10) unsigned NOT NULL,
  `status` enum('pending','paid','expired','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `snack_status` enum('ordered','preparing','ready','sold') DEFAULT NULL,
  `snack_number` smallint(5) unsigned DEFAULT NULL,
  `snack_scanned_at` datetime DEFAULT NULL,
  `snack_sold_at` datetime DEFAULT NULL,
  `paymongo_checkout_id` varchar(64) DEFAULT NULL,
  `paymongo_intent_id` varchar(64) DEFAULT NULL,
  `paymongo_card_intent_id` varchar(64) DEFAULT NULL,
  `paymongo_wallet_intent_id` varchar(64) DEFAULT NULL,
  `paymongo_payment_id` varchar(64) DEFAULT NULL,
  `paymongo_refund_id` varchar(64) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `scanned_at` datetime DEFAULT NULL,
  `scanned_by` int(10) unsigned DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `refunded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bookings_reference` (`reference`),
  UNIQUE KEY `uq_bookings_checkout` (`paymongo_checkout_id`),
  UNIQUE KEY `uq_bookings_intent` (`paymongo_intent_id`),
  UNIQUE KEY `uq_bookings_card_intent` (`paymongo_card_intent_id`),
  UNIQUE KEY `uq_bookings_wallet_intent` (`paymongo_wallet_intent_id`),
  KEY `idx_bookings_user` (`user_id`,`created_at`),
  KEY `idx_bookings_status` (`status`,`expires_at`),
  KEY `idx_bookings_snacks` (`status`,`snack_status`),
  KEY `fk_bookings_movie` (`movie_id`),
  KEY `fk_bookings_scanned_by` (`scanned_by`),
  KEY `fk_bookings_refunded_by` (`refunded_by`),
  CONSTRAINT `fk_bookings_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`),
  CONSTRAINT `fk_bookings_refunded_by` FOREIGN KEY (`refunded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_bookings_scanned_by` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=318 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `bookings` WRITE;
UNLOCK TABLES;

DROP TABLE IF EXISTS `booking_seats`;
CREATE TABLE `booking_seats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `movie_id` int(10) unsigned NOT NULL,
  `show_date` date NOT NULL,
  `show_time` time NOT NULL,
  `seat_code` varchar(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seat_per_showing` (`movie_id`,`show_date`,`show_time`,`seat_code`),
  KEY `idx_booking_seats_booking` (`booking_id`),
  CONSTRAINT `fk_booking_seats_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=341 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `booking_seats` WRITE;
UNLOCK TABLES;

DROP TABLE IF EXISTS `booking_snacks`;
CREATE TABLE `booking_snacks` (
  `booking_id` int(10) unsigned NOT NULL,
  `snack_id` int(10) unsigned NOT NULL,
  `quantity` tinyint(3) unsigned NOT NULL,
  `unit_price` int(10) unsigned NOT NULL,
  PRIMARY KEY (`booking_id`,`snack_id`),
  KEY `fk_booking_snacks_snack` (`snack_id`),
  CONSTRAINT `fk_booking_snacks_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booking_snacks_snack` FOREIGN KEY (`snack_id`) REFERENCES `snacks` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `booking_snacks` WRITE;
UNLOCK TABLES;

DROP TABLE IF EXISTS `webhook_events`;
CREATE TABLE `webhook_events` (
  `event_id` varchar(64) NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `webhook_events` WRITE;
UNLOCK TABLES;

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bucket` varchar(40) NOT NULL,
  `identifier` varchar(190) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attempts_lookup` (`bucket`,`identifier`,`attempted_at`),
  KEY `idx_attempts_ip` (`bucket`,`ip_address`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3125 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
