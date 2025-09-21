<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user has permission to manage bin
if (!isSuperAdmin() && $_SESSION['user_role'] !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

$page_title = 'Deleted Users - Bin Management';

$message = '';
$error = '';

// Handle restore operation
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    
    try {
        // Get user data from bin
        $stmt = $pdo->prepare("SELECT * FROM bin_users WHERE id = ?");
        $stmt->execute([$id]);
        $user_data = $stmt->fetch();
        
        if ($user_data) {
            // Check if username or NIDA already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR nida_number = ?");
            $stmt->execute([$user_data['username'], $user_data['nida_number']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $error = 'Cannot restore user: Username or NIDA number already exists in active users.';
            } else {
                // Insert back to users table
                $stmt = $pdo->prepare("
                    INSERT INTO users (full_name, username, password, role, nida_number, assigned_ward_id, assigned_village_id, created_at, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $user_data['full_name'],
                    $user_data['username'],
                    $user_data['password'],
                    $user_data['role'],
                    $user_data['nida_number'],
                    $user_data['assigned_ward_id'],
                    $user_data['assigned_village_id'],
                    $user_data['original_created_at']
                ]);
                
                // Remove from bin
                $stmt = $pdo->prepare("DELETE FROM bin_users WHERE id = ?");
                $stmt->execute([$id]);
                
                // Log operation
                $stmt = $pdo->prepare("
                    INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                    VALUES ('restore', 'user', ?, ?, ?, 'User restored from bin')
                ");
                $stmt->execute([$user_data['original_user_id'], $id, $_SESSION['user_id']]);
                
                $message = 'User "' . $user_data['full_name'] . '" has been restored successfully.';
            }
        } else {
            $error = 'User not found in bin.';
        }
    } catch (PDOException $e) {
        $error = 'Error restoring user: ' . $e->getMessage();
    }
}

// Handle permanent deletion
if (isset($_GET['permanent_delete']) && is_numeric($_GET['permanent_delete'])) {
    $id = (int)$_GET['permanent_delete'];
    
    try {
        // Get user data before deletion
        $stmt = $pdo->prepare("SELECT full_name, original_user_id FROM bin_users WHERE id = ?");
        $stmt->execute([$id]);
        $user_data = $stmt->fetch();
        
        if ($user_data) {
            // Log operation before deletion
            $stmt = $pdo->prepare("
                INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                VALUES ('permanent_delete', 'user', ?, ?, ?, 'User permanently deleted from bin')
            ");
            $stmt->execute([$user_data['original_user_id'], $id, $_SESSION['user_id']]);
            
            // Permanently delete
            $stmt = $pdo->prepare("DELETE FROM bin_users WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = 'User "' . $user_data['full_name'] . '" has been permanently deleted.';
        } else {
            $error = 'User not found in bin.';
        }
    } catch (PDOException $e) {
        $error = 'Error permanently deleting user: ' . $e->getMessage();
    }
}

// Get deleted users
try {
    if (isSuperAdmin()) {
        // Super admin can see all deleted users
        $stmt = $pdo->query("
            SELECT * FROM bin_users 
            ORDER BY deleted_at DESC
        ");
    } else {
        // Ward Admin can only see deleted users from their ward
        $user_location = getUserLocationInfo();
        $stmt = $pdo->prepare("
            SELECT * FROM bin_users 
            WHERE assigned_ward_id = ?
            ORDER BY deleted_at DESC
        ");
        $stmt->execute([$user_location['ward_id']]);
    }
    $deleted_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $deleted_users = [];
    $error = 'Error loading deleted users: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-user-times mr-2"></i>Deleted Users
                </h1>
                <p class="text-gray-600">Manage deleted user accounts in the bin</p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="bin_management.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Bin Management
                </a>
                <?php if (isSuperAdmin()): ?>
                <span class="bg-purple-100 text-purple-800 text-sm font-medium px-3 py-1 rounded-full">
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
                        All deleted users in the system
                    <?php else: ?>
                        Deleted users from your ward only
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
                                        <?php echo formatDate($user['deleted_at']); ?>
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
                                        <a href="?permanent_delete=<?php echo $user['id']; ?>" 
                                           class="text-red-600 hover:text-red-900"
                                           onclick="return confirm('Are you sure you want to permanently delete this user? This action cannot be undone.')"
                                           title="Permanent Delete">
                                            <i class="fas fa-trash"></i>
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

function confirmPermanentDelete(message) {
    return confirm(message);
}
</script>

<?php include 'includes/footer.php'; ?>
