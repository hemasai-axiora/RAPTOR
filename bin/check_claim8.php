<?php
require __DIR__ . '/../app/config/config.php';
require __DIR__ . '/../app/core/Database.php';

$db = Database::getInstance()->getConnection();
$row = $db->query('SELECT * FROM reimbursements WHERE reimbursement_id = 8')->fetch(PDO::FETCH_ASSOC);
print_r($row);
