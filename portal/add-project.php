<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

try {
    PortalRepository::ensureInstalled();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Portal database error: ' . portal_h($e->getMessage());
    exit;
}

function portal_valid_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array(strtolower((string) $scheme), ['http', 'https'], true);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'logout') {
    unset($_SESSION['portal_admin']);
    portal_flash('Logged out.', 'success');
    header('Location: add-project.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        $password = (string) ($_POST['password'] ?? '');
        if (PortalRepository::verifyAdminPassword($password)) {
            $_SESSION['portal_admin'] = true;
            portal_flash('Welcome back.', 'success');
            header('Location: add-project.php');
            exit;
        }
        portal_flash('Incorrect password.', 'error');
        header('Location: add-project.php');
        exit;
    }

    if (!portal_is_admin()) {
        portal_flash('Please log in first.', 'error');
        header('Location: add-project.php');
        exit;
    }

    if ($action === 'save_profile') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));
        $avatar = trim((string) ($_POST['avatar'] ?? 'assets/img/pfp.png'));

        if ($username === '') {
            portal_flash('Username is required.', 'error');
        } else {
            PortalRepository::updateProfile($username, $bio, $avatar !== '' ? $avatar : 'assets/img/pfp.png');
            portal_flash('Profile updated.', 'success');
        }
        header('Location: add-project.php');
        exit;
    }

    if ($action === 'add_link') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        $icon = trim((string) ($_POST['icon'] ?? 'fas fa-link'));

        if ($title === '' || $url === '') {
            portal_flash('Title and URL are required.', 'error');
        } elseif (!portal_valid_url($url)) {
            portal_flash('Enter a valid http/https URL.', 'error');
        } else {
            PortalRepository::addLink($title, $description, $url, $icon, true);
            portal_flash('Project added.', 'success');
        }
        header('Location: add-project.php');
        exit;
    }

    if ($action === 'update_link') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        $icon = trim((string) ($_POST['icon'] ?? 'fas fa-link'));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']);

        if ($id <= 0 || $title === '' || $url === '') {
            portal_flash('Invalid link data.', 'error');
        } elseif (!portal_valid_url($url)) {
            portal_flash('Enter a valid http/https URL.', 'error');
        } else {
            PortalRepository::updateLink($id, $title, $description, $url, $icon, $sortOrder, $isActive);
            portal_flash('Project updated.', 'success');
        }
        header('Location: add-project.php' . ($id > 0 ? '?edit=' . $id : ''));
        exit;
    }

    if ($action === 'delete_link') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            PortalRepository::deleteLink($id);
            portal_flash('Project deleted.', 'success');
        }
        header('Location: add-project.php');
        exit;
    }

    if ($action === 'change_password') {
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if (strlen($password) < 6) {
            portal_flash('Password must be at least 6 characters.', 'error');
        } elseif ($password !== $confirm) {
            portal_flash('Passwords do not match.', 'error');
        } else {
            PortalRepository::updateAdminPassword($password);
            portal_flash('Admin password updated.', 'success');
        }
        header('Location: add-project.php');
        exit;
    }
}

$flash = portal_flash();
$profile = PortalRepository::getProfile();
$links = PortalRepository::getAllLinks();
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editLink = $editId > 0 ? PortalRepository::findLink($editId) : null;
$isAdmin = portal_is_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Projects — My Links</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/portal.css">
</head>
<body>
    <div class="container">
        <div class="admin-top">
            <div>
                <strong>Project Manager</strong>
            </div>
            <div>
                <a href="index.php"><i class="fas fa-arrow-left"></i> Back to links</a>
                <?php if ($isAdmin): ?>
                    &nbsp;·&nbsp;
                    <a href="add-project.php?action=logout">Logout</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash <?= portal_h($flash['type']) ?>"><?= portal_h($flash['message']) ?></div>
        <?php endif; ?>

        <?php if (!$isAdmin): ?>
            <div class="admin-card">
                <h2><i class="fas fa-lock"></i> Admin login</h2>
                <form method="post" action="add-project.php">
                    <input type="hidden" name="action" value="login">
                    <div class="form-row">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autofocus>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Login</button>
                    </div>
                </form>
                <p style="margin-top:14px;font-size:0.8rem;color:rgba(255,255,255,0.7);">
                    Default password: <code>Portal123!</code>
                </p>
            </div>
        <?php else: ?>

            <div class="admin-card">
                <h2><i class="fas fa-user"></i> Profile</h2>
                <form method="post" action="add-project.php">
                    <input type="hidden" name="action" value="save_profile">
                    <div class="form-row">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" maxlength="100" required value="<?= portal_h($profile['username']) ?>">
                    </div>
                    <div class="form-row">
                        <label for="bio">Bio</label>
                        <input type="text" id="bio" name="bio" maxlength="500" value="<?= portal_h($profile['bio']) ?>">
                    </div>
                    <div class="form-row">
                        <label for="avatar">Avatar path / URL</label>
                        <input type="text" id="avatar" name="avatar" maxlength="255" value="<?= portal_h($profile['avatar']) ?>">
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Save profile</button>
                    </div>
                </form>
            </div>

            <div class="admin-card">
                <h2>
                    <i class="fas fa-<?= $editLink ? 'pen' : 'plus' ?>"></i>
                    <?= $editLink ? 'Edit project' : 'Add project' ?>
                </h2>
                <form method="post" action="add-project.php">
                    <?php if ($editLink): ?>
                        <input type="hidden" name="action" value="update_link">
                        <input type="hidden" name="id" value="<?= (int) $editLink['id'] ?>">
                    <?php else: ?>
                        <input type="hidden" name="action" value="add_link">
                    <?php endif; ?>

                    <div class="form-row">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" maxlength="200" required value="<?= portal_h($editLink['title'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" maxlength="500"><?= portal_h($editLink['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <label for="url">URL</label>
                        <input type="url" id="url" name="url" maxlength="2048" required placeholder="https://..." value="<?= portal_h($editLink['url'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <label for="icon">Icon class (Font Awesome)</label>
                        <input type="text" id="icon" name="icon" maxlength="120" placeholder="fas fa-link" value="<?= portal_h($editLink['icon'] ?? 'fas fa-link') ?>">
                    </div>

                    <?php if ($editLink): ?>
                        <div class="form-row">
                            <label for="sort_order">Sort order</label>
                            <input type="number" id="sort_order" name="sort_order" value="<?= (int) ($editLink['sortOrder'] ?? 0) ?>">
                        </div>
                        <div class="form-row">
                            <label>
                                <input type="checkbox" name="is_active" <?= !empty($editLink['isActive']) ? 'checked' : '' ?>>
                                Active (visible on portal)
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">
                            <?= $editLink ? 'Update project' : 'Add project' ?>
                        </button>
                        <?php if ($editLink): ?>
                            <a class="btn btn-ghost" href="add-project.php">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="admin-card">
                <h2><i class="fas fa-list"></i> All projects (<?= count($links) ?>)</h2>
                <div class="manage-list">
                    <?php if (!$links): ?>
                        <p style="color:rgba(255,255,255,0.75);">No projects yet.</p>
                    <?php endif; ?>
                    <?php foreach ($links as $link): ?>
                        <div class="manage-item">
                            <div class="link-icon" style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,0.35);background:rgba(255,255,255,0.08);">
                                <i class="<?= portal_h($link['icon']) ?>"></i>
                            </div>
                            <div class="meta">
                                <strong><?= portal_h($link['title']) ?></strong>
                                <span><?= portal_h($link['url']) ?></span>
                            </div>
                            <span class="badge"><?= !empty($link['isActive']) ? 'Active' : 'Hidden' ?></span>
                            <span class="badge"><?= !empty($link['isCustom']) ? 'Custom' : 'Seed' ?></span>
                            <div class="actions">
                                <a class="btn" href="add-project.php?edit=<?= (int) $link['id'] ?>">Edit</a>
                                <form method="post" action="add-project.php" onsubmit="return confirm('Delete this project?');">
                                    <input type="hidden" name="action" value="delete_link">
                                    <input type="hidden" name="id" value="<?= (int) $link['id'] ?>">
                                    <button class="btn btn-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-card">
                <h2><i class="fas fa-key"></i> Change password</h2>
                <form method="post" action="add-project.php">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-row">
                        <label for="new_password">New password</label>
                        <input type="password" id="new_password" name="password" minlength="6" required>
                    </div>
                    <div class="form-row">
                        <label for="password_confirm">Confirm password</label>
                        <input type="password" id="password_confirm" name="password_confirm" minlength="6" required>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Update password</button>
                    </div>
                </form>
            </div>

        <?php endif; ?>

        <div class="footer">
            <i class="fas fa-link" aria-hidden="true"></i> My Links Admin
        </div>
    </div>
</body>
</html>
