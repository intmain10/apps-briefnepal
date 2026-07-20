<?php
/**
 * Admin bootstrap — loads core, enforces auth (except on the login page).
 * @package OmniTools\Admin
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';

/** Authenticate an admin by email + password. */
function admin_login(string $email, string $password): bool
{
    $db = Database::getInstance();
    if (!$db->isConnected()) {
        return false;
    }
    $user = $db->selectOne('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']    = (int)$user['id'];
        $_SESSION['admin_name']  = $user['name'];
        $_SESSION['admin_email'] = $user['email'];
        $db->execute('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
        return true;
    }
    return false;
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email']);
    session_regenerate_id(true);
}
