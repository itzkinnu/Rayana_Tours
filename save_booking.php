<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Get raw POST data
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || !isset($data['request']) || !isset($data['response'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload or missing request/response data']);
    exit;
}

$request = $data['request'];
$response = $data['response'];

// Extract data from request
$uniqueNo = $request['uniqueNo'] ?? 'UNKNOWN';
$tourDetails = $request['TourDetails'][0] ?? [];
$passenger = $request['passengers'][0] ?? [];

// Prepare data for database insertion
try {
    $sql = "INSERT INTO bookings (
        unique_no, tour_id, option_id, transfer_id, tour_date, start_time,
        adult_rate, child_rate, service_total, first_name, last_name,
        email, mobile, nationality, request_data, response_data,
        status_code, reference_no, booking_id, confirmation_no
    ) VALUES (
        :unique_no, :tour_id, :option_id, :transfer_id, :tour_date, :start_time,
        :adult_rate, :child_rate, :service_total, :first_name, :last_name,
        :email, :mobile, :nationality, :request_data, :response_data,
        :status_code, :reference_no, :booking_id, :confirmation_no
    )";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':unique_no' => $uniqueNo,
        ':tour_id' => $tourDetails['tourId'] ?? 0,
        ':option_id' => $tourDetails['optionId'] ?? 0,
        ':transfer_id' => $tourDetails['transferId'] ?? 0,
        ':tour_date' => $tourDetails['tourDate'] ?? date('Y-m-d'),
        ':start_time' => $tourDetails['startTime'] ?? '',
        ':adult_rate' => $tourDetails['adultRate'] ?? 0.00,
        ':child_rate' => $tourDetails['childRate'] ?? 0.00,
        ':service_total' => $tourDetails['serviceTotal'] ?? 0.00,
        ':first_name' => $passenger['firstName'] ?? '',
        ':last_name' => $passenger['lastName'] ?? '',
        ':email' => $passenger['email'] ?? '',
        ':mobile' => $passenger['mobile'] ?? '',
        ':nationality' => $passenger['nationality'] ?? '',
        ':request_data' => json_encode($request),
        ':response_data' => json_encode($response),
        ':status_code' => $response['statuscode'] ?? null,
        ':reference_no' => $response['result'][0]['refernceNo'] ?? null,
        ':booking_id' => $response['result'][0]['bookingId'] ?? null,
        ':confirmation_no' => $response['result'][0]['confirmationNo'] ?? null
    ]);
    
    $bookingId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Booking saved successfully',
        'booking_id' => $bookingId
    ]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
?>