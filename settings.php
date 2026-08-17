<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';
Auth::requireAdmin();

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'password') {
        $pass = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if (strlen($pass) < 8) {
            flash('error', 'Password must be at least 8 characters.');
        } elseif ($pass !== $confirm) {
            flash('error', 'Passwords do not match.');
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $uid = Auth::user()['id'];
            DB::pdo()->prepare('UPDATE users SET password_hash = :h, updated_at = UTC_TIMESTAMP(3) WHERE id = :id')
                ->execute(['h' => $hash, 'id' => $uid]);
            flash('success', 'Password updated.');
        }
        redirect('settings.php');
    }
}

$dbOk = DB::ok();
$pageTitle = 'Settings';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="settings-grid">
  <div class="card">
    <h2>System</h2>
    <p class="muted text-sm">Stack: PHP + MySQL + HTML/CSS/JS</p>
    <p>Database: <strong><?= $dbOk ? 'Connected' : 'Error' ?></strong></p>
    <p class="text-sm muted">APP_URL: <code><?= e(Env::get('APP_URL')) ?></code></p>
    <p class="text-sm muted">Health: <a href="<?= e(url('health.php')) ?>"><?= e(url('health.php')) ?></a></p>
  </div>

  <div class="card">
    <h2>Change password</h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <div class="form-group mb-1">
        <label for="password">New password</label>
        <input type="password" id="password" name="password" minlength="8" required>
      </div>
      <div class="form-group mb-2">
        <label for="password_confirm">Confirm</label>
        <input type="password" id="password_confirm" name="password_confirm" minlength="8" required>
      </div>
      <button class="btn btn-primary" type="submit">Update password</button>
    </form>
  </div>

  <div class="card">
    <h2>Cron monitor</h2>
    <p class="muted text-sm">Run every minute on hosting:</p>
    <code class="block" style="display:block;padding:0.75rem;background:var(--bg-muted);border-radius:8px;font-size:0.8rem">
      php <?= e(str_replace('\\', '/', __DIR__ . '/cli/monitor.php')) ?>
    </code>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
