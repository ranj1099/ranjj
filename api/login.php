<?php
// api/login.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->api_key)) {
    $query = "SELECT * FROM users WHERE api_key = :api_key";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':api_key', $data->api_key);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // تۆمارکردنی چوونەژوورەوە لە لۆگەکان
        $ip = $_SERVER['REMOTE_ADDR'];
        $logQuery = "INSERT INTO logs (user_id, user_name, action_type, details, ip_address) 
                     VALUES (:user_id, :user_name, 'login', 'چوونەژوورەوە', :ip)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindParam(':user_id', $user['id']);
        $logStmt->bindParam(':user_name', $user['full_name']);
        $logStmt->bindParam(':ip', $ip);
        $logStmt->execute();
        
        echo json_encode([
            "success" => true,
            "user" => [
                "id" => $user['id'],
                "username" => $user['username'],
                "full_name" => $user['full_name'],
                "role" => $user['role']
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "کلیلی API هەڵەیە"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "تکایە کلیلی API بنووسە"]);
}
?>