<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

try {
    PortalRepository::ensureInstalled();
    $profile = PortalRepository::getProfile();
    $links = PortalRepository::getActiveLinks();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Portal database error. Check MySQL and api/.env. Details: ' . portal_h($e->getMessage());
    exit;
}

$portalData = [
    'profile' => $profile,
    'links' => $links,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Links — <?= portal_h($profile['username']) ?></title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/portal.css">
</head>
<body>
    <div class="container">
        <div class="profile" id="profileSection">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h1><?= portal_h($profile['username']) ?></h1>
            <p><?= portal_h($profile['bio']) ?></p>
        </div>

        <div class="search-toolbar">
            <div class="search-container">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="searchInput" placeholder="Search projects..." autocomplete="off" aria-label="Search projects">
            </div>
            <div class="filter-group">
                <label for="sortSelect">Sort</label>
                <select id="sortSelect" aria-label="Sort projects">
                    <option value="default">Default</option>
                    <option value="title">A–Z</option>
                    <option value="newest">Newest</option>
                </select>
            </div>
        </div>

        <div class="links-list" id="linksList" role="list"></div>

        <div class="footer">
            <i class="fas fa-link" aria-hidden="true"></i> My Links
        </div>
    </div>

    <script>
        window.PORTAL_DATA = <?= json_encode($portalData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="assets/js/portal.js"></script>
</body>
</html>
