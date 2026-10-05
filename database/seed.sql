-- Starting data. Staff accounts (Argon2id hashes only, no plain passwords):
--   admin@gmail.com          role admin
--   scanner@gmail.com        role scanner

SET NAMES utf8mb4;
SET time_zone = '+08:00';

INSERT INTO users (name, email, password_hash, role) VALUES
  ('Cinemax Admin',  'admin@gmail.com',
   '$argon2id$v=19$m=65536,t=4,p=1$eUdrZHFzVWg2NTE1WUkzcg$zDo9ZNprzS9DY82fcQ1ll7ln13YKxPJU3HpSk9mh6Gk',
   'admin'),
  ('Ticket Scanner', 'scanner@gmail.com',
   '$argon2id$v=19$m=65536,t=4,p=1$cElQWXJZOHpnQmJ5VnV6dA$wRw2G5rfEmpl1AVrpsbxB5ehIYtb0GUSB7VnYGPy8A4',
   'scanner');

-- No end dates. Upcoming films only have an opening day.
INSERT INTO movies (slug, title, genre, duration_minutes, rating, price, status, opens_on, ends_on, poster_path) VALUES
  ('eternal-sunshine', 'Eternal Sunshine of the Spotless Mind', 'Romance, Drama', 108, 'PG-13', 220,
   'now_showing', NULL, NULL, 'assets/img/posters/eternal-sunshine.jpg'),
  ('pieces-of-us', 'Pieces of Us', 'Romance, Drama', 112, 'PG-13', 220,
   'now_showing', NULL, NULL, 'assets/img/posters/Pieces-of-us.jpg'),
  ('the-reckoning', 'The Reckoning', 'Action, Thriller', 118, 'R-16', 250,
   'now_showing', NULL, NULL, 'assets/img/posters/The-Reckoning.jpg'),
  ('hush', 'Hush', 'Horror, Thriller', 82, 'R-16', 220,
   'upcoming', '2026-10-09', NULL, 'assets/img/posters/Hush.jpg'),
  ('broken-of-love', 'Broken [of] Love', 'Romance, Drama', 105, 'PG-13', 220,
   'upcoming', '2026-10-17', NULL, 'assets/img/posters/Broken-of-Love.jpg');

-- Films take turns in each cinema, with 10 minutes of cleaning after each
-- show. No two films share a time in the same cinema.
--
--   Cinema 1                                  Cinema 2
--   10:00 AM  Eternal Sunshine  - 11:48 AM    10:00 AM  Broken [of] Love (from Oct 17) - 11:45 AM
--   12:00 PM  Pieces of Us      -  1:52 PM    12:15 PM  The Reckoning  -  2:13 PM
--    2:05 PM  Eternal Sunshine  -  3:53 PM     2:25 PM  Broken [of] Love (from Oct 17) -  4:10 PM
--    4:05 PM  Pieces of Us      -  5:57 PM     4:40 PM  The Reckoning  -  6:38 PM
--    6:10 PM  Eternal Sunshine  -  7:58 PM     6:50 PM  Broken [of] Love (from Oct 17) -  8:35 PM
--    8:10 PM  Pieces of Us      - 10:02 PM     9:05 PM  The Reckoning  - 11:03 PM
--   10:15 PM  Hush (from Oct 9) - 11:37 PM
INSERT INTO showtimes (movie_id, cinema, start_time)
SELECT m.id, t.cinema, t.start_time
FROM movies m
JOIN (
  SELECT 'eternal-sunshine' AS slug, 1 AS cinema, '10:00:00' AS start_time UNION ALL
  SELECT 'eternal-sunshine', 1, '14:05:00' UNION ALL
  SELECT 'eternal-sunshine', 1, '18:10:00' UNION ALL
  SELECT 'pieces-of-us',     1, '12:00:00' UNION ALL
  SELECT 'pieces-of-us',     1, '16:05:00' UNION ALL
  SELECT 'pieces-of-us',     1, '20:10:00' UNION ALL
  SELECT 'hush',             1, '22:15:00' UNION ALL
  SELECT 'broken-of-love',   2, '10:00:00' UNION ALL
  SELECT 'broken-of-love',   2, '14:25:00' UNION ALL
  SELECT 'broken-of-love',   2, '18:50:00' UNION ALL
  SELECT 'the-reckoning',    2, '12:15:00' UNION ALL
  SELECT 'the-reckoning',    2, '16:40:00' UNION ALL
  SELECT 'the-reckoning',    2, '21:05:00'
) t ON t.slug = m.slug;

INSERT INTO snacks (name, detail, category, price, sort_order) VALUES
  ('Popcorn, Regular', 'Salted, 64 oz',                    'Popcorn', 120, 1),
  ('Popcorn, Large',   'Cheese or Caramel, 85 oz',          'Popcorn', 160, 2),
  ('Soda, Regular',    '16 oz',                             'Drinks',   70, 3),
  ('Soda, Large',      '22 oz, free refill',                'Drinks',   95, 4),
  ('Chocolate Bar',    '90 g',                              'Candy',    60, 5),
  ('Gummy Candy',      'Assorted, 100 g',                   'Candy',    55, 6),
  ('Solo Combo',       'Regular popcorn and regular soda',  'Combos',  180, 7),
  ('Barkada Combo',    '2 large popcorn and 2 large sodas', 'Combos',  480, 8);
