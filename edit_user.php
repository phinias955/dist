<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user has permission to manage users
if (!canAccessPage('users')) {
    header('Location: unauthorized.php');
    exit();
}

$page_title = 'Edit User';

$message = '';
$error = '';

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header('Location: users.php');
    exit();
}

// Get user data
try {
    $stmt = $pdo->prepare("SELECT id, full_name, username, role, nida_number, is_active, assigned_ward_id, assigned_village_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: users.php');
        exit();
    }
    
    // Prevent editing super admin (only super admin can edit super admin)
    if ($user['role'] === 'super_admin' && !isSuperAdmin()) {
        header('Location: users.php');
        exit();
    }
    
    // Location-based access control for editing users
    if (!isSuperAdmin()) {
        $user_location = getUserLocationInfo();
        $can_edit = false;
        
        if ($_SESSION['user_role'] === 'veo') {
            // VEO can only edit users from their village
            $can_edit = ($user['assigned_village_id'] == $user_location['village_id']);
        } elseif ($_SESSION['user_role'] === 'weo' || $_SESSION['user_role'] === 'admin') {
            // WEO and Admin can edit users from their ward
            $can_edit = ($user['assigned_ward_id'] == $user_location['ward_id']);
        }
        
        if (!$can_edit) {
            header('Location: users.php');
            exit();
        }
    }
} catch (PDOException $e) {
    header('Location: users.php');
    exit();
}

// Get accessible wards and villages for the current user
$accessible_wards = getAccessibleWards();
$accessible_villages = getAccessibleVillages();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name']);
    $username = sanitizeInput($_POST['username']);
    $role = $_POST['role'];
    $nida_number = sanitizeInput($_POST['nida_number']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $change_password = isset($_POST['change_password']);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $assigned_ward_id = !empty($_POST['assigned_ward_id']) ? (int)$_POST['assigned_ward_id'] : null;
    $assigned_village_id = !empty($_POST['assigned_village_id']) ? (int)$_POST['assigned_village_id'] : null;
    
    // Validation
    if (empty($full_name) || empty($username) || empty($role) || empty($nida_number)) {
        $error = 'All fields are required';
    } elseif (!in_array($role, ['admin', 'weo', 'veo', 'data_collector'])) {
        $error = 'Invalid role selected';
    } elseif ($change_password && $new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif ($change_password && strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long';
    } elseif ($role === 'veo' && (!$assigned_ward_id || !$assigned_village_id)) {
        $error = 'VEO role requires both ward and street/village assignment';
    } elseif ($role === 'data_collector' && (!$assigned_ward_id || !$assigned_village_id)) {
        $error = 'Data Collector role requires both ward and street/village assignment';
    } elseif (($role === 'admin' || $role === 'weo') && !$assigned_ward_id) {
        $error = 'Admin and WEO roles require ward assignment';
    } elseif ($assigned_ward_id && !canAccessWard($assigned_ward_id)) {
        $error = 'You do not have permission to assign this ward';
    } elseif ($assigned_village_id && !canAccessVillage($assigned_village_id)) {
        $error = 'You do not have permission to assign this street/village';
    } else {
        try {
            // Check if username is already taken by another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $error = 'Username already taken';
            } else {
                // Check if NIDA number is already taken by another user
                $stmt = $pdo->prepare("SELECT id FROM users WHERE nida_number = ? AND id != ?");
                $stmt->execute([$nida_number, $user_id]);
                if ($stmt->fetch()) {
                    $error = 'NIDA number already taken';
                } else {
                    // Update user
                    if ($change_password && !empty($new_password)) {
                        $hashed_password = hashPassword($new_password);
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, role = ?, nida_number = ?, password = ?, is_active = ?, assigned_ward_id = ?, assigned_village_id = ? WHERE id = ?");
                        $stmt->execute([$full_name, $username, $role, $nida_number, $hashed_password, $is_active, $assigned_ward_id, $assigned_village_id, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, role = ?, nida_number = ?, is_active = ?, assigned_ward_id = ?, assigned_village_id = ? WHERE id = ?");
                        $stmt->execute([$full_name, $username, $role, $nida_number, $is_active, $assigned_ward_id, $assigned_village_id, $user_id]);
                    }
                    
                    $message = 'User updated successfully';
                    
                    // Update user data for display
                    $user['full_name'] = $full_name;
                    $user['username'] = $username;
                    $user['role'] = $role;
                    $user['nida_number'] = $nida_number;
                    $user['is_active'] = $is_active;
                    $user['assigned_ward_id'] = $assigned_ward_id;
                    $user['assigned_village_id'] = $assigned_village_id;
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred';
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit User</h1>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 alert">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6" id="editUserForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2"></i>Full Name *
                    </label>
                    <input type="text" id="full_name" name="full_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($user['full_name']); ?>">
                </div>
                
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-at mr-2"></i>Username *
                    </label>
                    <input type="text" id="username" name="username" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($user['username']); ?>">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user-tag mr-2"></i>Role *
                    </label>
                    <select id="role" name="role" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        <option value="weo" <?php echo $user['role'] === 'weo' ? 'selected' : ''; ?>>Ward Executive Officer (WEO)</option>
                        <option value="veo" <?php echo $user['role'] === 'veo' ? 'selected' : ''; ?>>Village Executive Officer (VEO)</option>
                        <option value="data_collector" <?php echo $user['role'] === 'data_collector' ? 'selected' : ''; ?>>Data Collector</option>
                    </select>
                </div>
                
                <div>
                    <label for="nida_number" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-id-card mr-2"></i>NIDA Number *
                    </label>
                    <input type="text" id="nida_number" name="nida_number" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($user['nida_number']); ?>">
                </div>
            </div>
            
            <!-- Ward and Village Assignment -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="assigned_ward_id" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-building mr-2"></i>Assigned Ward *
                    </label>
                    <select id="assigned_ward_id" name="assigned_ward_id" required>
                        <option value="">Select Ward</option>
                        <?php foreach ($accessible_wards as $ward): ?>
                            <option value="<?php echo $ward['id']; ?>" 
                                    <?php echo $user['assigned_ward_id'] == $ward['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ward['ward_name'] . ' (' . $ward['ward_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="village_assignment_field">
                    <label for="assigned_village_id" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-home mr-2"></i>Assigned Street/Village
                    </label>
                    <select id="assigned_village_id" name="assigned_village_id">
                        <option value="">Select Street/Village</option>
                        <?php 
                        // Filter villages based on current assigned ward
                        $current_ward_villages = array_filter($accessible_villages, function($village) use ($user) {
                            return $village['ward_id'] == $user['assigned_ward_id'];
                        });
                        foreach ($current_ward_villages as $village): 
                        ?>
                            <option value="<?php echo $village['id']; ?>" 
                                    <?php echo $user['assigned_village_id'] == $village['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($village['village_name'] . ' (' . $village['village_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Required for VEO role</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-3">
                <input type="checkbox" id="is_active" name="is_active" 
                       <?php echo $user['is_active'] ? 'checked' : ''; ?>
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="is_active" class="text-sm font-medium text-gray-700">
                    Active User
                </label>
            </div>
            
            <!-- Password Change Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="flex items-center space-x-3 mb-4">
                    <input type="checkbox" id="change_password" name="change_password" 
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="change_password" class="text-sm font-medium text-gray-700">
                        Change Password
                    </label>
                </div>
                
                <div id="password_fields" class="space-y-4 hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-lock mr-2"></i>New Password
                            </label>
                            <input type="password" id="new_password" name="new_password"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-lock mr-2"></i>Confirm New Password
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4">
                <a href="users.php" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-save mr-2"></i>Update User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Ward and village data for filtering
const villagesData = <?php echo json_encode($accessible_villages); ?>;

// Toggle password fields visibility
document.getElementById('change_password').addEventListener('change', function() {
    const passwordFields = document.getElementById('password_fields');
    if (this.checked) {
        passwordFields.classList.remove('hidden');
        document.getElementById('new_password').required = true;
        document.getElementById('confirm_password').required = true;
    } else {
        passwordFields.classList.add('hidden');
        document.getElementById('new_password').required = false;
        document.getElementById('confirm_password').required = false;
    }
});

// Handle role change for village field visibility
document.getElementById('role').addEventListener('change', function() {
    const role = this.value;
    const villageField = document.getElementById('village_assignment_field');
    const villageSelect = document.getElementById('assigned_village_id');
    
    if (role === 'veo' || role === 'data_collector') {
        villageSelect.required = true;
        villageField.querySelector('label').innerHTML = '<i class="fas fa-home mr-2"></i>Assigned Street/Village *';
    } else {
        villageSelect.required = false;
        villageField.querySelector('label').innerHTML = '<i class="fas fa-home mr-2"></i>Assigned Street/Village';
        villageSelect.value = '';
    }
});

// Handle ward change to filter villages
document.getElementById('assigned_ward_id').addEventListener('change', function() {
    const wardId = this.value;
    const villageSelect = document.getElementById('assigned_village_id');
    
    // Clear current options
    villageSelect.innerHTML = '<option value="">Select Street/Village</option>';
    
    if (wardId) {
        // Filter villages by selected ward
        const wardVillages = villagesData.filter(village => village.ward_id == wardId);
        
        wardVillages.forEach(village => {
            const option = document.createElement('option');
            option.value = village.id;
            option.textContent = village.village_name + ' (' + village.village_code + ')';
            villageSelect.appendChild(option);
        });
    }
});

// Initialize form state on page load
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const villageField = document.getElementById('village_assignment_field');
    const villageSelect = document.getElementById('assigned_village_id');
    
    // Set initial state based on current role
    if (roleSelect.value === 'veo' || roleSelect.value === 'data_collector') {
        villageSelect.required = true;
        villageField.querySelector('label').innerHTML = '<i class="fas fa-home mr-2"></i>Assigned Street/Village *';
    } else {
        villageSelect.required = false;
        villageField.querySelector('label').innerHTML = '<i class="fas fa-home mr-2"></i>Assigned Street/Village';
    }
});

// Form validation
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    const changePassword = document.getElementById('change_password').checked;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const role = document.getElementById('role').value;
    const assignedWard = document.getElementById('assigned_ward_id').value;
    const assignedVillage = document.getElementById('assigned_village_id').value;
    
    if (changePassword) {
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New passwords do not match');
            return false;
        }
        
        if (newPassword.length < 6) {
            e.preventDefault();
            alert('New password must be at least 6 characters long');
            return false;
        }
    }
    
    // Validate role-based requirements
    if (role === 'veo' && (!assignedWard || !assignedVillage)) {
        e.preventDefault();
        alert('VEO role requires both ward and street/village assignment');
        return false;
    }
    
    if ((role === 'admin' || role === 'weo') && !assignedWard) {
        e.preventDefault();
        alert('Admin and WEO roles require ward assignment');
        return false;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
