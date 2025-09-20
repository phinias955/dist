<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requirePermission('manage_users');

$page_title = 'Manage Users';

$message = '';
$error = '';

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    // Prevent deleting super admin
    if ($user_id == 1) {
        $error = 'Cannot deactivate super administrator';
    } else {
        try {
            // Check if user can access this user (location-based access control)
            if (!isSuperAdmin()) {
                $user_location = getUserLocationInfo();
                $can_access = false;
                
                if ($_SESSION['user_role'] === 'veo') {
                    // VEO can only deactivate users from their village
                    $stmt = $pdo->prepare("SELECT assigned_village_id FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $target_user = $stmt->fetch();
                    $can_access = ($target_user && $target_user['assigned_village_id'] == $user_location['village_id']);
                } elseif ($_SESSION['user_role'] === 'weo' || $_SESSION['user_role'] === 'admin') {
                    // WEO and Admin can deactivate users from their ward
                    $stmt = $pdo->prepare("SELECT assigned_ward_id FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $target_user = $stmt->fetch();
                    $can_access = ($target_user && $target_user['assigned_ward_id'] == $user_location['ward_id']);
                }
                
                if (!$can_access) {
                    $error = 'You do not have permission to deactivate this user';
                }
            } else {
                $can_access = true; // Super admin can access all users
            }
            
            if ($can_access) {
                // Get user info before deactivating
                $stmt = $pdo->prepare("SELECT full_name, role FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_info = $stmt->fetch();
                
                $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'User "' . $user_info['full_name'] . '" has been deactivated. They will need to contact their administrator to reactivate their account.';
            }
        } catch (PDOException $e) {
            $error = 'Error deactivating user';
        }
    }
}

// Handle user activation
if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
    $user_id = (int)$_GET['activate'];
    
    try {
        // Check if user can access this user (location-based access control)
        if (!isSuperAdmin()) {
            $user_location = getUserLocationInfo();
            $can_access = false;
            
            if ($_SESSION['user_role'] === 'veo') {
                // VEO can only activate users from their village
                $stmt = $pdo->prepare("SELECT assigned_village_id FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $target_user = $stmt->fetch();
                $can_access = ($target_user && $target_user['assigned_village_id'] == $user_location['village_id']);
            } elseif ($_SESSION['user_role'] === 'weo' || $_SESSION['user_role'] === 'admin') {
                // WEO and Admin can activate users from their ward
                $stmt = $pdo->prepare("SELECT assigned_ward_id FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $target_user = $stmt->fetch();
                $can_access = ($target_user && $target_user['assigned_ward_id'] == $user_location['ward_id']);
            }
            
            if (!$can_access) {
                $error = 'You do not have permission to activate this user';
            }
        } else {
            $can_access = true; // Super admin can access all users
        }
        
        if ($can_access) {
            // Get user info before activating
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_info = $stmt->fetch();
            
            $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            $message = 'User "' . $user_info['full_name'] . '" has been activated successfully. They can now log in to the system.';
        }
    } catch (PDOException $e) {
        $error = 'Error activating user';
    }
}

// Get users based on location access control
try {
    if (isSuperAdmin()) {
        // Super admin can see all users
        $stmt = $pdo->query("
            SELECT u.id, u.full_name, u.username, u.role, u.nida_number, u.created_at, u.is_active, 
                   u.assigned_ward_id, u.assigned_village_id,
                   w.ward_name, w.ward_code,
                   v.village_name, v.village_code
            FROM users u
            LEFT JOIN wards w ON u.assigned_ward_id = w.id
            LEFT JOIN villages v ON u.assigned_village_id = v.id
            ORDER BY u.created_at DESC
        ");
    } else {
        // Location-based access control for other roles
        $user_location = getUserLocationInfo();
        
        if ($_SESSION['user_role'] === 'veo') {
            // VEO can only see users from their assigned village
            if (!empty($user_location['village_id'])) {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.full_name, u.username, u.role, u.nida_number, u.created_at, u.is_active,
                           u.assigned_ward_id, u.assigned_village_id,
                           w.ward_name, w.ward_code,
                           v.village_name, v.village_code
                    FROM users u
                    LEFT JOIN wards w ON u.assigned_ward_id = w.id
                    LEFT JOIN villages v ON u.assigned_village_id = v.id
                    WHERE u.role != 'super_admin' 
                    AND u.assigned_village_id = ?
                    ORDER BY u.created_at DESC
                ");
                $stmt->execute([$user_location['village_id']]);
            } else {
                $users = []; // No village assigned
            }
        } elseif ($_SESSION['user_role'] === 'weo' || $_SESSION['user_role'] === 'admin') {
            // WEO and Admin can see users from their assigned ward
            if (!empty($user_location['ward_id'])) {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.full_name, u.username, u.role, u.nida_number, u.created_at, u.is_active,
                           u.assigned_ward_id, u.assigned_village_id,
                           w.ward_name, w.ward_code,
                           v.village_name, v.village_code
                    FROM users u
                    LEFT JOIN wards w ON u.assigned_ward_id = w.id
                    LEFT JOIN villages v ON u.assigned_village_id = v.id
                    WHERE u.role != 'super_admin' 
                    AND u.assigned_ward_id = ?
                    ORDER BY u.created_at DESC
                ");
                $stmt->execute([$user_location['ward_id']]);
            } else {
                $users = []; // No ward assigned
            }
        } else {
            // Fallback - no users if role not recognized
            $users = [];
        }
    }
    
    // Only fetch if $stmt is defined
    if (isset($stmt)) {
        $users = $stmt->fetchAll();
    } else {
        $users = [];
    }
} catch (PDOException $e) {
    $error = 'Database error occurred';
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Manage Users</h1>
            <?php if (!isSuperAdmin()): ?>
            <?php 
            $user_location = getUserLocationInfo();
            if ($user_location): 
            ?>
            <p class="text-sm text-gray-600 mt-1">
                <i class="fas fa-map-marker-alt mr-1"></i>
                Managing users for: 
                <span class="font-medium"><?php echo htmlspecialchars($user_location['ward_name'] ?? 'Unknown Ward'); ?></span>
                <?php if (!empty($user_location['village_name'])): ?>
                    - <span class="font-medium"><?php echo htmlspecialchars($user_location['village_name']); ?></span>
                <?php endif; ?>
            </p>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <a href="add_user.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
            <i class="fas fa-plus mr-2"></i>Add New User
        </a>
    </div>
    
    <!-- Messages -->
    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded alert">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded alert">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIDA Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Ward</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Street/Village</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-gray-600"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php 
                                switch($user['role']) {
                                    case 'super_admin': echo 'bg-red-100 text-red-800'; break;
                                    case 'admin': echo 'bg-purple-100 text-purple-800'; break;
                                    case 'weo': echo 'bg-blue-100 text-blue-800'; break;
                                    case 'veo': echo 'bg-green-100 text-green-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?php echo getRoleDisplayName($user['role']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($user['nida_number']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php if ($user['ward_name']): ?>
                                <div class="flex items-center">
                                    <i class="fas fa-building mr-2 text-gray-400"></i>
                                    <div>
                                        <div class="font-medium"><?php echo htmlspecialchars($user['ward_name']); ?></div>
                                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($user['ward_code']); ?></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-400 italic">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php if ($user['village_name']): ?>
                                <div class="flex items-center">
                                    <i class="fas fa-home mr-2 text-gray-400"></i>
                                    <div>
                                        <div class="font-medium"><?php echo htmlspecialchars($user['village_name']); ?></div>
                                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($user['village_code']); ?></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-400 italic">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Locked'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo formatDate($user['created_at']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($user['id'] != 1): // Don't show delete for super admin ?>
                                <?php if ($user['is_active']): ?>
                                    <a href="?delete=<?php echo $user['id']; ?>" 
                                       class="text-red-600 hover:text-red-900"
                                       onclick="return confirmDelete('Are you sure you want to lock this user account? They will not be able to log in until reactivated.')"
                                       title="Lock Account">
                                        <i class="fas fa-lock"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="?activate=<?php echo $user['id']; ?>" 
                                       class="text-green-600 hover:text-green-900"
                                       title="Unlock Account">
                                        <i class="fas fa-unlock"></i>
                                    </a>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
