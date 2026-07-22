<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT u.user_id, u.name, u.email, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total users: " . count($users) . "\n";
$byRole = [];
foreach ($users as $u) {
    $byRole[$u['role_name']][] = $u['email'];
}

foreach ($byRole as $role => $emails) {
    echo "Role: $role (" . count($emails) . " users) -> Sample: " . $emails[0] . "\n";
}
