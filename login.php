<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';

if (Auth::check()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    try {
        if (!DB::ok()) {
            throw new RuntimeException('Database is not connected. Check api/.env on the server.');
        }
        $user = Auth::attempt($email, $password);
        if (!$user) {
            throw new RuntimeException('Invalid email or password.');
        }
        Auth::login($user);
        flash('success', 'Welcome back, ' . $user['name'] . '.');
        redirect('dashboard.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Sign in';
$authLayout = true;
require __DIR__ . '/includes/layout_header.php';
?>

<div class="auth-topbar">
  <a class="btn btn-ghost btn-sm" href="<?= e(url('manual.php')) ?>">User Manual</a>
  <button type="button" class="btn btn-ghost btn-icon" data-theme-toggle aria-label="Toggle theme">◐</button>
</div>

<div class="auth-card">
  <div class="auth-brand">
    <div class="auth-brand-mark" aria-hidden="true">W</div>
    <p class="auth-kicker">Please sign in to continue</p>
    <h1>WebMonitor</h1>
    <p>Monitor uptime, latency, and incidents.</p>
  </div>

  <?php if ($error): ?>
    <div class="flash flash-error" style="margin-bottom:1rem"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="auth-actions">
    <p class="auth-actions-label">Quick fill</p>
    <button type="button" class="btn btn-outline btn-sm" data-autofill
            data-email="admin@webmonitor.local" data-password="ChangeMe123!">Autofill Admin</button>
    <button type="button" class="btn btn-outline btn-sm" data-autofill
            data-email="user@webmonitor.local" data-password="User123!">Autofill User</button>
    <a class="auth-manual-link" href="<?= e(url('manual.php')) ?>">User Manual</a>
  </div>

  <form method="post" action="<?= e(url('login.php')) ?>">
    <?= csrf_field() ?>
    <div class="form-group mb-1">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autocomplete="username" placeholder="you@example.com"
             value="<?= e((string) ($_POST['email'] ?? '')) ?>">
    </div>
    <div class="form-group mb-2">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
    </div>
    <button type="submit" class="btn btn-primary auth-submit">Sign in</button>
  </form>

  <div class="auth-hint">
    <strong>Demo accounts</strong>
    Admin: <code>admin@webmonitor.local</code> / <code>ChangeMe123!</code><br>
    User: <code>user@webmonitor.local</code> / <code>User123!</code>
  </div>

  <p class="auth-links"><a href="<?= e(url('status.php')) ?>">View public status page →</a></p>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
