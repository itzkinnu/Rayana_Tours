<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db_connection.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || !isset($data['request']) || !isset($data['response'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload or missing request/response data']);
    exit;
}

$request = $data['request'];
$response = $data['response'];

$bookingId = $request['bookingId'] ?? null;
$referenceNo = $request['referenceNo'] ?? null;
$statusCode = $response['statuscode'] ?? -1; // use -1 for cancelled if not provided

try {
    $sql = "UPDATE bookings 
            SET status_code = :status_code,
                response_data = :response_data
            WHERE (booking_id = :booking_id AND :booking_id IS NOT NULL) 
               OR (reference_no = :reference_no AND :reference_no IS NOT NULL)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status_code' => $statusCode,
        ':response_data' => json_encode($response),
        ':booking_id' => $bookingId,
        ':reference_no' => $referenceNo,
    ]);

    echo json_encode(['success' => true, 'message' => 'Booking status updated']);
} catch (PDOException $e) {
    error_log('DB update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
?>