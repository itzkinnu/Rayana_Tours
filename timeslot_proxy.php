<?php
require_once 'db_connection.php';

header('Content-Type: application/json');

// Get bearer token from environment or use default (matching touroption_proxy.php)
$bearer = getenv('RAYNA_BEARER') ?: 'eyJhbGciOiJodHRwOi8vd3d3LnczLm9yZy8yMDAxLzA0L3htbGRzaWctbW9yZSNobWFjLXNoYTI1NiIsInR5cCI6IkpXVCJ9.eyJqdGkiOiJlNDEwNjliZS1hMzE4LTRmZmEtYTM0OS04OTA2Mzk3YWNlNTYiLCJVc2VySWQiOiI1NDkyOSIsIlVzZXJUeXBlIjoiQWdlbnQiLCJQYXJlbnRJRCI6IjAiLCJFbWFpbElEIjoia2lyYW5Ad2hpdGVtb25rLmluIiwiaXNzIjoiaHR0cDovL2RldnJheW5hYXBpLnJheW5hdG91cnMuY29tLyIsImF1ZCI6Imh0dHA6Ly9kZXZyYXluYWFwaS5yYXluYXRvdXJzLmNvbS8ifQ.-ddDW451rRvRjCTKBc6z6SjWB3dYjrjUMAKIeE8ykbM';

$postData = file_get_contents('php://input');
$requestData = json_decode($postData, true);

// Log request data
error_log("[Time Slots Debug] Raw POST data: " . $postData);
error_log("[Time Slots Debug] Decoded request data: " . print_r($requestData, true));

// Validate required fields
$requiredFields = ['tourId', 'tourOptionId', 'travelDate', 'transferId', 'adult', 'child', 'infant', 'contractId'];
$missingFields = [];
foreach ($requiredFields as $field) {
    if (!isset($requestData[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    error_log("[Time Slots Debug] Missing required fields: " . implode(', ', $missingFields));
    echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missingFields)]);
    exit;
}

// API endpoint
$url = 'https://sandbox.raynatours.com/api/Tour/timeslot';
error_log("[Time Slots Debug] API Endpoint: " . $url);

// Initialize cURL session
$ch = curl_init($url);

// Set cURL options with verbose debug output
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Force IPv4 (sometimes helps with API connectivity)
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

// Add more detailed headers
$headers = array(
    'Content-Type: application/json',
    'Authorization: Bearer ' . $bearer,
    'Accept: application/json',
    'User-Agent: RayanaTours/1.0',
    'Connection: close'
);

error_log("[Time Slots Debug] Request Headers: " . print_r($headers, true));
error_log("[Time Slots Debug] Request Body: " . $postData);

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Follow redirects if any
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

// Log cURL headers
error_log("Time Slots Request Headers: " . print_r(array(
    'Content-Type: application/json',
    'Authorization: Bearer ' . substr($bearer, 0, 20) . '...'
), true));

// Execute cURL request
$response = curl_exec($ch);

// Get verbose debug output
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
error_log("[Time Slots Debug] Verbose cURL output: " . $verboseLog);

// Check for errors
if (curl_errno($ch)) {
    $error = curl_error($ch);
    error_log("[Time Slots Debug] cURL Error: " . $error);
    echo json_encode(['error' => $error]);
    exit;
}

// Get HTTP status code and other info
$info = curl_getinfo($ch);
error_log("[Time Slots Debug] cURL Info: " . print_r($info, true));
error_log("[Time Slots Debug] HTTP Status Code: " . $info['http_code']);
error_log("[Time Slots Debug] Content Type: " . $info['content_type']);
error_log("[Time Slots Debug] Total Time: " . $info['total_time'] . " seconds");
error_log("[Time Slots Debug] Primary IP: " . $info['primary_ip']);
error_log("[Time Slots Debug] Local IP: " . $info['local_ip']);
error_log("[Time Slots Debug] Redirect Count: " . $info['redirect_count']);
error_log("[Time Slots Debug] Redirect URL: " . ($info['redirect_url'] ?? 'none'));

curl_close($ch);

// Log response details
$responseLength = strlen($response);
error_log("[Time Slots Debug] Response Length: " . $responseLength . " bytes");
error_log("[Time Slots Debug] Raw Response: " . ($responseLength > 0 ? $response : 'EMPTY'));

// Try to decode JSON response
$decodedResponse = json_decode($response, true);
$jsonError = json_last_error();
if ($jsonError !== JSON_ERROR_NONE) {
    error_log("[Time Slots Debug] JSON decode error: " . json_last_error_msg());
    error_log("[Time Slots Debug] First 1000 chars of response: " . substr($response, 0, 1000));
    
    // Try to detect response format
    if ($responseLength > 0) {
        $firstChar = substr($response, 0, 1);
        $lastChar = substr($response, -1);
        error_log("[Time Slots Debug] Response first/last chars: '$firstChar' / '$lastChar'");
        
        // Check for common HTML indicators
        if (stripos($response, '<!DOCTYPE') !== false || stripos($response, '<html') !== false) {
            error_log("[Time Slots Debug] Response appears to be HTML instead of JSON");
            echo json_encode(['error' => 'Received HTML response instead of JSON']);
            exit;
        }
    }
}

// Return the API response
if (empty($response)) {
    error_log("[Time Slots Debug] Empty response received");
    echo json_encode([
        'error' => 'Empty response from API',
        'debug_info' => [
            'http_code' => $info['http_code'],
            'content_type' => $info['content_type'],
            'total_time' => $info['total_time'],
            'response_size' => $responseLength
        ]
    ]);
} else {
    if ($jsonError === JSON_ERROR_NONE && is_array($decodedResponse)) {
        error_log("[Time Slots Debug] Valid JSON response received");
        error_log("[Time Slots Debug] Response structure: " . print_r(array_keys($decodedResponse), true));
    }
    echo $response;
}
?>