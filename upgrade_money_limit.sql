-- Script nâng giới hạn số dư Gtlm từ BIGINT UNSIGNED sang DECIMAL
-- DECIMAL(30,2) cho phép lưu trữ số Gtlm lên đến 999,999,999,999,999,999,999,999,999,999.99 VNĐ

-- Backup dữ liệu trước khi thay đổi (khuyến nghị)
-- CREATE TABLE users_backup AS SELECT * FROM users;

-- Thay đổi kiểu dữ liệu của cột Money
ALTER TABLE users MODIFY COLUMN Money DECIMAL(30,2) UNSIGNED NOT NULL DEFAULT 0;

-- Kiểm tra kết quả
-- DESCRIBE users;

