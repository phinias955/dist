<?php
// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: unauthorized.php');
        exit();
    }
}

function isSuperAdmin() {
    return hasRole('super_admin');
}

function isAdmin() {
    return hasRole('admin') || isSuperAdmin();
}

function isWEO() {
    return hasRole('weo') || isAdmin();
}

function isVEO() {
    return hasRole('veo') || isAdmin();
}

// Password hashing
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Get user role display name
function getRoleDisplayName($role) {
    $roles = [
        'super_admin' => 'Super Administrator',
        'admin' => 'Administrator',
        'weo' => 'Ward Executive Officer',
        'veo' => 'Village Executive Officer',
        'data_collector' => 'Data Collector'
    ];
    return $roles[$role] ?? $role;
}

// Get dashboard URL based on role
function getDashboardUrl($role) {
    $urls = [
        'super_admin' => 'dashboard.php',
        'admin' => 'dashboard.php',
        'weo' => 'dashboard.php',
        'veo' => 'dashboard.php'
    ];
    return $urls[$role] ?? 'dashboard.php';
}

// Legacy permission functions (kept for backward compatibility)
function canManageUsers() {
    return canAccessPage('users');
}

function canManageResidences() {
    return canAccessPage('residences');
}

function canViewReports() {
    return canAccessPage('reports');
}

function canManagePermissions() {
    return canAccessPage('permissions');
}

function canViewAllData() {
    return isSuperAdmin();
}

function canViewOwnData() {
    return !isSuperAdmin();
}


// System Settings Functions
function getSystemSetting($setting_key, $default = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$setting_key]);
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function updateSystemSetting($setting_key, $setting_value) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        return $stmt->execute([$setting_value, $setting_key]);
    } catch (PDOException $e) {
        return false;
    }
}

function getAllSystemSettings() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM system_settings ORDER BY setting_key");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Ward Management Functions
function getAllWards() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT w.*, u.full_name as created_by_name FROM wards w 
                            LEFT JOIN users u ON w.created_by = u.id 
                            ORDER BY w.ward_name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getWardById($ward_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function createWard($ward_name, $ward_code, $description = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO wards (ward_name, ward_code, description, created_by) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$ward_name, $ward_code, $description, $_SESSION['user_id']]);
    } catch (PDOException $e) {
        return false;
    }
}

function updateWard($ward_id, $ward_name, $ward_code, $description = '', $is_active = true) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE wards SET ward_name = ?, ward_code = ?, description = ?, is_active = ? WHERE id = ?");
        return $stmt->execute([$ward_name, $ward_code, $description, $is_active, $ward_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function deleteWard($ward_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM wards WHERE id = ?");
        return $stmt->execute([$ward_id]);
    } catch (PDOException $e) {
        return false;
    }
}

// Village Management Functions
function getAllVillages() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT v.*, w.ward_name, u.full_name as created_by_name FROM villages v 
                            LEFT JOIN wards w ON v.ward_id = w.id 
                            LEFT JOIN users u ON v.created_by = u.id 
                            ORDER BY w.ward_name, v.village_name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getVillagesByWard($ward_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM villages WHERE ward_id = ? AND is_active = 1 ORDER BY village_name");
        $stmt->execute([$ward_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function createVillage($village_name, $village_code, $ward_id, $description = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO villages (village_name, village_code, ward_id, description, created_by) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$village_name, $village_code, $ward_id, $description, $_SESSION['user_id']]);
    } catch (PDOException $e) {
        return false;
    }
}

function updateVillage($village_id, $village_name, $village_code, $ward_id, $description = '', $is_active = true) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE villages SET village_name = ?, village_code = ?, ward_id = ?, description = ?, is_active = ? WHERE id = ?");
        return $stmt->execute([$village_name, $village_code, $ward_id, $description, $is_active, $village_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function deleteVillage($village_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM villages WHERE id = ?");
        return $stmt->execute([$village_id]);
    } catch (PDOException $e) {
        return false;
    }
}

// Location-based access control functions
function getUserAssignedWard() {
    if (!isLoggedIn()) return null;
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT assigned_ward_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        return $result ? $result['assigned_ward_id'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

function getUserAssignedVillage() {
    if (!isLoggedIn()) return null;
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT assigned_village_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        return $result ? $result['assigned_village_id'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

function getUserLocationInfo() {
    if (!isLoggedIn()) return null;
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT u.assigned_ward_id, u.assigned_village_id, 
                                     w.ward_name, w.ward_code,
                                     v.village_name, v.village_code
                              FROM users u 
                              LEFT JOIN wards w ON u.assigned_ward_id = w.id 
                              LEFT JOIN villages v ON u.assigned_village_id = v.id 
                              WHERE u.id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        
        if ($result) {
            // Add ward_id and village_id for consistency
            $result['ward_id'] = $result['assigned_ward_id'];
            $result['village_id'] = $result['assigned_village_id'];
        }
        
        return $result;
    } catch (PDOException $e) {
        return null;
    }
}

function canAccessWard($ward_id) {
    if (isSuperAdmin()) return true;
    if (!isLoggedIn()) return false;
    
    $user_ward = getUserAssignedWard();
    return $user_ward == $ward_id;
}

function canAccessVillage($village_id) {
    if (isSuperAdmin()) return true;
    if (!isLoggedIn()) return false;
    
    $user_village = getUserAssignedVillage();
    return $user_village == $village_id;
}

function getAccessibleWards() {
    if (isSuperAdmin()) {
        return getAllWards();
    }
    
    $user_ward = getUserAssignedWard();
    if (!$user_ward) return [];
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM wards WHERE id = ?");
        $stmt->execute([$user_ward]);
        $ward = $stmt->fetch();
        return $ward ? [$ward] : [];
    } catch (PDOException $e) {
        return [];
    }
}

function getAccessibleVillages() {
    if (isSuperAdmin()) {
        return getAllVillages();
    }
    
    $user_ward = getUserAssignedWard();
    $user_village = getUserAssignedVillage();
    
    global $pdo;
    
    try {
        if ($user_village) {
            // VEO can only see their assigned village
            $stmt = $pdo->prepare("SELECT v.*, w.ward_name FROM villages v 
                                  LEFT JOIN wards w ON v.ward_id = w.id 
                                  WHERE v.id = ?");
            $stmt->execute([$user_village]);
            $village = $stmt->fetch();
            return $village ? [$village] : [];
        } elseif ($user_ward) {
            // WEO can see all villages in their ward
            $stmt = $pdo->prepare("SELECT v.*, w.ward_name FROM villages v 
                                  LEFT JOIN wards w ON v.ward_id = w.id 
                                  WHERE v.ward_id = ?");
            $stmt->execute([$user_ward]);
            return $stmt->fetchAll();
        }
        
        return [];
    } catch (PDOException $e) {
        return [];
    }
}

function getAccessibleResidences() {
    if (isSuperAdmin()) {
        global $pdo;
        try {
            $stmt = $pdo->query("SELECT r.*, u.full_name as registered_by_name, w.ward_name, w.ward_code, v.village_name, v.village_code
                                FROM residences r 
                                LEFT JOIN users u ON r.registered_by = u.id 
                                LEFT JOIN wards w ON r.ward_id = w.id 
                                LEFT JOIN villages v ON r.village_id = v.id 
                                ORDER BY r.registered_at DESC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    if (canViewAllData()) {
        global $pdo;
        try {
            $stmt = $pdo->query("SELECT r.*, u.full_name as registered_by_name, w.ward_name, w.ward_code, v.village_name, v.village_code
                                FROM residences r 
                                LEFT JOIN users u ON r.registered_by = u.id 
                                LEFT JOIN wards w ON r.ward_id = w.id 
                                LEFT JOIN villages v ON r.village_id = v.id 
                                ORDER BY r.registered_at DESC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    $user_ward = getUserAssignedWard();
    $user_village = getUserAssignedVillage();
    
    global $pdo;
    
    try {
        if ($user_village) {
            // VEO can only see residences in their village
            $stmt = $pdo->prepare("SELECT r.*, u.full_name as registered_by_name, w.ward_name, v.village_name 
                                  FROM residences r 
                                  LEFT JOIN users u ON r.registered_by = u.id 
                                  LEFT JOIN wards w ON r.ward_id = w.id 
                                  LEFT JOIN villages v ON r.village_id = v.id 
                                  WHERE r.village_id = ? 
                                  ORDER BY r.registered_at DESC");
            $stmt->execute([$user_village]);
        } elseif ($user_ward) {
            // WEO can see residences in their ward
            $stmt = $pdo->prepare("SELECT r.*, u.full_name as registered_by_name, w.ward_name, v.village_name 
                                  FROM residences r 
                                  LEFT JOIN users u ON r.registered_by = u.id 
                                  LEFT JOIN wards w ON r.ward_id = w.id 
                                  LEFT JOIN villages v ON r.village_id = v.id 
                                  WHERE r.ward_id = ? 
                                  ORDER BY r.registered_at DESC");
            $stmt->execute([$user_ward]);
        } else {
            return [];
        }
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Get pending transfers for VEO (transfers waiting for VEO acceptance)
function getPendingTransfersForVEO() {
    if ($_SESSION['user_role'] !== 'veo') {
        return [];
    }

    $user_location = getUserLocationInfo();
    if (!$user_location || empty($user_location['village_id'])) {
        return [];
    }

    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT rt.*, r.house_no, r.resident_name,
                   fw.ward_name as from_ward_name, fv.village_name as from_village_name,
                   tw.ward_name as to_ward_name, tv.village_name as to_village_name,
                   u.full_name as requested_by_name
            FROM residence_transfers rt
            LEFT JOIN residences r ON rt.residence_id = r.id
            LEFT JOIN wards fw ON rt.from_ward_id = fw.id
            LEFT JOIN villages fv ON rt.from_village_id = fv.id
            LEFT JOIN wards tw ON rt.to_ward_id = tw.id
            LEFT JOIN villages tv ON rt.to_village_id = tv.id
            LEFT JOIN users u ON rt.requested_by = u.id
            WHERE rt.to_village_id = ?
            AND rt.status = 'ward_approved'
            ORDER BY rt.created_at DESC
        ");
        $stmt->execute([$user_location['village_id']]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Get pending transfers for WEO (transfers waiting for WEO approval)
function getPendingTransfersForWEO() {
    if ($_SESSION['user_role'] !== 'weo') {
        return [];
    }

    $user_location = getUserLocationInfo();
    if (!$user_location || empty($user_location['ward_id'])) {
        return [];
    }

    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT rt.*, r.house_no, r.resident_name,
                   fw.ward_name as from_ward_name, fv.village_name as from_village_name,
                   tw.ward_name as to_ward_name, tv.village_name as to_village_name,
                   u.full_name as requested_by_name
            FROM residence_transfers rt
            LEFT JOIN residences r ON rt.residence_id = r.id
            LEFT JOIN wards fw ON rt.from_ward_id = fw.id
            LEFT JOIN villages fv ON rt.from_village_id = fv.id
            LEFT JOIN wards tw ON rt.to_ward_id = tw.id
            LEFT JOIN villages tv ON rt.to_village_id = tv.id
            LEFT JOIN users u ON rt.requested_by = u.id
            WHERE (rt.from_ward_id = ? OR rt.to_ward_id = ?)
            AND rt.status = 'pending_approval'
            ORDER BY rt.created_at DESC
        ");
        $stmt->execute([$user_location['ward_id'], $user_location['ward_id']]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Get pending transfers for Ward Admin (transfers waiting for ward approval)
function getPendingTransfersForWardAdmin() {
    if ($_SESSION['user_role'] !== 'admin') {
        return [];
    }

    $user_location = getUserLocationInfo();
    if (!$user_location || empty($user_location['ward_id'])) {
        return [];
    }

    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT rt.*, r.house_no, r.resident_name,
                   fw.ward_name as from_ward_name, fv.village_name as from_village_name,
                   tw.ward_name as to_ward_name, tv.village_name as to_village_name,
                   u.full_name as requested_by_name
            FROM residence_transfers rt
            LEFT JOIN residences r ON rt.residence_id = r.id
            LEFT JOIN wards fw ON rt.from_ward_id = fw.id
            LEFT JOIN villages fv ON rt.from_village_id = fv.id
            LEFT JOIN wards tw ON rt.to_ward_id = tw.id
            LEFT JOIN villages tv ON rt.to_village_id = tv.id
            LEFT JOIN users u ON rt.requested_by = u.id
            WHERE rt.to_ward_id = ?
            AND rt.status = 'weo_approved'
            ORDER BY rt.created_at DESC
        ");
        $stmt->execute([$user_location['ward_id']]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Permission management functions
function hasPermission($page_name, $action_name = 'view') {
    if (isSuperAdmin()) {
        return true;
    }
    
    global $pdo;
    try {
        // First check user-specific permissions (overrides role permissions)
        $stmt = $pdo->prepare("
            SELECT up.can_access
            FROM user_permissions up
            JOIN permission_pages pp ON up.page_id = pp.id
            LEFT JOIN permission_actions pa ON up.action_id = pa.id
            WHERE up.user_id = ? 
            AND pp.page_name = ? 
            AND (pa.action_name = ? OR (pa.action_name IS NULL AND ? = 'view'))
            AND pp.is_active = TRUE
            AND (pa.is_active = TRUE OR pa.is_active IS NULL)
        ");
        $stmt->execute([$_SESSION['user_id'], $page_name, $action_name, $action_name]);
        $user_result = $stmt->fetch();
        
        // If user has specific permission set, use that
        if ($user_result !== false) {
            return (bool)$user_result['can_access'];
        }
        
        // Otherwise, fall back to role permissions
        $stmt = $pdo->prepare("
            SELECT rp.can_access
            FROM role_permissions rp
            JOIN permission_pages pp ON rp.page_id = pp.id
            LEFT JOIN permission_actions pa ON rp.action_id = pa.id
            WHERE rp.role = ? 
            AND pp.page_name = ? 
            AND (pa.action_name = ? OR (pa.action_name IS NULL AND ? = 'view'))
            AND pp.is_active = TRUE
            AND (pa.is_active = TRUE OR pa.is_active IS NULL)
        ");
        $stmt->execute([$_SESSION['user_role'], $page_name, $action_name, $action_name]);
        $result = $stmt->fetch();
        return $result ? (bool)$result['can_access'] : false;
    } catch (PDOException $e) {
        return false;
    }
}

function canAccessPage($page_name) {
    return hasPermission($page_name, 'view');
}

function canPerformAction($page_name, $action_name) {
    return hasPermission($page_name, $action_name);
}

function getRolePermissions($role) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                pm.module_name, pm.module_display_name, pm.module_icon,
                pp.id as page_id, pp.page_name, pp.page_display_name, pp.page_url, pp.page_icon,
                pa.id as action_id, pa.action_name, pa.action_display_name, pa.action_type,
                rp.can_access
            FROM role_permissions rp
            JOIN permission_pages pp ON rp.page_id = pp.id
            JOIN permission_modules pm ON pp.module_id = pm.id
            LEFT JOIN permission_actions pa ON rp.action_id = pa.id
            WHERE rp.role = ?
            AND pp.is_active = TRUE
            AND (pa.is_active = TRUE OR pa.is_active IS NULL)
            ORDER BY pm.sort_order, pp.sort_order, pa.action_name
        ");
        $stmt->execute([$role]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getAllModules() {
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT * FROM permission_modules 
            WHERE is_active = TRUE 
            ORDER BY sort_order
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getPagesByModule($module_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM permission_pages 
            WHERE module_id = ? AND is_active = TRUE 
            ORDER BY sort_order
        ");
        $stmt->execute([$module_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getActionsByPage($page_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM permission_actions 
            WHERE page_id = ? AND is_active = TRUE 
            ORDER BY action_name
        ");
        $stmt->execute([$page_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function updateRolePermission($role, $page_id, $action_id, $can_access) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO role_permissions (role, page_id, action_id, can_access) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE can_access = ?
        ");
        return $stmt->execute([$role, $page_id, $action_id, $can_access, $can_access]);
    } catch (PDOException $e) {
        return false;
    }
}

function getAvailableRoles() {
    return ['super_admin', 'admin', 'weo', 'veo', 'data_collector'];
}

// User-specific permission functions
function getUserPermissions($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT up.page_id, up.action_id, up.can_access, pp.page_name, pa.action_name
            FROM user_permissions up
            JOIN permission_pages pp ON up.page_id = pp.id
            LEFT JOIN permission_actions pa ON up.action_id = pa.id
            WHERE up.user_id = ?
            ORDER BY pp.page_name, pa.action_name
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function setUserPermission($user_id, $page_id, $action_id, $can_access) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_permissions (user_id, page_id, action_id, can_access) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE can_access = VALUES(can_access)
        ");
        return $stmt->execute([$user_id, $page_id, $action_id, $can_access]);
    } catch (PDOException $e) {
        return false;
    }
}

function removeUserPermission($user_id, $page_id, $action_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            DELETE FROM user_permissions 
            WHERE user_id = ? AND page_id = ? AND action_id = ?
        ");
        return $stmt->execute([$user_id, $page_id, $action_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function clearUserPermissions($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        return $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function getUserPermissionStatus($user_id, $page_name, $action_name = 'view') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT up.can_access
            FROM user_permissions up
            JOIN permission_pages pp ON up.page_id = pp.id
            LEFT JOIN permission_actions pa ON up.action_id = pa.id
            WHERE up.user_id = ? 
            AND pp.page_name = ? 
            AND (pa.action_name = ? OR (pa.action_name IS NULL AND ? = 'view'))
        ");
        $stmt->execute([$user_id, $page_name, $action_name, $action_name]);
        $result = $stmt->fetch();
        return $result ? (bool)$result['can_access'] : null; // null means no specific permission set
    } catch (PDOException $e) {
        return null;
    }
}

// Validation functions
function validateNidaNumber($nida_number) {
    // Remove any spaces or dashes
    $nida_number = preg_replace('/[\s\-]/', '', $nida_number);
    
    // Check if it's exactly 20 digits
    if (preg_match('/^\d{20}$/', $nida_number)) {
        return [
            'valid' => true,
            'cleaned' => $nida_number,
            'error' => null
        ];
    } else {
        return [
            'valid' => false,
            'cleaned' => $nida_number,
            'error' => 'NIDA number must be exactly 20 digits'
        ];
    }
}

function validatePhoneNumber($phone) {
    // Remove any spaces, dashes, or plus signs
    $phone = preg_replace('/[\s\-\+]/', '', $phone);
    
    // Check if it's exactly 10 digits
    if (preg_match('/^\d{10}$/', $phone)) {
        return [
            'valid' => true,
            'cleaned' => $phone,
            'error' => null
        ];
    } else {
        return [
            'valid' => false,
            'cleaned' => $phone,
            'error' => 'Phone number must be exactly 10 digits'
        ];
    }
}

function validateFormData($data, $required_fields = []) {
    $errors = [];
    
    // Validate NIDA number if present
    if (isset($data['nida_number']) && !empty($data['nida_number'])) {
        $nida_validation = validateNidaNumber($data['nida_number']);
        if (!$nida_validation['valid']) {
            $errors['nida_number'] = $nida_validation['error'];
        } else {
            $data['nida_number'] = $nida_validation['cleaned'];
        }
    }
    
    // Validate phone number if present
    if (isset($data['phone']) && !empty($data['phone'])) {
        $phone_validation = validatePhoneNumber($data['phone']);
        if (!$phone_validation['valid']) {
            $errors['phone'] = $phone_validation['error'];
        } else {
            $data['phone'] = $phone_validation['cleaned'];
        }
    }
    
    // Check required fields
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $data
    ];
}

?>
