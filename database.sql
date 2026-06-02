-- File: database.sql
-- Jalankan di phpMyAdmin untuk membuat database dan tabel

CREATE DATABASE IF NOT EXISTS monitoringskjatim;
USE monitoringskjatim;

CREATE TABLE IF NOT EXISTS sensor_data (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(10) NOT NULL,
    temperature FLOAT NOT NULL,
    humidity FLOAT NOT NULL,
    flame INT(1) NOT NULL,
    voltage FLOAT NOT NULL,
    relay VARCHAR(50) NOT NULL,
    system_mode ENUM('auto', 'manual') DEFAULT 'auto',
    relay_state ENUM('ON', 'OFF') DEFAULT 'ON',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert data sample untuk testing
INSERT INTO sensor_data (client_id, temperature, humidity, flame, voltage, relay, system_mode, relay_state) 
VALUES 
('A', 28.5, 65.2, 0, 220.5, 'ON (Normal)', 'auto', 'ON'),
('A', 29.1, 63.8, 0, 219.8, 'ON (Normal)', 'auto', 'ON'),
('A', 27.9, 67.1, 1, 221.2, 'OFF (Proteksi Aktif)', 'auto', 'OFF');