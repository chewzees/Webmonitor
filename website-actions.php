<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('websites.php');
}
verify_csrf();

$action = (string) ($_POST['action'] ?? '');
$id = trim((string) ($_POST['id'] ?? ''));
$ref = (string) ($_SERVER['HTTP_REFERER'] ?? url('websites.php'));

try {
    if ($action === 'check' && $id !== '') {
        $stmt = DB::pdo()->prepare('SELECT * FROM websites WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $site = $stmt->fetch();
        if (!$site) {
            throw new RuntimeException('Website not found.');
        }
        $result = Monitor::runCheck($site);
        flash('success', 'Check finished: ' . $result['status'] . ($result['response_ms'] !== null ? ' · ' . $result['response_ms'] . 'ms' : ''));
        redirect('website.php?id=' . urlencode($id));
    }

    if ($action === 'check-all') {
        $sites = DB::pdo()->query('SELECT * FROM websites WHERE is_active = 1')->fetchAll();
        $n = 0;
        foreach ($sites as $site) {
            Monitor::runCheck($site);
            $n++;
        }
        flash('success', "Ran checks for {$n} active website(s).");
        redirect('dashboard.php');
    }

    if ($action === 'delete' && $id !== '') {
        DB::pdo()->prepare('DELETE FROM websites WHERE id = :id')->execute(['id' => $id]);
        flash('success', 'Website deleted.');
        redirect('websites.php');
    }

    flash('error', 'Unknown action.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

header('Location: ' . $ref);
exit;
