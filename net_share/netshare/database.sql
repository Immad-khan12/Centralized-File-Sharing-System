-- ============================================================
--  NetShare — Network File Transfer System
--  COMPLETE DATABASE SCHEMA
--  Run this file in phpMyAdmin → SQL tab
-- ============================================================

CREATE DATABASE IF NOT EXISTS netshare
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE netshare;

-- ────────────────────────────────────────────────────────────
--  TABLE: users
--  Stores registered user accounts.
--  NETWORKING: ip_address field tracks which LAN device registered.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)     NOT NULL UNIQUE,
    email         VARCHAR(100)    NOT NULL UNIQUE,
    password_hash VARCHAR(255)    NOT NULL,
    ip_address    VARCHAR(45)     NULL,          -- LAN IP of registering device
    last_login    TIMESTAMP       NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_username (username),
    INDEX idx_email    (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
--  TABLE: files
--  Stores metadata of uploaded files.
--  Actual file bytes are saved on disk in /uploads/
--  NETWORKING: upload_ip tracks which client sent the file.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS files (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    original_name VARCHAR(255)    NOT NULL,   -- Client's original filename
    stored_name   VARCHAR(255)    NOT NULL UNIQUE, -- Unique safe name on disk
    file_type     VARCHAR(10)     NOT NULL,   -- Extension: pdf, jpg, png, docx
    file_size     BIGINT UNSIGNED NOT NULL,   -- Size in bytes
    upload_ip     VARCHAR(45)     NOT NULL,   -- Client IP at upload time
    uploaded_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_id    (user_id),
    INDEX idx_uploaded_at(uploaded_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
--  TABLE: transfer_logs
--  Audit trail for ALL upload & download events.
--  NETWORKING: Like a router's access log or Wireshark capture.
--  Records: who transferred, what, when, from where, how fast.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transfer_logs (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    username      VARCHAR(50)     NOT NULL,           -- Denormalized for log integrity
    file_name     VARCHAR(255)    NOT NULL,           -- Original filename
    file_size     VARCHAR(20)     NOT NULL,           -- Human-readable: "2.3 MB"
    transfer_type ENUM('upload','download') NOT NULL,
    status        ENUM('success','failed')  NOT NULL,
    speed         VARCHAR(30)     NULL,               -- e.g. "4.72 MB/s"
    ip_address    VARCHAR(45)     NOT NULL,           -- Client LAN IP
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_id   (user_id),
    INDEX idx_created_at(created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
--  TEST DATA: Default admin account
--  Password: admin123   (bcrypt hashed)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO users (username, email, password_hash, ip_address)
VALUES (
    'admin',
    'admin@netshare.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- "password" for testing
    '127.0.0.1'
);

-- Verify
SELECT 'Tables created successfully!' AS status;
SHOW TABLES;
