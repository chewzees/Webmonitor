<?php
declare(strict_types=1);
require __DIR__ . '/includes/app.php';
Auth::logout();
flash('success', 'Signed out.');
redirect('login.php');
