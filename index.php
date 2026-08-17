<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';

if (Auth::check()) {
    redirect('dashboard.php');
}
redirect('login.php');
