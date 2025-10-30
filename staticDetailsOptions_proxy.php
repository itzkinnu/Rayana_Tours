<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([ 'error' => 'Invalid JSON payload' ]);
    exit;
}

$apiBaseUrl = 'https://sandbox.raynatours.com';
$apiEndpoint = '/api/Tour/touroptionstaticdata';

// Reuse bearer token from index.php if available via include; fallback to env or empty
// Prefer environment, fallback to local hardcoded token to match index.php
$bearerToken = getenv('RAYNA_BEARER') ?: 'eyJhbGciOiJodHRwOi8vd3d3LnczLm9yZy8yMDAxLzA0L3htbGRzaWctbW9yZSNobWFjLXNoYTI1NiIsInR5cCI6IkpXVCJ9.eyJqdGkiOiJlNDEwNjliZS1hMzE4LTRmZmEtYTM0OS04OTA2Mzk3YWNlNTYiLCJVc2VySWQiOiI1NDkyOSIsIlVzZXJUeXBlIjoiQWdlbnQiLCJQYXJlbnRJRCI6IjAiLCJFbWFpbElEIjoia2lyYW5Ad2hpdGVtb25rLmluIiwiaXNzIjoiaHR0cDovL2RldnJheW5hYXBpLnJheW5hdG91cnMuY29tLyIsImF1ZCI6Imh0dHA6Ly9kZXZyYXluYWFwaS5yYXluYXRvdXJzLmNvbS8ifQ.-ddDW451rRvRjCTKBc6z6SjWB3dYjrjUMAKIeE8ykbM';

$url = $apiBaseUrl . $apiEndpoint;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
// Build headers without empty entries
$headers = [ 'Content-Type: application/json' ];
if (!empty($bearerToken)) {
    $headers[] = 'Authorization: Bearer ' . $bearerToken;
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
// Match index.php SSL setting to avoid local certificate issues
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

// Basic server-side logging for debugging (view in MAMP php_error.log)
error_log('[staticDetailsOptions_proxy] payload=' . json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

error_log('[staticDetailsOptions_proxy] upstream_status=' . $httpCode . ' curlErr=' . ($curlErr ?: '')); 
if ($response === false) {
    error_log('[staticDetailsOptions_proxy] upstream_response=false');
} else {
    // Truncate long responses for log
    error_log('[staticDetailsOptions_proxy] upstream_response_snippet=' . substr($response, 0, 200));
}

if ($response === false) {
    http_response_code(502);
    echo json_encode([ 'error' => 'Upstream error', 'detail' => $curlErr ]);
    exit;
}

http_response_code($httpCode ?: 200);
echo $response;
?>