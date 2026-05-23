-- ============================================
-- TRAVEL BOOKING SYSTEM — Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS travel_booking;
USE travel_booking;

-- USERS TABLE
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- FLIGHTS TABLE
CREATE TABLE flights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    airline VARCHAR(100) NOT NULL,
    origin VARCHAR(100) NOT NULL,
    destination VARCHAR(100) NOT NULL,
    departure_time DATETIME NOT NULL,
    arrival_time DATETIME NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    seats_available INT DEFAULT 50,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- HOTELS TABLE
CREATE TABLE hotels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_name VARCHAR(150) NOT NULL,
    location VARCHAR(150) NOT NULL,
    description TEXT,
    price_per_night DECIMAL(10,2) NOT NULL,
    rating DECIMAL(2,1) DEFAULT 4.0,
    rooms_available INT DEFAULT 20,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- BOOKINGS TABLE
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    booking_type ENUM('flight', 'hotel') NOT NULL,
    item_id INT NOT NULL,
    check_in DATE,
    check_out DATE,
    guests INT DEFAULT 1,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'confirmed',
    transaction_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- SEED DATA
-- ============================================

-- Admin user (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@travel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Sample Flights
INSERT INTO flights (airline, origin, destination, departure_time, arrival_time, price, seats_available) VALUES
('Philippine Airlines', 'Manila (MNL)', 'Cebu (CEB)', '2025-08-01 06:00:00', '2025-08-01 07:10:00', 2500.00, 120),
('Cebu Pacific', 'Manila (MNL)', 'Davao (DVO)', '2025-08-02 08:00:00', '2025-08-02 09:30:00', 1800.00, 180),
('AirAsia', 'Manila (MNL)', 'Boracay (MPH)', '2025-08-03 10:00:00', '2025-08-03 11:10:00', 3200.00, 100),
('Philippine Airlines', 'Cebu (CEB)', 'Manila (MNL)', '2025-08-05 14:00:00', '2025-08-05 15:10:00', 2700.00, 90),
('Cebu Pacific', 'Davao (DVO)', 'Manila (MNL)', '2025-08-06 16:00:00', '2025-08-06 17:30:00', 1900.00, 150),
('AirAsia', 'Manila (MNL)', 'Palawan (PPS)', '2025-08-07 07:00:00', '2025-08-07 08:30:00', 2900.00, 80),
('Philippine Airlines', 'Manila (MNL)', 'Singapore (SIN)', '2025-08-10 09:00:00', '2025-08-10 12:30:00', 8500.00, 200),
('Cebu Pacific', 'Manila (MNL)', 'Bangkok (BKK)', '2025-08-12 11:00:00', '2025-08-12 14:00:00', 7200.00, 160);

-- Sample Hotels
INSERT INTO hotels (hotel_name, location, description, price_per_night, rating, rooms_available) VALUES
('Shangri-La Boracay', 'Boracay, Aklan', 'Luxury beachfront resort with stunning ocean views and world-class amenities.', 12000.00, 4.9, 30),
('Crimson Resort', 'Mactan, Cebu', 'Elegant island resort featuring private beach and infinity pool.', 8500.00, 4.7, 40),
('Hue Hotels', 'Coron, Palawan', 'Boutique hotel near the famous Coron Bay with dive packages available.', 5500.00, 4.5, 25),
('Marco Polo Plaza', 'Cebu City', 'Premier business hotel in the heart of Cebu City with panoramic views.', 4500.00, 4.6, 60),
('Discovery Shores', 'Boracay, Aklan', 'Award-winning beachfront hotel on the famous White Beach.', 9800.00, 4.8, 35),
('El Nido Resorts', 'El Nido, Palawan', 'Eco-friendly island resort surrounded by limestone cliffs and turquoise waters.', 15000.00, 4.9, 20),
('Seda BGC', 'Taguig, Metro Manila', 'Modern lifestyle hotel at the center of Bonifacio Global City.', 6500.00, 4.5, 80),
('The Bellevue Manila', 'Alabang, Metro Manila', 'Elegant hotel offering refined luxury in the south of Metro Manila.', 5800.00, 4.4, 50);
