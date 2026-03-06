<?php
// api/measurements.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// پشکنینی کلیلی API
$api_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
if (empty($api_key)) {
    echo json_encode(["success" => false, "message" => "API Key required"]);
    exit;
}

$userQuery = "SELECT * FROM users WHERE api_key = :api_key";
$userStmt = $db->prepare($userQuery);
$userStmt->bindParam(':api_key', $api_key);
$userStmt->execute();
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(["success" => false, "message" => "Invalid API Key"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        $customer_id = isset($_GET['customer_id']) ? $_GET['customer_id'] : null;
        
        if ($customer_id) {
            $query = "SELECT m.*, c.full_name as customer_name 
                      FROM measurements m
                      JOIN customers c ON m.customer_id = c.id
                      WHERE m.customer_id = :customer_id 
                      ORDER BY m.measurement_date DESC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id);
        } else {
            $query = "SELECT m.*, c.full_name as customer_name 
                      FROM measurements m
                      JOIN customers c ON m.customer_id = c.id
                      ORDER BY m.created_at DESC LIMIT 20";
            $stmt = $db->prepare($query);
        }
        
        $stmt->execute();
        $measurements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["success" => true, "data" => $measurements]);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->customer_id) && !empty($data->measurement_date)) {
            $query = "INSERT INTO measurements 
                      (customer_id, measurement_date, chest_cm, waist_cm, hips_cm, 
                       shoulder_cm, sleeve_length_cm, dress_length_cm, notes, created_by) 
                      VALUES 
                      (:customer_id, :measurement_date, :chest_cm, :waist_cm, :hips_cm,
                       :shoulder_cm, :sleeve_length_cm, :dress_length_cm, :notes, :created_by)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':customer_id', $data->customer_id);
            $stmt->bindParam(':measurement_date', $data->measurement_date);
            $stmt->bindParam(':chest_cm', $data->chest_cm);
            $stmt->bindParam(':waist_cm', $data->waist_cm);
            $stmt->bindParam(':hips_cm', $data->hips_cm);
            $stmt->bindParam(':shoulder_cm', $data->shoulder_cm);
            $stmt->bindParam(':sleeve_length_cm', $data->sleeve_length_cm);
            $stmt->bindParam(':dress_length_cm', $data->dress_length_cm);
            $stmt->bindParam(':notes', $data->notes);
            $stmt->bindParam(':created_by', $user['id']);
            
            if ($stmt->execute()) {
                $measurement_id = $db->lastInsertId();
                
                // تۆمارکردن لە لۆگەکان
                $logQuery = "INSERT INTO logs (user_id, user_name, action_type, table_name, record_id, details, ip_address) 
                             VALUES (:user_id, :user_name, 'add', 'measurements', :record_id, :details, :ip)";
                $logStmt = $db->prepare($logQuery);
                $details = "قیاس بۆ کڕیاری: " . $data->customer_id;
                $ip = $_SERVER['REMOTE_ADDR'];
                $logStmt->bindParam(':user_id', $user['id']);
                $logStmt->bindParam(':user_name', $user['full_name']);
                $logStmt->bindParam(':record_id', $measurement_id);
                $logStmt->bindParam(':details', $details);
                $logStmt->bindParam(':ip', $ip);
                $logStmt->execute();
                
                echo json_encode(["success" => true, "message" => "قیاس تۆمارکرا", "id" => $measurement_id]);
            } else {
                echo json_encode(["success" => false, "message" => "هەڵە لە تۆمارکردن"]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "کڕیار و بەروار پێویستە"]);
        }
        break;
}
?>