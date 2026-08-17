<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';
Auth::requireAdmin();
flash('info', 'Telegram settings were simplified out of this rebuild. Use Settings for account options.');
redirect('settings.php');
