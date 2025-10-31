<?php
// Rayana Tours API Integration
// Configuration
$apiBaseUrl = 'https://sandbox.raynatours.com';
$apiEndpoint = '/api/Tour/tourstaticdata';
$bearerToken = 'eyJhbGciOiJodHRwOi8vd3d3LnczLm9yZy8yMDAxLzA0L3htbGRzaWctbW9yZSNobWFjLXNoYTI1NiIsInR5cCI6IkpXVCJ9.eyJqdGkiOiJlNDEwNjliZS1hMzE4LTRmZmEtYTM0OS04OTA2Mzk3YWNlNTYiLCJVc2VySWQiOiI1NDkyOSIsIlVzZXJUeXBlIjoiQWdlbnQiLCJQYXJlbnRJRCI6IjAiLCJFbWFpbElEIjoia2lyYW5Ad2hpdGVtb25rLmluIiwiaXNzIjoiaHR0cDovL2RldnJheW5hYXBpLnJheW5hdG91cnMuY29tLyIsImF1ZCI6Imh0dHA6Ly9kZXZyYXluYWFwaS5yYXluYXRvdXJzLmNvbS8ifQ.-ddDW451rRvRjCTKBc6z6SjWB3dYjrjUMAKIeE8ykbM';
$requestData = [
    'CountryId' => 13063,
    'cityId' => 13668
];

// Function to validate and format image URLs
function formatImageUrl($imageUrl) {
    // Check if URL is already valid
    if (empty($imageUrl)) {
        return '';
    }
    
    // If URL doesn't start with http or https, prepend the cloudfront URL
    if (!preg_match('/^https?:\/\//', $imageUrl)) {
        $imageUrl = 'https://d1i3enf1i5tb1f.cloudfront.net/' . $imageUrl;
    }
    
    // If URL has no file extension, append _S.jpg (Rayna image convention)
    if (!preg_match('/\.(jpg|jpeg|png|webp)$/i', $imageUrl)) {
        $imageUrl .= '_S.jpg';
    }
    
    return $imageUrl;
}

// Function to fetch tours from API
function fetchTours($apiBaseUrl, $apiEndpoint, $bearerToken, $requestData) {
    $url = $apiBaseUrl . $apiEndpoint;
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $bearerToken
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Added to handle SSL issues
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error];
    }
    
    if ($httpCode != 200) {
        return ['error' => 'API returned status code ' . $httpCode];
    }
    
    $decoded = json_decode($response, true);
    
    // Check if the response was successfully decoded
    if ($decoded === null) {
        return ['error' => 'Failed to decode JSON response: ' . json_last_error_msg()];
    }
    
    return $decoded;
}

// Try to extract tours array from various API response shapes
function normalizeTours($data) {
    if (!is_array($data)) return [];
    // Common wrappers
    foreach (['Data', 'data', 'Result', 'result', 'Tours', 'tours'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) {
            return $data[$key];
        }
    }
    // Direct array of items
    $isNumericArray = array_keys($data) === range(0, count($data) - 1);
    if ($isNumericArray) return $data;
    return [];
}

// Try to fetch tours data from API
$toursData = fetchTours($apiBaseUrl, $apiEndpoint, $bearerToken, $requestData);
// Timestamp of API call (ISO 8601)
$apiTimestamp = date('c');

// Check if there was an error
$error = isset($toursData['error']) ? $toursData['error'] : null;

// Prefer API data when available; fallback to sample for UI
$tours = [];
if (!$error) {
    $tours = normalizeTours($toursData);
}

$totalTours = count($tours);

// Client-side filtering - no server-side pagination needed
// All tours will be loaded and filtered on the client side
$toursToShow = $tours;

// Derive tour categories dynamically from API data (cityTourType or categoryName)
$tourCategories = [];
foreach ($tours as $t) {
    $cat = isset($t['cityTourType']) && $t['cityTourType'] ? $t['cityTourType'] : (isset($t['categoryName']) ? $t['categoryName'] : null);
    if ($cat) {
        $tourCategories[$cat] = true;
    }
}
$tourCategories = array_keys($tourCategories);

// View mode (grid or list)
$viewMode = isset($_GET['view']) ? $_GET['view'] : 'grid';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rayana Tours</title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --text-color: #333;
            --light-gray: #f5f5f5;
            --border-color: #ddd;
            --shadow-color: rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f9f9f9;
            color: var(--text-color);
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        h1 {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .tour-count {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .controls {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .search-box {
            display: flex;
            max-width: 300px;
        }
        
        .search-box input {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px 0 0 4px;
            font-size: 14px;
            flex-grow: 1;
        }
        
        .search-box button {
            padding: 8px 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .search-box button:hover {
            background-color: #2980b9;
        }
        
        .filter-box {
            display: flex;
            align-items: center;
        }
        
        .filter-box select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            background-color: white;
            cursor: pointer;
            min-width: 180px;
        }
        
        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #666;
        }
        
        .pagination-controls select {
            padding: 6px 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 13px;
        }
        
        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            font-size: 14px;
        }
        
        .pagination-nav button {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            background-color: white;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .pagination-nav button:hover:not(:disabled) {
            background-color: var(--primary-color);
            color: white;
        }
        
        .pagination-nav button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-nav .page-info {
            color: #666;
            margin: 0 10px;
        }
        
        .view-toggle {
            display: flex;
            align-items: center;
        }
        
        .view-toggle span {
            font-size: 14px;
            margin-right: 8px;
        }
        .section ul {
            margin-left: 20px;
        }

        /* Details Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal {
            background: #fff;
            width: 100%;
            max-width: 1100px;
            max-height: 90vh;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 10px 30px var(--shadow-color);
            display: flex;
            flex-direction: column;
            border: 1px solid var(--border-color);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            background: var(--light-gray);
        }
        .modal-header h2 {
            font-size: 20px;
            color: var(--primary-color);
            font-weight: 600;
        }
        .modal-actions { display: flex; align-items: center; gap: 10px; }
        .modal-close {
            border: none; background: transparent; font-size: 22px; cursor: pointer;
            width: 32px; height: 32px; border-radius: 50%; line-height: 1; color: var(--text-color);
        }
        .modal-content {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 16px 20px;
            overflow-y: auto;
            overscroll-behavior: contain;
        }
        .banner img {
            width: 100%;
            max-height: 280px;
            object-fit: cover;
            border-radius: 6px;
        }
        .banner-slider { position: relative; overflow: hidden; border-radius: 6px; }
        .banner-slides { display: flex; transition: transform 0.3s ease; }
        .banner-slide { min-width: 100%; }
        .banner-slide img { width: 100%; height: 280px; object-fit: cover; display: block; }
        .banner-nav { position: absolute; inset: 0; display:flex; justify-content: space-between; align-items:center; pointer-events:none; }
        .banner-nav button { pointer-events:auto; border:none; background:rgba(0,0,0,0.5); color:#fff; width:36px; height:36px; border-radius:50%; cursor:pointer; }
        .banner-dots { position:absolute; bottom:8px; left:50%; transform:translateX(-50%); display:flex; gap:6px; }
        .banner-dots button { width:8px; height:8px; border-radius:50%; border:none; background:#ccc; cursor:pointer; }
        .banner-dots button.active { background: var(--primary-color); }
        .section { 
            background: #fff; 
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }
        .section-header {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--light-gray);
        }
        .section-content { 
            font-size: 14px; 
            color: var(--text-color);
            line-height: 1.6;
        }
        .key-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 16px;
        }
        .key-info-item {
            display: grid; grid-template-columns: 28px 1fr; gap: 10px;
            padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;
            background: #fff;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .key-info-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        .key-icon { width: 24px; height: 24px; display: inline-block; }
        .key-text { line-height: 1.4; }
        .info-video-row { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 16px; align-items: start; }
        .video-col iframe, .video-col video { width: 100%; height: 260px; border: none; border-radius: 6px; }
        .section-group .section { padding-bottom: 8px; border-bottom: 1px solid var(--border-color); }
        .section-group .section:last-child { border-bottom: none; }
        @media (max-width: 768px) {
            .key-info { grid-template-columns: 1fr; }
            .banner img { max-height: 220px; }
            .info-video-row { grid-template-columns: 1fr; }
            .video-col iframe, .video-col video { height: 200px; }
        }
        .gallery {
            overflow-x: auto;
            display: flex;
            gap: 8px;
            padding-bottom: 8px;
        }
        .gallery img {
            height: 180px;
            border-radius: 6px;
            object-fit: cover;
        }
        .tabs {
            display: flex;
            gap: 4px;
            border-bottom: 2px solid var(--light-gray);
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .tabs button {
            padding: 10px 16px;
            border: none;
            background: transparent;
            border-radius: 6px 6px 0 0;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-color);
            transition: all 0.2s ease;
            position: relative;
            font-weight: 500;
        }
        .tabs button:hover {
            background: var(--light-gray);
            color: var(--primary-color);
        }
        .tabs button.active { 
            background: var(--primary-color); 
            color: #fff; 
            font-weight: 600;
        }
        .tabs button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-color);
        }
        .tab-panel {
            overflow: auto;
            max-height: 50vh;
            padding-right: 8px;
        }
        .reviews {
            margin-top: 16px;
        }
        .review-item { 
            border-top: 1px solid var(--border-color); 
            padding: 16px 0;
            transition: background-color 0.2s ease;
        }
        .review-item:hover {
            background-color: var(--light-gray);
            border-radius: 6px;
            padding: 16px;
            margin: 0 -16px;
        }
        .faq-section { 
            margin-top: 16px; 
        }
        .faq-section ul { 
            margin: 8px 0 8px 20px; 
        }
        .faq-section li { 
            margin: 6px 0; 
            line-height: 1.6;
        }
        .faq-section details {
            background: var(--light-gray);
            border-radius: 8px;
            padding: 12px 16px;
            margin: 8px 0;
        }
        .faq-section summary {
            font-weight: 600;
            cursor: pointer;
            color: var(--primary-color);
        }
        .faq-section details[open] summary {
            margin-bottom: 12px;
        }
        
        /* Tour Options Styles */
        .touroptions-container {
            display: flex;
            overflow-x: auto;
            gap: 16px;
            padding: 8px 4px 16px 4px;
            scrollbar-width: thin;
            scrollbar-color: var(--border-color) transparent;
        }
        
        .touroptions-container::-webkit-scrollbar {
            height: 6px;
        }
        
        .touroptions-container::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .touroptions-container::-webkit-scrollbar-thumb {
            background-color: var(--border-color);
            border-radius: 3px;
        }
        
        .touroption-card {
            min-width: 320px;
            max-width: 380px;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            flex-shrink: 0;
            position: relative; /* For positioning info icon */
        }
        
        .touroption-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .touroption-header {
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--light-gray);
        }
        
        .touroption-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0 0 4px 0;
        }
        
        .touroption-id-badge {
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 400;
            background-color: #6c757d;
            color: white;
            white-space: nowrap;
            margin-left: 8px;
        }
        
        .touroption-description {
            font-size: 13px;
            color: #666;
            line-height: 1.4;
            margin: 0;
        }
        
        .touroption-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px 12px;
            margin-bottom: 12px;
        }
        
        .touroption-detail-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .touroption-detail-label {
            font-size: 11px;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
        }
        
        .touroption-detail-value {
            font-size: 13px;
            color: var(--text-color);
            line-height: 1.3;
        }
        
        .touroption-section {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--light-gray);
        }
        
        .touroption-section-header {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0 0 8px 0;
        }
        
        .touroption-section-content {
            font-size: 13px;
            color: var(--text-color);
            line-height: 1.4;
        }
        
        .touroption-section-content ul {
            margin: 4px 0 4px 16px;
            padding: 0;
        }
        
        .touroption-section-content li {
            margin: 2px 0;
            line-height: 1.4;
        }
        
        .operation-days {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .operation-day {
            width: 28px;
            height: 28px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            background: var(--light-gray);
            color: #666;
        }
        
        .operation-day.active {
            background: var(--primary-color);
            color: white;
        }
        
        .transfer-time-item {
            background: var(--light-gray);
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        
        .transfer-time-type {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 4px;
        }
        
        .transfer-time-details {
            font-size: 12px;
            color: #666;
            line-height: 1.3;
        }

        .transfer-details-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
            border-left: 4px solid #007bff;
        }

        .transfer-detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .transfer-detail-label {
            font-weight: 600;
            color: #495057;
        }

        .transfer-detail-value {
            color: #6c757d;
        }

        .transfer-id-badge {
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            background-color: #6f42c1;
            color: white;
            margin-left: 8px;
        }

        .availability-badge {
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            background-color: #28a745;
            color: white;
            margin-left: 8px;
        }

        .availability-badge.unavailable {
            background-color: #dc3545;
        }

        .availability-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .book-button {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .book-button:hover {
            background-color: #0056b3;
        }

        .book-button:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }

        .transfer-pricing {
            background-color: #e8f5e8;
            border-radius: 6px;
            padding: 8px;
            margin-top: 8px;
            border: 1px solid #c3e6cb;
        }

        .pricing-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 12px;
        }

        .pricing-label {
            font-weight: 600;
            color: #155724;
        }

        .pricing-value {
            color: #155724;
        }
        .modal-info-icon {
            width: 28px; height: 28px; border-radius: 50%; border: 1px solid var(--border-color);
            display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
            background: #fff; color: var(--text-color);
        }
        .modal-tooltip { position: relative; }
        /* Use the same tooltip styles as cards */
        .modal-tooltip .tooltip { right: 0; top: 32px; }
        .modal-tooltip pre { white-space: pre-wrap; word-break: break-word; font-size: 12px; line-height: 1.5; }

        /* Lock background scroll when modal is open */
        body.modal-open { overflow: hidden; }
        
        .view-toggle a {
            padding: 6px 10px;
            background-color: var(--light-gray);
            color: var(--text-color);
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            transition: background-color 0.2s;
        }
        
            .view-toggle a.active {
                background-color: var(--primary-color);
                color: white;
            }

            /* Header Cancel Booking link styling */
            #cancel-booking-link {
                padding: 6px 12px;
                border-radius: 6px;
                border: 1px solid #dc3545;
                color: #dc3545;
                background: transparent;
                text-decoration: none;
                font-weight: 600;
                transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
            }
            #cancel-booking-link:hover {
                background: #dc3545;
                color: #fff;
                box-shadow: 0 2px 8px var(--shadow-color);
            }
        
        .tours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .tours-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .tour-card {
            background-color: white;
            border-radius: 8px;
            overflow: visible; /* allow tooltip to overflow beyond card */
            box-shadow: 0 2px 8px var(--shadow-color);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            cursor: pointer;
        }
        
        .tour-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px var(--shadow-color);
        }
        
        .tour-card.list {
            display: flex;
            height: 150px;
        }
        
        .tour-image {
            height: 160px;
            overflow: hidden;
            position: relative;
        }
        
        .tour-card.list .tour-image {
            width: 200px;
            height: 100%;
            flex-shrink: 0;
        }
        
        .tour-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .tour-card:hover .tour-image img {
            transform: scale(1.05);
        }
        
        .tour-details {
            padding: 15px;
        }
        
        .tour-card.list .tour-details {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 100%;
        }
        
        .tour-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-color);
        }
        
        .tour-city {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .tour-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        
        .tour-duration {
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
        }
        
        .tour-duration::before {
            content: "⏱";
            margin-right: 4px;
        }
        
        .tour-rating {
            display: flex;
            align-items: center;
            font-size: 12px;
        }
        
        .stars {
            color: #f39c12;
            margin-right: 4px;
        }
        
        .review-count {
            color: #666;
        }
        
        .tour-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }
        
        .tour-category {
            font-size: 12px;
            color: #666;
            background-color: var(--light-gray);
            padding: 3px 8px;
            border-radius: 12px;
        }
        
        .tour-policy {
            font-size: 10px;
            color: var(--secondary-color);
        }
        .tour-short-description {
            font-size: 12px;
            color: #555;
            margin-top: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: white;
        }
        
        .badge.recommended {
            background-color: var(--secondary-color);
        }
        
        .badge.private {
            background-color: #e74c3c;
        }
        .badge.slot {
            left: 10px;
            right: auto;
            background-color: #8e44ad;
        }
        
        /* Tour ID badges */
        .tour-name-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 4px;
        }
        
        .tour-id-badge {
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 400;
            background-color: #6c757d;
            color: white;
            white-space: nowrap;
        }
        
        .modal-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .modal-tour-id-badge {
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
            background-color: #495057;
            color: white;
            white-space: nowrap;
        }
        
        .modal-tour-date-badge {
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
            background-color: #28a745;
            color: white;
            white-space: nowrap;
        }
        
        .info-icon {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 24px;
            height: 24px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            cursor: pointer;
            line-height: 1;
            box-shadow: 0 2px 6px var(--shadow-color);
        }
        .info-icon:focus {
            outline: 2px solid #fff;
            outline-offset: 2px;
        }

        .tooltip {
            position: absolute;
            bottom: 40px;
            right: 10px;
            background-color: #111;
            color: #f0f0f0;
            padding: 12px;
            border-radius: 8px;
            font-size: 12px;
            width: 320px;
            max-width: 90vw;
            box-shadow: 0 8px 24px var(--shadow-color);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease, transform 0.2s ease;
            transform: translateY(8px);
            z-index: 20;
            display: flex;
            flex-direction: column;
            min-height: 400px;
            max-height: 400px;
        }
        .tooltip.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .tooltip-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .tooltip-title {
            font-weight: 600;
            font-size: 12px;
            color: #fff;
        }
        .tooltip-meta {
            font-size: 11px;
            color: #bbb;
            text-align: right;
        }
        .tooltip-content {
            max-height: 320px;
            overflow: auto;
            border-top: 1px solid #333;
            padding-top: 8px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .tooltip-error {
            background-color: #7a2a2a;
            color: #fff;
            padding: 8px;
            border-radius: 6px;
            margin-top: 6px;
        }
        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid #666;
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* JSON syntax highlight */
        .json-key { color: #9cdcfe; }
        .json-string { color: #ce9178; }
        .json-number { color: #b5cea8; }
        .json-boolean { color: #569cd6; }
        .json-null { color: #808080; font-style: italic; }
        .json-entry { margin-left: 12px; }
        details { margin: 4px 0; }
        summary { cursor: pointer; }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .tours-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .tour-card.list {
                flex-direction: column;
                height: auto;
            }
            
            .tour-card.list .tour-image {
                width: 100%;
                height: 160px;
            }
            
            header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .controls {
                width: 100%;
                justify-content: space-between;
            }
        }
        
        @media (max-width: 480px) {
            .tours-grid {
                grid-template-columns: 1fr;
            }
            
            .controls {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-box {
                width: 100%;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                <div>
                    <h1>Rayana Tours</h1>
                    <p class="tour-count">Showing <span id="filtered-count"><?php echo $totalTours; ?></span> of <?php echo $totalTours; ?> Tours</p>
                </div>
                <div>
                    <a href="#" id="cancel-booking-link">Cancel Booking</a>
                </div>
            </div>
            
            <div class="controls">
                <div class="search-box">
                    <input type="text" id="search-input" placeholder="Search tours or categories..." autocomplete="off">
                    <button type="button" onclick="clearSearch()">Clear</button>
                </div>
                
                <div class="filter-box">
                    <select id="category-filter">
                        <option value="">All Categories</option>
                        <?php foreach ($tourCategories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="view-toggle">
                    <span>View:</span>
                    <a href="#" onclick="setViewMode('grid')" class="<?php echo $viewMode === 'grid' ? 'active' : ''; ?>" id="grid-view">Grid</a>
                    <a href="#" onclick="setViewMode('list')" class="<?php echo $viewMode === 'list' ? 'active' : ''; ?>" id="list-view">List</a>
                </div>

                <div class="pagination-controls" style="display:flex;align-items:center;gap:8px;">
                    <label for="items-per-page">Items per page:</label>
                    <select id="items-per-page">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50" selected>50</option>
                        <option value="all">All</option>
                    </select>
                </div>
            </div>
        </header>
        
        <?php if ($error): ?>
            <div class="error-message">
                <p>Error: <?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="tours-<?php echo $viewMode; ?>">
            <?php foreach ($toursToShow as $tour): ?>
                <?php
                    // Format image URLs
                    $imagePath = formatImageUrl($tour['imagePath'] ?? '');
                    $categoryImage = formatImageUrl($tour['categoryImage'] ?? '');
                    
                    // Determine if tour is recommended or private
                    $isRecommended = isset($tour['isRecommended']) && $tour['isRecommended'];
                    $isPrivate = isset($tour['isPrivate']) && $tour['isPrivate'];
                    // Additional mappings and derived fields
                    $categoryName = $tour['cityTourType'] ?? ($tour['categoryName'] ?? 'Uncategorized');
                    $policyName = $tour['cancellationPolicyName'] ?? ($tour['cancellationPolicy'] ?? 'Standard Policy');
                    $isSlot = filter_var($tour['isSlot'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $shortDesc = $tour['tourShortDescription'] ?? '';
                ?>
                <div class="tour-card <?php echo $viewMode; ?>" 
                     data-response="<?php echo htmlspecialchars(json_encode($tour), ENT_QUOTES); ?>"
                     data-error="<?php echo htmlspecialchars($error ?? '', ENT_QUOTES); ?>"
                     data-endpoint="<?php echo htmlspecialchars($apiEndpoint, ENT_QUOTES); ?>"
                     data-method="POST"
                     data-timestamp="<?php echo htmlspecialchars($apiTimestamp, ENT_QUOTES); ?>"
                     data-request="<?php echo htmlspecialchars(json_encode($requestData), ENT_QUOTES); ?>">
                    <div class="tour-image">
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($tour['tourName'] ?? 'Tour Image'); ?>">
                        <?php if ($isRecommended): ?>
                            <div class="badge recommended">Recommended</div>
                        <?php elseif ($isPrivate): ?>
                            <div class="badge private">Private</div>
                        <?php endif; ?>
                        <?php if ($isSlot): ?>
                            <div class="badge slot">Slot</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tour-details">
                        <div class="tour-name-row">
                            <h3 class="tour-name"><?php echo htmlspecialchars($tour['tourName'] ?? 'Unknown Tour'); ?></h3>
                            <?php if (isset($tour['tourId'])): ?>
                                <span class="tour-id-badge">ID: <?php echo htmlspecialchars($tour['tourId']); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="tour-city"><?php echo htmlspecialchars($tour['cityName'] ?? 'Unknown City'); ?></p>
                        
                        <div class="tour-meta">
                            <div class="tour-duration">
                                <?php echo htmlspecialchars($tour['duration'] ?? 'N/A'); ?>
                            </div>
                            
                            <div class="tour-rating">
                                <span class="stars">
                                    <?php
                                        $rating = isset($tour['rating']) ? (float)$tour['rating'] : 0;
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $rating ? '★' : '☆';
                                        }
                                    ?>
                                </span>
                                <span class="review-count">(<?php echo htmlspecialchars($tour['reviewCount'] ?? 0); ?>)</span>
                            </div>
                        </div>
                        
                        <div class="tour-footer">
                            <div class="tour-category">
                                <?php echo htmlspecialchars($categoryName); ?>
                            </div>
                            
                            <div class="tour-policy">
                                <?php echo htmlspecialchars($policyName); ?>
                            </div>
                        </div>
                        <?php if (!empty($shortDesc)): ?>
                            <p class="tour-short-description"><?php echo htmlspecialchars($shortDesc); ?></p>
                        <?php endif; ?>
                        
                        <div style="display:flex; gap:8px; margin-top:8px;">
                            <button class="info-icon" aria-label="View API details" aria-expanded="false" type="button">i</button>
                        </div>
                        <div class="tooltip" role="dialog" aria-modal="false" aria-hidden="true">
                            <div class="tooltip-header">
                                <div class="tooltip-title">API Response</div>
                                <div class="tooltip-meta">
                                    <div id="tooltip-endpoint">POST <?php echo htmlspecialchars($apiEndpoint); ?></div>
                                    <div id="tooltip-timestamp">Timestamp: <?php echo htmlspecialchars($apiTimestamp); ?></div>
                                </div>
                            </div>
                            <div class="tooltip-content" aria-live="polite">
                                <span class="spinner" aria-hidden="true"></span> Loading response...
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($tours)): ?>
                <p>No tours found. Please try a different search.</p>
            <?php endif; ?>
        </div>

        <!-- Client-side pagination navigation -->
        <div class="pagination-nav" id="pagination-nav" style="display: none;">
            <button id="prev-btn" onclick="changePage(-1)">Previous</button>
            <span class="page-info" id="page-info">Page 1 of 1</span>
            <button id="next-btn" onclick="changePage(1)">Next</button>
        </div>
        
        <!-- Details Modal -->
        <div id="details-modal" class="modal-overlay" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="modal">
                <div class="modal-header">
                    <div class="modal-title-row">
                        <h2 id="modal-title">Tour Details</h2>
                        <span class="modal-tour-id-badge" id="modal-tour-id"></span>
                        <span class="modal-tour-date-badge" id="modal-tour-date"></span>
                    </div>
                    <div class="modal-actions">
                        <div class="modal-tooltip">
                            <button class="modal-info-icon" id="modal-info" aria-label="View request/response">i</button>
                            <div class="tooltip" id="modal-tooltip-box" role="dialog" aria-modal="false" aria-hidden="true">
                                <div class="tooltip-header">
                                    <div class="tooltip-title">Details API</div>
                                    <div class="tooltip-meta">
                                        <div id="modal-endpoint"></div>
                                        <div id="modal-timestamp"></div>
                                        <div>Status: <span id="modal-status"></span></div>
                                    </div>
                                </div>
                                <div class="tooltip-content" id="modal-tooltip-content" aria-live="polite"></div>
                            </div>
                        </div>
                        <button class="modal-close" id="modal-close" aria-label="Close">&times;</button>
                    </div>
                </div>
                <div class="modal-content">
                    <div id="modal-banner" class="banner"></div>
                    <div id="modal-short" class="section">
                        <div class="section-header">Overview</div>
                        <div class="section-content" id="modal-short-content"></div>
                    </div>
                    <div id="modal-touroptions" class="section">
                        <div class="section-header">Tour Options</div>
                        <div class="touroptions-container" id="modal-touroptions-content"></div>
                    </div>
                    <div id="modal-keyvideo" class="section">
                        <div class="section-header">Key Information</div>
                        <div class="info-video-row">
                            <div class="section-content key-info" id="modal-keyinfo-content"></div>
                            <div class="video-col" id="modal-video"></div>
                        </div>
                    </div>
                    <div id="modal-tabs-section" class="section">
                        <div class="section-header">Details</div>
                        <div class="tabs" id="modal-tabs"></div>
                        <div class="tab-panel" id="modal-tab-content"></div>
                    </div>
                    <div id="modal-terms" class="section">
                        <div class="section-header">Terms and Conditions</div>
                        <div class="section-content" id="modal-terms-content"></div>
                    </div>
                    <div id="modal-cancel" class="section">
                        <div class="section-header">Cancellation Policy</div>
                        <div class="section-content" id="modal-cancel-content"></div>
                    </div>
                    <div id="modal-extra" class="section-group"></div>
                    <div id="modal-faq" class="section faq-section"></div>
                    <div id="modal-reviews" class="section reviews"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Global variables for filtering and pagination
        let allTours = [];
        let filteredTours = [];
        let currentPage = 1;
        let itemsPerPage = 50;
        let currentViewMode = '<?php echo $viewMode; ?>';
        const baseRequestData = <?php echo json_encode($requestData); ?>;
        
        // Initialize tours data
        document.addEventListener('DOMContentLoaded', function() {
            // Extract tour data from DOM
            const tourCards = document.querySelectorAll('.tour-card');
            allTours = Array.from(tourCards).map(card => ({
                element: card,
                name: card.querySelector('.tour-name')?.textContent?.toLowerCase() || '',
                city: card.querySelector('.tour-city')?.textContent?.toLowerCase() || '',
                category: card.querySelector('.tour-category')?.textContent?.toLowerCase() || '',
                policy: card.querySelector('.tour-policy')?.textContent?.toLowerCase() || '',
                description: card.querySelector('.tour-short-description')?.textContent?.toLowerCase() || '',
                searchText: ''
            }));
            
            // Create searchable text for each tour
            allTours.forEach(tour => {
                tour.searchText = [tour.name, tour.city, tour.category, tour.policy, tour.description].join(' ');
            });
            
            filteredTours = [...allTours];
            
            // Initialize event listeners
            initializeEventListeners();
            
            // Initial render
            renderTours();
            
            // Bind details buttons
            bindDetailsButtons();
        });
        
        function initializeEventListeners() {
            // Search input
            const searchInput = document.getElementById('search-input');
            searchInput.addEventListener('input', debounce(handleSearch, 300));
            
            // Category filter
            const categoryFilter = document.getElementById('category-filter');
            categoryFilter.addEventListener('change', handleCategoryFilter);
            
            // Items per page
            const itemsPerPageSelect = document.getElementById('items-per-page');
            itemsPerPageSelect.addEventListener('change', handleItemsPerPageChange);
        }
        
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        function handleSearch() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase().trim();
            const categoryFilter = document.getElementById('category-filter').value.toLowerCase();
            
            filteredTours = allTours.filter(tour => {
                const matchesSearch = !searchTerm || tour.searchText.includes(searchTerm);
                const matchesCategory = !categoryFilter || tour.category.includes(categoryFilter.toLowerCase());
                return matchesSearch && matchesCategory;
            });
            
            currentPage = 1;
            renderTours();
        }
        
        function handleCategoryFilter() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase().trim();
            const categoryFilter = document.getElementById('category-filter').value.toLowerCase();
            
            filteredTours = allTours.filter(tour => {
                const matchesSearch = !searchTerm || tour.searchText.includes(searchTerm);
                const matchesCategory = !categoryFilter || tour.category.includes(categoryFilter.toLowerCase());
                return matchesSearch && matchesCategory;
            });
            
            currentPage = 1;
            renderTours();
        }
        
        function handleItemsPerPageChange() {
            const newItemsPerPage = document.getElementById('items-per-page').value;
            itemsPerPage = newItemsPerPage === 'all' ? filteredTours.length : parseInt(newItemsPerPage);
            currentPage = 1;
            renderTours();
        }
        
        function renderTours() {
            // Hide all tours first
            allTours.forEach(tour => {
                tour.element.style.display = 'none';
            });
            
            // Calculate pagination
            const totalPages = itemsPerPage === filteredTours.length ? 1 : Math.ceil(filteredTours.length / itemsPerPage);
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const toursToShow = filteredTours.slice(startIndex, endIndex);
            
            // Show filtered tours
            toursToShow.forEach(tour => {
                tour.element.style.display = 'block';
            });
            
            // Update tour count
            document.getElementById('filtered-count').textContent = filteredTours.length;
            
            // Update pagination
            updatePagination(totalPages);
        }
        
        function updatePagination(totalPages) {
            const paginationNav = document.getElementById('pagination-nav');
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            const pageInfo = document.getElementById('page-info');
            
            if (totalPages <= 1) {
                paginationNav.style.display = 'none';
            } else {
                paginationNav.style.display = 'flex';
                prevBtn.disabled = currentPage === 1;
                nextBtn.disabled = currentPage === totalPages;
                pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
            }
        }
        
        function changePage(direction) {
            const totalPages = Math.ceil(filteredTours.length / itemsPerPage);
            const newPage = currentPage + direction;
            
            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                renderTours();
            }
        }
        
        function clearSearch() {
            document.getElementById('search-input').value = '';
            document.getElementById('category-filter').value = '';
            filteredTours = [...allTours];
            currentPage = 1;
            renderTours();
        }
        
        function setViewMode(mode) {
            currentViewMode = mode;
            const container = document.querySelector('.tours-container');
            const gridView = document.getElementById('grid-view');
            const listView = document.getElementById('list-view');
            
            if (mode === 'grid') {
                container.className = 'tours-container grid-view';
                gridView.classList.add('active');
                listView.classList.remove('active');
            } else {
                container.className = 'tours-container list-view';
                listView.classList.add('active');
                gridView.classList.remove('active');
            }
        }

        // ----- Details Modal Logic -----
        function sanitizeUrl(url) {
            return (url || '').replace(/[`\s]/g, '');
        }
        function largeImageUrl(path) {
            const p = sanitizeUrl(path);
            if (!p) return '';
            // If JPG/JPEG, switch small to large variant
            if (/\.(jpg|jpeg)$/i.test(p)) {
                return p.replace(/_S\.jpg$/i, '_L.jpg');
            }
            // If there is NO extension at the end, append `_L.jpg`
            const hasExt = /\.[a-z0-9]+$/i.test(p);
            if (!hasExt) {
                // Handle paths ending with _S or _L gracefully
                if (/_S$/i.test(p)) return p.replace(/_S$/i, '_L.jpg');
                if (/_L$/i.test(p)) return p + '.jpg';
                return p + '_L.jpg';
            }
            // For PNG/WEBP/GIF or other extensions, leave as is
            return p;
        }

        // Ensure a JSON renderer is available globally
        function ensureJSONRenderer() {
            if (typeof window.renderJSONTree !== 'function') {
                window.renderJSONTree = function(obj, container) {
                    const pre = document.createElement('pre');
                    try {
                        pre.textContent = JSON.stringify(obj, null, 2);
                    } catch (e) {
                        pre.textContent = String(obj);
                    }
                    container.appendChild(pre);
                };
            }
        }

        function formatDateYYYYMMDD(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }
        function datePlusDays(days) {
            const d = new Date();
            d.setDate(d.getDate() + days);
            return d;
        }

        function bindDetailsButtons() {
            document.querySelectorAll('.tour-card').forEach(card => {
                card.addEventListener('click', async (event) => {
                    // Don't trigger on info icon clicks
                    if (event.target.closest('.info-icon') || event.target.closest('.tooltip')) {
                        return;
                    }
                    
                    const responseRaw = card?.getAttribute('data-response') || '{}';
                    let tourData = {};
                    try { tourData = JSON.parse(responseRaw); } catch (e) {}
                    const tourId = tourData.tourId || tourData.TourId || tourData.TourID || tourData.tourID || tourData.tourid;
                    const contractIdRaw = tourData.contractId || tourData.contractID || tourData.ContractId || tourData.ContractID;
                    const payload = {
                        CountryId: baseRequestData.CountryId,
                        CityId: baseRequestData.cityId,
                        TourId: Number(tourId),
                        TravelDate: formatDateYYYYMMDD(datePlusDays(5))
                    };
                    if (contractIdRaw && !isNaN(Number(contractIdRaw))) {
                        payload.ContractId = Number(contractIdRaw);
                    }
                    
                    // Create separate payload for Tour Options API with lowercase property names
                    const tourOptionsPayload = {
                        tourId: Number(tourId),
                        contractId: contractIdRaw && !isNaN(Number(contractIdRaw)) ? Number(contractIdRaw) : null
                    };
                    openDetailsModal(payload, tourOptionsPayload);
                });
            });
        }

        async function openDetailsModal(payload, tourOptionsPayload) {
            ensureJSONRenderer();
            const overlay = document.getElementById('details-modal');
            const titleEl = document.getElementById('modal-title');
            const tourIdBadge = document.getElementById('modal-tour-id');
            const tourDateBadge = document.getElementById('modal-tour-date');
            const bannerEl = document.getElementById('modal-banner');
            const shortEl = document.getElementById('modal-short-content');
            const keyInfoEl = document.getElementById('modal-keyinfo-content');
            const videoEl = document.getElementById('modal-video');
            const keyVideoSection = document.getElementById('modal-keyvideo');
            const tabsEl = document.getElementById('modal-tabs');
            const tabPanelEl = document.getElementById('modal-tab-content');
            const tabsSection = document.getElementById('modal-tabs-section');
            const termsSection = document.getElementById('modal-terms');
            const termsEl = document.getElementById('modal-terms-content');
            const cancelSection = document.getElementById('modal-cancel');
            const cancelEl = document.getElementById('modal-cancel-content');
            const extraEl = document.getElementById('modal-extra');
            const reviewsEl = document.getElementById('modal-reviews');
            const faqEl = document.getElementById('modal-faq');
            const infoBtn = document.getElementById('modal-info');
            const tooltipBox = document.getElementById('modal-tooltip-box');
            const tooltipContent = document.getElementById('modal-tooltip-content');
            const endpointLabel = document.getElementById('modal-endpoint');
            const tsLabel = document.getElementById('modal-timestamp');
            const statusLabel = document.getElementById('modal-status');

            // Reset content
            titleEl.textContent = 'Loading...';
            tourIdBadge.textContent = '';
            tourDateBadge.textContent = '';
            bannerEl.innerHTML = '';
            shortEl.innerHTML = '';
            keyInfoEl.innerHTML = '';
            videoEl.innerHTML = '';
            tabsEl.innerHTML = '';
            tabPanelEl.innerHTML = '';
            termsEl.innerHTML = '';
            cancelEl.innerHTML = '';
            extraEl.innerHTML = '';
            reviewsEl.innerHTML = '';
            faqEl.innerHTML = '';
            tooltipContent.innerHTML = '';
            endpointLabel.textContent = 'POST /api/Tour/tourStaticDataById';
            const nowIso = new Date().toISOString();
            tsLabel.textContent = 'Timestamp: ' + nowIso;
            statusLabel.textContent = 'Pending';

            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');

            try {
                console.group('Details API');
                console.log('Endpoint', '/api/Tour/tourStaticDataById');
                console.log('Timestamp', nowIso);
                console.log('Request Payload', payload);

                const res = await fetch('details_proxy.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                statusLabel.textContent = String(res.status);
                if (!res.ok) {
                    const text = await res.text();
                    console.log('Response Status', res.status);
                    console.log('Response (raw)', text);
                    titleEl.textContent = `Error ${res.status}`;
                    shortEl.textContent = text || 'Failed to load details.';
                    tooltipContent.innerHTML = '';
                    const reqDiv = document.createElement('div');
                    reqDiv.innerHTML = '<span class="json-key">Request</span>:';
                    renderJSONTree(payload, reqDiv);
                    const respDiv = document.createElement('div');
                    respDiv.innerHTML = '<span class="json-key">Response (raw)</span>:';
                    const pre = document.createElement('pre');
                    pre.textContent = text;
                    respDiv.appendChild(pre);
                    tooltipContent.appendChild(reqDiv);
                    tooltipContent.appendChild(respDiv);
                    return;
                }
                let parsed;
                try {
                    parsed = await res.json();
                } catch (e) {
                    const text = await res.text();
                    console.log('Response Status', res.status);
                    console.log('Response (raw, non-JSON)', text);
                    titleEl.textContent = 'Invalid JSON response';
                    shortEl.textContent = text || 'Response could not be parsed as JSON.';
                    tooltipContent.innerHTML = '';
                    const reqDiv = document.createElement('div');
                    reqDiv.innerHTML = '<span class="json-key">Request</span>:';
                    renderJSONTree(payload, reqDiv);
                    const respDiv = document.createElement('div');
                    respDiv.innerHTML = '<span class="json-key">Response (raw)</span>:';
                    const pre = document.createElement('pre');
                    pre.textContent = text;
                    respDiv.appendChild(pre);
                    tooltipContent.appendChild(reqDiv);
                    tooltipContent.appendChild(respDiv);
                    return;
                }
                const json = parsed;
                console.log('Response Status', res.status);
                console.log('Response (JSON)', json);
                const detail = (json && (json.result || json.Result)) ? (json.result || json.Result)[0] : json;

                // Populate tooltip request/response
                tooltipContent.innerHTML = '';
                const reqDiv = document.createElement('div');
                reqDiv.innerHTML = '<span class="json-key">Request</span>:';
                renderJSONTree(payload, reqDiv);
                const respDiv = document.createElement('div');
                respDiv.innerHTML = '<span class="json-key">Response</span>:';
                renderJSONTree(json, respDiv);
                tooltipContent.appendChild(reqDiv);
                tooltipContent.appendChild(respDiv);

                // Title
                titleEl.textContent = detail.tourName || 'Tour Details';
                
                // Tour ID badge
                if (payload.TourId) {
                    tourIdBadge.textContent = `ID: ${payload.TourId}`;
                }

                // Tour date badge
                if (payload.TravelDate) {
                    tourDateBadge.textContent = `Date: ${payload.TravelDate}`;
                }

                // Banner slider
                const images = Array.isArray(detail.tourImages) ? detail.tourImages : [];
                if (images.length) {
                    let current = 0;
                    const slider = document.createElement('div');
                    slider.className = 'banner-slider';
                    const slides = document.createElement('div');
                    slides.className = 'banner-slides';
                    images.forEach(img => {
                        const url = largeImageUrl(img.imagePath || '');
                        if (!url) return;
                        const slide = document.createElement('div');
                        slide.className = 'banner-slide';
                        const imageEl = document.createElement('img');
                        imageEl.src = url;
                        imageEl.alt = img.imageCaptionName || 'Tour Image';
                        slide.appendChild(imageEl);
                        slides.appendChild(slide);
                    });
                    const nav = document.createElement('div');
                    nav.className = 'banner-nav';
                    const prevBtn = document.createElement('button'); prevBtn.textContent = '‹';
                    const nextBtn = document.createElement('button'); nextBtn.textContent = '›';
                    nav.appendChild(prevBtn); nav.appendChild(nextBtn);
                    const dots = document.createElement('div'); dots.className = 'banner-dots';
                    const dotBtns = images.map((_, i) => {
                        const b = document.createElement('button');
                        if (i === 0) b.classList.add('active');
                        dots.appendChild(b);
                        b.addEventListener('click', () => { current = i; update(); });
                        return b;
                    });
                    function update() {
                        slides.style.transform = `translateX(${-current * 100}%)`;
                        dotBtns.forEach((b, i) => b.classList.toggle('active', i === current));
                    }
                    prevBtn.addEventListener('click', () => { current = (current - 1 + images.length) % images.length; update(); });
                    nextBtn.addEventListener('click', () => { current = (current + 1) % images.length; update(); });
                    slider.appendChild(slides);
                    slider.appendChild(nav);
                    slider.appendChild(dots);
                    bannerEl.appendChild(slider);
                    update();
                }

                // Short description (2-3 sentences)
                const stripHtml = (s) => String(s || '').replace(/<[^>]*>/g, '').trim();
                const pickShort = () => {
                    const short = detail.tourShortDescription || '';
                    if (short && String(short).trim()) return stripHtml(short);
                    const desc = detail.tourDescription || detail.whatsInThisTour || '';
                    const plain = stripHtml(desc);
                    const sentences = plain.split(/([.!?])\s+/).reduce((acc, cur, i, arr) => {
                        if (i % 2 === 0) acc.push(cur + (arr[i+1] || ''));
                        return acc;
                    }, []);
                    return sentences.slice(0, 3).join(' ');
                };
                shortEl.textContent = pickShort();

                // Key Information (slimmer items, with added fields and font-based icons)
                const keyItems = [];
                const duration = detail.tourDuration || detail.duration || '10 Hours (Approx)';
                keyItems.push({ icon: '⌛', label: 'Duration', value: duration });
                const departurePoint = detail.departurePoint || detail.pickupPoint || 'Hotel (Centrally located in Dubai city)';
                keyItems.push({ icon: '📍', label: 'Departure Point', value: departurePoint });
                const reportingTime = detail.reportingTime || '';
                if (reportingTime) keyItems.push({ icon: '🕘', label: 'Reporting Time', value: reportingTime });
                const startTime = detail.startTime || detail.operatingHours || ((detail.openingTime && detail.closingTime) ? `${detail.openingTime} to ${detail.closingTime}` : '');
                if (startTime) keyItems.push({ icon: '🕒', label: 'Start Time', value: startTime });
                const language = detail.tourLanguage || detail.language || 'English / Arabic';
                keyItems.push({ icon: '🗣️', label: 'Tour Language', value: language });
                const meal = detail.meal || detail.meals || '';
                if (meal) keyItems.push({ icon: '🍽️', label: 'Meal', value: meal });
                const mapUrl = sanitizeUrl(detail.googleMapUrl || '');
                if (mapUrl) keyItems.push({ icon: '🗺️', label: 'Map', value: `<a href="${mapUrl}" target="_blank" rel="noopener">View on Google Maps</a>` });
                keyInfoEl.innerHTML = '';
                keyItems.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'key-info-item';
                    div.innerHTML = `<span class="key-icon" aria-hidden="true">${item.icon}</span>
                        <div class="key-text"><div style="font-weight:600">${item.label}</div><div>${item.value}</div></div>`;
                    keyInfoEl.appendChild(div);
                });

                // Video embed side-by-side
                const normalizeVideoUrl = (u) => {
                    const v = sanitizeUrl(u || '');
                    if (!v) return '';
                    const yt = v.match(/(?:youtu\.be\/|v=)([A-Za-z0-9_-]+)/);
                    if (yt && yt[1]) return `https://www.youtube.com/embed/${yt[1]}`;
                    return v;
                };
                const vidUrl = normalizeVideoUrl(detail.videoUrl || '');
                const infoVideoRow = document.querySelector('.info-video-row');
                if (vidUrl) {
                    videoEl.innerHTML = `<iframe src="${vidUrl}" title="Tour Video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
                    keyVideoSection.style.display = '';
                    infoVideoRow.style.gridTemplateColumns = '1.2fr 0.8fr';
                } else {
                    videoEl.innerHTML = '';
                    keyVideoSection.style.display = 'none';
                    infoVideoRow.style.gridTemplateColumns = '1fr';
                }

                // Tabs for detailed content; show only if content exists
                const tabsSpec = [
                    { key: 'tourDescription', label: 'Description' },
                    { key: 'tourInclusion', label: 'Inclusions' },
                    { key: 'importantInformation', label: 'Important Info' },
                    { key: 'itenararyDescription', label: 'Itinerary' },
                    { key: 'usefulInformation', label: 'Useful Info' },
                    { key: 'raynaToursAdvantage', label: 'Rayna Advantage' },
                    { key: 'whatsInThisTour', label: "What's In This Tour" },
                    { key: 'tourExclusion', label: 'Exclusions' },
                    { key: 'howToRedeem', label: 'How To Redeem' }
                ];
                
                // Filter tabs to only show those with content
                const tabItems = tabsSpec.filter(t => {
                    // Handle array-based inclusions and exclusions
                    if (t.key === 'tourInclusion' && Array.isArray(detail.cancellationPolicy) && detail.cancellationPolicy.length) {
                        return detail.cancellationPolicy.some(p => p.inclusion && String(p.inclusion).trim());
                    }
                    if (t.key === 'tourExclusion' && Array.isArray(detail.cancellationPolicy) && detail.cancellationPolicy.length) {
                        return detail.cancellationPolicy.some(p => p.exclusion && String(p.exclusion).trim());
                    }
                    // Handle regular fields
                    return detail[t.key] && String(detail[t.key]).trim();
                });
                
                tabsEl.innerHTML = '';
                tabPanelEl.innerHTML = '';
                if (tabItems.length) {
                    let activeIndex = 0;
                    const renderTab = (idx) => {
                        activeIndex = idx;
                        // buttons active state
                        [...tabsEl.children].forEach((b, i) => b.classList.toggle('active', i === activeIndex));
                        // content
                        const item = tabItems[activeIndex];
                        let content = '';
                        
                        // Handle array-based inclusions and exclusions
                        if (item.key === 'tourInclusion' && Array.isArray(detail.cancellationPolicy) && detail.cancellationPolicy.length) {
                            content = detail.cancellationPolicy.map(p => p.inclusion).filter(Boolean).join('<br>');
                        } else if (item.key === 'tourExclusion' && Array.isArray(detail.cancellationPolicy) && detail.cancellationPolicy.length) {
                            content = detail.cancellationPolicy.map(p => p.exclusion).filter(Boolean).join('<br>');
                        } else {
                            content = detail[item.key] || '';
                        }
                        
                        tabPanelEl.innerHTML = `<div class="section-content">${content}</div>`;
                    };
                    tabItems.forEach((t, i) => {
                        const btn = document.createElement('button');
                        btn.textContent = t.label;
                        if (i === 0) btn.classList.add('active');
                        btn.addEventListener('click', () => renderTab(i));
                        tabsEl.appendChild(btn);
                    });
                    renderTab(0);
                    tabsSection.style.display = '';
                } else {
                    tabsSection.style.display = 'none';
                }

                // Terms and Conditions section
                if (detail.termsAndConditions && String(detail.termsAndConditions).trim()) {
                    termsEl.innerHTML = detail.termsAndConditions;
                    termsSection.style.display = '';
                } else {
                    termsEl.innerHTML = '';
                    termsSection.style.display = 'none';
                }

                // Cancellation Policy section
                let cancelHtml = '';
                
                // Handle array-based cancellation policies (new format)
                if (Array.isArray(detail.cancellationPolicy) && detail.cancellationPolicy.length) {
                    detail.cancellationPolicy.forEach(policy => {
                        const adultName = policy.cancellationPolicy || '';
                        const adultDesc = policy.cancellationPolicyDescription || '';
                        const childName = policy.childPolicy || '';
                        const childDesc = policy.childPolicyDescription || '';
                        const childAge = policy.childAge || '';
                        const infantAge = policy.infantAge || '';
                        
                        if (adultName || adultDesc) {
                            cancelHtml += `<div><div style="font-weight:600">${adultName}</div><div>${adultDesc}</div></div>`;
                        }
                        if (childName || childDesc) {
                            cancelHtml += `<div style="margin-top:8px"><div style="font-weight:600">${childName}</div><div>${childDesc}</div></div>`;
                        }
                        if (childAge || infantAge) {
                            cancelHtml += `<div style="margin-top:6px; font-size:13px; color:#555;">${childAge ? `Child Age: ${childAge}` : ''}${childAge && infantAge ? ' • ' : ''}${infantAge ? `Infant Age: ${infantAge}` : ''}</div>`;
                        }
                    });
                } else {
                    // Handle flat field cancellation policies (old format)
                    const adultName = detail.cancellationPolicyName || detail.cancellationPolicy || '';
                    const adultDesc = detail.cancellationPolicyDescription || '';
                    const childName = detail.childCancellationPolicyName || detail.childPolicy || '';
                    const childDesc = detail.childCancellationPolicyDescription || detail.childPolicyDescription || '';
                    const childAge = detail.childAge || '';
                    const infantAge = detail.infantAge || '';
                    
                    if (adultName || adultDesc) {
                        cancelHtml += `<div><div style="font-weight:600">${adultName}</div><div>${adultDesc}</div></div>`;
                    }
                    if (childName || childDesc) {
                        cancelHtml += `<div style="margin-top:8px"><div style="font-weight:600">${childName}</div><div>${childDesc}</div></div>`;
                    }
                    if (childAge || infantAge) {
                        cancelHtml += `<div style="margin-top:6px; font-size:13px; color:#555;">${childAge ? `Child Age: ${childAge}` : ''}${childAge && infantAge ? ' • ' : ''}${infantAge ? `Infant Age: ${infantAge}` : ''}</div>`;
                    }
                }
                
                if (cancelHtml) {
                    cancelEl.innerHTML = cancelHtml;
                    cancelSection.style.display = '';
                } else {
                    cancelEl.innerHTML = '';
                    cancelSection.style.display = 'none';
                }

                // Reviews
                if (Array.isArray(detail.tourReview) && detail.tourReview.length) {
                    const head = document.createElement('div');
                    head.className = 'section-header';
                    head.textContent = `Reviews (${detail.reviewCount || detail.tourReview.length})`;
                    reviewsEl.appendChild(head);
                    detail.tourReview.slice(0, 6).forEach(r => {
                        const ri = document.createElement('div');
                        ri.className = 'review-item';
                        ri.innerHTML = `<div style=\"font-weight:600;\">${r.reviewTitle || ''} <span style=\"color:#f39c12;\">${'★'.repeat(Number(r.rating||0))}</span></div>
                            <div style=\"font-size:13px;color:#555;\">${r.reviewContent || ''}</div>
                            <div style=\"font-size:12px;color:#777;\">${r.guestName || ''} ${r.visitMonth ? '• '+r.visitMonth : ''}</div>`;
                        reviewsEl.appendChild(ri);
                    });
                }

                // FAQs (render provided HTML as-is, styled)
                if (detail.faqDetails && String(detail.faqDetails).trim()) {
                    const head = document.createElement('div');
                    head.className = 'section-header';
                    head.textContent = 'FAQs';
                    const detailsEl = document.createElement('details');
                    const summaryEl = document.createElement('summary');
                    summaryEl.textContent = 'Show FAQs';
                    const content = document.createElement('div');
                    content.className = 'section-content';
                    content.innerHTML = detail.faqDetails;
                    detailsEl.appendChild(summaryEl);
                    detailsEl.appendChild(content);
                    faqEl.appendChild(head);
                    faqEl.appendChild(detailsEl);
                }

                // Load Tour Options
                console.log('Payload for tour options:', tourOptionsPayload);
                if (tourOptionsPayload && tourOptionsPayload.tourId && tourOptionsPayload.contractId) {
                    console.log('Calling loadTourOptions with tourId:', tourOptionsPayload.tourId, 'contractId:', tourOptionsPayload.contractId);
                    // Add a small delay to ensure the modal is fully rendered before making the API call
                    setTimeout(() => {
                        loadTourOptions(tourOptionsPayload.tourId, tourOptionsPayload.contractId);
                    }, 100);
                } else {
                    console.log('Missing tourId or contractId in tourOptionsPayload:', tourOptionsPayload);
                }
            } catch (e) {
                console.error('Details fetch error', e);
                statusLabel.textContent = 'Error';
                tooltipContent.innerHTML = '';
                const reqDiv = document.createElement('div');
                reqDiv.innerHTML = '<span class="json-key">Request</span>:';
                renderJSONTree(payload, reqDiv);
                const errDiv = document.createElement('div');
                errDiv.innerHTML = '<span class="json-key">Error</span>:';
                const pre = document.createElement('pre');
                pre.textContent = (e && e.message) ? e.message : String(e);
                errDiv.appendChild(pre);
                tooltipContent.appendChild(reqDiv);
                tooltipContent.appendChild(errDiv);
                console.groupEnd();
                titleEl.textContent = 'Failed to load details';
                shortEl.textContent = 'An error occurred while fetching tour details.';
            }
            console.groupEnd();

            // Close handlers
            document.getElementById('modal-close').onclick = () => closeDetailsModal();
            overlay.addEventListener('click', (ev) => {
                if (ev.target === overlay) closeDetailsModal();
            });
            // Tooltip toggle in modal + outside click to close
            infoBtn.onclick = () => {
                tooltipBox.classList.toggle('visible');
            };
            const __modalTooltipHandler = (ev) => {
                const isVisible = tooltipBox.classList.contains('visible');
                if (!isVisible) return;
                if (!tooltipBox.contains(ev.target) && ev.target !== infoBtn) {
                    tooltipBox.classList.remove('visible');
                }
            };
            document.addEventListener('click', __modalTooltipHandler);
            window.__modalTooltipHandler = __modalTooltipHandler;

            // Esc to close modal
            const __modalKeyHandler = (e) => {
                if (e.key === 'Escape') closeDetailsModal();
            };
            document.addEventListener('keydown', __modalKeyHandler);
            window.__modalKeyHandler = __modalKeyHandler;
            
            // Dispatch modalOpened event to trigger auto-load of pricing and availability
            document.dispatchEvent(new CustomEvent('modalOpened'));
        }

        function closeDetailsModal() {
            const overlay = document.getElementById('details-modal');
            const tooltipBox = document.getElementById('modal-tooltip-box');
            overlay.style.display = 'none';
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            // Remove modal-specific listeners
            if (window.__modalTooltipHandler) {
                document.removeEventListener('click', window.__modalTooltipHandler);
                window.__modalTooltipHandler = null;
            }
            if (window.__modalKeyHandler) {
                document.removeEventListener('keydown', window.__modalKeyHandler);
                window.__modalKeyHandler = null;
            }
            if (tooltipBox) tooltipBox.classList.remove('visible');
        }
        
        // Tooltip and formatting logic
        document.addEventListener('DOMContentLoaded', function() {
            const escapeHtml = (unsafe) => (
                unsafe.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;')
                      .replace(/"/g, '&quot;')
                      .replace(/'/g, '&#039;')
            );

            const isXMLString = (str) => {
                return typeof str === 'string' && /<[^>]+>/.test(str.trim());
            };

            const createValueSpan = (value) => {
                const span = document.createElement('span');
                if (value === null) { span.className = 'json-null'; span.textContent = 'null'; return span; }
                switch (typeof value) {
                    case 'string': span.className = 'json-string'; span.textContent = '"' + value + '"'; break;
                    case 'number': span.className = 'json-number'; span.textContent = String(value); break;
                    case 'boolean': span.className = 'json-boolean'; span.textContent = String(value); break;
                    default: span.textContent = String(value);
                }
                return span;
            };

            const renderJSONTree = (obj, container) => {
                const buildEntry = (key, value) => {
                    const entry = document.createElement('div');
                    entry.className = 'json-entry';
                    if (value && typeof value === 'object') {
                        const details = document.createElement('details');
                        details.open = false;
                        const summary = document.createElement('summary');
                        const keySpan = document.createElement('span');
                        keySpan.className = 'json-key';
                        keySpan.textContent = key + (Array.isArray(value) ? ' [ ]' : ' { }');
                        summary.appendChild(keySpan);
                        details.appendChild(summary);
                        const childContainer = document.createElement('div');
                        Object.entries(value).forEach(([k, v]) => {
                            childContainer.appendChild(buildEntry(k, v));
                        });
                        details.appendChild(childContainer);
                        entry.appendChild(details);
                    } else {
                        const keySpan = document.createElement('span');
                        keySpan.className = 'json-key';
                        keySpan.textContent = key + ': ';
                        entry.appendChild(keySpan);
                        entry.appendChild(createValueSpan(value));
                    }
                    return entry;
                };
                const root = document.createElement('div');
                Object.entries(obj).forEach(([k, v]) => {
                    root.appendChild(buildEntry(k, v));
                });
                container.appendChild(root);
            };

            const renderXML = (xmlString, container) => {
                const escaped = escapeHtml(xmlString);
                // Simple syntax highlight for tags/attributes
                const highlighted = escaped
                    .replace(/(&lt;\/?)([\w:-]+)([^&]*?)(\s?&gt;)/g, (m, open, name, attrs, close) => {
                        return `${open}<span style="color:#9cdcfe">${name}</span>${attrs}${close}`;
                    })
                    .replace(/([\w:-]+)=("[^"]*"|'[^']*')/g, (m, attr, val) => {
                        return `<span style="color:#b5cea8">${attr}</span>=<span class="json-string">${val}</span>`;
                    });
                const pre = document.createElement('pre');
                pre.style.whiteSpace = 'pre-wrap';
                pre.style.wordBreak = 'break-word';
                pre.innerHTML = highlighted;
                container.appendChild(pre);
            };

            function toggleTooltip(card, icon, tooltip) {
                const isVisible = tooltip.classList.contains('visible');
                if (isVisible) {
                    tooltip.classList.remove('visible');
                    tooltip.setAttribute('aria-hidden', 'true');
                    icon.setAttribute('aria-expanded', 'false');
                    return;
                }
                // Show loading state
                tooltip.classList.add('visible');
                tooltip.setAttribute('aria-hidden', 'false');
                icon.setAttribute('aria-expanded', 'true');

                const content = tooltip.querySelector('.tooltip-content');
                content.innerHTML = '<span class="spinner" aria-hidden="true"></span> Loading response...';

                const error = card.getAttribute('data-error');
                const responseRaw = card.getAttribute('data-response');
                const timestamp = card.getAttribute('data-timestamp');
                const endpoint = card.getAttribute('data-endpoint');
                const method = card.getAttribute('data-method') || 'GET';
                const requestRaw = card.getAttribute('data-request') || '';

                // Update header meta
                const metaEndpoint = tooltip.querySelector('#tooltip-endpoint');
                const metaTs = tooltip.querySelector('#tooltip-timestamp');
                if (metaEndpoint) metaEndpoint.textContent = method + ' ' + endpoint;
                if (metaTs) metaTs.textContent = 'Timestamp: ' + timestamp;

                if (error) {
                    content.innerHTML = '<div class="tooltip-error">API request failed: ' + escapeHtml(error) + '</div>';
                    return;
                }

                // Build structured view
                try {
                    const json = JSON.parse(responseRaw);
                    content.innerHTML = '';
                    // Request summary
                    if (requestRaw) {
                        const reqDiv = document.createElement('div');
                        reqDiv.style.marginBottom = '8px';
                        reqDiv.innerHTML = '<span class="json-key">Request</span>:';
                        try {
                            const reqJson = JSON.parse(requestRaw);
                            renderJSONTree(reqJson, reqDiv);
                        } catch (e) {
                            const pre = document.createElement('pre');
                            pre.textContent = requestRaw;
                            reqDiv.appendChild(pre);
                        }
                        content.appendChild(reqDiv);
                    }
                    const respDiv = document.createElement('div');
                    respDiv.innerHTML = '<span class="json-key">Response</span>:';
                    renderJSONTree(json, respDiv);
                    content.appendChild(respDiv);
                } catch (e) {
                    // Not JSON, try XML or plain text
                    content.innerHTML = '';
                    if (isXMLString(responseRaw)) {
                        renderXML(responseRaw, content);
                    } else {
                        const pre = document.createElement('pre');
                        pre.textContent = responseRaw;
                        content.appendChild(pre);
                    }
                }

                // Close on click outside
                const onDocClick = (ev) => {
                    if (!tooltip.contains(ev.target) && ev.target !== icon) {
                        tooltip.classList.remove('visible');
                        tooltip.setAttribute('aria-hidden', 'true');
                        icon.setAttribute('aria-expanded', 'false');
                        document.removeEventListener('click', onDocClick);
                    }
                };
                setTimeout(() => document.addEventListener('click', onDocClick), 0);
            }

            document.querySelectorAll('.tour-card').forEach(card => {
                const icon = card.querySelector('.info-icon');
                const tooltip = card.querySelector('.tooltip');
                if (!icon || !tooltip) return;
                icon.addEventListener('click', () => toggleTooltip(card, icon, tooltip));
                icon.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleTooltip(card, icon, tooltip);
                    }
                });
            });

            // Add event listeners for tour option info icons
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('info-icon') && e.target.closest('.touroption-card')) {
                    const card = e.target.closest('.touroption-card');
                    const tooltip = card.querySelector('.tooltip');
                    if (card && tooltip) {
                        toggleTooltip(card, e.target, tooltip);
                    }
                }
            });

            // Add event listeners for modal info icons (transfer and availability) - tooltip only, no API calls
            document.addEventListener('click', function(e) {
                const infoBtn = e.target.closest('.modal-info-icon');
                if (infoBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    const container = infoBtn.closest('.transfer-pricing, .availability-container, .availability-badge, .pricing-section, .touroption-card, .tour-list-item, .card, .modal-section');
                    const tooltipBox = document.getElementById('modal-tooltip-box');
                    const tooltipContent = document.getElementById('modal-tooltip-content');
                    if (container && tooltipBox && tooltipContent) {
                        // Toggle off if already visible
                        if (tooltipBox.classList.contains('visible') && infoBtn.getAttribute('aria-expanded') === 'true') {
                            tooltipBox.classList.remove('visible');
                            tooltipBox.setAttribute('aria-hidden', 'true');
                            infoBtn.setAttribute('aria-expanded', 'false');
                            return;
                        }
                        // Prefer nearest element that has stored data
                        const sourceEl = container.querySelector('[data-response]') || container.querySelector('.availability-badge[data-response]') || container;
                        const requestData = sourceEl.getAttribute('data-request');
                        const responseData = sourceEl.getAttribute('data-response');
                        const endpoint = infoBtn.getAttribute('data-endpoint');
                        const method = infoBtn.getAttribute('data-method');

                        // Populate tooltip content
                        tooltipContent.innerHTML = '';
                        const endpointDiv = document.createElement('div');
                        endpointDiv.innerHTML = `<span class="json-key">Endpoint:</span> ${method} ${endpoint}`;
                        tooltipContent.appendChild(endpointDiv);

                        if (requestData) {
                            const reqDiv = document.createElement('div');
                            reqDiv.innerHTML = '<span class="json-key">Request:</span>';
                            renderJSONTree(JSON.parse(requestData), reqDiv);
                            tooltipContent.appendChild(reqDiv);
                        }

                        if (responseData) {
                            const respDiv = document.createElement('div');
                            respDiv.innerHTML = '<span class="json-key">Response:</span>';
                            renderJSONTree(JSON.parse(responseData), respDiv);
                            tooltipContent.appendChild(respDiv);
                        }

                        // Show tooltip
                        tooltipBox.classList.add('visible');
                        tooltipBox.setAttribute('aria-hidden', 'false');
                        infoBtn.setAttribute('aria-expanded', 'true');

                        // Close tooltip on outside click
                        const onDocClick = (event) => {
                            if (!tooltipBox.contains(event.target) && event.target !== infoBtn) {
                                tooltipBox.classList.remove('visible');
                                tooltipBox.setAttribute('aria-hidden', 'true');
                                infoBtn.setAttribute('aria-expanded', 'false');
                                document.removeEventListener('click', onDocClick);
                            }
                        };
                        setTimeout(() => document.addEventListener('click', onDocClick), 0);
                    }
                }
            });

            // Close all tooltips on Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.tooltip.visible').forEach(t => {
                        const icon = t.parentElement.querySelector('.info-icon');
                        t.classList.remove('visible');
                        t.setAttribute('aria-hidden', 'true');
                        if (icon) icon.setAttribute('aria-expanded', 'false');
                    });
                }
            });
        });

        // Tour Options API Functions
        async function fetchTourOptions(tourId, contractId) {
            console.log('Fetching tour options for tourId:', tourId, 'contractId:', contractId);
            try {
                console.log('Making API request to staticDetailsOptions_proxy.php with payload:', {tourId, contractId});
            const response = await fetch('staticDetailsOptions_proxy.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        tourId: tourId,
                        contractId: contractId
                    })
                });

                console.log('API Response status:', response.status);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('HTTP error! status:', response.status, 'response:', errorText);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('API Response data:', data);
                
                // Store response data for tooltips
                setTimeout(() => {
                    document.querySelectorAll('.touroption-card').forEach((card, index) => {
                        const optionData = data.touroption && data.touroption[index] ? data.touroption[index] : null;
                        if (optionData) {
                            card.setAttribute('data-response', JSON.stringify(data));
                            card.setAttribute('data-request', JSON.stringify({ tourId, contractId }));
                            card.setAttribute('data-endpoint', 'staticDetailsOptions_proxy.php');
                            card.setAttribute('data-method', 'POST');
                            card.setAttribute('data-timestamp', new Date().toISOString());
                        }
                    });
                }, 100);
                
                return data.result;
            } catch (error) {
                console.error('Error fetching tour options:', error);
                return null;
            }
        }

        // Helper function to get correct proxy URL
        function getProxyUrl(proxyFile) {
            // Use relative path for most cases
            return proxyFile;
        }

        // New API function to fetch tour option details with pricing
        async function fetchTourOptionDetails(tourId, contractId, travelDate, noOfAdult = 1, noOfChild = 0, noOfInfant = 0) {
            console.log('Fetching tour option details for tourId:', tourId, 'contractId:', contractId, 'travelDate:', travelDate);
            try {
                const response = await fetch(getProxyUrl('touroption_proxy.php'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        tourId: parseInt(tourId),
                        contractId: parseInt(contractId),
                        travelDate: travelDate,
                        noOfAdult: parseInt(noOfAdult),
                        noOfChild: parseInt(noOfChild),
                        noOfInfant: parseInt(noOfInfant)
                    })
                });

                console.log('Tour Option Details API Response status:', response.status);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('HTTP error! status:', response.status, 'response:', errorText);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Tour Option Details API Response data:', data);
                return data;
            } catch (error) {
                console.error('Error fetching tour option details:', error);
                return null;
            }
        }

        // New API function to check availability
        async function checkAvailability(tourId, tourOptionId, travelDate, transferId, adult = 1, child = 0, infant = 0, contractId = 300) {
            console.log('Checking availability for tourId:', tourId, 'tourOptionId:', tourOptionId, 'transferId:', transferId);
            try {
                const response = await fetch(getProxyUrl('availability_proxy.php'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        tourId: parseInt(tourId),
                        tourOptionId: parseInt(tourOptionId),
                        travelDate: travelDate,
                        transferId: parseInt(transferId),
                        adult: parseInt(adult),
                        child: parseInt(child),
                        infant: parseInt(infant),
                        contractId: parseInt(contractId)
                    })
                });

                console.log('Availability API Response status:', response.status);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('HTTP error! status:', response.status, 'response:', errorText);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Availability API Response data:', data);
                
                // Process the response according to the new format
                // status: 1 means available, other values mean unavailable
                if (data && data.result) {
                    return {
                        ...data,
                        isAvailable: data.result.status === 1
                    };
                }
                
                return data;
            } catch (error) {
                console.error('Error checking availability:', error);
                return null;
            }
        }

        // New API function to fetch time slots for a transfer
        // Helper to normalize date strings to YYYY-MM-DD
        function normalizeDateYMD(dateStr) {
            if (!dateStr) return '';
            let normalized = dateStr;
            if (normalized.includes('-')) {
                const parts = normalized.split('-');
                if (parts.length === 3) {
                    if (parts[0].length === 4) {
                        normalized = `${parts[0]}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')}`;
                    } else {
                        normalized = `${parts[2]}-${parts[0].padStart(2, '0')}-${parts[1].padStart(2, '0')}`;
                    }
                }
            }
            return normalized;
        }

        async function fetchTimeSlots(tourId, tourOptionId, travelDate, transferId, adult = 1, child = 0, infant = 0, contractId = 300) {
            console.log('Fetching time slots for tourId:', tourId, 'tourOptionId:', tourOptionId, 'transferId:', transferId);
            try {
                // Normalize travelDate to YYYY-MM-DD for timeslot payload
                let normalizedDate = normalizeDateYMD(travelDate);
                const response = await fetch(getProxyUrl('timeslot_proxy.php'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        tourId: parseInt(tourId),
                        tourOptionId: parseInt(tourOptionId),
                        travelDate: normalizedDate,
                        transferId: parseInt(transferId),
                        adult: parseInt(adult),
                        child: parseInt(child),
                        infant: parseInt(infant),
                        contractId: parseInt(contractId)
                    })
                });

                console.log('Time Slots API Response status:', response.status);
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('HTTP error! status:', response.status, 'response:', errorText);
                    throw new Error('HTTP error! status: ' + response.status);
                }

                const data = await response.json();
                console.log('Time Slots API Response data:', data);
                return data;
            } catch (error) {
                console.error('Error fetching time slots:', error);
                return null;
            }
        }

        function renderTourOptions(tourOptionsData, tourId, contractId) {
            console.log('Rendering tour options with data:', tourOptionsData, 'tourId:', tourId, 'contractId:', contractId);
            const container = document.getElementById('modal-touroptions-content');
            if (!container || !tourOptionsData) return;

            // Check if the data structure matches our expectations
            if (!tourOptionsData.touroption) {
                console.log('Tour options data structure unexpected:', Object.keys(tourOptionsData));
                container.innerHTML = '<p class="no-data">Unexpected data format received.</p>';
                return;
            }

            const { touroption, operationdays, specialdates, transfertime } = tourOptionsData;

            if (!touroption || touroption.length === 0) {
                container.innerHTML = '<p class="no-data">No tour options available.</p>';
                return;
            }

            container.innerHTML = touroption.map(option => {
                const optionId = option.tourOptionId;
                const optionOperationDays = operationdays?.find(od => od.tourOptionId === optionId);
                const optionSpecialDates = specialdates?.filter(sd => sd.tourOptionId === optionId);
                const optionTransferTimes = transfertime?.filter(tt => tt.tourOptionId === optionId);

                return `
                    <div class="touroption-card">
                        <div class="touroption-header">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <h3 class="touroption-name">${option.optionName || 'Unnamed Option'}</h3>
                                ${optionId ? `<span class="touroption-id-badge">ID: ${optionId}</span>` : ''}
                            </div>
                            <button class="info-icon" aria-label="View API details" aria-expanded="false" type="button" style="position: absolute; top: 16px; right: 16px;">i</button>
                        </div>
                        <div class="tooltip" role="dialog" aria-modal="false" aria-hidden="true" style="top: 40px; right: 16px; bottom: auto;">
                            <div class="tooltip-header">
                                <div class="tooltip-title">Tour Options API</div>
                                <div class="tooltip-meta">
                                    <div>POST /staticDetailsOptions_proxy.php</div>
                                    <div>Tour ID: ${tourId}</div>
                                    <div>Contract ID: ${contractId}</div>
                                </div>
                            </div>
                            <div class="tooltip-content" aria-live="polite">
                                <span class="spinner" aria-hidden="true"></span> Loading response...
                            </div>
                        </div>
                        
                        ${option.optionDescription ? `
                            <div class="touroption-description">
                                ${option.optionDescription}
                            </div>
                        ` : ''}

                        <div class="touroption-details">
                            ${option.childAge ? `
                                <div class="touroption-detail-item">
                                    <span class="touroption-detail-label">Child Age:</span>
                                    <span class="touroption-detail-value">${option.childAge}</span>
                                </div>
                            ` : ''}

                            ${option.infantAge ? `
                                <div class="touroption-detail-item">
                                    <span class="touroption-detail-label">Infant Age:</span>
                                    <span class="touroption-detail-value">${option.infantAge}</span>
                                </div>
                            ` : ''}

                            ${option.duration ? `
                                <div class="touroption-detail-item">
                                    <span class="touroption-detail-label">Duration:</span>
                                    <span class="touroption-detail-value">${option.duration}</span>
                                </div>
                            ` : ''}

                            ${option.timeZone ? `
                                <div class="touroption-detail-item">
                                    <span class="touroption-detail-label">Time Zone:</span>
                                    <span class="touroption-detail-value">${option.timeZone}</span>
                                </div>
                            ` : ''}

                            ${option.minPax !== undefined ? `
                                <div class="touroption-detail-item">
                                    <span class="touroption-detail-label">Min Pax:</span>
                                    <span class="touroption-detail-value">${option.minPax}</span>
                                </div>
                            ` : ''}

                            ${option.maxPax !== undefined ? `
                                <div class="touroption-detail-item">
                                    <span class="touroption-detail-label">Max Pax:</span>
                                    <span class="touroption-detail-value">${option.maxPax}</span>
                                </div>
                            ` : ''}

                            ${option.isWithoutAdult !== undefined ? `
                                <div class="touroption-detail-item">
                                    <span class="touroption-detail-label">Without Adult:</span>
                                    <span class="touroption-detail-value">${option.isWithoutAdult ? 'Yes' : 'No'}</span>
                                </div>
                            ` : ''}

                            ${option.isTourGuide !== undefined ? `
                                <div class="touroption-detail-item">
                                    <span class="touroption-detail-label">Tour Guide:</span>
                                    <span class="touroption-detail-value">${option.isTourGuide ? 'Yes' : 'No'}</span>
                                </div>
                            ` : ''}

                            ${option.compulsoryOptions !== undefined ? `
                                <div class="touroption-detail-item">
                                    <span class="touroption-detail-label">Compulsory Options:</span>
                                    <span class="touroption-detail-value">${option.compulsoryOptions ? 'Yes' : 'No'}</span>
                                </div>
                            ` : ''}

                            ${option.isHourly !== undefined ? `
                                <div class="touroption-detail-item">
                                    <span class="touroption-detail-label">Hourly:</span>
                                    <span class="touroption-detail-value">${option.isHourly ? 'Yes' : 'No'}</span>
                                </div>
                            ` : ''}

                            ${option.googleNavigation ? `
                                <div class="touroption-detail-item">
                                    <span class="touroption-detail-label">Google Navigation:</span>
                                    <span class="touroption-detail-value">${option.googleNavigation}</span>
                                </div>
                            ` : ''}

                            ${option.address ? `
                                <div class="touroption-detail-item">
                                    <span class="touroption-detail-label">Address:</span>
                                    <span class="touroption-detail-value">${option.address}</span>
                                </div>
                            ` : ''}
                        </div>

                        ${optionOperationDays ? `
                            <div class="touroption-section">
                                <h4 class="touroption-section-header">Operation Days</h4>
                                <div class="touroption-section-content operation-days">
                                    ${renderOperationDays(optionOperationDays)}
                                </div>
                            </div>
                        ` : ''}

                        ${optionSpecialDates && optionSpecialDates.length > 0 ? `
                            <div class="touroption-section">
                                <h4 class="touroption-section-header">Special Dates</h4>
                                <div class="touroption-section-content">
                                    ${optionSpecialDates.map(date => `
                                        <div class="special-date-item">
                                            ${date.specialDateName || 'Special Date'}: ${date.specialDate || 'N/A'}
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}

                        ${optionTransferTimes && optionTransferTimes.length > 0 ? `
                            <div class="touroption-section">
                                <h4 class="touroption-section-header">Transfer Times</h4>
                                <div class="touroption-section-content">
                                    ${optionTransferTimes.map(transfer => `
                                        <div class="transfer-time-item">
                                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                                <span class="transfer-time-type">${transfer.transferType || 'Transfer'}:</span>
                                                ${transfer.transferId ? `<span class="transfer-id-badge">ID: ${transfer.transferId}</span>` : ''}
                                                <div class="availability-container">
                                                    <span class="availability-badge" data-tour-id="${tourId}" data-tour-option-id="${optionId}" data-transfer-id="${transfer.transferId}" data-transfer-name="${transfer.transferType || ''}" style="cursor: pointer;">
                                                        Check Availability
                                                    </span>
                                                    <button class="modal-info-icon" style="margin-left: 4px;" aria-label="View availability API details" data-endpoint="https://sandbox.raynatours.com/api/Tour/availability" data-method="POST" data-request='{"tourId":${tourId},"tourOptionId":${optionId},"travelDate":"","transferId":${transfer.transferId},"adult":1,"child":0,"infant":0,"contractId":${contractId}}'>i</button>
                                                    <button class="book-button" style="display: none; margin-left: 8px;" data-tour-id="${tourId}" data-tour-option-id="${optionId}" data-transfer-id="${transfer.transferId}" data-transfer-name="${transfer.transferType || ''}" data-contract-id="${contractId}">Book</button>
                                                </div>
                                            </div>
                                            <span class="transfer-time-details">${transfer.transferTime || 'N/A'}</span>
                                            
                                            <div class="transfer-details-section">
                                                ${option.duration ? `
                                                    <div class="transfer-detail-item">
                                                        <span class="transfer-detail-label">Duration:</span>
                                                        <span class="transfer-detail-value">${option.duration}</span>
                                                    </div>
                                                ` : ''}
                                                
                                                ${option.mobileVoucher !== undefined ? `
                                                    <div class="transfer-detail-item">
                                                        <span class="transfer-detail-label">Mobile Voucher:</span>
                                                        <span class="transfer-detail-value">${option.mobileVoucher ? 'Yes' : 'No'}</span>
                                                    </div>
                                                ` : ''}
                                                
                                                ${option.printedVoucher !== undefined ? `
                                                    <div class="transfer-detail-item">
                                                        <span class="transfer-detail-label">Printed Voucher:</span>
                                                        <span class="transfer-detail-value">${option.printedVoucher ? 'Yes' : 'No'}</span>
                                                    </div>
                                                ` : ''}
                                                
                                                ${option.instantConfirmation !== undefined ? `
                                                    <div class="transfer-detail-item">
                                                        <span class="transfer-detail-label">Instant Confirmation:</span>
                                                        <span class="transfer-detail-value">${option.instantConfirmation ? 'Yes' : 'No'}</span>
                                                    </div>
                                                ` : ''}
                                                
                                                ${option.cancellationPolicy ? `
                                                    <div class="transfer-detail-item">
                                                        <span class="transfer-detail-label">Cancellation Policy:</span>
                                                        <span class="transfer-detail-value">${option.cancellationPolicy}</span>
                                                    </div>
                                                ` : ''}
                                                
                                                ${option.cancellationPolicyDescription ? `
                                                    <div class="transfer-detail-item">
                                                        <span class="transfer-detail-label">Cancellation Details:</span>
                                                        <span class="transfer-detail-value">${option.cancellationPolicyDescription}</span>
                                                    </div>
                                                ` : ''}
                                                
                                                ${option.childPolicy ? `
                                                    <div class="transfer-detail-item">
                                                        <span class="transfer-detail-label">Child Policy:</span>
                                                        <span class="transfer-detail-value">${option.childPolicy}</span>
                                                    </div>
                                                ` : ''}
                                                
                                                ${option.childPolicyDescription ? `
                                                    <div class="transfer-detail-item">
                                                        <span class="transfer-detail-label">Child Policy Details:</span>
                                                        <span class="transfer-detail-value">${option.childPolicyDescription}</span>
                                                    </div>
                                                ` : ''}
                                                
                                                ${option.cutOffhrs ? `
                                                    <div class="transfer-detail-item">
                                                        <span class="transfer-detail-label">Cut-off Hours:</span>
                                                        <span class="transfer-detail-value">${option.cutOffhrs}</span>
                                                    </div>
                                                ` : ''}
                                                
                                                ${option.inclusion ? `
                                                    <div class="transfer-detail-item">
                                                        <span class="transfer-detail-label">Inclusions:</span>
                                                        <span class="transfer-detail-value">${option.inclusion}</span>
                                                    </div>
                                                ` : ''}
                                                
                                                ${option.exclusion ? `
                                                    <div class="transfer-detail-item">
                                                        <span class="transfer-detail-label">Exclusions:</span>
                                                        <span class="transfer-detail-value">${option.exclusion}</span>
                                                    </div>
                                                ` : ''}
                                                
                                                <div class="transfer-pricing" data-tour-id="${tourId}" data-contract-id="${contractId}" data-tour-option-id="${optionId}" data-transfer-name="${transfer.transferType || ''}">
                                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                                        <strong>Pricing Details</strong>
                                                        <button class="modal-info-icon" aria-label="View pricing API details" data-endpoint="https://sandbox.raynatours.com/api/Tour/touroption" data-method="POST" data-request='{"tourId":${tourId},"contractId":${contractId},"travelDate":"","noOfAdult":1,"noOfChild":0,"noOfInfant":0}'>i</button>
                                                    </div>
                                                    <div class="loading-spinner" style="font-size: 12px;">Loading pricing...</div>
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}

                        ${option.cancellationPolicy ? `
                            <div class="touroption-section">
                                <h4 class="touroption-section-header">Cancellation Policy</h4>
                                <div class="touroption-section-content">
                                    ${option.cancellationPolicy}
                                </div>
                            </div>
                        ` : ''}

                        ${option.cancellationPolicyDescription ? `
                            <div class="touroption-section">
                                <h4 class="touroption-section-header">Cancellation Policy Details</h4>
                                <div class="touroption-section-content">
                                    ${option.cancellationPolicyDescription}
                                </div>
                            </div>
                        ` : ''}

                        ${option.childPolicyDescription ? `
                            <div class="touroption-section">
                                <h4 class="touroption-section-header">Child Policy</h4>
                                <div class="touroption-section-content">
                                    ${option.childPolicyDescription}
                                </div>
                            </div>
                        ` : ''}

                        ${option.termsAndConditions ? `
                            <div class="touroption-section">
                                <h4 class="touroption-section-header">Terms & Conditions</h4>
                                <div class="touroption-section-content">
                                    ${option.termsAndConditions}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
        }

        function renderOperationDays(operationDays) {
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            
            return days.map((day, index) => {
                const isActive = operationDays[day] === 1;
                return `
                    <span class="operation-day ${isActive ? 'active' : 'inactive'}" title="${dayNames[index]}: ${isActive ? 'Available' : 'Not Available'}">
                        ${dayNames[index].substring(0, 3)}
                    </span>
                `;
            }).join('');
        }

        // Function to load tour options when modal opens
        function loadTourOptions(tourId, contractId) {
            const tourOptionsSection = document.getElementById('modal-touroptions');
            if (!tourOptionsSection) {
                console.error('Tour Options section not found');
                return;
            }

            // Show loading state
            const content = document.getElementById('modal-touroptions-content');
            if (!content) {
                console.error('Tour Options content container not found');
                return;
            }
            
            content.innerHTML = '<div class="loading-spinner">Loading tour options...</div>';

            console.log('Loading tour options for tourId:', tourId, 'contractId:', contractId);
            
            fetchTourOptions(tourId, contractId)
                .then(data => {
                    console.log('Tour options API response:', data);
                    if (data) {
                        renderTourOptions(data, tourId, contractId);
                        // After rendering tour options, trigger availability checks for all transfers
                        checkAllTransfersAvailability(data, tourId, contractId);
                    } else {
                        content.innerHTML = '<p class="no-data">No tour options available.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading tour options:', error);
                    content.innerHTML = '<p class="no-data">Error loading tour options.</p>';
                });
        }

        // Event listeners for pricing and availability API calls
        document.addEventListener('click', function(e) {
            // Handle pricing API calls (ignore clicks on info icons)
            if (e.target.closest('.modal-info-icon')) return;
            if (e.target.closest('.transfer-pricing')) {
                const pricingElement = e.target.closest('.transfer-pricing');
                const tourId = pricingElement.getAttribute('data-tour-id');
                const contractId = pricingElement.getAttribute('data-contract-id');
                const tourOptionId = pricingElement.getAttribute('data-tour-option-id');
                const transferName = pricingElement.getAttribute('data-transfer-name');
                
                // Get travel date from modal (if available) and convert to MM-DD-YYYY
                let travelDate = document.getElementById('modal-tour-date')?.textContent?.replace('Date: ', '') || '';
                if (travelDate && travelDate.includes('-')) {
                    const dateParts = travelDate.split('-');
                    if (dateParts.length === 3) {
                        // Convert from YYYY-MM-DD to MM-DD-YYYY
                        travelDate = `${dateParts[1].padStart(2, '0')}-${dateParts[2].padStart(2, '0')}-${dateParts[0]}`; // MM-DD-YYYY
                    }
                }
                
                if (tourId && contractId && tourOptionId) {
                    fetchTourOptionDetails(tourId, contractId, travelDate, 1, 0, 0)
                        .then(data => {
                            if (data && data.result && data.result.length > 0) {
                                // Find the specific transfer option
                                const transferOption = data.result.find(option => 
                                    option.tourOptionId == tourOptionId && 
                                    option.transferName === transferName
                                );
                                
                                if (transferOption) {
                                    const normalizedDateLocal = normalizeDateYMD(travelDate);
                                    pricingElement.innerHTML = `
                                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                            <strong>Pricing Details</strong>
                                            <button class="modal-info-icon" aria-label="View pricing API details" data-endpoint="https://sandbox.raynatours.com/api/Tour/touroption" data-method="POST" data-request='{"tourId":${tourId},"contractId":${contractId},"travelDate":"${travelDate}","noOfAdult":1,"noOfChild":0,"noOfInfant":0}'>i</button>
                                        </div>
                                        <div class="pricing-item">
                                            <span class="pricing-label">Adult Price:</span>
                                            <span class="pricing-value">${transferOption.adultPrice || 'N/A'}</span>
                                        </div>
                                        <div class="pricing-item">
                                            <span class="pricing-label">Child Price:</span>
                                            <span class="pricing-value">${transferOption.childPrice || 'N/A'}</span>
                                        </div>
                                        <div class="pricing-item">
                                            <span class="pricing-label">Infant Price:</span>
                                            <span class="pricing-value">${transferOption.infantPrice || 'N/A'}</span>
                                        </div>
                                        <div class="pricing-item">
                                            <span class="pricing-label">Without Discount:</span>
                                            <span class="pricing-value">${transferOption.withoutDiscountAmount || 'N/A'}</span>
                                        </div>
                                        <div class="pricing-item">
                                            <span class="pricing-label">Final Amount:</span>
                                            <span class="pricing-value">${transferOption.finalAmount || 'N/A'}</span>
                                        </div>
                                        <div class="pricing-item">
                                            <span class="pricing-label">Start Time:</span>
                                            <span class="pricing-value">${transferOption.startTime || 'N/A'}</span>
                                        </div>
                                        <div class="pricing-item">
                                            <span class="pricing-label">Departure Time:</span>
                                            <span class="pricing-value">${transferOption.departureTime || 'N/A'}</span>
                                        </div>
                                        <div class="pricing-item">
                                            <span class="pricing-label">Cut-off:</span>
                                            <span class="pricing-value">${transferOption.cutOff || 'N/A'}</span>
                                        </div>
                                        <div class="pricing-item">
                                            <span class="pricing-label">Is Slot:</span>
                                            <span class="pricing-value">${transferOption.isSlot ? 'Yes' : 'No'}</span>
                                        </div>
                                        <div class="timeslot-section" style="margin-top:8px;">
                                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                                <strong>Available Time Slots</strong>
                                                <span class="timeslot-date">Date: ${normalizedDateLocal}</span>
                                            </div>
                                            <div class="timeslot-list" style="display:flex;flex-wrap:wrap;gap:4px;">Loading time slots...</div>
                                        </div>
                                    `;
                                    
                                    // Store API response data for tooltips
                                    pricingElement.setAttribute('data-response', JSON.stringify(data));
                                    pricingElement.setAttribute('data-request', JSON.stringify({
                                        tourId,
                                        contractId,
                                        travelDate,
                                        noOfAdult: 1,
                                        noOfChild: 0,
                                        noOfInfant: 0
                                    }));
                                    // Fetch time slots for this transfer and render below pricing
                                    const tsList = pricingElement.querySelector('.timeslot-list');
                                    if (tsList && transferOption.transferId) {
                                        tsList.innerHTML = 'Loading time slots...';
                                        fetchTimeSlots(tourId, tourOptionId, travelDate, transferOption.transferId, 1, 0, 0, 300)
                                            .then(tsData => {
                                                var slots = [];
                                                if (tsData && tsData.result && Array.isArray(tsData.result)) {
                                                    slots = tsData.result;
                                                } else if (tsData && tsData.data && tsData.data.result && Array.isArray(tsData.data.result)) {
                                                    slots = tsData.data.result;
                                                }
                                                if (slots && slots.length > 0) {
                                                    const items = slots.map(function(slot){
                                                        var label = (slot && (slot.timeSlot || slot.startTime)) ? (slot.timeSlot || slot.startTime) : 'N/A';
                                                        var id = (slot && slot.timeSlotId) ? String(slot.timeSlotId) : '';
                                                        return `<span class="timeslot-pill" data-label="${label}" data-time-slot-id="${id}" style="display:inline-block;padding:6px 10px;border:1px solid #ddd;border-radius:9999px;background:#f8f8f8;margin:4px;font-size:12px;cursor:pointer;">`+
                                                               `${label}`+
                                                               `${id ? `<span class=\"slot-id\" style=\"margin-left:6px;color:\#888;font-size:11px;\">#${id}</span>` : ''}`+
                                                               `</span>`;
                                                    }).join('');
                                                    tsList.innerHTML = items;
                                                    pricingElement.setAttribute('data-timeslots', JSON.stringify(slots));
                                                } else {
                                                    tsList.innerHTML = '<div class="no-data">No slots found.</div>';
                                                }
                                            })
                                            .catch(err => {
                                                console.error('Error loading time slots:', err);
                                                tsList.innerHTML = '<div class="no-data">Error loading time slots.</div>';
                                            });
                                    }
                                } else {
                                    pricingElement.innerHTML = '<div class="no-data">No pricing data available for this transfer.</div>';
                                }
                            } else {
                                pricingElement.innerHTML = '<div class="no-data">No pricing data available.</div>';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching pricing details:', error);
                            pricingElement.innerHTML = '<div class="no-data">Error loading pricing details.</div>';
                        });
                }
            }
            
            // Handle availability checks
            if (e.target.closest('.availability-badge')) {
                const badge = e.target.closest('.availability-badge');
                const tourId = badge.getAttribute('data-tour-id');
                const tourOptionId = badge.getAttribute('data-tour-option-id');
                const transferId = badge.getAttribute('data-transfer-id');
                const transferName = badge.getAttribute('data-transfer-name');
                
                // Get travel date from modal (if available) and convert to MM-DD-YYYY
                let travelDate = document.getElementById('modal-tour-date')?.textContent?.replace('Date: ', '') || '';
                if (travelDate && travelDate.includes('-')) {
                    const dateParts = travelDate.split('-');
                    if (dateParts.length === 3) {
                        // Convert from YYYY-MM-DD to MM-DD-YYYY
                        travelDate = `${dateParts[1].padStart(2, '0')}-${dateParts[2].padStart(2, '0')}-${dateParts[0]}`; // MM-DD-YYYY
                    }
                }
                
                if (tourId && tourOptionId && transferId) {
                    badge.innerHTML = 'Checking...';
                    
                    checkAvailability(tourId, tourOptionId, travelDate, transferId, 1, 0, 0)
                        .then(data => {
                            if (data && data.isAvailable !== undefined) {
                                if (data.isAvailable) {
                                    badge.innerHTML = `Available`;
                                    badge.classList.remove('unavailable');
                                } else {
                                    badge.innerHTML = `Unavailable`;
                                    badge.classList.add('unavailable');
                                }
                                
                                // Store API response data for tooltips
                                badge.setAttribute('data-response', JSON.stringify(data));
                                badge.setAttribute('data-request', JSON.stringify({
                                    tourId,
                                    tourOptionId,
                                    travelDate,
                                    transferId,
                                    adult: 1,
                                    child: 0,
                                    infant: 0,
                                    contractId: 300
                                }));

                                // After availability, fetch time slots and show count
                                return fetchTimeSlots(tourId, tourOptionId, travelDate, transferId, 1, 0, 0, 300)
                                    .then(tsData => {
                                        if (!tsData) {
                                            return;
                                        }
                                        // Try to extract slots array from various possible structures
                                        var slots = [];
                                        if (tsData && tsData.result && Array.isArray(tsData.result)) {
                                            slots = tsData.result;
                                        } else if (tsData && tsData.data && tsData.data.result && Array.isArray(tsData.data.result)) {
                                            slots = tsData.data.result;
                                        }
                                        if (slots && slots.length > 0) {
                                            // Append slot count to badge with normalized date
                                            var text = badge.textContent || '';
                                            var prefix = (text && text.trim().length > 0) ? text.trim() + ' — ' : '';
                                            var normalizedDateLocal = normalizeDateYMD(travelDate);
                                            badge.innerHTML = prefix + slots.length + ' time slots on ' + normalizedDateLocal;
                                            // Store timeslot data for debugging/tooltips if needed
                                            badge.setAttribute('data-timeslots', JSON.stringify(slots));
                                        } else {
                                            // If no slots, keep availability text; optionally indicate none
                                            // badge.innerHTML = (badge.textContent || 'Unavailable') + ' — No slots';
                                        }
                                    });
                            } else {
                                badge.innerHTML = 'Availability Unknown';
                            }
                        })
                        .catch(error => {
                            console.error('Error checking availability:', error);
                            badge.innerHTML = 'Error';
                        });
                }
            }
        });

        // Auto-load Tour Options data when modal opens (if travel date is available)
        document.addEventListener('modalOpened', function() {
            setTimeout(() => {
                const travelDateElement = document.getElementById('modal-tour-date');
                let travelDate = travelDateElement?.textContent?.replace('Date: ', '');
                const tourId = document.getElementById('modal-tour-id')?.textContent?.replace('ID: ', '');
                const contractId = 300; // Default contract ID
                
                // Convert date format from YYYY-MM-DD to MM-DD-YYYY for API calls
                if (travelDate && travelDate.includes('-')) {
                    const dateParts = travelDate.split('-');
                    if (dateParts.length === 3) {
                        // Convert from YYYY-MM-DD to MM-DD-YYYY
                        travelDate = `${dateParts[1].padStart(2, '0')}-${dateParts[2].padStart(2, '0')}-${dateParts[0]}`; // MM-DD-YYYY
                    }
                }
                
                if (travelDate && tourId) {
                    // Fetch Tour Options data for all transfer options
                    fetchTourOptionDetails(tourId, contractId, travelDate, 1, 0, 0)
                        .then(data => {
                            if (data && data.result && data.result.length > 0) {
                                // Update all transfer pricing sections
                                document.querySelectorAll('.transfer-pricing').forEach(pricingElement => {
                                    const tourOptionId = pricingElement.getAttribute('data-tour-option-id');
                                    const transferName = pricingElement.getAttribute('data-transfer-name');
                                    
                                    const transferOption = data.result.find(option => 
                                        option.tourOptionId == tourOptionId && 
                                        option.transferName === transferName
                                    );
                                    
                                    if (transferOption) {
                                        const normalizedDateLocal = normalizeDateYMD(travelDate);
                                        pricingElement.innerHTML = `
                                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                                <strong>Pricing Details</strong>
                                                <button class="modal-info-icon" aria-label="View pricing API details" data-endpoint="https://sandbox.raynatours.com/api/Tour/touroption" data-method="POST" data-request='{"tourId":${tourId},"contractId":${contractId},"travelDate":"${travelDate}","noOfAdult":1,"noOfChild":0,"noOfInfant":0}'>i</button>
                                            </div>
                                            <div class="pricing-item">
                                                <span class="pricing-label">Adult Price:</span>
                                                <span class="pricing-value">${transferOption.adultPrice || 'N/A'}</span>
                                            </div>
                                            <div class="pricing-item">
                                                <span class="pricing-label">Child Price:</span>
                                                <span class="pricing-value">${transferOption.childPrice || 'N/A'}</span>
                                            </div>
                                            <div class="pricing-item">
                                                <span class="pricing-label">Infant Price:</span>
                                                <span class="pricing-value">${transferOption.infantPrice || 'N/A'}</span>
                                            </div>
                                            <div class="pricing-item">
                                                <span class="pricing-label">Without Discount:</span>
                                                <span class="pricing-value">${transferOption.withoutDiscountAmount || 'N/A'}</span>
                                            </div>
                                            <div class="pricing-item">
                                                <span class="pricing-label">Final Amount:</span>
                                                <span class="pricing-value">${transferOption.finalAmount || 'N/A'}</span>
                                            </div>
                                            <div class="pricing-item">
                                                <span class="pricing-label">Start Time:</span>
                                                <span class="pricing-value">${transferOption.startTime || 'N/A'}</span>
                                            </div>
                                            <div class="pricing-item">
                                                <span class="pricing-label">Departure Time:</span>
                                                <span class="pricing-value">${transferOption.departureTime || 'N/A'}</span>
                                            </div>
                                            <div class="pricing-item">
                                                <span class="pricing-label">Cut-off:</span>
                                                <span class="pricing-value">${transferOption.cutOff || 'N/A'}</span>
                                            </div>
                                            <div class="pricing-item">
                                                <span class="pricing-label">Is Slot:</span>
                                                <span class="pricing-value">${transferOption.isSlot ? 'Yes' : 'No'}</span>
                                            </div>
                                            <div class="timeslot-section" style="margin-top:8px;">
                                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                                    <strong>Available Time Slots</strong>
                                                    <span class="timeslot-date">Date: ${normalizedDateLocal}</span>
                                                </div>
                                                <div class="timeslot-list" style="display:flex;flex-wrap:wrap;gap:4px;">Loading time slots...</div>
                                            </div>
                                        `;
                                        
                                        // Store API response data for tooltips
                                        pricingElement.setAttribute('data-response', JSON.stringify(data));
                                        pricingElement.setAttribute('data-request', JSON.stringify({
                                            tourId,
                                            contractId,
                                            travelDate,
                                            noOfAdult: 1,
                                            noOfChild: 0,
                                            noOfInfant: 0
                                        }));
                                        
                                        // Update transfer ID badge with actual transferId from API response
                                        const transferIdBadge = pricingElement.closest('.transfer-time-item').querySelector('.transfer-id-badge');
                                        if (transferIdBadge && transferOption.transferId) {
                                            transferIdBadge.textContent = `Transfer ID: ${transferOption.transferId}`;
                                            transferIdBadge.setAttribute('data-transfer-id', transferOption.transferId);
                                        }
                                        
                                        // Update availability badge with transferId
                                        const availabilityBadge = pricingElement.closest('.transfer-time-item').querySelector('.availability-badge');
                                        if (availabilityBadge && transferOption.transferId) {
                                            availabilityBadge.setAttribute('data-transfer-id', transferOption.transferId);
                                            // Update Book button with transferId
                                            const bookBtn = pricingElement.closest('.transfer-time-item').querySelector('.book-button');
                                            if (bookBtn) {
                                                bookBtn.setAttribute('data-transfer-id', transferOption.transferId);
                                            }
                                            
                                        // Auto-check availability
                                        checkAvailability(tourId, tourOptionId, travelDate, transferOption.transferId, 1, 0, 0)
                                            .then(availabilityData => {
                                                if (availabilityData && availabilityData.isAvailable !== undefined) {
                                                    if (availabilityData.isAvailable) {
                                                        availabilityBadge.innerHTML = `Available`;
                                                        availabilityBadge.classList.remove('unavailable');
                                                            
                                                            // Show Book button when available
                                                            const bookButton = availabilityBadge.closest('.availability-container').querySelector('.book-button');
                                                            if (bookButton) {
                                                                bookButton.style.display = 'inline-block';
                                                            }
                                                        } else {
                                                            availabilityBadge.innerHTML = `Unavailable`;
                                                            availabilityBadge.classList.add('unavailable');
                                                            
                                                            // Hide Book button when unavailable
                                                            const bookButton = availabilityBadge.closest('.availability-container').querySelector('.book-button');
                                                            if (bookButton) {
                                                                bookButton.style.display = 'none';
                                                            }
                                                        }
                                                        
                                                        // Store API response data for tooltips
                                                    availabilityBadge.setAttribute('data-response', JSON.stringify(availabilityData));
                                                    availabilityBadge.setAttribute('data-request', JSON.stringify({
                                                        tourId,
                                                        tourOptionId,
                                                        travelDate,
                                                        transferId: transferOption.transferId,
                                                        adult: 1,
                                                        child: 0,
                                                        infant: 0,
                                                        contractId: 300
                                                    }));

                                                    // Also fetch time slots for this transfer and show count
                                                    return fetchTimeSlots(tourId, tourOptionId, travelDate, transferOption.transferId, 1, 0, 0, 300)
                                                        .then(tsData => {
                                                            if (!tsData) {
                                                                return;
                                                            }
                                                            var slots = [];
                                                            if (tsData && tsData.result && Array.isArray(tsData.result)) {
                                                                slots = tsData.result;
                                                            } else if (tsData && tsData.data && tsData.data.result && Array.isArray(tsData.data.result)) {
                                                                slots = tsData.data.result;
                                                            }
                                                            if (slots && slots.length > 0) {
                                                                var text = availabilityBadge.textContent || '';
                                                                var prefix = (text && text.trim().length > 0) ? text.trim() + ' — ' : '';
                                                                var normalizedDateLocal = normalizeDateYMD(travelDate);
                                                                availabilityBadge.innerHTML = prefix + slots.length + ' time slots on ' + normalizedDateLocal;
                                                                availabilityBadge.setAttribute('data-timeslots', JSON.stringify(slots));
                                                                // Also render slots under Pricing Details
                                                                const tsList = pricingElement.querySelector('.timeslot-list');
                                                                if (tsList) {
                                                                const items = slots.map(function(slot){
                                                                    var label = (slot && (slot.timeSlot || slot.startTime)) ? (slot.timeSlot || slot.startTime) : 'N/A';
                                                                    var id = (slot && slot.timeSlotId) ? String(slot.timeSlotId) : '';
                                                                    return `<span class="timeslot-pill" data-label="${label}" data-time-slot-id="${id}" style="display:inline-block;padding:6px 10px;border:1px solid #ddd;border-radius:9999px;background:#f8f8f8;margin:4px;font-size:12px;cursor:pointer;">`+
                                                                           `${label}`+
                                                                           `${id ? `<span class=\"slot-id\" style=\"margin-left:6px;color:\#888;font-size:11px;\">#${id}</span>` : ''}`+
                                                                           `</span>`;
                                                                }).join('');
                                                                tsList.innerHTML = items;
                                                                pricingElement.setAttribute('data-timeslots', JSON.stringify(slots));
                                                                }
                                                            } else {
                                                                const tsList = pricingElement.querySelector('.timeslot-list');
                                                                if (tsList) {
                                                                    tsList.innerHTML = '<div class="no-data">No slots found.</div>';
                                                                }
                                                            }
                                                        });
                                                } else {
                                                    availabilityBadge.innerHTML = 'Availability Unknown';
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Error checking availability:', error);
                                                availabilityBadge.innerHTML = 'Error';
                                            });
                                        }
                                    } else {
                                        pricingElement.innerHTML = '<div class="no-data">No pricing data available for this transfer.</div>';
                                    }
                                });
                            } else {
                                document.querySelectorAll('.transfer-pricing').forEach(pricingElement => {
                                    pricingElement.innerHTML = '<div class="no-data">No pricing data available.</div>';
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching tour options:', error);
                            document.querySelectorAll('.transfer-pricing').forEach(pricingElement => {
                                pricingElement.innerHTML = '<div class="no-data">Error loading pricing details.</div>';
                            });
                        });
                }
            }, 1000);
        });
        
        // Function to check availability for all transfers
        async function checkAllTransfersAvailability(tourOptionsData, tourId, contractId) {
            // Build pairs (tourOptionId, transferId) from multiple possible shapes
            let pairs = [];
            if (tourOptionsData && Array.isArray(tourOptionsData.transfertime)) {
                pairs = tourOptionsData.transfertime
                    .filter(tt => tt && tt.transferId && tt.tourOptionId)
                    .map(tt => ({ tourOptionId: parseInt(tt.tourOptionId), transferId: parseInt(tt.transferId) }));
            } else if (tourOptionsData && Array.isArray(tourOptionsData.result)) {
                pairs = tourOptionsData.result
                    .filter(ro => ro && ro.transferId && ro.tourOptionId)
                    .map(ro => ({ tourOptionId: parseInt(ro.tourOptionId), transferId: parseInt(ro.transferId) }));
            } else {
                // Fallback to DOM badges if API shape differs
                document.querySelectorAll('.availability-badge').forEach(badge => {
                    const tourOptionId = parseInt(badge.getAttribute('data-tour-option-id'));
                    const transferId = parseInt(badge.getAttribute('data-transfer-id'));
                    if (tourOptionId && transferId) pairs.push({ tourOptionId, transferId });
                });
            }

            if (!pairs.length) {
                console.warn('No transfers found to check availability.');
                return [];
            }

            // Get travel date from modal (if available) and convert to MM-DD-YYYY
            let travelDate = document.getElementById('modal-tour-date')?.textContent?.replace('Date: ', '') || '';
            if (travelDate && travelDate.includes('-')) {
                const dateParts = travelDate.split('-');
                if (dateParts.length === 3) {
                    travelDate = `${dateParts[1].padStart(2, '0')}-${dateParts[2].padStart(2, '0')}-${dateParts[0]}`; // MM-DD-YYYY
                }
            }

            const availabilityPromises = pairs.map(({ tourOptionId, transferId }) => {
                return checkAvailability(
                    tourId,
                    tourOptionId,
                    travelDate,
                    transferId,
                    1,
                    0,
                    0
                )
                .then(availabilityData => {
                    const availabilityBadge = document.querySelector(`.availability-badge[data-transfer-id="${transferId}"]`);
                    if (availabilityBadge) {
                        if (availabilityData && availabilityData.isAvailable !== undefined) {
                            if (availabilityData.isAvailable) {
                                availabilityBadge.innerHTML = `Available`;
                                availabilityBadge.classList.remove('unavailable');
                                const bookButton = availabilityBadge.closest('.availability-container')?.querySelector('.book-button');
                                if (bookButton) bookButton.style.display = 'inline-block';
                            } else {
                                availabilityBadge.innerHTML = `Unavailable`;
                                availabilityBadge.classList.add('unavailable');
                                const bookButton = availabilityBadge.closest('.availability-container')?.querySelector('.book-button');
                                if (bookButton) bookButton.style.display = 'none';
                            }

                            const reqJson = {
                                tourId,
                                tourOptionId,
                                travelDate,
                                transferId,
                                adult: 1,
                                child: 0,
                                infant: 0,
                                contractId: 300
                            };
                            availabilityBadge.setAttribute('data-response', JSON.stringify(availabilityData));
                            availabilityBadge.setAttribute('data-request', JSON.stringify(reqJson));
                            // Reflect request to adjacent info icon so tooltip shows details
                            const infoBtn = availabilityBadge.closest('.availability-container')?.querySelector('.modal-info-icon');
                            if (infoBtn) {
                                infoBtn.setAttribute('data-request', JSON.stringify(reqJson));
                            }
                        } else {
                            availabilityBadge.innerHTML = 'Availability Unknown';
                        }
                    }
                    return availabilityData;
                })
                .catch(error => {
                    console.error(`Error checking availability for transfer ${transferId}:`, error);
                    const availabilityBadge = document.querySelector(`.availability-badge[data-transfer-id="${transferId}"]`);
                    if (availabilityBadge) {
                        availabilityBadge.innerHTML = 'Error';
                    }
                    return null;
                });
            });

            return Promise.all(availabilityPromises);
        }

        // Book button event listener (dynamic data)
        document.addEventListener('click', function(e) {
            // Handle time slot pill selection to prefill booking
            const pill = e.target.classList.contains('timeslot-pill') ? e.target : e.target.closest && e.target.closest('.timeslot-pill');
            if (pill && pill.classList && pill.classList.contains('timeslot-pill')) {
                const transferItem = pill.closest('.transfer-time-item');
                const pricingElement = transferItem?.querySelector('.transfer-pricing');
                const bookBtn = transferItem?.querySelector('.book-button');
                const label = pill.getAttribute('data-label') || pill.textContent.trim();
                const tsId = pill.getAttribute('data-time-slot-id') || '';

                // Visual selection state
                const siblings = transferItem ? transferItem.querySelectorAll('.timeslot-pill') : [];
                siblings.forEach(function(s){
                    s.classList.remove('selected');
                    s.style.background = '#f8f8f8';
                    s.style.borderColor = '#ddd';
                    s.style.color = '';
                });
                pill.classList.add('selected');
                pill.style.background = '#e6f4ff';
                pill.style.borderColor = '#3399ff';
                pill.style.color = '#174f7a';

                // Persist selection for booking
                if (pricingElement) {
                    pricingElement.setAttribute('data-selected-start-time', label);
                    pricingElement.setAttribute('data-selected-time-slot-id', tsId);
                }
                if (bookBtn) {
                    bookBtn.setAttribute('data-start-time', label);
                    bookBtn.setAttribute('data-time-slot-id', tsId);
                    // Hide any open modal tooltip before opening booking form
                    try {
                        const tooltipBox = document.getElementById('modal-tooltip-box');
                        if (tooltipBox && tooltipBox.classList.contains('visible')) {
                            tooltipBox.classList.remove('visible');
                            tooltipBox.setAttribute('aria-hidden', 'true');
                        }
                    } catch {}
                    // Trigger booking flow immediately when a pill is selected
                    try { bookBtn.click(); } catch {}
                }
            }
            
            if (e.target.classList.contains('book-button')) {
                e.preventDefault();
                e.stopPropagation();
                // Ensure tooltip is closed so booking renders as lightbox, not inside tooltip
                try {
                    const tooltipBox = document.getElementById('modal-tooltip-box');
                    if (tooltipBox && tooltipBox.classList.contains('visible')) {
                        tooltipBox.classList.remove('visible');
                        tooltipBox.setAttribute('aria-hidden', 'true');
                    }
                } catch {}
                const tourId = parseInt(e.target.getAttribute('data-tour-id'));
                const tourOptionId = parseInt(e.target.getAttribute('data-tour-option-id'));
                const transferId = parseInt(e.target.getAttribute('data-transfer-id'));
                
                // Get travel date from modal and convert to MM-DD-YYYY
                let travelDate = document.getElementById('modal-tour-date')?.textContent?.replace('Date: ', '') || '';
                if (travelDate && travelDate.includes('-')) {
                    const dateParts = travelDate.split('-');
                    if (dateParts.length === 3) {
                        travelDate = `${dateParts[1].padStart(2, '0')}-${dateParts[2].padStart(2, '0')}-${dateParts[0]}`;
                    }
                }
                
                const transferItem = e.target.closest('.transfer-time-item');
                const pricingElement = transferItem?.querySelector('.transfer-pricing');
                let transferOption = null;
                let adultRate = 0, childRate = 0, startTime = '', timeSlotId = '', pickup = false;
                let adult = 1, child = 0, infant = 0;
                
                // Try to read adult/child counts from stored availability request
                const availabilityBadge = transferItem?.querySelector('.availability-badge');
                try {
                    const req = availabilityBadge?.getAttribute('data-request');
                    if (req) {
                        const rq = JSON.parse(req);
                        adult = rq.adult ?? adult;
                        child = rq.child ?? child;
                        infant = rq.infant ?? infant;
                    }
                } catch {}
                
                // Extract transferOption details from stored pricing response
                try {
                    const pricingDataRaw = pricingElement?.getAttribute('data-response');
                    if (pricingDataRaw) {
                        const pricingData = JSON.parse(pricingDataRaw);
                        const options = pricingData.result || pricingData.data?.result || [];
                        transferOption = options.find(o => parseInt(o.transferId) === transferId) || null;
                        if (transferOption) {
                            // Map to correct fields from touroption API
                            adultRate = parseFloat(transferOption.adultPrice ?? 0);
                            childRate = parseFloat(transferOption.childPrice ?? 0);
                            startTime = transferOption.startTime || '';
                            timeSlotId = String(transferOption.timeSlotId || '');
                            pickup = Boolean(transferOption.pickup ?? false);
                        }
                    }
                } catch {}

                // If user selected a time slot pill, prefer it
                const selectedStartFromBtn = e.target.getAttribute('data-start-time');
                const selectedIdFromBtn = e.target.getAttribute('data-time-slot-id');
                const selectedStartFromPricing = pricingElement?.getAttribute('data-selected-start-time');
                const selectedIdFromPricing = pricingElement?.getAttribute('data-selected-time-slot-id');
                const preferredStart = selectedStartFromBtn || selectedStartFromPricing || '';
                const preferredId = selectedIdFromBtn || selectedIdFromPricing || '';
                if (preferredStart) startTime = preferredStart;
                if (preferredId) timeSlotId = preferredId;
                
                // Prefer API-provided finalAmount; fallback to computed total
                let serviceTotal = 0;
                if (transferOption && transferOption.finalAmount !== undefined) {
                    serviceTotal = parseFloat(transferOption.finalAmount) || 0;
                } else {
                    serviceTotal = (adultRate * adult) + (childRate * child);
                }
                
                // Generate unique numbers
                const uniqueNo = `UNIQ-${Date.now()}-${Math.floor(Math.random()*1000)}`;
                const serviceUniqueId = `SUID-${Date.now()}-${Math.floor(Math.random()*1000)}`;
                
                // Generate random passenger
                function randomFrom(arr){ return arr[Math.floor(Math.random()*arr.length)]; }
                const firstNames = ['Ayaan','Neha','Ravi','Sara','Omar','Liam','Mia','Anya'];
                const lastNames = ['Khan','Patel','Singh','Verma','Ali','Wong','Smith','Garcia'];
                const fn = randomFrom(firstNames);
                const ln = randomFrom(lastNames);
                const email = `${fn.toLowerCase()}.${ln.toLowerCase()}${Math.floor(Math.random()*1000)}@example.com`;
                const mobile = `05${Math.floor(10000000 + Math.random()*89999999)}`;
                
                const bookingData = {
                    uniqueNo,
                    TourDetails: [
                        {
                            serviceUniqueId,
                            tourId,
                            optionId: tourOptionId,
                            adult,
                            child,
                            infant,
                            tourDate: travelDate,
                            timeSlotId,
                            startTime,
                            transferId,
                            pickup,
                            adultRate: adultRate.toFixed(2),
                            childRate: (child > 0 ? childRate : 0).toFixed(2),
                            serviceTotal: serviceTotal.toFixed(2)
                        }
                    ],
                    passengers: [
                        {
                            serviceType: 'tour',
                            prefix: 'Mr',
                            firstName: fn,
                            lastName: ln,
                            email,
                            mobile,
                            nationality: 'IN',
                            message: '',
                            leadPassenger: 1,
                            paxType: 'Adult',
                            clientReferenceNo: 'CLIENT-TEST'
                        }
                    ]
                };
                
                openBookingForm(bookingData);
            }
        });


        // Function to open booking form modal
        function openBookingForm(bookingData) {
            // Create booking form modal
            const modal = document.createElement('div');
            modal.className = 'modal booking-modal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
            `;
            
            modal.innerHTML = `
                <div class="booking-form-container" style="background: white; padding: 20px; border-radius: 8px; max-width: 1000px; width: 96%; max-height: 85vh; overflow-y: auto;">
                    <h2 style="margin-top: 0;">Book Tour</h2>
                    <form id="booking-form" style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px;">
                        <fieldset style="grid-column: 1 / -1; border: 1px solid #ddd; border-radius: 6px; padding: 10px;">
                            <legend style="padding: 0 8px; font-weight: 600;">Tour Details</legend>
                            <div style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px;">
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Tour ID</label>
                                    <input type="text" value="${bookingData.TourDetails[0].tourId}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Option ID</label>
                                    <input type="text" value="${bookingData.TourDetails[0].optionId}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Transfer ID</label>
                                    <input type="text" value="${bookingData.TourDetails[0].transferId}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Date</label>
                                    <input type="text" value="${bookingData.TourDetails[0].tourDate}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Start Time</label>
                                    <input type="text" value="${bookingData.TourDetails[0].startTime}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Time Slot ID</label>
                                    <input type="text" value="${bookingData.TourDetails[0].timeSlotId}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Pickup</label>
                                    <input type="text" value="${bookingData.TourDetails[0].pickup ? 'Yes' : 'No'}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Adult Rate</label>
                                    <input type="text" value="${bookingData.TourDetails[0].adultRate}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Child Rate</label>
                                    <input type="text" value="${bookingData.TourDetails[0].childRate}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Adults</label>
                                    <input type="number" value="${bookingData.TourDetails[0].adult}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Children</label>
                                    <input type="number" value="${bookingData.TourDetails[0].child}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Infants</label>
                                    <input type="number" value="${bookingData.TourDetails[0].infant}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Service Total</label>
                                    <input type="text" value="${bookingData.TourDetails[0].serviceTotal}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Unique No</label>
                                    <input type="text" value="${bookingData.uniqueNo}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Service Unique ID</label>
                                    <input type="text" value="${bookingData.TourDetails[0].serviceUniqueId}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                            </div>
                        </fieldset>

                        <fieldset style="grid-column: 1 / -1; border: 1px solid #ddd; border-radius: 6px; padding: 10px;">
                            <legend style="padding: 0 8px; font-weight: 600;">Passenger Details</legend>
                            <div style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px;">
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Service Type</label>
                                    <input type="text" name="serviceType" value="${bookingData.passengers[0].serviceType}" readonly style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Prefix</label>
                                    <input type="text" name="prefix" value="${bookingData.passengers[0].prefix}" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Nationality</label>
                                    <input type="text" name="nationality" value="${bookingData.passengers[0].nationality}" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">First Name</label>
                                    <input type="text" name="firstName" value="${bookingData.passengers[0].firstName}" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Last Name</label>
                                    <input type="text" name="lastName" value="${bookingData.passengers[0].lastName}" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Email</label>
                                    <input type="email" name="email" value="${bookingData.passengers[0].email}" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Mobile</label>
                                    <input type="tel" name="mobile" value="${bookingData.passengers[0].mobile}" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Message</label>
                                    <input type="text" name="message" value="${bookingData.passengers[0].message}" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Lead Passenger</label>
                                    <input type="number" name="leadPassenger" value="${bookingData.passengers[0].leadPassenger}" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Pax Type</label>
                                    <input type="text" name="paxType" value="${bookingData.passengers[0].paxType}" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; font-weight:500; font-size:12px;">Client Reference No</label>
                                    <input type="text" name="clientReferenceNo" value="${bookingData.passengers[0].clientReferenceNo}" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                                </div>
                            </div>
                        </fieldset>

                        <div style="grid-column: 1 / -1; display: flex; gap: 10px;">
                            <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Submit Booking</button>
                            <button type="button" class="close-booking" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                        </div>
                    </form>
                    <div id="booking-result" style="margin-top: 15px;"></div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Close modal handler
            modal.querySelector('.close-booking').addEventListener('click', function() {
                document.body.removeChild(modal);
            });
            
            // Form submission handler
            modal.querySelector('#booking-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Update form data with user input
                const formData = new FormData(this);
                bookingData.passengers[0].firstName = formData.get('firstName');
                bookingData.passengers[0].lastName = formData.get('lastName');
                bookingData.passengers[0].email = formData.get('email');
                bookingData.passengers[0].mobile = formData.get('mobile');
                
                // Merge any edited passenger fields back into bookingData
                bookingData.passengers[0].serviceType = formData.get('serviceType') || bookingData.passengers[0].serviceType;
                bookingData.passengers[0].prefix = formData.get('prefix') || bookingData.passengers[0].prefix;
                bookingData.passengers[0].nationality = formData.get('nationality') || bookingData.passengers[0].nationality;
                bookingData.passengers[0].message = formData.get('message') || '';
                bookingData.passengers[0].leadPassenger = Number(formData.get('leadPassenger') || bookingData.passengers[0].leadPassenger || 1);
                bookingData.passengers[0].paxType = formData.get('paxType') || bookingData.passengers[0].paxType;
                bookingData.passengers[0].clientReferenceNo = formData.get('clientReferenceNo') || bookingData.passengers[0].clientReferenceNo;

                // Submit booking (testing: only log payload)
                submitBooking(bookingData);
            });
            
            // Close on outside click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    document.body.removeChild(modal);
                }
            });
        }

        // Function to submit booking
        async function submitBooking(bookingData) {
            const resultDiv = document.getElementById('booking-result');
            resultDiv.innerHTML = '<div style="background:#e7f1ff; color:#084298; padding:10px; border-radius:4px;">Submitting booking...</div>';
            try {
                const resp = await fetch('booking_proxy.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(bookingData)
                });
                const text = await resp.text();
                let data = null;
                try { data = JSON.parse(text); } catch { /* keep raw text */ }

                const statusCode = (data && (data.statuscode || data.statusCode)) || resp.status;
                const message = (data && (data.message || data.status)) || (resp.ok ? 'Booking submitted' : 'Booking failed');

                resultDiv.innerHTML = `
                    <div style="background:${resp.ok ? '#e7f1ff' : '#f8d7da'}; color:${resp.ok ? '#084298' : '#721c24'}; padding:10px; border-radius:4px; margin-top:10px;">
                        <strong>Booking Response</strong><br>
                        Status Code: ${statusCode}<br>
                        Message: ${message}
                    </div>
                `;

                // Attempt to save request/response to DB
                try {
                    await fetch('save_booking.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ request: bookingData, response: data || { status: resp.status, raw: text } })
                    });
                } catch (err) {
                    console.warn('Failed to save booking to DB', err);
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:4px; margin-top:10px;">
                        <strong>Booking Failed</strong><br>
                        Error: ${error.message}
                    </div>
                `;
            }
        }

        // Function to save booking to database
        async function saveBookingToDatabase(request, response) { /* disabled in test mode */ }
    </script>
    <script>
        // Cancel Booking UI and flow
        document.addEventListener('click', function(e){
            if (e.target && e.target.id === 'cancel-booking-link'){
                e.preventDefault();
                const modal = document.createElement('div');
                modal.className = 'cancel-booking-modal';
                modal.style.position = 'fixed';
                modal.style.top = '0';
                modal.style.left = '0';
                modal.style.width = '100%';
                modal.style.height = '100%';
                modal.style.background = 'rgba(0,0,0,0.5)';
                modal.style.display = 'flex';
                modal.style.alignItems = 'center';
                modal.style.justifyContent = 'center';
                modal.innerHTML = `
                    <div class="cancel-form-container" style="background: white; padding: 20px; border-radius: 8px; max-width: 600px; width: 95%; max-height: 80vh; overflow-y: auto;">
                        <h2 style="margin-top: 0;">Cancel Booking</h2>
                        <form id="cancel-booking-form" style="display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 12px;">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Booking ID:</label>
                                <input type="text" name="bookingId" placeholder="e.g., 123456" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Reference No:</label>
                                <input type="text" name="referenceNo" placeholder="e.g., RN123" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Cancellation Reason:</label>
                                <textarea name="cancellationReason" rows="3" placeholder="Reason for cancellation" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                            </div>
                            <div style="grid-column: 1 / -1; display: flex; gap: 10px;">
                                <button type="submit" style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">Submit Cancellation</button>
                                <button type="button" class="close-cancel" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Close</button>
                            </div>
                        </form>
                        <div id="cancel-result" style="margin-top: 15px;"></div>
                    </div>
                `;
                document.body.appendChild(modal);

                modal.querySelector('.close-cancel').addEventListener('click', function(){
                    document.body.removeChild(modal);
                });

                modal.querySelector('#cancel-booking-form').addEventListener('submit', async function(ev){
                    ev.preventDefault();
                    const fd = new FormData(this);
                    const payload = {
                        bookingId: fd.get('bookingId') || '',
                        referenceNo: fd.get('referenceNo') || '',
                        cancellationReason: fd.get('cancellationReason') || ''
                    };
                    const resultDiv = modal.querySelector('#cancel-result');
                    resultDiv.innerHTML = '<div style="color:#dc3545;">Submitting cancellation...</div>';
                    try{
                        const resp = await fetch('cancelbooking_proxy.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify(payload)
                        });
                        if(!resp.ok) throw new Error(`HTTP error ${resp.status}`);
                        const data = await resp.json();

                        resultDiv.innerHTML = `
                            <div style="background:#f8d7da; color:#721c24; padding: 10px; border-radius: 4px; margin-top: 10px;">
                                <strong>Cancellation Response</strong><br>
                                Status Code: ${data.statuscode || 'N/A'}<br>
                                Message: ${data.message || data.status || 'N/A'}
                            </div>
                        `;

                        // Update DB status
                        try {
                            await fetch('cancel_booking.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({ request: payload, response: data })
                            });
                        } catch (err) { console.warn('Failed to update DB cancellation status', err); }
                    } catch (error){
                        resultDiv.innerHTML = `
                            <div style="background:#f8d7da; color:#721c24; padding: 10px; border-radius: 4px; margin-top: 10px;">
                                <strong>Cancellation Failed</strong><br>
                                Error: ${error.message}
                            </div>
                        `;
                    }
                });

                // Close on overlay click
                modal.addEventListener('click', function(ev){ if (ev.target === modal) document.body.removeChild(modal); });
            }
        });
    </script>
</body>
</html>