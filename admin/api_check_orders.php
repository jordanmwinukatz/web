<?php
require_once 'auth_check.php';
require_once '../config/database.php';

$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("SELECT COUNT(*) as new_orders, MAX(id) as max_id FROM user_submissions WHERE id > ?");
$stmt->execute([$last_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'has_new' => $result['new_orders'] > 0,
    'new_count' => $result['new_orders'],
    'max_id' => $result['max_id'] ?: $last_id
]);
exit;
