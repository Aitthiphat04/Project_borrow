-- ========================================================
-- PEA ITEMFLOW Database Initialization & Schema Definition
-- Database: borrow_db
-- ========================================================

CREATE DATABASE IF NOT EXISTS `borrow_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `borrow_db`;

-- 1. ตาราง transactions
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `dept_seq` INT NOT NULL DEFAULT 1,
  `borrower_name` VARCHAR(150) NOT NULL,
  `borrower_username` VARCHAR(100) NOT NULL DEFAULT '',
  `borrower_dept_name` VARCHAR(150) NOT NULL DEFAULT '',
  `borrower_phone` VARCHAR(30) NOT NULL DEFAULT '',
  `item_name` VARCHAR(255) NOT NULL,
  `sub_item_name` VARCHAR(150) NOT NULL DEFAULT '',
  `serial_number` VARCHAR(100) NOT NULL DEFAULT '',
  `borrow_duration` VARCHAR(100) NOT NULL DEFAULT '',
  `lender_name` VARCHAR(100) NOT NULL DEFAULT '',
  `department` VARCHAR(150) NOT NULL DEFAULT 'แผนกโครงข่ายสื่อสารหลัก',
  `action_type` VARCHAR(20) NOT NULL DEFAULT 'Borrow',
  `image_path` TEXT NOT NULL,
  `borrower_image_path` TEXT NULL,
  `return_image_path` TEXT NULL,
  `record_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `return_time` DATETIME NULL,
  `condition_status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `condition_note` TEXT NULL,
  `extension_status` VARCHAR(20) NOT NULL DEFAULT 'none',
  `extension_days` INT NOT NULL DEFAULT 0,
  `extension_reason` TEXT NULL,
  `extension_request_time` DATETIME NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_action_dept` (`action_type`, `department`),
  INDEX `idx_borrower_username` (`borrower_username`),
  INDEX `idx_record_time` (`record_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ตาราง department_users
CREATE TABLE IF NOT EXISTS `department_users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `password` VARCHAR(255) NOT NULL,
  `dept_key` VARCHAR(50) NOT NULL,
  `dept_name` VARCHAR(150) NOT NULL,
  `role` ENUM('borrower', 'lender', 'admin', 'super_admin') NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`),
  INDEX `idx_dept_role` (`dept_key`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ข้อมูลผู้ใช้เริ่มต้น (Default Users)
INSERT INTO `department_users` (`username`, `full_name`, `phone`, `password`, `dept_key`, `dept_name`, `role`) VALUES
('cn_borrower', 'สมชาย สายสื่อสาร', '0811111111', '1234', 'core_network', 'แผนกโครงข่ายสื่อสารหลัก', 'borrower'),
('cn_lender', 'สมศักดิ์ ผู้ให้ยืม', '0812222222', '1234', 'core_network', 'แผนกโครงข่ายสื่อสารหลัก', 'lender'),
('cn_admin', 'แอดมิน โครงข่าย', '0813333333', '1234', 'core_network', 'แผนกโครงข่ายสื่อสารหลัก', 'admin'),

('pt_borrower', 'วิชัย ระบบไฟ', '0821111111', '1234', 'power_telecom', 'แผนกระบบสื่อสารสนับสนุนงานระบบไฟฟ้า', 'borrower'),
('pt_lender', 'ธนากร ไฟฟ้าสื่อสาร', '0822222222', '1234', 'power_telecom', 'แผนกระบบสื่อสารสนับสนุนงานระบบไฟฟ้า', 'lender'),
('pt_admin', 'แอดมิน ระบบไฟฟ้า', '0823333333', '1234', 'power_telecom', 'แผนกระบบสื่อสารสนับสนุนงานระบบไฟฟ้า', 'admin'),

('cc_borrower', 'นภา สื่อสารองค์กร', '0831111111', '1234', 'corporate_comm', 'แผนกเทคโนโลยีการสื่อสารองค์กร', 'borrower'),
('cc_lender', 'ปรีชา เทคโนโลยี', '0832222222', '1234', 'corporate_comm', 'แผนกเทคโนโลยีการสื่อสารองค์กร', 'lender'),
('cc_admin', 'แอดมิน องค์กร', '0833333333', '1234', 'corporate_comm', 'แผนกเทคโนโลยีการสื่อสารองค์กร', 'admin'),

('admin', 'ผู้ดูแลระบบส่วนกลาง', '0899999999', 'admin1234', 'all', 'สำนักงานใหญ่ / ส่วนกลาง', 'super_admin')
ON DUPLICATE KEY UPDATE 
  `full_name` = VALUES(`full_name`),
  `phone` = VALUES(`phone`),
  `password` = VALUES(`password`),
  `dept_name` = VALUES(`dept_name`),
  `role` = VALUES(`role`);

-- 4. ข้อมูลตัวอย่าง transactions
INSERT INTO `transactions` (`id`, `dept_seq`, `borrower_name`, `borrower_username`, `borrower_dept_name`, `borrower_phone`, `item_name`, `sub_item_name`, `serial_number`, `borrow_duration`, `lender_name`, `department`, `action_type`, `image_path`, `borrower_image_path`, `record_time`, `condition_status`) VALUES
(1, 1, 'สมชาย สายสื่อสาร', 'cn_borrower', 'แผนกโครงข่ายสื่อสารหลัก', '0811111111', 'OTDR วัดสายใยแก้วนำแสง', 'หัวต่อ SC/APC', 'OTDR-2026-001', '3 วัน', 'สมศักดิ์ ผู้ให้ยืม', 'แผนกโครงข่ายสื่อสารหลัก', 'Borrow', 'uploads/equipment_6adeeda2dad9a4.52131349.jpg', 'uploads/equipment_6adeeda2db1586.61810662.jpg', NOW(), 'pending'),
(2, 2, 'สมชาย สายสื่อสาร', 'cn_borrower', 'แผนกโครงข่ายสื่อสารหลัก', '0811111111', 'เครื่องเชื่อมสายใยแก้ว Fusion Splicer', 'พร้อมแท่นตัด Cleaver', 'FUS-8890', '5 วัน', 'สมศักดิ์ ผู้ให้ยืม', 'แผนกโครงข่ายสื่อสารหลัก', 'Borrow', 'uploads/equipment_6ae002ee825416.74182043.jpg', 'uploads/equipment_6ae002ee827165.81122062.jpg', NOW(), 'pending')
ON DUPLICATE KEY UPDATE 
  `borrower_name` = VALUES(`borrower_name`),
  `item_name` = VALUES(`item_name`);
