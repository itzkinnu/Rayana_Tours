<?php
// Database connection configuration
$host = 'localhost';
$dbname = 'rayana_tours';
$username = 'root';
$password = 'root';
$port = 8889; // MAMP default MySQL port

// Create PDO connection
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // Don't expose database errors to users
    die('Database connection error');
}

// Function to create bookings table if it doesn't exist
function createBookingsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unique_no VARCHAR(50) NOT NULL,
        tour_id INT NOT NULL,
        option_id INT NOT NULL,
        transfer_id INT NOT NULL,
        tour_date DATE NOT NULL,
        start_time VARCHAR(20) NOT NULL,
        adult_rate DECIMAL(10,2) NOT NULL,
        child_rate DECIMAL(10,2) NOT NULL,
        service_total DECIMAL(10,2) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        nationality VARCHAR(10) NOT NULL,
        request_data JSON NOT NULL,
        response_data JSON NOT NULL,
        status_code INT,
        reference_no VARCHAR(100),
        booking_id VARCHAR(100),
        confirmation_no VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    try {
        $pdo->exec($sql);
        error_log("Bookings table created or already exists");
    } catch (PDOException $e) {
        error_log("Error creating bookings table: " . $e->getMessage());
    }
}

// Create table on include
createBookingsTable($pdo);
?>