-- MySQL schema for Rayana Tours integration
-- Create database (optional)
-- CREATE DATABASE rayana_tours;
-- USE rayana_tours;

-- Table: bookings
CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id VARCHAR(64) NULL UNIQUE,
  reference_no VARCHAR(64) NULL UNIQUE,
  status_code INT NULL,
  request_data JSON NULL,
  response_data JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: cancellations
CREATE TABLE IF NOT EXISTS cancellations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id VARCHAR(64) NULL,
  reference_no VARCHAR(64) NULL,
  cancellation_reason TEXT NULL,
  status_code INT NULL,
  request_data JSON NULL,
  response_data JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Optional: index to speed lookups on bookings by booking_id/reference_no
CREATE INDEX IF NOT EXISTS idx_bookings_booking_id ON bookings (booking_id);
CREATE INDEX IF NOT EXISTS idx_bookings_reference_no ON bookings (reference_no);

-- Optional: index for cancellations
CREATE INDEX IF NOT EXISTS idx_cancellations_booking_ref ON cancellations (booking_id, reference_no);