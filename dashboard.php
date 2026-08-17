<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';

$user = Auth::requireLogin();
$admin = Auth::isAdmin($user);

try {
    $pdo = DB::pdo();
    $total = (int) $pdo->query('SELECT COUNT(*) FROM websites')->fetchColumn();
    $up = (int) $pdo->query("SELECT COUNT(*) FROM websites WHERE current_status = 'UP'")->fetchColumn();
    $down = (int) $pdo->query("SELECT COUNT(*) FROM websites WHERE current_status = 'DOWN'")->fetchColumn();
    $unknown = (int) $pdo->query("SELECT COUNT(*) FROM websites WHERE current_status IN ('UNKNOWN','DEGRADED')")->fetchColumn();

    $sites = $pdo->query(
        'SELECT id, name, url, slug, current_status, last_checked_at, last_response_ms, is_active
         FROM websites ORDER BY name ASC LIMIT 50'
    )->fetchAll();

    $incidents = $pdo->query(
        'SELECT i.*, w.name AS website_name
         FROM incidents i
         JOIN websites w ON w.id = i.website_id
         WHERE i.resolved_at IS NULL
         ORDER BY i.started_at DESC LIMIT 10'
    )->fetchAll();

    $avgMs = $pdo->query(
        'SELECT AVG(last_response_ms) FROM websites WHERE last_response_ms IS NOT NULL'
    )->fetchColumn();
} catch (Throwable $e) {
    flash('error', 'Database error: ' . $e->getMessage());
    $total = $up = $down = $unknown = 0;
    $sites = $incidents = [];
    $avgMs = null;
}

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
  <p class="muted text-sm" style="margin:0">Overview of monitors, uptime, and open incidents.</p>
  <?php if ($admin): ?>
    <form method="post" action="<?= e(url('website-actions.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="check-all">
      <button type="submit" class="btn btn-primary" data-confirm="Run health checks for all active websites?">
        Run all checks
      </button>
    </form>
  <?php endif; ?>
</div>

<div class="panel-grid">
  <div class="stat-card">
    <div class="stat-label">Total sites</div>
    <div class="stat-value"><?= $total ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Up</div>
    <div class="stat-value"><?= $up ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Down</div>
    <div class="stat-value"><?= $down ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Avg latency</div>
    <div class="stat-value"><?= e(format_ms($avgMs !== null ? (int) round((float) $avgMs) : null)) ?></div>
  </div>
</div>

<div class="card mt-2">
  <h2>Monitors</h2>
  <?php if (!$sites): ?>
    <div class="empty-state">No websites yet. <a href="<?= e(url('website-form.php')) ?>">Add one</a>.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Name</th>
            <th>Status</th>
            <th>Latency</th>
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
              <td><?= e(format_ms(isset($s['last_response_ms']) ? (int) $s['last_response_ms'] : null)) ?></td>
              <td class="text-sm"><?= e(format_dt($s['last_checked_at'] ?? null)) ?></td>
              <td>
                <?php if ($admin): ?>
                  <form method="post" action="<?= e(url('website-actions.php')) ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="check">
                    <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                    <button class="btn btn-ghost btn-sm" type="submit">Check</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card mt-2">
  <h2>Open incidents</h2>
  <?php if (!$incidents): ?>
    <div class="empty-state">No open incidents.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>Site</th><th>Status</th><th>Started</th><th>Summary</th></tr>
        </thead>
        <tbody>
          <?php foreach ($incidents as $i): ?>
            <tr>
              <td><?= e($i['website_name']) ?></td>
              <td><?= status_badge((string) $i['status']) ?></td>
              <td class="text-sm"><?= e(format_dt($i['started_at'] ?? null)) ?></td>
              <td class="text-sm"><?= e($i['summary'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
