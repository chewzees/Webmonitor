<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';

$error = null;
$sites = [];
$overall = 'UNKNOWN';

try {
    $rows = DB::pdo()->query(
        'SELECT * FROM websites WHERE is_public = 1 ORDER BY name ASC'
    )->fetchAll();

    $statuses = [];
    foreach ($rows as $w) {
        $u24 = Monitor::uptime($w['id'], 24);
        $u7 = Monitor::uptime($w['id'], 24 * 7);
        $sites[] = [
            'row' => $w,
            'u24' => $u24,
            'u7' => $u7,
        ];
        $statuses[] = (string) $w['current_status'];
    }

    if ($statuses === []) {
        $overall = 'UNKNOWN';
    } elseif (in_array('DOWN', $statuses, true)) {
        $overall = 'DOWN';
    } elseif (in_array('DEGRADED', $statuses, true)) {
        $overall = 'DEGRADED';
    } elseif (count(array_filter($statuses, static fn ($s) => $s === 'UNKNOWN')) === count($statuses)) {
        $overall = 'UNKNOWN';
    } else {
        $overall = 'UP';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
    http_response_code(503);
}

$labels = [
    'UP' => 'All systems operational',
    'DOWN' => 'Major outage detected',
    'DEGRADED' => 'Partial degradation',
    'UNKNOWN' => 'Status unknown',
];

$pageTitle = $error ? 'Status unavailable' : 'Status';
$publicLayout = true;
require __DIR__ . '/includes/layout_header.php';
?>

<?php if ($error): ?>
  <div class="status-banner status-unknown">
    <?= status_badge('UNKNOWN') ?>
    <h1 class="mt-1">Status page temporarily unavailable</h1>
    <p>We could not load monitor data right now.</p>
  </div>
  <div class="card">
    <h2 style="margin-top:0">Database connection failed</h2>
    <p class="muted">Update <code>api/.env</code> with your hosting MySQL credentials, then import <code>database/schema.sql</code> and run <code>php cli/seed.php</code>.</p>
    <p class="text-sm muted">Check: <a href="<?= e(url('health.php')) ?>"><?= e(url('health.php')) ?></a></p>
  </div>
<?php else: ?>
  <div data-auto-refresh="60">
    <div class="status-banner status-<?= e(strtolower($overall)) ?>">
      <?= status_badge($overall) ?>
      <h1 class="mt-1"><?= e($labels[$overall] ?? 'Status') ?></h1>
      <p>Updated <?= e(gmdate('Y-m-d H:i:s')) ?> UTC · refreshes every 60s</p>
    </div>

    <?php if (!$sites): ?>
      <div class="card empty-state">No public monitors are configured yet.</div>
    <?php else: ?>
      <div class="monitor-grid">
        <?php foreach ($sites as $item):
            $w = $item['row'];
            ?>
          <article class="monitor-card">
            <div class="monitor-card-head">
              <div>
                <h2>
                  <a href="<?= e(url('status-site.php?slug=' . urlencode($w['slug']))) ?>">
                    <?= e($w['name']) ?>
                  </a>
                </h2>
                <div class="url"><?= e($w['url']) ?></div>
              </div>
              <div><?= status_badge((string) $w['current_status']) ?></div>
            </div>
            <?php if (!empty($w['description'])): ?>
              <p class="text-sm muted"><?= e($w['description']) ?></p>
            <?php endif; ?>
            <div class="monitor-meta">
              <span>24h <strong><?= e(format_pct($item['u24']['uptime'])) ?></strong></span>
              <span>7d <strong><?= e(format_pct($item['u7']['uptime'])) ?></strong></span>
              <span>Latency <strong><?= e(format_ms(isset($w['last_response_ms']) ? (int) $w['last_response_ms'] : null)) ?></strong></span>
              <span>Checked <strong><?= e(format_dt($w['last_checked_at'] ?? null)) ?></strong></span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
