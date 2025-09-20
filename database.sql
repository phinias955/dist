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
    role ENUM('super_admin', 'admin', 'weo', 'veo', 'data_collector') NOT NULL,
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

-- Permission system tables
CREATE TABLE permission_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_name VARCHAR(100) NOT NULL,
    module_display_name VARCHAR(255) NOT NULL,
    module_description TEXT,
    module_icon VARCHAR(50),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permission_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    page_name VARCHAR(100) NOT NULL,
    page_display_name VARCHAR(255) NOT NULL,
    page_url VARCHAR(255) NOT NULL,
    page_description TEXT,
    page_icon VARCHAR(50),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES permission_modules(id) ON DELETE CASCADE
);

CREATE TABLE permission_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    action_name VARCHAR(100) NOT NULL,
    action_display_name VARCHAR(255) NOT NULL,
    action_description TEXT,
    action_type ENUM('view', 'create', 'edit', 'delete', 'approve', 'reject', 'transfer', 'export', 'import') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES permission_pages(id) ON DELETE CASCADE
);

CREATE TABLE role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    page_id INT NOT NULL,
    action_id INT NULL,
    can_access BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES permission_pages(id) ON DELETE CASCADE,
    FOREIGN KEY (action_id) REFERENCES permission_actions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_page_action (role, page_id, action_id)
);

-- Insert permission modules
INSERT INTO permission_modules (module_name, module_display_name, module_description, module_icon, sort_order) VALUES
('dashboard', 'Dashboard', 'Main dashboard and overview', 'fas fa-tachometer-alt', 1),
('users', 'User Management', 'Manage system users and roles', 'fas fa-users', 2),
('residences', 'Residence Management', 'Manage residence records and family members', 'fas fa-home', 3),
('locations', 'Location Management', 'Manage wards and villages', 'fas fa-map-marker-alt', 4),
('transfers', 'Transfer Management', 'Manage residence transfers and approvals', 'fas fa-exchange-alt', 5),
('reports', 'Reports & Analytics', 'Generate reports and view statistics', 'fas fa-chart-bar', 6),
('settings', 'System Settings', 'Configure system settings and permissions', 'fas fa-cog', 7);

-- Insert permission pages
INSERT INTO permission_pages (module_id, page_name, page_display_name, page_url, page_description, page_icon, sort_order) VALUES
-- Dashboard module
(1, 'dashboard', 'Dashboard', 'dashboard.php', 'Main dashboard overview', 'fas fa-tachometer-alt', 1),

-- User Management module
(2, 'users', 'Users', 'users.php', 'View and manage users', 'fas fa-users', 1),
(2, 'add_user', 'Add User', 'add_user.php', 'Add new user', 'fas fa-user-plus', 2),
(2, 'edit_user', 'Edit User', 'edit_user.php', 'Edit user information', 'fas fa-user-edit', 3),
(2, 'permissions', 'Permissions', 'permissions.php', 'Manage role permissions', 'fas fa-shield-alt', 4),

-- Residence Management module
(3, 'residences', 'Residences', 'residences.php', 'View and manage residences', 'fas fa-home', 1),
(3, 'add_residence', 'Add Residence', 'add_residence.php', 'Add new residence', 'fas fa-plus-circle', 2),
(3, 'edit_residence', 'Edit Residence', 'edit_residence.php', 'Edit residence information', 'fas fa-edit', 3),
(3, 'view_residence', 'View Residence', 'view_residence.php', 'View residence details', 'fas fa-eye', 4),
(3, 'family_members', 'Family Members', 'family_members.php', 'Manage family members', 'fas fa-users', 5),
(3, 'deleted_residences', 'Deleted Residences', 'deleted_residences.php', 'View deleted residences', 'fas fa-trash', 6),

-- Location Management module
(4, 'ward_management', 'Ward Management', 'ward_management.php', 'Manage wards', 'fas fa-building', 1),
(4, 'village_management', 'Village Management', 'village_management.php', 'Manage villages', 'fas fa-home', 2),

-- Transfer Management module
(5, 'transfer_approvals', 'Transfer Approvals', 'transfer_approvals.php', 'Approve transfer requests', 'fas fa-exchange-alt', 1),
(5, 'transfer_status', 'Transfer Status', 'transfer_status.php', 'View transfer status', 'fas fa-chart-line', 2),
(5, 'transferred_out', 'Transferred Out', 'transferred_out.php', 'View transferred residences', 'fas fa-arrow-right', 3),
(5, 'transfer_residence', 'Transfer Residence', 'transfer_residence.php', 'Initiate residence transfer', 'fas fa-exchange-alt', 4),
(5, 'transfer_details', 'Transfer Details', 'transfer_details.php', 'View transfer details', 'fas fa-info-circle', 5),

-- Reports module
(6, 'reports', 'Reports', 'reports.php', 'Generate reports', 'fas fa-chart-bar', 1),

-- Settings module
(7, 'system_settings', 'System Settings', 'system_settings.php', 'Configure system settings', 'fas fa-cog', 1);

-- Insert permission actions
INSERT INTO permission_actions (page_id, action_name, action_display_name, action_description, action_type) VALUES
-- Dashboard actions
(1, 'view', 'View Dashboard', 'View main dashboard', 'view'),

-- User management actions
(2, 'view', 'View Users', 'View user list', 'view'),
(2, 'create', 'Add User', 'Add new user', 'create'),
(2, 'edit', 'Edit User', 'Edit user information', 'edit'),
(2, 'delete', 'Delete User', 'Delete user', 'delete'),
(2, 'activate', 'Activate User', 'Activate user account', 'edit'),
(2, 'deactivate', 'Deactivate User', 'Deactivate user account', 'edit'),

(3, 'view', 'View Add User', 'View add user form', 'view'),
(3, 'create', 'Create User', 'Create new user', 'create'),

(4, 'view', 'View Edit User', 'View edit user form', 'view'),
(4, 'edit', 'Update User', 'Update user information', 'edit'),

(5, 'view', 'View Permissions', 'View permission management', 'view'),
(5, 'edit', 'Manage Permissions', 'Manage role permissions', 'edit'),

-- Residence management actions
(6, 'view', 'View Residences', 'View residence list', 'view'),
(6, 'create', 'Add Residence', 'Add new residence', 'create'),
(6, 'edit', 'Edit Residence', 'Edit residence information', 'edit'),
(6, 'delete', 'Delete Residence', 'Delete residence', 'delete'),
(6, 'activate', 'Activate Residence', 'Activate residence', 'edit'),
(6, 'deactivate', 'Deactivate Residence', 'Deactivate residence', 'edit'),
(6, 'transfer', 'Transfer Residence', 'Transfer residence', 'transfer'),

(7, 'view', 'View Add Residence', 'View add residence form', 'view'),
(7, 'create', 'Create Residence', 'Create new residence', 'create'),

(8, 'view', 'View Edit Residence', 'View edit residence form', 'view'),
(8, 'edit', 'Update Residence', 'Update residence information', 'edit'),

(9, 'view', 'View Residence Details', 'View residence details', 'view'),

(10, 'view', 'View Family Members', 'View family members', 'view'),
(10, 'create', 'Add Family Member', 'Add family member', 'create'),
(10, 'edit', 'Edit Family Member', 'Edit family member', 'edit'),
(10, 'delete', 'Delete Family Member', 'Delete family member', 'delete'),

(11, 'view', 'View Deleted Residences', 'View deleted residences', 'view'),
(11, 'restore', 'Restore Residence', 'Restore deleted residence', 'edit'),

-- Location management actions
(12, 'view', 'View Wards', 'View ward list', 'view'),
(12, 'create', 'Add Ward', 'Add new ward', 'create'),
(12, 'edit', 'Edit Ward', 'Edit ward information', 'edit'),
(12, 'delete', 'Delete Ward', 'Delete ward', 'delete'),

(13, 'view', 'View Villages', 'View village list', 'view'),
(13, 'create', 'Add Village', 'Add new village', 'create'),
(13, 'edit', 'Edit Village', 'Edit village information', 'edit'),
(13, 'delete', 'Delete Village', 'Delete village', 'delete'),

-- Transfer management actions
(14, 'view', 'View Transfer Approvals', 'View transfer approvals', 'view'),
(14, 'approve', 'Approve Transfer', 'Approve transfer request', 'approve'),
(14, 'reject', 'Reject Transfer', 'Reject transfer request', 'reject'),
(14, 'cancel', 'Cancel Transfer', 'Cancel transfer request', 'edit'),

(15, 'view', 'View Transfer Status', 'View transfer status', 'view'),

(16, 'view', 'View Transferred Out', 'View transferred residences', 'view'),

(17, 'view', 'View Transfer Residence', 'View transfer residence form', 'view'),
(17, 'create', 'Create Transfer', 'Create transfer request', 'create'),

(18, 'view', 'View Transfer Details', 'View transfer details', 'view'),

-- Reports actions
(19, 'view', 'View Reports', 'View reports', 'view'),
(19, 'export', 'Export Reports', 'Export reports', 'export'),

-- Settings actions
(20, 'view', 'View Settings', 'View system settings', 'view'),
(20, 'edit', 'Update Settings', 'Update system settings', 'edit');

-- Insert default role permissions (Super Admin has all permissions)
INSERT INTO role_permissions (role, page_id, action_id, can_access)
SELECT 'super_admin', p.id, a.id, TRUE
FROM permission_pages p
JOIN permission_actions a ON p.id = a.page_id
WHERE p.is_active = TRUE AND a.is_active = TRUE;

-- Insert default permissions for other roles
-- Admin permissions
INSERT INTO role_permissions (role, page_id, action_id, can_access) VALUES
('admin', 1, 1, TRUE), -- Dashboard view
('admin', 2, 1, TRUE), -- View users
('admin', 2, 2, TRUE), -- Add user
('admin', 2, 3, TRUE), -- Edit user
('admin', 2, 4, TRUE), -- Delete user
('admin', 2, 5, TRUE), -- Activate user
('admin', 2, 6, TRUE), -- Deactivate user
('admin', 3, 1, TRUE), -- View add user
('admin', 3, 2, TRUE), -- Create user
('admin', 4, 1, TRUE), -- View edit user
('admin', 4, 2, TRUE), -- Update user
('admin', 6, 1, TRUE), -- View residences
('admin', 6, 2, TRUE), -- Add residence
('admin', 6, 3, TRUE), -- Edit residence
('admin', 6, 4, TRUE), -- Delete residence
('admin', 6, 5, TRUE), -- Activate residence
('admin', 6, 6, TRUE), -- Deactivate residence
('admin', 6, 7, TRUE), -- Transfer residence
('admin', 7, 1, TRUE), -- View add residence
('admin', 7, 2, TRUE), -- Create residence
('admin', 8, 1, TRUE), -- View edit residence
('admin', 8, 2, TRUE), -- Update residence
('admin', 9, 1, TRUE), -- View residence details
('admin', 10, 1, TRUE), -- View family members
('admin', 10, 2, TRUE), -- Add family member
('admin', 10, 3, TRUE), -- Edit family member
('admin', 10, 4, TRUE), -- Delete family member
('admin', 11, 1, TRUE), -- View deleted residences
('admin', 11, 2, TRUE), -- Restore residence
('admin', 12, 1, TRUE), -- View wards
('admin', 12, 2, TRUE), -- Add ward
('admin', 12, 3, TRUE), -- Edit ward
('admin', 12, 4, TRUE), -- Delete ward
('admin', 13, 1, TRUE), -- View villages
('admin', 13, 2, TRUE), -- Add village
('admin', 13, 3, TRUE), -- Edit village
('admin', 13, 4, TRUE), -- Delete village
('admin', 14, 1, TRUE), -- View transfer approvals
('admin', 14, 2, TRUE), -- Approve transfer
('admin', 14, 3, TRUE), -- Reject transfer
('admin', 14, 4, TRUE), -- Cancel transfer
('admin', 15, 1, TRUE), -- View transfer status
('admin', 16, 1, TRUE), -- View transferred out
('admin', 17, 1, TRUE), -- View transfer residence
('admin', 17, 2, TRUE), -- Create transfer
('admin', 18, 1, TRUE), -- View transfer details
('admin', 19, 1, TRUE), -- View reports
('admin', 19, 2, TRUE), -- Export reports
('admin', 20, 1, TRUE), -- View settings
('admin', 20, 2, TRUE); -- Update settings

-- WEO permissions (read-only for residences, full access for transfers)
INSERT INTO role_permissions (role, page_id, action_id, can_access) VALUES
('weo', 1, 1, TRUE), -- Dashboard view
('weo', 6, 1, TRUE), -- View residences (read-only)
('weo', 9, 1, TRUE), -- View residence details
('weo', 14, 1, TRUE), -- View transfer approvals
('weo', 14, 2, TRUE), -- Approve transfer
('weo', 14, 3, TRUE), -- Reject transfer
('weo', 15, 1, TRUE), -- View transfer status
('weo', 16, 1, TRUE), -- View transferred out
('weo', 18, 1, TRUE), -- View transfer details
('weo', 19, 1, TRUE), -- View reports
('weo', 19, 2, TRUE); -- Export reports

-- VEO permissions (limited access)
INSERT INTO role_permissions (role, page_id, action_id, can_access) VALUES
('veo', 1, 1, TRUE), -- Dashboard view
('veo', 6, 1, TRUE), -- View residences (read-only)
('veo', 9, 1, TRUE), -- View residence details
('veo', 14, 1, TRUE), -- View transfer approvals
('veo', 14, 2, TRUE), -- Approve transfer
('veo', 14, 3, TRUE), -- Reject transfer
('veo', 15, 1, TRUE), -- View transfer status
('veo', 16, 1, TRUE), -- View transferred out
('veo', 18, 1, TRUE), -- View transfer details
('veo', 19, 1, TRUE); -- View reports

-- Data Collector permissions (mobile data collection only)
INSERT INTO role_permissions (role, page_id, action_id, can_access) VALUES
('data_collector', 1, 1, TRUE), -- Dashboard view
('data_collector', 6, 1, TRUE), -- View residences
('data_collector', 6, 2, TRUE), -- Add residence
('data_collector', 9, 1, TRUE), -- View residence details
('data_collector', 9, 2, TRUE), -- Edit residence
('data_collector', 10, 1, TRUE), -- View family members
('data_collector', 10, 2, TRUE), -- Add family member
('data_collector', 10, 3, TRUE), -- Edit family member
('data_collector', 10, 4, TRUE); -- Delete family member

-- User-specific permissions table (overrides role permissions)
CREATE TABLE user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    page_id INT NOT NULL,
    action_id INT NULL,
    can_access BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (page_id) REFERENCES permission_pages(id) ON DELETE CASCADE,
    FOREIGN KEY (action_id) REFERENCES permission_actions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_page_action (user_id, page_id, action_id)
);