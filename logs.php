<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';
Auth::requireLogin();

$status = strtoupper(trim((string) ($_GET['status'] ?? '')));
$sql = 'SELECT l.*, w.name AS website_name
        FROM monitor_logs l
        JOIN websites w ON w.id = l.website_id
        WHERE 1=1';
$params = [];
if (in_array($status, ['UP', 'DOWN', 'DEGRADED', 'UNKNOWN'], true)) {
    $sql .= ' AND l.status = :status';
    $params['status'] = $status;
}
$sql .= ' ORDER BY l.checked_at DESC LIMIT 200';
$stmt = DB::pdo()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Logs';
require __DIR__ . '/includes/layout_header.php';
?>

<form class="filters" method="get">
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

<div class="card">
  <?php if (!$rows): ?>
    <div class="empty-state">No logs yet. Run a check from the dashboard.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>When</th><th>Website</th><th>Status</th><th>Code</th><th>Latency</th><th>Error</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td class="text-sm"><?= e(format_dt($row['checked_at'] ?? null)) ?></td>
              <td><?= e($row['website_name']) ?></td>
              <td><?= status_badge((string) $row['status']) ?></td>
              <td><?= e((string) ($row['status_code'] ?? '—')) ?></td>
              <td><?= e(format_ms(isset($row['response_ms']) ? (int) $row['response_ms'] : null)) ?></td>
              <td class="text-sm muted"><?= e($row['error_message'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
