<?php
/**
 * Feedback / contact submission endpoint.
 * Stores messages in the DB when available; always validates & rate-limits.
 * @package Toolzy
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    json_error('Invalid session token. Please refresh and try again.', 419);
}
if (!rate_limit('feedback', 5, 300)) {
    json_error('Too many messages. Please try again later.', 429);
}

$name    = trim((string)($_POST['name'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$type    = trim((string)($_POST['type'] ?? 'Feedback'));
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || mb_strlen($name) > 100) json_error('Please enter a valid name.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Please enter a valid email address.');
if ($message === '' || mb_strlen($message) > 2000) json_error('Please enter a message (up to 2000 characters).');

$db = Database::getInstance();
if ($db->isConnected()) {
    try {
        $db->execute(
            'INSERT INTO feedback (name, email, type, message, ip_hash, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$name, $email, $type, $message, substr(hash('sha256', client_ip() . APP_SECRET), 0, 32)]
        );
    } catch (Throwable $e) {
        // Fall back to a local log file if the table is missing.
        @file_put_contents(
            UPLOADS_PATH . '/feedback.log',
            date('c') . "\t" . $type . "\t" . $email . "\t" . str_replace("\n", ' ', $message) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
} else {
    @file_put_contents(
        UPLOADS_PATH . '/feedback.log',
        date('c') . "\t" . $type . "\t" . $email . "\t" . str_replace("\n", ' ', $message) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

json_response(['ok' => true, 'message' => 'Thanks! Your message has been received.']);
