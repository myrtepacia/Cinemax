<?php
declare(strict_types=1);

// Staff Add Movie page: the details of a new film and an optional poster.
// Everything is checked here on the server; the page's script only helps
// with the date picker and points out empty boxes before sending.

require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('admin');

const ADD_MOVIE_MAX_POSTER_BYTES = 5 * 1024 * 1024;
const ADD_MOVIE_MIN_POSTER_SIDE = 50;
const ADD_MOVIE_MAX_POSTER_SIDE = 6000;

// The picture types a poster may be, by what the file really is, with the
// ending the saved copy gets. The name the file came with is never used.
const ADD_MOVIE_POSTER_TYPES = [
    'image/jpeg' => ['type' => IMAGETYPE_JPEG, 'ext' => 'jpg'],
    'image/png'  => ['type' => IMAGETYPE_PNG, 'ext' => 'png'],
    'image/webp' => ['type' => IMAGETYPE_WEBP, 'ext' => 'webp'],
];

/**
 * Text as one tidy line: runs of spaces, tabs and line breaks become one space.
 */
function add_movie_one_line(string $text): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

/**
 * Checks the uploaded poster without saving it. Returns
 * ['error' => null, 'file' => null] when no poster was chosen,
 * ['error' => null, 'file' => ['tmp' => ..., 'ext' => ...]] for a good one,
 * or ['error' => 'message for the form', 'file' => null].
 */
function add_movie_check_poster($upload): array
{
    $none = ['error' => null, 'file' => null];
    if ($upload === null) {
        return $none;
    }
    $unreadable = ['error' => 'The poster could not be read. Choose a JPG, PNG or WebP picture.', 'file' => null];

    // A field sent as poster[] arrives as lists, not one file
    if (!is_array($upload) || !isset($upload['error'], $upload['tmp_name']) || !is_int($upload['error']) || !is_string($upload['tmp_name'])) {
        return $unreadable;
    }

    switch ($upload['error']) {
        case UPLOAD_ERR_NO_FILE:
            return $none;
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['error' => 'The poster must be 5 MB or smaller.', 'file' => null];
        default:
            return ['error' => 'The poster did not upload properly. Please choose it again.', 'file' => null];
    }

    $tmp = $upload['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        return $unreadable;
    }

    $size = filesize($tmp);
    if ($size === false || $size === 0) {
        return $unreadable;
    }
    if ($size > ADD_MOVIE_MAX_POSTER_BYTES) {
        return ['error' => 'The poster must be 5 MB or smaller.', 'file' => null];
    }

    // What the file really is, from its contents, not from its name or
    // from the type the browser claimed
    try {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $info = getimagesize($tmp);
    } catch (Throwable $e) {
        return $unreadable;
    }
    if (!is_string($mime) || !isset(ADD_MOVIE_POSTER_TYPES[$mime]) || !is_array($info)) {
        return $unreadable;
    }

    // Both checks must agree on the kind of picture
    $kind = ADD_MOVIE_POSTER_TYPES[$mime];
    if ((int) ($info[2] ?? 0) !== $kind['type']) {
        return $unreadable;
    }

    $width = (int) ($info[0] ?? 0);
    $height = (int) ($info[1] ?? 0);
    if ($width < ADD_MOVIE_MIN_POSTER_SIDE || $height < ADD_MOVIE_MIN_POSTER_SIDE
        || $width > ADD_MOVIE_MAX_POSTER_SIDE || $height > ADD_MOVIE_MAX_POSTER_SIDE) {
        return ['error' => 'The poster must be between 50 and 6000 pixels wide and tall.', 'file' => null];
    }

    return ['error' => null, 'file' => ['tmp' => $tmp, 'ext' => $kind['ext']]];
}

/**
 * Saves a checked poster under a random name in uploads/posters and returns
 * its path from the site root, or null if it could not be saved.
 */
function add_movie_save_poster(array $file): ?string
{
    $name = bin2hex(random_bytes(16)) . '.' . $file['ext'];
    $target = APP_ROOT . '/uploads/posters/' . $name;
    try {
        $moved = move_uploaded_file($file['tmp'], $target);
    } catch (Throwable $e) {
        error_log('[' . date('c') . '] Poster upload could not be saved: ' . $e->getMessage());
        return null;
    }
    return $moved ? 'uploads/posters/' . $name : null;
}

/**
 * Deletes a saved poster again, quietly: this runs while another error is
 * already on its way up, and must not replace it.
 */
function add_movie_delete_poster(string $path): void
{
    $file = APP_ROOT . '/' . $path;
    try {
        if (is_file($file)) {
            unlink($file);
        }
    } catch (Throwable $e) {
        error_log('[' . date('c') . '] Poster could not be deleted: ' . $path);
    }
}

/**
 * ' class="invalid"' on a box whose value was refused.
 */
function add_movie_invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' class="invalid"' : '';
}

// What the form shows: blank to start with, what was typed after a problem
$old = [
    'title'    => '',
    'genre'    => '',
    'hours'    => '',
    'minutes'  => '',
    'rating'   => 'PG-13',
    'price'    => '',
    'status'   => 'now_showing',
    'run_date' => '',
];
$errors = [];
$posterWasChosen = false;

if (is_post()) {
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    if (count($_POST) === 0 && count($_FILES) === 0 && $contentLength > 0) {
        // Bigger than the server accepts at all, so PHP dropped the whole
        // form, CSRF token included. Nothing is changed; just say why.
        $errors['poster'] = 'The poster must be 5 MB or smaller.';
    } else {
        verify_csrf();

        // Read a little longer than allowed, so a long title is refused
        // rather than quietly cut short
        $title = add_movie_one_line(input_string($_POST, 'title', 1000));
        $genre = add_movie_one_line(input_string($_POST, 'genre', 1000));
        $hours = input_int($_POST, 'hours', 0, 9);
        $minutes = input_int($_POST, 'minutes', 0, 59);
        $rating = input_string($_POST, 'rating', 10);
        $price = input_int($_POST, 'price', 1, 10000);
        $status = input_string($_POST, 'status', 20);
        $runDate = input_date($_POST, 'run_date');

        $old = [
            'title'    => $title,
            'genre'    => $genre,
            'hours'    => input_string($_POST, 'hours', 10),
            'minutes'  => input_string($_POST, 'minutes', 10),
            'rating'   => in_array($rating, MOVIE_RATINGS, true) ? $rating : 'PG-13',
            'price'    => input_string($_POST, 'price', 10),
            'status'   => in_array($status, ['now_showing', 'upcoming'], true) ? $status : 'now_showing',
            'run_date' => $runDate ?? '',
        ];

        if ($title === '') {
            $errors['title'] = 'Enter the movie title.';
        } elseif (mb_strlen($title) > 150) {
            $errors['title'] = 'The title can be at most 150 characters.';
        }

        if ($genre === '') {
            $errors['genre'] = 'Enter the genre.';
        } elseif (mb_strlen($genre) > 100) {
            $errors['genre'] = 'The genre can be at most 100 characters.';
        }

        $duration = null;
        if ($hours === null || $minutes === null) {
            $errors['length'] = 'Enter the length as hours (0 to 9) and minutes (0 to 59).';
        } else {
            $duration = $hours * 60 + $minutes;
            if ($duration < 1 || $duration > 600) {
                $errors['length'] = 'The length must be at least 1 minute.';
            }
        }

        if (!in_array($rating, MOVIE_RATINGS, true)) {
            $errors['rating'] = 'Choose one of the listed ratings.';
        }

        if ($price === null) {
            $errors['price'] = "Enter a ticket price from \u{20B1}1 to \u{20B1}10,000, in whole pesos.";
        }

        if (!in_array($status, ['now_showing', 'upcoming'], true)) {
            $errors['status'] = 'Choose Now Showing or Upcoming Shows.';
        }

        $dateName = $old['status'] === 'upcoming' ? 'opening day' : 'last day showing';
        if ($runDate === null) {
            $errors['run_date'] = 'Choose the ' . $dateName . '.';
        } elseif ($runDate < today()) {
            $errors['run_date'] = 'The ' . $dateName . ' cannot be a day that has already gone by.';
        }

        $upload = $_FILES['poster'] ?? null;
        $posterWasChosen = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $poster = add_movie_check_poster($upload);
        if ($poster['error'] !== null) {
            $errors['poster'] = $poster['error'];
        }

        if (count($errors) === 0) {
            $posterPath = null;
            if ($poster['file'] !== null) {
                $posterPath = add_movie_save_poster($poster['file']);
                if ($posterPath === null) {
                    $errors['poster'] = 'The poster could not be saved. Please try again.';
                }
            }

            if (count($errors) === 0) {
                try {
                    $movieId = create_movie([
                        'title'            => $title,
                        'genre'            => $genre,
                        'duration_minutes' => $duration,
                        'rating'           => $rating,
                        'price'            => $price,
                        'status'           => $status,
                        // A film that is showing runs until its last day; an
                        // upcoming one can be booked from its opening day on
                        'opens_on'         => $status === 'upcoming' ? $runDate : null,
                        'ends_on'          => $status === 'now_showing' ? $runDate : null,
                        'poster_path'      => $posterPath,
                    ]);
                } catch (Throwable $e) {
                    if ($posterPath !== null) {
                        add_movie_delete_poster($posterPath);
                    }
                    throw $e;
                }

                // It gets the first free times in the cinemas, if any
                $shows = [];
                foreach (movie_schedule($movieId) as $time => $cinema) {
                    $shows[] = format_time((string) $time) . ' (' . cinema_label($cinema) . ')';
                }
                if ($shows !== []) {
                    $last = array_pop($shows);
                    $list = $shows !== [] ? implode(', ', $shows) . ' and ' . $last : $last;
                    flash('success', $title . ' was added. It shows every day at ' . $list . '.');
                } else {
                    flash('notice', $title . ' was added, but both cinemas are full on the days it runs, so it has no showtimes yet.');
                }
                redirect('admin/movies.php');
            }
        }
    }
}

$upcoming = $old['status'] === 'upcoming';
$runDateText = $old['run_date'] !== ''
    ? (new DateTimeImmutable($old['run_date']))->format('D, j M Y')
    : 'Choose a date';

render_head('Add Movie', ['assets/css/admin.css']);
render_header(['staff' => true, 'current' => 'add-movie']);
?>
  <div class="admin-layout">
    <?php render_staff_sidebar('add-movie'); ?>

    <div class="admin-head">
      <h1>Add a Movie</h1>
      <p class="admin-intro">Fill in the details, choose a poster, and press Add movie.</p>
    </div>

    <main class="admin-page">
      <?php render_flashes(); ?>

      <section class="panel">

        <h2>Movie details</h2>
        <p class="panel-note">Every field is needed except the poster.</p>

<?php if (count($errors) > 0): ?>
        <div class="form-message form-message-error" id="add-errors" role="alert">
          <p>The movie was not added. Please fix the following:</p>
          <ul class="form-error-list">
<?php foreach ($errors as $message): ?>
            <li><?= e($message) ?></li>
<?php endforeach; ?>
<?php if ($posterWasChosen && !isset($errors['poster'])): ?>
            <li>Choose the poster again: it is not kept when the form has a problem.</li>
<?php endif; ?>
          </ul>
        </div>
<?php endif; ?>

        <p class="form-message hidden" id="add-message" role="status"></p>

        <form id="add-form" method="post" action="<?= e(url('admin/add-movie.php')) ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>

          <div class="add-grid">

            <div class="form-field">
              <label for="new-title">Movie title</label>
              <input id="new-title" name="title" type="text" maxlength="150" placeholder="The Reckoning" required
                     value="<?= e($old['title']) ?>"<?= add_movie_invalid($errors, 'title') ?>>
            </div>

            <div class="form-field">
              <label for="new-genre">Genre</label>
              <input id="new-genre" name="genre" type="text" maxlength="100" placeholder="Action, Thriller" required
                     value="<?= e($old['genre']) ?>"<?= add_movie_invalid($errors, 'genre') ?>>
            </div>

            <div class="form-field">
              <label for="new-hours">Length</label>
              <div class="length-pair">
                <span class="length-part">
                  <input id="new-hours" name="hours" type="number" min="0" max="9" step="1" placeholder="1" required
                         value="<?= e($old['hours']) ?>"<?= add_movie_invalid($errors, 'length') ?>>
                  <span class="length-unit">hrs</span>
                </span>
                <span class="length-part">
                  <input id="new-minutes" name="minutes" type="number" min="0" max="59" step="1" placeholder="58" required
                         value="<?= e($old['minutes']) ?>"<?= add_movie_invalid($errors, 'length') ?>>
                  <span class="length-unit">min</span>
                </span>
              </div>
            </div>

            <div class="form-field">
              <label for="new-rating">Rating</label>
              <select id="new-rating" name="rating"<?= add_movie_invalid($errors, 'rating') ?>>
<?php foreach (MOVIE_RATINGS as $rating): ?>
                <option value="<?= e($rating) ?>"<?= $rating === $old['rating'] ? ' selected' : '' ?>><?= e($rating) ?></option>
<?php endforeach; ?>
              </select>
            </div>

            <div class="form-field">
              <label for="new-price">Ticket price in pesos</label>
              <input id="new-price" name="price" type="number" min="1" max="10000" step="1" placeholder="220" required
                     value="<?= e($old['price']) ?>"<?= add_movie_invalid($errors, 'price') ?>>
            </div>

            <div class="form-field">
              <label for="new-status">Where it goes</label>
              <select id="new-status" name="status"<?= add_movie_invalid($errors, 'status') ?>>
                <option value="now_showing"<?= $upcoming ? '' : ' selected' ?>>Now Showing</option>
                <option value="upcoming"<?= $upcoming ? ' selected' : '' ?>>Upcoming Shows</option>
              </select>
            </div>

            <div class="form-field">
              <label id="run-date-label" for="run-date-button"><?= e($upcoming ? 'Opening day' : 'Last day showing') ?></label>

              <div class="date-field" id="run-date-field">

                <button class="date-button" type="button" id="run-date-button"
                        aria-haspopup="true" aria-expanded="false">
                  <span id="run-date-text"><?= e($runDateText) ?></span>
                  <span class="date-arrow">&#9662;</span>
                </button>

                <div class="calendar" id="run-calendar" role="group" aria-labelledby="run-date-label" hidden>

                  <div class="calendar-head">
                    <button class="calendar-nav" type="button" id="run-calendar-back" aria-label="Previous month">&#8249;</button>
                    <p class="calendar-month" id="run-calendar-month">Month</p>
                    <button class="calendar-nav" type="button" id="run-calendar-next" aria-label="Next month">&#8250;</button>
                  </div>

                  <div class="calendar-grid calendar-day-names">
                    <span>Sun</span>
                    <span>Mon</span>
                    <span>Tue</span>
                    <span>Wed</span>
                    <span>Thu</span>
                    <span>Fri</span>
                    <span>Sat</span>
                  </div>

                  <div class="calendar-grid" id="run-calendar-dates"></div>

                </div>

              </div>

              <input type="hidden" id="new-run-date" name="run_date" value="<?= e($old['run_date']) ?>">
            </div>

            <div class="form-field">
              <label for="new-poster">Poster image</label>
              <input id="new-poster" name="poster" type="file" accept="image/jpeg,image/png,image/webp" data-max-bytes="<?= e((string) ADD_MOVIE_MAX_POSTER_BYTES) ?>"<?= add_movie_invalid($errors, 'poster') ?>>
            </div>

          </div>

          <button class="button button-red" type="submit" id="add-button">Add movie</button>

        </form>

      </section>

    </main>

  </div>
<?php render_footer(['js' => ['assets/js/add-movie.js']]);
