<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';
Auth::requireAdmin();
flash('info', 'Audit log UI was simplified out of this rebuild.');
redirect('settings.php');
