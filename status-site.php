<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$pageTitle = 'Site status';
$publicLayout = true;

if ($slug === '') {
    http_response_code(404);
    require __DIR__ . '/includes/layout_header.php';
    echo '<div class="card empty-state">Missing site slug.</div>';
    require __DIR__ . '/includes/layout_footer.php';
    exit;
}

try {
    $stmt = DB::pdo()->prepare('SELECT * FROM websites WHERE slug = :slug AND is_public = 1 LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $site = $stmt->fetch();
    if (!$site) {
        http_response_code(404);
        require __DIR__ . '/includes/layout_header.php';
        echo '<div class="card empty-state">Website not found.</div>';
        echo '<p class="auth-links"><a href="' . e(url('status.php')) . '">← Back to status</a></p>';
        require __DIR__ . '/includes/layout_footer.php';
        exit;
    }

    $u24 = Monitor::uptime($site['id'], 24);
    $u7 = Monitor::uptime($site['id'], 24 * 7);
    $u30 = Monitor::uptime($site['id'], 24 * 30);

    $logs = DB::pdo()->prepare(
        'SELECT * FROM monitor_logs WHERE website_id = :id ORDER BY checked_at DESC LIMIT 20'
    );
    $logs->execute(['id' => $site['id']]);
    $logRows = $logs->fetchAll();

    $pageTitle = $site['name'];
    require __DIR__ . '/includes/layout_header.php';
    ?>
    <div class="status-banner status-<?= e(strtolower((string) $site['current_status'])) ?>">
      <?= status_badge((string) $site['current_status']) ?>
      <h1 class="mt-1"><?= e($site['name']) ?></h1>
      <p><?= e($site['url']) ?></p>
    </div>

    <div class="panel-grid">
      <div class="stat-card"><div class="stat-label">24h</div><div class="stat-value"><?= e(format_pct($u24['uptime'])) ?></div></div>
      <div class="stat-card"><div class="stat-label">7d</div><div class="stat-value"><?= e(format_pct($u7['uptime'])) ?></div></div>
      <div class="stat-card"><div class="stat-label">30d</div><div class="stat-value"><?= e(format_pct($u30['uptime'])) ?></div></div>
      <div class="stat-card"><div class="stat-label">Latency</div><div class="stat-value"><?= e(format_ms(isset($site['last_response_ms']) ? (int) $site['last_response_ms'] : null)) ?></div></div>
    </div>

    <div class="card mt-2">
      <h2>Recent checks</h2>
      <?php if (!$logRows): ?>
        <div class="empty-state">No checks yet.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>When</th><th>Status</th><th>Code</th><th>Latency</th></tr></thead>
            <tbody>
              <?php foreach ($logRows as $row): ?>
                <tr>
                  <td class="text-sm"><?= e(format_dt($row['checked_at'] ?? null)) ?></td>
                  <td><?= status_badge((string) $row['status']) ?></td>
                  <td><?= e((string) ($row['status_code'] ?? '—')) ?></td>
                  <td><?= e(format_ms(isset($row['response_ms']) ? (int) $row['response_ms'] : null)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <p class="auth-links mt-2"><a href="<?= e(url('status.php')) ?>">← Back to status</a></p>
    <?php
    require __DIR__ . '/includes/layout_footer.php';
} catch (Throwable $e) {
    http_response_code(503);
    require __DIR__ . '/includes/layout_header.php';
    echo '<div class="card empty-state">Database unavailable.</div>';
    require __DIR__ . '/includes/layout_footer.php';
}
