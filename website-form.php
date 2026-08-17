<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';
Auth::requireAdmin();

$id = trim((string) ($_GET['id'] ?? ''));
$editing = $id !== '';
$site = null;

if ($editing) {
    $stmt = DB::pdo()->prepare('SELECT * FROM websites WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $site = $stmt->fetch();
    if (!$site) {
        flash('error', 'Website not found.');
        redirect('websites.php');
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $urlValue = trim((string) ($_POST['url'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $method = strtoupper(trim((string) ($_POST['method'] ?? 'GET')));
    $interval = max(30, (int) ($_POST['interval_seconds'] ?? 60));
    $timeout = max(1000, (int) ($_POST['timeout_ms'] ?? 10000));
    $expected = (int) ($_POST['expected_status'] ?? 200);
    $keyword = trim((string) ($_POST['keyword'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isPublic = isset($_POST['is_public']) ? 1 : 0;

    if ($slug === '') {
        $slug = slugify($name);
    } else {
        $slug = slugify($slug);
    }

    if ($name === '' || $urlValue === '') {
        $error = 'Name and URL are required.';
    } elseif (!filter_var($urlValue, FILTER_VALIDATE_URL)) {
        $error = 'Enter a valid URL.';
    } elseif (!in_array($method, ['GET', 'HEAD'], true)) {
        $error = 'Method must be GET or HEAD.';
    } else {
        try {
            if ($editing) {
                DB::pdo()->prepare(
                    'UPDATE websites SET
                      name=:name, url=:url, slug=:slug, description=:description, method=:method,
                      interval_seconds=:interval_seconds, timeout_ms=:timeout_ms, expected_status=:expected_status,
                      keyword=:keyword, is_active=:is_active, is_public=:is_public, updated_at=UTC_TIMESTAMP(3)
                     WHERE id=:id'
                )->execute([
                    'name' => $name,
                    'url' => $urlValue,
                    'slug' => $slug,
                    'description' => $description !== '' ? $description : null,
                    'method' => $method,
                    'interval_seconds' => $interval,
                    'timeout_ms' => $timeout,
                    'expected_status' => $expected,
                    'keyword' => $keyword !== '' ? $keyword : null,
                    'is_active' => $isActive,
                    'is_public' => $isPublic,
                    'id' => $id,
                ]);
                flash('success', 'Website updated.');
                redirect('website.php?id=' . urlencode($id));
            } else {
                $newId = cuid();
                DB::pdo()->prepare(
                    'INSERT INTO websites (
                      id, name, url, slug, description, method, interval_seconds, timeout_ms,
                      expected_status, keyword, is_active, is_public, current_status, created_at, updated_at
                     ) VALUES (
                      :id, :name, :url, :slug, :description, :method, :interval_seconds, :timeout_ms,
                      :expected_status, :keyword, :is_active, :is_public, \'UNKNOWN\', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)
                     )'
                )->execute([
                    'id' => $newId,
                    'name' => $name,
                    'url' => $urlValue,
                    'slug' => $slug,
                    'description' => $description !== '' ? $description : null,
                    'method' => $method,
                    'interval_seconds' => $interval,
                    'timeout_ms' => $timeout,
                    'expected_status' => $expected,
                    'keyword' => $keyword !== '' ? $keyword : null,
                    'is_active' => $isActive,
                    'is_public' => $isPublic,
                ]);
                flash('success', 'Website created.');
                redirect('website.php?id=' . urlencode($newId));
            }
        } catch (Throwable $e) {
            $error = 'Save failed: ' . $e->getMessage();
        }
    }

    $site = array_merge($site ?? [], $_POST);
}

$pageTitle = $editing ? 'Edit website' : 'Add website';
require __DIR__ . '/includes/layout_header.php';

$v = static function (string $key, $default = '') use ($site) {
    return e((string) ($site[$key] ?? $default));
};
?>

<div class="card" style="max-width:720px">
  <?php if ($error): ?>
    <div class="flash flash-error mb-2"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-group full">
        <label for="name">Name</label>
        <input id="name" name="name" required maxlength="200" value="<?= $v('name') ?>">
      </div>
      <div class="form-group full">
        <label for="url">URL</label>
        <input id="url" name="url" type="url" required maxlength="2048" placeholder="https://..." value="<?= $v('url') ?>">
      </div>
      <div class="form-group">
        <label for="slug">Slug</label>
        <input id="slug" name="slug" maxlength="100" value="<?= $v('slug') ?>" placeholder="auto from name">
      </div>
      <div class="form-group">
        <label for="method">Method</label>
        <select id="method" name="method">
          <option value="GET" <?= ($site['method'] ?? 'GET') === 'GET' ? 'selected' : '' ?>>GET</option>
          <option value="HEAD" <?= ($site['method'] ?? '') === 'HEAD' ? 'selected' : '' ?>>HEAD</option>
        </select>
      </div>
      <div class="form-group full">
        <label for="description">Description</label>
        <textarea id="description" name="description"><?= $v('description') ?></textarea>
      </div>
      <div class="form-group">
        <label for="interval_seconds">Interval (seconds)</label>
        <input id="interval_seconds" name="interval_seconds" type="number" min="30" value="<?= $v('interval_seconds', '60') ?>">
      </div>
      <div class="form-group">
        <label for="timeout_ms">Timeout (ms)</label>
        <input id="timeout_ms" name="timeout_ms" type="number" min="1000" value="<?= $v('timeout_ms', '10000') ?>">
      </div>
      <div class="form-group">
        <label for="expected_status">Expected status</label>
        <input id="expected_status" name="expected_status" type="number" value="<?= $v('expected_status', '200') ?>">
      </div>
      <div class="form-group">
        <label for="keyword">Keyword (optional)</label>
        <input id="keyword" name="keyword" value="<?= $v('keyword') ?>">
      </div>
      <div class="form-group">
        <label><input type="checkbox" name="is_active" <?= !isset($site['is_active']) || !empty($site['is_active']) ? 'checked' : '' ?>> Active</label>
      </div>
      <div class="form-group">
        <label><input type="checkbox" name="is_public" <?= !isset($site['is_public']) || !empty($site['is_public']) ? 'checked' : '' ?>> Public status page</label>
      </div>
    </div>
    <div class="btn-row mt-2">
      <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Create website' ?></button>
      <a class="btn btn-ghost" href="<?= e(url($editing ? 'website.php?id=' . urlencode($id) : 'websites.php')) ?>">Cancel</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
