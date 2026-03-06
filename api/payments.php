<?php
// api/payments.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
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

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->debt_id) && !empty($data->amount) && !empty($data->payment_date)) {
    // زیادکردنی پارەدان
    $query = "INSERT INTO payments (debt_id, amount, payment_date, notes, created_by) 
              VALUES (:debt_id, :amount, :payment_date, :notes, :created_by)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':debt_id', $data->debt_id);
    $stmt->bindParam(':amount', $data->amount);
    $stmt->bindParam(':payment_date', $data->payment_date);
    $stmt->bindParam(':notes', $data->notes);
    $stmt->bindParam(':created_by', $user['id']);
    
    if ($stmt->execute()) {
        // نوێکردنەوەی قەرز
        $updateQuery = "UPDATE debts 
                        SET paid_amount = paid_amount + :amount,
                            status = CASE 
                                WHEN (total_amount - (paid_amount + :amount)) <= 0 THEN 'paid' 
                                ELSE 'active' 
                            END
                        WHERE id = :debt_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':amount', $data->amount);
        $updateStmt->bindParam(':debt_id', $data->debt_id);
        $updateStmt->execute();
        
        // تۆمارکردن لە لۆگەکان
        $logQuery = "INSERT INTO logs (user_id, user_name, action_type, table_name, record_id, details, ip_address) 
                     VALUES (:user_id, :user_name, 'add', 'payments', :record_id, :details, :ip)";
        $logStmt = $db->prepare($logQuery);
        $details = "پارەدان بۆ قەرزی: " . $data->debt_id . ", بڕ: " . $data->amount;
        $ip = $_SERVER['REMOTE_ADDR'];
        $payment_id = $db->lastInsertId();
        $logStmt->bindParam(':user_id', $user['id']);
        $logStmt->bindParam(':user_name', $user['full_name']);
        $logStmt->bindParam(':record_id', $payment_id);
        $logStmt->bindParam(':details', $details);
        $logStmt->bindParam(':ip', $ip);
        $logStmt->execute();
        
        echo json_encode(["success" => true, "message" => "پارەدان تۆمارکرا"]);
    } else {
        echo json_encode(["success" => false, "message" => "هەڵە لە تۆمارکردن"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "زانیاری پێویست نین"]);
}
?>