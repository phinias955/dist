-- Bin System Database Tables
-- This file creates tables to store all deleted data in the system

-- Bin for deleted users
CREATE TABLE IF NOT EXISTS bin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_user_id INT,
    full_name VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'weo', 'veo', 'data_collector') NOT NULL,
    nida_number VARCHAR(20) NOT NULL,
    assigned_ward_id INT NULL,
    assigned_village_id INT NULL,
    original_created_at TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_by INT NOT NULL,
    deletion_reason ENUM('deleted', 'deactivated') NOT NULL,
    ward_name VARCHAR(255),
    village_name VARCHAR(255),
    FOREIGN KEY (deleted_by) REFERENCES users(id)
);

-- Bin for deleted family members
CREATE TABLE IF NOT EXISTS bin_family_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_family_member_id INT,
    original_residence_id INT,
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
    original_created_at TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_by INT NOT NULL,
    deletion_reason ENUM('deleted', 'residence_deleted') NOT NULL,
    residence_house_no VARCHAR(50),
    ward_name VARCHAR(255),
    village_name VARCHAR(255),
    FOREIGN KEY (deleted_by) REFERENCES users(id)
);

-- Bin for deleted wards
CREATE TABLE IF NOT EXISTS bin_wards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_ward_id INT,
    ward_name VARCHAR(255) NOT NULL,
    ward_code VARCHAR(20) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    original_created_at TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_by INT NOT NULL,
    deletion_reason ENUM('deleted') NOT NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id)
);

-- Bin for deleted villages
CREATE TABLE IF NOT EXISTS bin_villages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_village_id INT,
    village_name VARCHAR(255) NOT NULL,
    village_code VARCHAR(20) NOT NULL,
    ward_id INT NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    original_created_at TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_by INT NOT NULL,
    deletion_reason ENUM('deleted') NOT NULL,
    ward_name VARCHAR(255),
    FOREIGN KEY (deleted_by) REFERENCES users(id)
);

-- Bin for deleted residences (enhanced version of existing deleted_residences)
CREATE TABLE IF NOT EXISTS bin_residences (
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
    ward_name VARCHAR(255),
    village_name VARCHAR(255),
    FOREIGN KEY (deleted_by) REFERENCES users(id)
);

-- Bin management table for tracking operations
CREATE TABLE IF NOT EXISTS bin_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operation_type ENUM('restore', 'permanent_delete') NOT NULL,
    data_type ENUM('user', 'residence', 'family_member', 'ward', 'village') NOT NULL,
    original_id INT NOT NULL,
    bin_id INT NOT NULL,
    performed_by INT NOT NULL,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (performed_by) REFERENCES users(id)
);
