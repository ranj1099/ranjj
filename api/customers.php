<?php
// api/customers.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, PUT');
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

// زانیاری بەکارهێنەر بەپێی کلیلی API
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
        // وەرگرتنی هەموو کڕیاران
        $query = "SELECT * FROM customers ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["success" => true, "data" => $customers]);
        break;
        
    case 'POST':
        // زیادکردنی کڕیاری نوێ
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->full_name) && !empty($data->phone_number)) {
            $query = "INSERT INTO customers (full_name, phone_number, address, created_by) 
                      VALUES (:full_name, :phone_number, :address, :created_by)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':full_name', $data->full_name);
            $stmt->bindParam(':phone_number', $data->phone_number);
            $stmt->bindParam(':address', $data->address);
            $stmt->bindParam(':created_by', $user['id']);
            
            if ($stmt->execute()) {
                $customer_id = $db->lastInsertId();
                
                // تۆمارکردن لە لۆگەکان
                $logQuery = "INSERT INTO logs (user_id, user_name, action_type, table_name, record_id, details, ip_address) 
                             VALUES (:user_id, :user_name, 'add', 'customers', :record_id, :details, :ip)";
                $logStmt = $db->prepare($logQuery);
                $details = "ناوی کڕیار: " . $data->full_name;
                $ip = $_SERVER['REMOTE_ADDR'];
                $logStmt->bindParam(':user_id', $user['id']);
                $logStmt->bindParam(':user_name', $user['full_name']);
                $logStmt->bindParam(':record_id', $customer_id);
                $logStmt->bindParam(':details', $details);
                $logStmt->bindParam(':ip', $ip);
                $logStmt->execute();
                
                echo json_encode(["success" => true, "message" => "کڕیار زیاد کرا", "id" => $customer_id]);
            } else {
                echo json_encode(["success" => false, "message" => "هەڵە لە زیادکردن"]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "ناو و ژمارەی مۆبایل پێویستە"]);
        }
        break;
        
    case 'DELETE':
        // سڕینەوەی کڕیار
        $id = isset($_GET['id']) ? $_GET['id'] : 0;
        
        // وەرگرتنی ناوی کڕیار بۆ لۆگ
        $nameQuery = "SELECT full_name FROM customers WHERE id = :id";
        $nameStmt = $db->prepare($nameQuery);
        $nameStmt->bindParam(':id', $id);
        $nameStmt->execute();
        $customer = $nameStmt->fetch(PDO::FETCH_ASSOC);
        
        $query = "DELETE FROM customers WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            // تۆمارکردن لە لۆگەکان
            $logQuery = "INSERT INTO logs (user_id, user_name, action_type, table_name, record_id, details, ip_address) 
                         VALUES (:user_id, :user_name, 'delete', 'customers', :record_id, :details, :ip)";
            $logStmt = $db->prepare($logQuery);
            $details = "ناوی کڕیار: " . ($customer ? $customer['full_name'] : 'نەناسراو');
            $ip = $_SERVER['REMOTE_ADDR'];
            $logStmt->bindParam(':user_id', $user['id']);
            $logStmt->bindParam(':user_name', $user['full_name']);
            $logStmt->bindParam(':record_id', $id);
            $logStmt->bindParam(':details', $details);
            $logStmt->bindParam(':ip', $ip);
            $logStmt->execute();
            
            echo json_encode(["success" => true, "message" => "کڕیار سڕایەوە"]);
        } else {
            echo json_encode(["success" => false, "message" => "هەڵە لە سڕینەوە"]);
        }
        break;
}
?>