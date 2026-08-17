<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';
Auth::requireLogin();

$q = trim((string) ($_GET['q'] ?? ''));
$status = strtoupper(trim((string) ($_GET['status'] ?? '')));

$sql = 'SELECT * FROM websites WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (name LIKE :q OR url LIKE :q OR slug LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if (in_array($status, ['UP', 'DOWN', 'DEGRADED', 'UNKNOWN'], true)) {
    $sql .= ' AND current_status = :status';
    $params['status'] = $status;
}
$sql .= ' ORDER BY name ASC';

$stmt = DB::pdo()->prepare($sql);
$stmt->execute($params);
$sites = $stmt->fetchAll();

$pageTitle = 'Websites';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
  <form class="filters" method="get" action="<?= e(url('websites.php')) ?>">
    <div class="form-group">
      <label for="q">Search</label>
      <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, URL, slug">
    </div>
    <div class="form-group">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="">All</option>
        <?php foreach (['UP', 'DOWN', 'DEGRADED', 'UNKNOWN'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-outline" type="submit">Filter</button>
  </form>
  <?php if (Auth::isAdmin()): ?>
    <a class="btn btn-primary" href="<?= e(url('website-form.php')) ?>">Add website</a>
  <?php endif; ?>
</div>

<div class="card">
  <?php if (!$sites): ?>
    <div class="empty-state">No websites found.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Name</th>
            <th>Status</th>
            <th>Active</th>
            <th>Interval</th>
            <th>Last check</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sites as $s): ?>
            <tr>
              <td>
                <a href="<?= e(url('website.php?id=' . urlencode($s['id']))) ?>"><?= e($s['name']) ?></a>
                <div class="text-sm muted truncate"><?= e($s['url']) ?></div>
              </td>
              <td><?= status_badge((string) $s['current_status']) ?></td>
              <td><?= !empty($s['is_active']) ? 'Yes' : 'No' ?></td>
              <td><?= (int) $s['interval_seconds'] ?>s</td>
              <td class="text-sm"><?= e(format_dt($s['last_checked_at'] ?? null)) ?></td>
              <td><a class="btn btn-ghost btn-sm" href="<?= e(url('website.php?id=' . urlencode($s['id']))) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
