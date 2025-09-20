<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requirePermission('manage_permissions');

$page_title = 'Manage Permissions';

$message = '';
$error = '';

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    $role = $_POST['role'];
    $permissions = $_POST['permissions'] ?? [];
    
    try {
        // Get all permissions for this role
        $role_permissions = getRolePermissions($role);
        
        foreach ($role_permissions as $permission) {
            $is_granted = in_array($permission['permission_key'], $permissions) ? 1 : 0;
            updatePermission($role, $permission['permission_key'], $is_granted);
        }
        
        $message = 'Permissions updated successfully for ' . getRoleDisplayName($role);
    } catch (Exception $e) {
        $error = 'Error updating permissions';
    }
}

// Get all roles and their permissions
$roles = ['admin', 'weo', 'veo']; // Super admin permissions are not editable
$role_permissions = [];

foreach ($roles as $role) {
    $role_permissions[$role] = getRolePermissions($role);
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Manage Permissions</h1>
    
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
    
    <!-- Permission Management Forms -->
    <?php foreach ($roles as $role): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-user-shield mr-2"></i>
            <?php echo getRoleDisplayName($role); ?> Permissions
        </h2>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="role" value="<?php echo $role; ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($role_permissions[$role] as $permission): ?>
                <div class="flex items-center space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <input type="checkbox" 
                           id="<?php echo $role . '_' . $permission['permission_key']; ?>" 
                           name="permissions[]" 
                           value="<?php echo $permission['permission_key']; ?>"
                           <?php echo $permission['is_granted'] ? 'checked' : ''; ?>
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="<?php echo $role . '_' . $permission['permission_key']; ?>" 
                           class="text-sm font-medium text-gray-700 cursor-pointer">
                        <?php echo $permission['permission_name']; ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" name="update_permissions" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-save mr-2"></i>Update Permissions
                </button>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
    
    <!-- Super Admin Permissions Info -->
    <div class="bg-gray-50 rounded-lg p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-crown mr-2 text-yellow-600"></i>
            Super Administrator Permissions
        </h2>
        <p class="text-gray-600 mb-4">
            Super Administrator has all permissions by default and cannot be modified. 
            This ensures system security and administrative control.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="flex items-center space-x-3 p-3 bg-green-100 border border-green-200 rounded-lg">
                <i class="fas fa-check text-green-600"></i>
                <span class="text-sm font-medium text-green-800">Manage Users</span>
            </div>
            <div class="flex items-center space-x-3 p-3 bg-green-100 border border-green-200 rounded-lg">
                <i class="fas fa-check text-green-600"></i>
                <span class="text-sm font-medium text-green-800">Manage Residences</span>
            </div>
            <div class="flex items-center space-x-3 p-3 bg-green-100 border border-green-200 rounded-lg">
                <i class="fas fa-check text-green-600"></i>
                <span class="text-sm font-medium text-green-800">View Reports</span>
            </div>
            <div class="flex items-center space-x-3 p-3 bg-green-100 border border-green-200 rounded-lg">
                <i class="fas fa-check text-green-600"></i>
                <span class="text-sm font-medium text-green-800">Manage Permissions</span>
            </div>
            <div class="flex items-center space-x-3 p-3 bg-green-100 border border-green-200 rounded-lg">
                <i class="fas fa-check text-green-600"></i>
                <span class="text-sm font-medium text-green-800">View All Data</span>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
