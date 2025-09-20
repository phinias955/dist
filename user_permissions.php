<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user has permission to manage permissions
if (!canAccessPage('permissions')) {
    header('Location: unauthorized.php');
    exit();
}

$page_title = 'User-Specific Permissions';

$message = '';
$error = '';

// Get all users (except super admin)
$users = [];
try {
    $stmt = $pdo->query("SELECT id, full_name, username, role FROM users WHERE role != 'super_admin' ORDER BY full_name");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error loading users: " . $e->getMessage();
}

// Get selected user
$selected_user = null;
$user_permissions = [];
$modules = [];

if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    
    // Get user details
    try {
        $stmt = $pdo->prepare("SELECT id, full_name, username, role FROM users WHERE id = ? AND role != 'super_admin'");
        $stmt->execute([$user_id]);
        $selected_user = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "Error loading user: " . $e->getMessage();
    }
    
    if ($selected_user) {
        // Get user's current permissions
        $user_permissions = getUserPermissions($user_id);
        
        // Get all modules and pages
        $modules = getAllModules();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isSuperAdmin()) {
        $error = "Only Super Admin can manage user permissions.";
    } else {
        $user_id = (int)$_POST['user_id'];
        $action = $_POST['action'];
        
        if ($action === 'update_permissions') {
            // Clear existing user permissions
            if (clearUserPermissions($user_id)) {
                // Set new permissions
                $success = true;
                if (isset($_POST['permissions'])) {
                    foreach ($_POST['permissions'] as $page_id => $actions) {
                        foreach ($actions as $action_id => $value) {
                            if ($value == '1') {
                                if (!setUserPermission($user_id, $page_id, $action_id, true)) {
                                    $success = false;
                                }
                            }
                        }
                    }
                }
                
                if ($success) {
                    $message = "User permissions updated successfully!";
                    // Refresh user permissions
                    $user_permissions = getUserPermissions($user_id);
                } else {
                    $error = "Error updating some permissions.";
                }
            } else {
                $error = "Error clearing existing permissions.";
            }
        } elseif ($action === 'clear_permissions') {
            if (clearUserPermissions($user_id)) {
                $message = "All user-specific permissions cleared. User will use role permissions.";
                $user_permissions = [];
            } else {
                $error = "Error clearing permissions.";
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">
            <i class="fas fa-user-shield mr-3"></i>User-Specific Permissions
        </h1>
        <p class="text-gray-600">Override role permissions for specific users</p>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- User Selection -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-users mr-2"></i>Select User
        </h2>
        
        <form method="GET" class="flex items-center space-x-4">
            <select name="user_id" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                <option value="">Select a user...</option>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>" <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['username'] . ') - ' . getRoleDisplayName($user['role'])); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                <i class="fas fa-search mr-2"></i>Load Permissions
            </button>
        </form>
    </div>

    <?php if ($selected_user): ?>
    <!-- User Information -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-user mr-2"></i>User Information
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Full Name</label>
                <p class="text-lg text-gray-900"><?php echo htmlspecialchars($selected_user['full_name']); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Username</label>
                <p class="text-lg text-gray-900"><?php echo htmlspecialchars($selected_user['username']); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Role</label>
                <p class="text-lg text-gray-900"><?php echo getRoleDisplayName($selected_user['role']); ?></p>
            </div>
        </div>
    </div>

    <!-- Permission Management -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-gray-800">
                <i class="fas fa-key mr-2"></i>Permission Override
            </h2>
            <div class="space-x-2">
                <button onclick="selectAllPermissions()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                    <i class="fas fa-check-double mr-2"></i>Select All
                </button>
                <button onclick="clearAllPermissions()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-200">
                    <i class="fas fa-times mr-2"></i>Clear All
                </button>
                <button onclick="clearUserPermissions()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">
                    <i class="fas fa-trash mr-2"></i>Reset to Role
                </button>
            </div>
        </div>

        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                <div>
                    <p class="text-sm text-yellow-800 font-medium">Permission Override Instructions:</p>
                    <ul class="text-sm text-yellow-700 mt-1 ml-4 list-disc">
                        <li>Checked permissions will be granted to this user</li>
                        <li>Unchecked permissions will be denied to this user</li>
                        <li>If no specific permission is set, the user will use their role permissions</li>
                        <li>User-specific permissions override role permissions</li>
                    </ul>
                </div>
            </div>
        </div>

        <form method="POST" id="permissionForm">
            <input type="hidden" name="user_id" value="<?php echo $selected_user['id']; ?>">
            <input type="hidden" name="action" value="update_permissions">

            <?php foreach ($modules as $module): ?>
            <div class="bg-gray-50 p-6 rounded-lg shadow-sm mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="<?php echo htmlspecialchars($module['module_icon']); ?> mr-2"></i>
                    <?php echo htmlspecialchars($module['module_display_name']); ?>
                </h3>
                
                <?php foreach (getPagesByModule($module['id']) as $page): ?>
                <div class="ml-4 mb-4">
                    <h4 class="text-md font-medium text-gray-700 mb-2">
                        <i class="<?php echo htmlspecialchars($page['page_icon']); ?> mr-2"></i>
                        <?php echo htmlspecialchars($page['page_display_name']); ?>
                    </h4>
                    <div class="ml-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                        <?php foreach (getActionsByPage($page['id']) as $action): ?>
                        <?php
                        $is_checked = false;
                        foreach ($user_permissions as $perm) {
                            if ($perm['page_id'] == $page['id'] && $perm['action_id'] == $action['id']) {
                                $is_checked = $perm['can_access'];
                                break;
                            }
                        }
                        ?>
                        <label class="inline-flex items-center">
                            <input type="checkbox" 
                                   name="permissions[<?php echo $page['id']; ?>][<?php echo $action['id']; ?>]" 
                                   value="1" 
                                   class="permission-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                   <?php echo $is_checked ? 'checked' : ''; ?>>
                            <span class="ml-2 text-sm text-gray-700">
                                <?php echo htmlspecialchars($action['action_display_name']); ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="clearAllPermissions()" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-200">
                    <i class="fas fa-times mr-2"></i>Clear All
                </button>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-save mr-2"></i>Save Permissions
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Clear User Permissions Form -->
<form id="clearPermissionsForm" method="POST" style="display: none;">
    <input type="hidden" name="user_id" value="<?php echo $selected_user['id'] ?? ''; ?>">
    <input type="hidden" name="action" value="clear_permissions">
</form>

<script>
function selectAllPermissions() {
    const checkboxes = document.querySelectorAll('.permission-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

function clearAllPermissions() {
    const checkboxes = document.querySelectorAll('.permission-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

function clearUserPermissions() {
    if (confirm('Are you sure you want to clear all user-specific permissions? This will reset the user to use only their role permissions.')) {
        document.getElementById('clearPermissionsForm').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
