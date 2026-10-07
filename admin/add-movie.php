<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('admin');

const ADD_MOVIE_MAX_POSTER_BYTES = 5 * 1024 * 1024;
const ADD_MOVIE_MIN_POSTER_SIDE = 50;
const ADD_MOVIE_MAX_POSTER_SIDE = 6000;

const ADD_MOVIE_POSTER_WIDTH = 900;
const ADD_MOVIE_POSTER_HEIGHT = 1200;

const ADD_MOVIE_POSTER_DIR = 'assets/img/posters';

const ADD_MOVIE_POSTER_TYPES = [
    'image/jpeg' => ['type' => IMAGETYPE_JPEG, 'ext' => 'jpg'],
    'image/png'  => ['type' => IMAGETYPE_PNG, 'ext' => 'png'],
    'image/webp' => ['type' => IMAGETYPE_WEBP, 'ext' => 'webp'],
];

function add_movie_one_line(string $text): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

function add_movie_check_poster($upload): array
{
    $none = ['error' => null, 'file' => null];
    if ($upload === null) {
        return $none;
    }
    $unreadable = ['error' => 'The poster could not be read. Choose a JPG, PNG or WebP picture.', 'file' => null];

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

    try {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $info = getimagesize($tmp);
    } catch (Throwable $e) {
        return $unreadable;
    }
    if (!is_string($mime) || !isset(ADD_MOVIE_POSTER_TYPES[$mime]) || !is_array($info)) {
        return $unreadable;
    }

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

function add_movie_can_fit_posters(): bool
{
    return function_exists('imagecreatetruecolor');
}

function add_movie_poster_name(string $title, string $ext): string
{
    $base = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-');
    $base = substr($base !== '' ? $base : 'movie', 0, 100);
    $name = $base . '.' . $ext;
    for ($n = 2; is_file(APP_ROOT . '/' . ADD_MOVIE_POSTER_DIR . '/' . $name); $n++) {
        $name = $base . '-' . $n . '.' . $ext;
    }
    return $name;
}

function add_movie_save_poster(array $file, string $title): ?string
{
    $fit = add_movie_can_fit_posters();
    $path = ADD_MOVIE_POSTER_DIR . '/' . add_movie_poster_name($title, $fit ? 'jpg' : $file['ext']);
    $target = APP_ROOT . '/' . $path;
    try {
        $saved = $fit
            ? add_movie_fit_poster($file['tmp'], $file['ext'], $target)
            : move_uploaded_file($file['tmp'], $target);
    } catch (Throwable $e) {
        error_log('[' . date('c') . '] Poster upload could not be saved: ' . $e->getMessage());
        add_movie_delete_poster($path);
        return null;
    }
    return $saved ? $path : null;
}

function add_movie_fit_poster(string $source, string $ext, string $target): bool
{
    switch ($ext) {
        case 'png':
            $picture = imagecreatefrompng($source);
            break;
        case 'webp':
            $picture = imagecreatefromwebp($source);
            break;
        default:
            $picture = imagecreatefromjpeg($source);
    }
    if ($picture === false) {
        return false;
    }

    if ($ext === 'jpg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($source);
        $turn = [3 => 180, 6 => 270, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($turn !== 0) {
            $turned = imagerotate($picture, $turn, 0);
            if ($turned !== false) {
                imagedestroy($picture);
                $picture = $turned;
            }
        }
    }

    $width = imagesx($picture);
    $height = imagesy($picture);
    if ($width * ADD_MOVIE_POSTER_HEIGHT > $height * ADD_MOVIE_POSTER_WIDTH) {
        $cropHeight = $height;
        $cropWidth = min($width, (int) round($height * ADD_MOVIE_POSTER_WIDTH / ADD_MOVIE_POSTER_HEIGHT));
    } else {
        $cropWidth = $width;
        $cropHeight = min($height, (int) round($width * ADD_MOVIE_POSTER_HEIGHT / ADD_MOVIE_POSTER_WIDTH));
    }

    $poster = imagecreatetruecolor(ADD_MOVIE_POSTER_WIDTH, ADD_MOVIE_POSTER_HEIGHT);
    imagefill($poster, 0, 0, imagecolorallocate($poster, 255, 255, 255));
    imagecopyresampled(
        $poster, $picture,
        0, 0, intdiv($width - $cropWidth, 2), intdiv($height - $cropHeight, 2),
        ADD_MOVIE_POSTER_WIDTH, ADD_MOVIE_POSTER_HEIGHT, $cropWidth, $cropHeight
    );
    imagedestroy($picture);

    $saved = imagejpeg($poster, $target, 85);
    imagedestroy($poster);
    return $saved;
}

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

function add_movie_invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' class="invalid"' : '';
}

function add_movie_date_text(string $opensOn): string
{
    return $opensOn === '' ? 'Choose the opening day' : format_day($opensOn);
}

$old = [
    'title'    => '',
    'genre'    => '',
    'hours'    => '',
    'minutes'  => '',
    'rating'   => 'PG-13',
    'price'    => '',
    'opens_on' => '',
    'cinema'   => '',
];
$errors = [];
$posterWasChosen = false;

if (is_post()) {
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    if (count($_POST) === 0 && count($_FILES) === 0 && $contentLength > 0) {
        $errors['poster'] = 'The poster must be 5 MB or smaller.';
    } else {
        verify_csrf();

        $title = add_movie_one_line(input_string($_POST, 'title', 1000));
        $genre = add_movie_one_line(input_string($_POST, 'genre', 1000));
        $hours = input_int($_POST, 'hours', 0, 9);
        $minutes = input_int($_POST, 'minutes', 0, 59);
        $rating = input_string($_POST, 'rating', 10);
        $price = input_int($_POST, 'price', 1, 10000);
        $opensOn = input_date($_POST, 'opens_on');
        $cinema = input_int($_POST, 'cinema', 1, CINEMA_COUNT);

        $old = [
            'title'    => $title,
            'genre'    => $genre,
            'hours'    => input_string($_POST, 'hours', 10),
            'minutes'  => input_string($_POST, 'minutes', 10),
            'rating'   => in_array($rating, MOVIE_RATINGS, true) ? $rating : 'PG-13',
            'price'    => input_string($_POST, 'price', 10),
            'opens_on' => $opensOn ?? '',
            'cinema'   => $cinema !== null ? (string) $cinema : '',
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

        if ($opensOn === null) {
            $errors['opens_on'] = 'Choose the opening day.';
        } elseif ($opensOn < today()) {
            $errors['opens_on'] = 'The opening day cannot be a day that has already gone by.';
        }

        if ($cinema === null) {
            $errors['cinema'] = 'Choose the cinema it shows in.';
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
                $posterPath = add_movie_save_poster($poster['file'], $title);
                if ($posterPath === null) {
                    $errors['poster'] = 'The poster could not be saved. Please try again.';
                }
            }

            if (count($errors) === 0) {
                try {
                    create_movie([
                        'title'            => $title,
                        'genre'            => $genre,
                        'duration_minutes' => $duration,
                        'rating'           => $rating,
                        'price'            => $price,
                        'status'           => 'upcoming',
                        'opens_on'         => $opensOn,
                        'ends_on'          => null,
                        'poster_path'      => $posterPath,
                        'cinema'           => $cinema,
                    ]);
                    redirect('admin/movies.php');
                } catch (ScheduleException $e) {
                    if ($posterPath !== null) {
                        add_movie_delete_poster($posterPath);
                    }
                    $errors['cinema'] = $e->getMessage();
                } catch (Throwable $e) {
                    if ($posterPath !== null) {
                        add_movie_delete_poster($posterPath);
                    }
                    throw $e;
                }
            }
        }
    }
}

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
              <label id="run-date-label" for="run-date-button">Opening day</label>

              <div class="date-field" id="run-date-field">

                <button class="date-button<?= isset($errors['opens_on']) ? ' invalid' : '' ?>" type="button" id="run-date-button"
                        aria-haspopup="true" aria-expanded="false">
                  <span id="run-date-text"><?= e(add_movie_date_text($old['opens_on'])) ?></span>
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

                  <p class="calendar-hint" id="run-calendar-hint">Pick the opening day</p>

                </div>

              </div>

              <input type="hidden" id="new-opens-on" name="opens_on" value="<?= e($old['opens_on']) ?>">
            </div>

            <div class="form-field">
              <label for="new-cinema">Cinema</label>
              <select id="new-cinema" name="cinema" required<?= add_movie_invalid($errors, 'cinema') ?>>
                <option value="" disabled hidden<?= $old['cinema'] === '' ? ' selected' : '' ?>>Choose a cinema</option>
<?php for ($number = 1; $number <= CINEMA_COUNT; $number++): ?>
                <option value="<?= e((string) $number) ?>"<?= $old['cinema'] === (string) $number ? ' selected' : '' ?>><?= e(cinema_label($number)) ?></option>
<?php endfor; ?>
              </select>
            </div>

            <div class="form-field">
              <label for="new-poster">Poster image</label>
              <input id="new-poster" name="poster" type="file" accept="image/jpeg,image/png,image/webp" data-max-bytes="<?= e((string) ADD_MOVIE_MAX_POSTER_BYTES) ?>"<?= add_movie_invalid($errors, 'poster') ?>>
<?php if (add_movie_can_fit_posters()): ?>
              <p class="field-hint">Any size works: it is fitted to 900 &times; 1200 like the other posters.</p>
<?php endif; ?>
            </div>

          </div>

          <button class="button button-red" type="submit" id="add-button">Add movie</button>

        </form>

      </section>

    </main>

  </div>
<?php render_footer(['js' => ['assets/js/dates.js', 'assets/js/add-movie.js']]);
