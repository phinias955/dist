<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user has permission to manage users
if (!canAccessPage('users')) {
    header('Location: unauthorized.php');
    exit();
}

// Only Super Admin and Ward Admin can access deleted users
if (!isSuperAdmin() && $_SESSION['user_role'] !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

$page_title = 'Deleted Users';

$message = '';
$error = '';

// Handle user restoration
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $user_id = (int)$_GET['restore'];
    
    try {
        // Check if user can access this user (location-based access control)
        if (!isSuperAdmin()) {
            $user_location = getUserLocationInfo();
            $can_access = false;
            
            if ($_SESSION['user_role'] === 'admin') {
                // Ward Admin can only restore users from their ward
                $stmt = $pdo->prepare("SELECT assigned_ward_id FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $target_user = $stmt->fetch();
                $can_access = ($target_user && $target_user['assigned_ward_id'] == $user_location['ward_id']);
            }
            
            if (!$can_access) {
                $error = 'You do not have permission to restore this user';
            }
        } else {
            $can_access = true;
        }
        
        if ($can_access) {
            // Get user info before restoring
            $stmt = $pdo->prepare("SELECT full_name, role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_info = $stmt->fetch();
            
            if ($user_info) {
                $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'User "' . $user_info['full_name'] . '" has been restored successfully. They can now log in to the system.';
            } else {
                $error = 'User not found';
            }
        }
    } catch (PDOException $e) {
        $error = 'Error restoring user';
    }
}

// Get deleted users based on location access control
try {
    if (isSuperAdmin()) {
        // Super admin can see all deleted users
        $stmt = $pdo->query("
            SELECT u.id, u.full_name, u.username, u.role, u.nida_number, u.created_at, u.updated_at, 
                   u.assigned_ward_id, u.assigned_village_id,
                   w.ward_name, w.ward_code,
                   v.village_name, v.village_code
            FROM users u
            LEFT JOIN wards w ON u.assigned_ward_id = w.id
            LEFT JOIN villages v ON u.assigned_village_id = v.id
            WHERE u.is_active = 0
            ORDER BY u.updated_at DESC
        ");
    } else {
        // Ward Admin can only see deleted users from their ward
        $user_location = getUserLocationInfo();
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.username, u.role, u.nida_number, u.created_at, u.updated_at, 
                   u.assigned_ward_id, u.assigned_village_id,
                   w.ward_name, w.ward_code,
                   v.village_name, v.village_code
            FROM users u
            LEFT JOIN wards w ON u.assigned_ward_id = w.id
            LEFT JOIN villages v ON u.assigned_village_id = v.id
            WHERE u.is_active = 0 AND u.assigned_ward_id = ?
            ORDER BY u.updated_at DESC
        ");
        $stmt->execute([$user_location['ward_id']]);
    }
    $deleted_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $deleted_users = [];
    $error = 'Error loading deleted users';
}

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Deleted Users</h1>
                <p class="text-gray-600">Manage deactivated user accounts</p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="users.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Users
                </a>
                <?php if (isSuperAdmin()): ?>
                <span class="bg-red-100 text-red-800 text-sm font-medium px-3 py-1 rounded-full">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Super Admin Access
                </span>
                <?php else: ?>
                <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full">
                    <i class="fas fa-user-tie mr-1"></i>
                    Ward Admin Access
                </span>
                <?php endif; ?>
            </div>
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
        
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-times text-red-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Deleted</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo count($deleted_users); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-tie text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Ward Admins</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo count(array_filter($deleted_users, function($user) { return $user['role'] === 'admin'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-cog text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">WEOs</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo count(array_filter($deleted_users, function($user) { return $user['role'] === 'weo'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-friends text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">VEOs</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo count(array_filter($deleted_users, function($user) { return $user['role'] === 'veo'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Deleted Users Table -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-users mr-2"></i>Deleted User Accounts
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    <?php if (isSuperAdmin()): ?>
                        All deactivated users in the system
                    <?php else: ?>
                        Deactivated users from your ward only
                    <?php endif; ?>
                </p>
            </div>
            
            <?php if (empty($deleted_users)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-user-check text-4xl mb-4"></i>
                    <p class="text-lg">No deleted users found</p>
                    <p class="text-sm">All users are currently active</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIDA Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deleted Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($deleted_users as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-4">
                                            <i class="fas fa-user-times text-red-600"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                @<?php echo htmlspecialchars($user['username']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        $role_colors = [
                                            'super_admin' => 'bg-purple-100 text-purple-800',
                                            'admin' => 'bg-blue-100 text-blue-800',
                                            'weo' => 'bg-green-100 text-green-800',
                                            'veo' => 'bg-yellow-100 text-yellow-800',
                                            'data_collector' => 'bg-gray-100 text-gray-800'
                                        ];
                                        echo $role_colors[$user['role']] ?? 'bg-gray-100 text-gray-800';
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($user['ward_name']): ?>
                                        <div class="flex items-center">
                                            <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                                            <div>
                                                <div class="font-medium"><?php echo htmlspecialchars($user['ward_name']); ?></div>
                                                <?php if ($user['village_name']): ?>
                                                    <div class="text-xs text-gray-400"><?php echo htmlspecialchars($user['village_name']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                    <?php echo htmlspecialchars($user['nida_number']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-clock mr-2 text-gray-400"></i>
                                        <?php echo formatDate($user['updated_at']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="?restore=<?php echo $user['id']; ?>" 
                                           class="text-green-600 hover:text-green-900"
                                           onclick="return confirm('Are you sure you want to restore this user? They will be able to log in again.')"
                                           title="Restore User">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900"
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmRestore(message) {
    return confirm(message);
}
</script>

<?php include 'includes/footer.php'; ?>
