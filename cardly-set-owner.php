<?php
/**
 * ONE-TIME: mark one card's owner by email (requested by the card owner), so
 * the card joins that account on sign-in. Private field, never shown. Deletes
 * itself after running; removed from the repo right after use.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly_auth.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (!hash_equals('125ee7637faa0b8835661fcf8c9399f2', (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit;
}

$slug  = 'shushant-kumar-singh-hijw';
$email = 'canihugyou80@gmail.com';

$card = cardly_load($slug);
if (!$card) {
    exit("Card not found.\n");
}
if (!empty($card['userId'])) {
    @unlink(__FILE__);
    exit("Card already belongs to account #" . (int) $card['userId'] . "; nothing changed.\n");
}
$card['ownerEmail'] = $email;
echo cardly_save($slug, $card) ? "Owner email saved on the card (private).\n" : "SAVE FAILED.\n";

$user = cardly_user_by_email($email);
if ($user && (int) $user['email_verified'] === 1) {
    echo cardly_claim($slug, (int) $user['id']) ? "Account exists: card attached now.\n" : "Attach failed.\n";
} else {
    echo "No verified account for that email yet: the card attaches when it signs in.\n";
}
@unlink(__FILE__); // one run only
