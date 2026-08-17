<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';

$pageTitle = 'User Manual';
$publicLayout = !Auth::check();
if (Auth::check()) {
    // use app layout when logged in
}
require __DIR__ . '/includes/layout_header.php';
?>

<div class="manual-hero">
  <h1>WebMonitor manual</h1>
  <p class="muted">Simple uptime monitoring with PHP, MySQL, HTML, CSS, and JavaScript.</p>
</div>

<div class="manual-grid">
  <div class="card manual-section">
    <h3>1. Configure database</h3>
    <p class="muted text-sm">Edit <code>api/.env</code> with your hosting MySQL details, import <code>database/schema.sql</code>, then run <code>php cli/seed.php</code>.</p>
  </div>
  <div class="card manual-section">
    <h3>2. Sign in</h3>
    <p class="muted text-sm">Default admin: <code>admin@webmonitor.local</code> / <code>ChangeMe123!</code></p>
  </div>
  <div class="card manual-section">
    <h3>3. Add websites</h3>
    <p class="muted text-sm">Go to Websites → Add website. Use Run check or cron <code>cli/monitor.php</code>.</p>
  </div>
  <div class="card manual-section">
    <h3>4. Public status</h3>
    <p class="muted text-sm">Share <a href="<?= e(url('status.php')) ?>">status.php</a> for public uptime.</p>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
