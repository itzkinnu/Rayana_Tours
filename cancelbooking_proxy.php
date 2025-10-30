<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$endpoint = 'https://sandbox.raynatours.com/api/Booking/cancelbooking';
// Attach bearer token similar to other proxies (env var preferred)
$bearerToken = getenv('RAYNA_BEARER') ?: 'eyJhbGciOiJodHRwOi8vd3d3LnczLm9yZy8yMDAxLzA0L3htbGRzaWctbW9yZSNobWFjLXNoYTI1NiIsInR5cCI6IkpXVCJ9.eyJqdGkiOiJlNDEwNjliZS1hMzE4LTRmZmEtYTM0OS04OTA2Mzk3YWNlNTYiLCJVc2VySWQiOiI1NDkyOSIsIlVzZXJUeXBlIjoiQWdlbnQiLCJQYXJlbnRJRCI6IjAiLCJFbWFpbElEIjoia2lyYW5Ad2hpdGVtb25rLmluIiwiaXNzIjoiaHR0cDovL2RldnJheW5hYXBpLnJheW5hdG91cnMuY29tLyIsImF1ZCI6Imh0dHA6Ly9kZXZyYXluYWFwaS5yYXluYXRvdXJzLmNvbS8ifQ.-ddDW451rRvRjCTKBc6z6SjWB3dYjrjUMAKIeE8ykbM';

$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing request body']);
    exit;
}

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
// Build headers including Authorization when available
$headers = [ 'Content-Type: application/json' ];
if (!empty($bearerToken)) { $headers[] = 'Authorization: Bearer ' . $bearerToken; }
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
// Match other proxies: disable SSL verification in local dev
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, $raw);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL error', 'message' => $curlErr]);
    exit;
}

http_response_code($httpCode ?: 200);
echo $response ?: json_encode(['error' => 'Empty response']);
?>