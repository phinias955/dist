-- Residence Register System Database
CREATE DATABASE IF NOT EXISTS residence_register;
USE residence_register;

-- Wards table (created first to avoid foreign key issues)
CREATE TABLE wards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ward_name VARCHAR(255) NOT NULL,
    ward_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Villages table (created after wards)
CREATE TABLE villages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    village_name VARCHAR(255) NOT NULL,
    village_code VARCHAR(20) UNIQUE NOT NULL,
    ward_id INT NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ward_id) REFERENCES wards(id)
);

-- Users table with roles (created after wards and villages)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'weo', 'veo') NOT NULL,
    nida_number VARCHAR(20) UNIQUE NOT NULL,
    assigned_ward_id INT NULL,
    assigned_village_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (assigned_ward_id) REFERENCES wards(id),
    FOREIGN KEY (assigned_village_id) REFERENCES villages(id)
);

-- Residence records table
CREATE TABLE residences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_name VARCHAR(255) NOT NULL,
    nida_number VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(15),
    email VARCHAR(100),
    occupation VARCHAR(100),
    family_members INT DEFAULT 1,
    ward_id INT NOT NULL,
    village_id INT NOT NULL,
    registered_by INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'moved') DEFAULT 'active',
    FOREIGN KEY (registered_by) REFERENCES users(id),
    FOREIGN KEY (ward_id) REFERENCES wards(id),
    FOREIGN KEY (village_id) REFERENCES villages(id)
);

-- Permissions table for role-based access control
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    permission_name VARCHAR(255) NOT NULL,
    is_granted BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_permission (role, permission_key)
);

-- System settings table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_name VARCHAR(255) NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'select', 'textarea') DEFAULT 'text',
    setting_options TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default super admin
INSERT INTO users (full_name, username, password, role, nida_number) 
VALUES ('Super Administrator', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', '12345678901234567890');

-- Insert sample admin
INSERT INTO users (full_name, username, password, role, nida_number) 
VALUES ('System Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '12345678901234567891');

-- Insert default permissions for each role
INSERT INTO permissions (role, permission_key, permission_name, is_granted) VALUES
-- Super Admin permissions (all granted by default)
('super_admin', 'manage_users', 'Manage Users', TRUE),
('super_admin', 'manage_residences', 'Manage Residences', TRUE),
('super_admin', 'view_reports', 'View Reports', TRUE),
('super_admin', 'manage_permissions', 'Manage Permissions', TRUE),
('super_admin', 'view_all_data', 'View All Data', TRUE),

-- Admin permissions (can be modified by super admin)
('admin', 'manage_users', 'Manage Users', TRUE),
('admin', 'manage_residences', 'Manage Residences', TRUE),
('admin', 'view_reports', 'View Reports', FALSE),
('admin', 'manage_permissions', 'Manage Permissions', FALSE),
('admin', 'view_all_data', 'View All Data', TRUE),

-- WEO permissions (fixed)
('weo', 'manage_residences', 'Manage Residences', TRUE),
('weo', 'view_own_data', 'View Own Data', TRUE),

-- VEO permissions (fixed)
('veo', 'manage_residences', 'Manage Residences', TRUE),
('veo', 'view_own_data', 'View Own Data', TRUE);

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_name, setting_value, setting_type, description) VALUES
('system_name', 'System Name', 'Residence Register System', 'text', 'Name of the residence registration system'),
('system_version', 'System Version', '1.0.0', 'text', 'Current system version'),
('max_residences_per_user', 'Max Residences Per User', '1000', 'number', 'Maximum number of residences a user can register'),
('allow_duplicate_nida', 'Allow Duplicate NIDA Numbers', 'false', 'boolean', 'Allow multiple residences with same NIDA number'),
('require_phone_verification', 'Require Phone Verification', 'false', 'boolean', 'Require phone number verification for residence registration'),
('auto_approve_residences', 'Auto Approve Residences', 'true', 'boolean', 'Automatically approve new residence registrations'),
('session_timeout', 'Session Timeout (minutes)', '30', 'number', 'User session timeout in minutes'),
('backup_frequency', 'Backup Frequency', 'daily', 'select', 'How often to create system backups'),
('notification_email', 'Notification Email', 'admin@residence-register.com', 'text', 'Email for system notifications'),
('maintenance_mode', 'Maintenance Mode', 'false', 'boolean', 'Enable maintenance mode to restrict system access');

-- Insert sample wards
INSERT INTO wards (ward_name, ward_code, description, created_by) VALUES
('Ward 1', 'W001', 'Central Ward', 1),
('Ward 2', 'W002', 'North Ward', 1),
('Ward 3', 'W003', 'South Ward', 1),
('Ward 4', 'W004', 'East Ward', 1),
('Ward 5', 'W005', 'West Ward', 1);

-- Insert sample villages
INSERT INTO villages (village_name, village_code, ward_id, description, created_by) VALUES
('Village A', 'V001', 1, 'Main village in Ward 1', 1),
('Village B', 'V002', 1, 'Secondary village in Ward 1', 1),
('Village C', 'V003', 2, 'Main village in Ward 2', 1),
('Village D', 'V004', 2, 'Secondary village in Ward 2', 1),
('Village E', 'V005', 3, 'Main village in Ward 3', 1);

-- Now add foreign key constraints for created_by fields
ALTER TABLE wards ADD FOREIGN KEY (created_by) REFERENCES users(id);
ALTER TABLE villages ADD FOREIGN KEY (created_by) REFERENCES users(id);