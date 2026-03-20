CREATE DATABASE IF NOT EXISTS url_shortener_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE url_shortener_db;

CREATE TABLE IF NOT EXISTS urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_url TEXT NOT NULL,
    short_code VARCHAR(20) NOT NULL UNIQUE,
    click_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_urls_click_count CHECK (click_count >= 0)
);

CREATE TABLE IF NOT EXISTS visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url_id INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    country VARCHAR(120) NULL,
    user_agent TEXT NULL,
    visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_visits_url
        FOREIGN KEY (url_id) REFERENCES urls(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE INDEX idx_urls_short_code ON urls(short_code);
CREATE INDEX idx_visits_url_timestamp ON visits(url_id, visited_at DESC);