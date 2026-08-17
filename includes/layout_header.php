<?php
declare(strict_types=1);

/** @var string $pageTitle */
/** @var bool|null $publicLayout */
/** @var bool|null $authLayout */

$pageTitle = $pageTitle ?? 'WebMonitor';
$publicLayout = !empty($publicLayout);
$authLayout = !empty($authLayout);
$user = Auth::user();
$flashItems = flashes();
$appName = 'WebMonitor';
$websitesActive = in_array(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), ['websites.php', 'website.php', 'website-form.php'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <title><?= e($pageTitle) ?> · <?= e($appName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
  <script>
    (function () {
      try {
        var t = localStorage.getItem('wm-theme');
        if (t !== 'light' && t !== 'dark') {
          t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', t);
      } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
      }
    })();
  </script>
</head>
<body class="<?= e($authLayout ? 'layout-auth' : ($publicLayout ? 'layout-public' : 'layout-app')) ?>">
<?php if ($authLayout): ?>
  <div class="auth-shell">
<?php elseif ($publicLayout): ?>
  <header class="topbar topbar-public">
    <div class="topbar-inner">
      <a class="brand" href="<?= e(url('status.php')) ?>"><?= e($appName) ?></a>
      <nav class="topbar-nav">
        <a href="<?= e(url('status.php')) ?>">Status</a>
        <a href="<?= e(url('manual.php')) ?>">Manual</a>
        <?php if ($user): ?>
          <a href="<?= e(url('dashboard.php')) ?>">Dashboard</a>
        <?php else: ?>
          <a class="btn btn-sm btn-primary" href="<?= e(url('login.php')) ?>">Sign in</a>
        <?php endif; ?>
        <button type="button" class="btn btn-ghost btn-icon" data-theme-toggle aria-label="Toggle theme">◐</button>
      </nav>
    </div>
  </header>
  <main class="public-main">
<?php else: ?>
  <div class="app-shell">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-brand">
        <a href="<?= e(url('dashboard.php')) ?>"><?= e($appName) ?></a>
        <button type="button" class="btn btn-ghost btn-icon sidebar-close" data-sidebar-close aria-label="Close menu">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
      <nav class="sidebar-nav">
        <a class="nav-link<?= is_active('dashboard.php') ?>" href="<?= e(url('dashboard.php')) ?>">Dashboard</a>
        <a class="nav-link<?= $websitesActive ? ' is-active' : '' ?>" href="<?= e(url('websites.php')) ?>">Websites</a>
        <a class="nav-link<?= is_active('logs.php') ?>" href="<?= e(url('logs.php')) ?>">Logs</a>
        <a class="nav-link" href="<?= e(url('status.php')) ?>" target="_blank" rel="noopener">Public status</a>
        <?php if (Auth::isAdmin($user)): ?>
          <div class="nav-section">Admin</div>
          <a class="nav-link<?= is_active('settings.php') ?>" href="<?= e(url('settings.php')) ?>">Settings</a>
        <?php endif; ?>
        <a class="nav-link<?= is_active('manual.php') ?>" href="<?= e(url('manual.php')) ?>">Manual</a>
      </nav>
      <div class="sidebar-foot">
        <div class="user-chip">
          <span class="user-name"><?= e($user['name'] ?? '') ?></span>
          <span class="user-role"><?= e($user['role'] ?? '') ?></span>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('logout.php')) ?>">Sign out</a>
      </div>
    </aside>
    <div class="app-main">
      <header class="topbar">
        <div class="topbar-inner">
          <button type="button" class="btn btn-ghost btn-icon sidebar-open" data-sidebar-open aria-label="Open menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
          </button>
          <h1 class="page-heading"><?= e($pageTitle) ?></h1>
          <div class="topbar-actions">
            <button type="button" class="btn btn-ghost btn-icon" data-theme-toggle aria-label="Toggle theme">◐</button>
          </div>
        </div>
      </header>
      <main class="content">
<?php endif; ?>

<?php if ($flashItems): ?>
  <div class="flash-stack" role="status">
    <?php foreach ($flashItems as $f): ?>
      <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
