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
    house_no VARCHAR(50) NOT NULL,
    resident_name VARCHAR(255) NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    date_of_birth DATE NOT NULL,
    nida_number VARCHAR(20) NOT NULL,
    phone VARCHAR(15),
    email VARCHAR(100),
    occupation VARCHAR(100),
    ownership ENUM('owner', 'tenant') NOT NULL,
    family_members INT DEFAULT 1,
    education_level ENUM('none', 'primary', 'secondary', 'diploma', 'degree', 'masters', 'phd') DEFAULT 'none',
    employment_status ENUM('employed', 'unemployed', 'student', 'retired', 'self_employed') DEFAULT 'unemployed',
    ward_id INT NOT NULL,
    village_id INT NOT NULL,
    registered_by INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'moved', 'pending_approval') DEFAULT 'active',
    FOREIGN KEY (registered_by) REFERENCES users(id),
    FOREIGN KEY (ward_id) REFERENCES wards(id),
    FOREIGN KEY (village_id) REFERENCES villages(id)
);

-- Family members table
CREATE TABLE family_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    residence_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    date_of_birth DATE,
    nida_number VARCHAR(20),
    relationship ENUM('spouse', 'child', 'parent', 'sibling', 'other') NOT NULL,
    is_minor BOOLEAN DEFAULT FALSE,
    phone VARCHAR(15),
    email VARCHAR(100),
    occupation VARCHAR(100),
    education_level ENUM('none', 'primary', 'secondary', 'diploma', 'degree', 'masters', 'phd') DEFAULT 'none',
    employment_status ENUM('employed', 'unemployed', 'student', 'retired', 'self_employed') DEFAULT 'unemployed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (residence_id) REFERENCES residences(id) ON DELETE CASCADE
);

-- Deleted residences table for tracking deletions and transfers
CREATE TABLE deleted_residences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_residence_id INT,
    house_no VARCHAR(50) NOT NULL,
    resident_name VARCHAR(255) NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    date_of_birth DATE NOT NULL,
    nida_number VARCHAR(20) NOT NULL,
    phone VARCHAR(15),
    email VARCHAR(100),
    occupation VARCHAR(100),
    ownership ENUM('owner', 'tenant') NOT NULL,
    family_members INT DEFAULT 1,
    education_level ENUM('none', 'primary', 'secondary', 'diploma', 'degree', 'masters', 'phd') DEFAULT 'none',
    employment_status ENUM('employed', 'unemployed', 'student', 'retired', 'self_employed') DEFAULT 'unemployed',
    ward_id INT NOT NULL,
    village_id INT NOT NULL,
    registered_by INT NOT NULL,
    original_registered_at TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_by INT NOT NULL,
    deletion_reason ENUM('deleted', 'transferred', 'moved') NOT NULL,
    status ENUM('pending_approval', 'approved', 'rejected') DEFAULT 'pending_approval',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
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
INSERT INTO users (full_name, username, password, role, nida_number, assigned_ward_id) 
VALUES ('Super Administrator', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', '12345678901234567890', 1);

-- Insert sample admin
INSERT INTO users (full_name, username, password, role, nida_number, assigned_ward_id) 
VALUES ('System Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '12345678901234567891', 1);

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

-- Insert sample residences
INSERT INTO residences (house_no, resident_name, gender, date_of_birth, nida_number, phone, email, occupation, ownership, family_members, education_level, employment_status, ward_id, village_id, registered_by) VALUES
('H001', 'John Mwalimu', 'male', '1985-03-15', '12345678901234567890', '0712345678', 'john@email.com', 'Teacher', 'owner', 3, 'degree', 'employed', 1, 1, 1),
('H002', 'Mary Kimaro', 'female', '1990-07-22', '12345678901234567891', '0723456789', 'mary@email.com', 'Nurse', 'tenant', 2, 'diploma', 'employed', 1, 1, 1),
('H003', 'Peter Mwangi', 'male', '1978-12-10', '12345678901234567892', '0734567890', 'peter@email.com', 'Farmer', 'owner', 4, 'secondary', 'self_employed', 2, 3, 1);

-- Residence transfers table for managing transfer requests and approvals
CREATE TABLE residence_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    residence_id INT NOT NULL,
    from_ward_id INT NOT NULL,
    from_village_id INT NOT NULL,
    to_ward_id INT NOT NULL,
    to_village_id INT NOT NULL,
    transfer_type ENUM('veo', 'ward_admin', 'super_admin') NOT NULL,
    transfer_reason TEXT NOT NULL,
    requested_by INT NOT NULL,
    status ENUM('pending_approval', 'weo_approved', 'ward_approved', 'veo_accepted', 'completed', 'rejected') DEFAULT 'pending_approval',
    weo_approved_by INT NULL,
    weo_approved_at TIMESTAMP NULL,
    ward_approved_by INT NULL,
    ward_approved_at TIMESTAMP NULL,
    veo_accepted_by INT NULL,
    veo_accepted_at TIMESTAMP NULL,
    rejected_by INT NULL,
    rejected_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (residence_id) REFERENCES residences(id) ON DELETE CASCADE,
    FOREIGN KEY (from_ward_id) REFERENCES wards(id),
    FOREIGN KEY (from_village_id) REFERENCES villages(id),
    FOREIGN KEY (to_ward_id) REFERENCES wards(id),
    FOREIGN KEY (to_village_id) REFERENCES villages(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (weo_approved_by) REFERENCES users(id),
    FOREIGN KEY (ward_approved_by) REFERENCES users(id),
    FOREIGN KEY (veo_accepted_by) REFERENCES users(id),
    FOREIGN KEY (rejected_by) REFERENCES users(id)
);

-- Now add foreign key constraints for created_by fields
ALTER TABLE wards ADD FOREIGN KEY (created_by) REFERENCES users(id);
ALTER TABLE villages ADD FOREIGN KEY (created_by) REFERENCES users(id);