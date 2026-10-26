<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('admin');

// Without ?category: the categories (add, delete, open one).
// With ?category=ID: that category's snacks (add, remove).
$categoryId = input_int($_GET, 'category', 1);
$category = $categoryId !== null ? find_snack_category($categoryId) : null;
if ($categoryId !== null && $category === null) {
    redirect('admin/snacks.php');
}

if (is_post()) {
    verify_csrf();
    $action = input_string($_POST, 'action', 20);
    $back = 'admin/snacks.php';
    $notice = '';
    $error = '';

    if ($action === 'add_category') {
        $name = input_string($_POST, 'name', SNACK_CATEGORY_NAME_MAX);
        $error = create_snack_category($name);
        $notice = $error === '' ? $name . ' was added. Open it to add snacks.' : '';
    } elseif ($action === 'delete_category') {
        $target = find_snack_category((int) input_int($_POST, 'category_id', 1));
        if ($target !== null && delete_snack_category((int) $target['id'])) {
            $notice = $target['name'] . ' and its snacks were taken off the menu.';
        }
    } elseif ($action === 'add_snack' && $category !== null) {
        $back = 'admin/snacks.php?category=' . (int) $category['id'];
        $name = input_string($_POST, 'name', SNACK_NAME_MAX);
        $error = add_snack(
            (int) $category['id'],
            $name,
            input_string($_POST, 'detail', SNACK_DETAIL_MAX),
            input_int($_POST, 'price', 1, SNACK_PRICE_MAX)
        );
        $notice = $error === '' ? $name . ' was added to ' . $category['name'] . '.' : '';
    } elseif ($action === 'remove_snack' && $category !== null) {
        $back = 'admin/snacks.php?category=' . (int) $category['id'];
        if (remove_snack((int) input_int($_POST, 'snack_id', 1), (int) $category['id'])) {
            $notice = 'The snack was taken off the menu.';
        }
    }

    // Shown once, after the redirect
    $_SESSION['snacks_flash'] = ['notice' => $notice, 'error' => $error, 'form' => $error !== '' ? $_POST : []];
    redirect($back);
}

$flash = $_SESSION['snacks_flash'] ?? ['notice' => '', 'error' => '', 'form' => []];
unset($_SESSION['snacks_flash']);
// A refused form keeps what was typed
$typed = static function (string $key) use ($flash): string {
    return is_string($flash['form'][$key] ?? null) ? (string) $flash['form'][$key] : '';
};

$here = $category !== null ? 'admin/snacks.php?category=' . (int) $category['id'] : 'admin/snacks.php';

render_head($category !== null ? $category['name'] . ' · Snacks' : 'Snacks', ['assets/css/admin.css']);
render_header(['staff' => true, 'current' => 'snacks']);
?>
  <div class="admin-layout">
    <?php render_staff_sidebar('snacks'); ?>

    <div class="admin-head">
<?php if ($category !== null): ?>
      <a class="back-link" href="<?= e(url('admin/snacks.php')) ?>">&larr; All categories</a>
      <h1><?= e($category['name']) ?></h1>
      <p class="admin-intro">The snacks in this category. Customers see them when they book.</p>
<?php else: ?>
      <h1>Snacks</h1>
<?php endif; ?>
    </div>

    <main class="admin-page">

<?php if ($flash['notice'] !== ''): ?>
      <p class="form-message form-message-success" role="status"><?= e($flash['notice']) ?></p>
<?php endif; ?>
<?php if ($flash['error'] !== ''): ?>
      <p class="form-message form-message-error" role="alert"><?= e($flash['error']) ?></p>
<?php endif; ?>

<?php if ($category === null): ?>
<?php $categories = snack_categories(); ?>
      <section class="panel">
        <div class="panel-head">
          <div>
            <h2>Categories</h2>
            <p class="panel-note">The headings on the booking page, in this order.</p>
          </div>
        </div>

        <form class="snack-admin-form" method="post" action="<?= e(url($here)) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_category">
          <div class="form-field">
            <label for="category-name">New category</label>
            <input id="category-name" name="name" type="text" maxlength="<?= e(SNACK_CATEGORY_NAME_MAX) ?>"
                   placeholder="Hotdogs" value="<?= e($typed('name')) ?>" required>
          </div>
          <button class="button button-red" type="submit">Add category</button>
        </form>

<?php if ($categories === []): ?>
        <p class="empty-state">
          <strong>No categories yet</strong>
          Add one above, then open it to add snacks.
        </p>
<?php else: ?>
        <ul class="snack-admin-list">
<?php foreach ($categories as $row): ?>
          <li class="snack-admin-row">
            <a class="snack-admin-open" href="<?= e(url('admin/snacks.php?category=' . (int) $row['id'])) ?>">
              <span class="snack-admin-name"><?= e($row['name']) ?></span>
              <span class="snack-admin-sub"><?= e(count_label((int) $row['snack_count'], 'snack', 'snacks')) ?></span>
            </a>
            <form method="post" action="<?= e(url($here)) ?>" class="inline-form confirm-form"
                  data-confirm="<?= e('Delete ' . $row['name'] . '? Its snacks come off the menu too. Orders already made stay valid.') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_category">
              <input type="hidden" name="category_id" value="<?= e((int) $row['id']) ?>">
              <button class="button button-outline button-small" type="submit">Delete</button>
            </form>
          </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </section>

<?php else: ?>
<?php $snacks = category_snacks((int) $category['id']); ?>
      <section class="panel">
        <div class="panel-head">
          <div>
            <h2>Add a snack</h2>
            <p class="panel-note">It goes on sale in <?= e($category['name']) ?> right away.</p>
          </div>
        </div>

        <form class="snack-admin-form snack-admin-form-wide" method="post" action="<?= e(url($here)) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_snack">
          <div class="form-field">
            <label for="snack-name">Name</label>
            <input id="snack-name" name="name" type="text" maxlength="<?= e(SNACK_NAME_MAX) ?>"
                   placeholder="Iced Tea" value="<?= e($typed('name')) ?>" required>
          </div>
          <div class="form-field">
            <label for="snack-detail">Details <span class="label-optional">(optional)</span></label>
            <input id="snack-detail" name="detail" type="text" maxlength="<?= e(SNACK_DETAIL_MAX) ?>"
                   placeholder="Lemon, 22 oz" value="<?= e($typed('detail')) ?>">
          </div>
          <div class="form-field">
            <label for="snack-price">Price (₱)</label>
            <input id="snack-price" name="price" type="number" min="1" max="<?= e(SNACK_PRICE_MAX) ?>" step="1"
                   placeholder="75" value="<?= e($typed('price')) ?>" required>
          </div>
          <button class="button button-red" type="submit">Add snack</button>
        </form>
      </section>

      <section class="panel">
        <div class="panel-head">
          <div>
            <h2>On sale</h2>
            <p class="panel-note"><?= e(count_label(count($snacks), 'snack', 'snacks')) ?> in <?= e($category['name']) ?>.</p>
          </div>
        </div>

<?php if ($snacks === []): ?>
        <p class="empty-state">
          <strong>No snacks in <?= e($category['name']) ?> yet</strong>
          Add one above. Until then, this category does not show on the booking page.
        </p>
<?php else: ?>
        <ul class="snack-admin-list">
<?php foreach ($snacks as $snack): ?>
          <li class="snack-admin-row">
            <span class="snack-admin-open">
              <span class="snack-admin-name"><?= e($snack['name']) ?></span>
<?php if ((string) $snack['detail'] !== ''): ?>
              <span class="snack-admin-sub"><?= e($snack['detail']) ?></span>
<?php endif; ?>
            </span>
            <span class="snack-admin-price"><?= e(peso((int) $snack['price'])) ?></span>
            <form method="post" action="<?= e(url($here)) ?>" class="inline-form confirm-form"
                  data-confirm="<?= e('Take ' . $snack['name'] . ' off the menu? Orders already made stay valid.') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove_snack">
              <input type="hidden" name="snack_id" value="<?= e((int) $snack['id']) ?>">
              <button class="button button-outline button-small" type="submit">Remove</button>
            </form>
          </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </section>
<?php endif; ?>

    </main>

  </div>
<?php render_footer(['js' => ['assets/js/confirm-form.js']]);
