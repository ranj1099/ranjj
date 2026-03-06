<?php
// api/debts.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, PUT');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

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
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        
        $query = "SELECT d.*, c.full_name as customer_name 
                  FROM debts d
                  JOIN customers c ON d.customer_id = c.id";
        
        if ($status) {
            $query .= " WHERE d.status = :status";
        }
        
        $query .= " ORDER BY d.created_at DESC";
        
        $stmt = $db->prepare($query);
        if ($status) {
            $stmt->bindParam(':status', $status);
        }
        $stmt->execute();
        $debts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(["success" => true, "data" => $debts]);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->customer_id) && isset($data->total_amount)) {
            $remaining = $data->total_amount - ($data->paid_amount ?? 0);
            $status = $remaining <= 0 ? 'paid' : 'active';
            
            $query = "INSERT INTO debts 
                      (customer_id, fabric_type, fabric_quantity, total_amount, 
                       paid_amount, debt_date, expected_payment_date, notes, status, created_by) 
                      VALUES 
                      (:customer_id, :fabric_type, :fabric_quantity, :total_amount,
                       :paid_amount, :debt_date, :expected_payment_date, :notes, :status, :created_by)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':customer_id', $data->customer_id);
            $stmt->bindParam(':fabric_type', $data->fabric_type);
            $stmt->bindParam(':fabric_quantity', $data->fabric_quantity);
            $stmt->bindParam(':total_amount', $data->total_amount);
            $stmt->bindParam(':paid_amount', $data->paid_amount);
            $stmt->bindParam(':debt_date', $data->debt_date);
            $stmt->bindParam(':expected_payment_date', $data->expected_payment_date);
            $stmt->bindParam(':notes', $data->notes);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':created_by', $user['id']);
            
            if ($stmt->execute()) {
                $debt_id = $db->lastInsertId();
                
                // تۆمارکردن لە لۆگەکان
                $logQuery = "INSERT INTO logs (user_id, user_name, action_type, table_name, record_id, details, ip_address) 
                             VALUES (:user_id, :user_name, 'add', 'debts', :record_id, :details, :ip)";
                $logStmt = $db->prepare($logQuery);
                $details = "قەرز بۆ کڕیاری: " . $data->customer_id . ", بڕ: " . $data->total_amount;
                $ip = $_SERVER['REMOTE_ADDR'];
                $logStmt->bindParam(':user_id', $user['id']);
                $logStmt->bindParam(':user_name', $user['full_name']);
                $logStmt->bindParam(':record_id', $debt_id);
                $logStmt->bindParam(':details', $details);
                $logStmt->bindParam(':ip', $ip);
                $logStmt->execute();
                
                echo json_encode(["success" => true, "message" => "قەرز تۆمارکرا", "id" => $debt_id]);
            } else {
                echo json_encode(["success" => false, "message" => "هەڵە لە تۆمارکردن"]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "زانیاری پێویست نین"]);
        }
        break;
        
    case 'DELETE':
        $id = isset($_GET['id']) ? $_GET['id'] : 0;
        
        $query = "DELETE FROM debts WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            // تۆمارکردن لە لۆگەکان
            $logQuery = "INSERT INTO logs (user_id, user_name, action_type, table_name, record_id, details, ip_address) 
                         VALUES (:user_id, :user_name, 'delete', 'debts', :record_id, :details, :ip)";
            $logStmt = $db->prepare($logQuery);
            $details = "سڕینەوەی قەرز: " . $id;
            $ip = $_SERVER['REMOTE_ADDR'];
            $logStmt->bindParam(':user_id', $user['id']);
            $logStmt->bindParam(':user_name', $user['full_name']);
            $logStmt->bindParam(':record_id', $id);
            $logStmt->bindParam(':details', $details);
            $logStmt->bindParam(':ip', $ip);
            $logStmt->execute();
            
            echo json_encode(["success" => true, "message" => "قەرز سڕایەوە"]);
        } else {
            echo json_encode(["success" => false, "message" => "هەڵە لە سڕینەوە"]);
        }
        break;
}
?>