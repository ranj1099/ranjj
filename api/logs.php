<?php
// api/logs.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$api_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
if (empty($api_key)) {
    echo json_encode(["success" => false, "message" => "API Key required"]);
    exit;
}

$userQuery = "SELECT * FROM users WHERE api_key = :api_key AND role = 'admin'";
$userStmt = $db->prepare($userQuery);
$userStmt->bindParam(':api_key', $api_key);
$userStmt->execute();
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(["success" => false, "message" => "تەنها ئەدمین دەتوانێت لۆگەکان ببینێت"]);
    exit;
}

$query = "SELECT * FROM logs ORDER BY created_at DESC LIMIT 100";
$stmt = $db->prepare($query);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["success" => true, "data" => $logs]);
?>