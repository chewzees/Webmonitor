<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';
Auth::requireLogin();

$id = trim((string) ($_GET['id'] ?? ''));
$stmt = DB::pdo()->prepare('SELECT * FROM websites WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$site = $stmt->fetch();
if (!$site) {
    flash('error', 'Website not found.');
    redirect('websites.php');
}

$uptime24 = Monitor::uptime($id, 24);
$uptime7 = Monitor::uptime($id, 24 * 7);

$logs = DB::pdo()->prepare(
    'SELECT * FROM monitor_logs WHERE website_id = :id ORDER BY checked_at DESC LIMIT 30'
);
$logs->execute(['id' => $id]);
$logRows = $logs->fetchAll();

$pageTitle = $site['name'];
require __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
  <div>
    <div class="muted text-sm truncate"><?= e($site['url']) ?></div>
    <div class="mt-1"><?= status_badge((string) $site['current_status']) ?></div>
  </div>
  <div class="btn-row">
    <?php if (Auth::isAdmin()): ?>
      <form method="post" action="<?= e(url('website-actions.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="check">
        <input type="hidden" name="id" value="<?= e($site['id']) ?>">
        <button class="btn btn-primary" type="submit">Run check</button>
      </form>
      <a class="btn btn-outline" href="<?= e(url('website-form.php?id=' . urlencode($site['id']))) ?>">Edit</a>
      <form method="post" action="<?= e(url('website-actions.php')) ?>" data-confirm="Delete this website and its logs?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= e($site['id']) ?>">
        <button class="btn btn-ghost" type="submit">Delete</button>
      </form>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= e(url('status-site.php?slug=' . urlencode($site['slug']))) ?>" target="_blank">Public page</a>
  </div>
</div>

<div class="panel-grid">
  <div class="stat-card">
    <div class="stat-label">24h uptime</div>
    <div class="stat-value"><?= e(format_pct($uptime24['uptime'])) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">7d uptime</div>
    <div class="stat-value"><?= e(format_pct($uptime7['uptime'])) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Last latency</div>
    <div class="stat-value"><?= e(format_ms(isset($site['last_response_ms']) ? (int) $site['last_response_ms'] : null)) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Last check</div>
    <div class="stat-value text-sm" style="font-size:1rem"><?= e(format_dt($site['last_checked_at'] ?? null)) ?></div>
  </div>
</div>

<?php if (!empty($site['last_error'])): ?>
  <div class="flash flash-error mt-2"><?= e($site['last_error']) ?></div>
<?php endif; ?>

<div class="card mt-2">
  <h2>Recent checks</h2>
  <?php if (!$logRows): ?>
    <div class="empty-state">No checks yet.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>When</th><th>Status</th><th>Code</th><th>Latency</th><th>Error</th></tr>
        </thead>
        <tbody>
          <?php foreach ($logRows as $row): ?>
            <tr>
              <td class="text-sm"><?= e(format_dt($row['checked_at'] ?? null)) ?></td>
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
