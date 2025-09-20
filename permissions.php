<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

// Only Super Admin can access this page
if (!isSuperAdmin()) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'Permission Management';

$message = '';
$error = '';

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_permissions') {
        $role = $_POST['role'];
        $permissions = $_POST['permissions'] ?? [];
        
        try {
            // Clear existing permissions for this role
            $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role = ?");
            $stmt->execute([$role]);
            
            // Insert new permissions
            $success_count = 0;
            foreach ($permissions as $permission) {
                $parts = explode('_', $permission);
                $page_id = $parts[0];
                $action_id = $parts[1] === 'null' ? null : $parts[1];
                
                if (updateRolePermission($role, $page_id, $action_id, true)) {
                    $success_count++;
                }
            }
            
            $message = "Permissions updated successfully for " . getRoleDisplayName($role) . " ($success_count permissions set)";
        } catch (PDOException $e) {
            $error = 'Error updating permissions: ' . $e->getMessage();
        }
    }
}

// Get all modules and their pages/actions
$modules = getAllModules();
$role_permissions = [];

// Get permissions for each role
$roles = getAvailableRoles();
foreach ($roles as $role) {
    $role_permissions[$role] = getRolePermissions($role);
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Permission Management</h1>
            <p class="text-gray-600">Manage what each role can see and do in the system</p>
        </div>
        <div class="flex items-center space-x-4">
            <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full">
                <i class="fas fa-shield-alt mr-1"></i>
                Super Admin Access
            </span>
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
    
    <!-- Role Selection -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-users mr-2"></i>Select Role to Manage
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <?php foreach ($roles as $role): ?>
            <button onclick="selectRole('<?php echo $role; ?>')" 
                    class="role-tab p-4 rounded-lg border-2 border-gray-200 hover:border-blue-500 transition duration-200 text-left
                           <?php echo $role === 'admin' ? 'border-blue-500 bg-blue-50' : ''; ?>"
                    id="tab-<?php echo $role; ?>">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center mr-3">
                        <i class="fas fa-user text-gray-600"></i>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900"><?php echo getRoleDisplayName($role); ?></h4>
                        <p class="text-sm text-gray-500">
                            <?php 
                            $permission_count = count(array_filter($role_permissions[$role], function($p) { return $p['can_access']; }));
                            echo $permission_count . ' permissions';
                            ?>
                        </p>
                    </div>
                </div>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Permission Management Form -->
    <form method="POST" id="permissionForm">
        <input type="hidden" name="action" value="update_permissions">
        <input type="hidden" name="role" id="selectedRole" value="admin">
        
        <!-- Module-based Permission Display -->
        <?php foreach ($modules as $module): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden module-section" id="module-<?php echo $module['id']; ?>">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <div class="flex items-center">
                    <i class="<?php echo $module['module_icon']; ?> text-gray-600 mr-3"></i>
                    <h3 class="text-lg font-semibold text-gray-800"><?php echo $module['module_display_name']; ?></h3>
                    <span class="ml-auto text-sm text-gray-500"><?php echo $module['module_description']; ?></span>
                </div>
            </div>
            
            <div class="p-6">
                <?php 
                $pages = getPagesByModule($module['id']);
                foreach ($pages as $page): 
                ?>
                <div class="mb-6">
                    <div class="flex items-center mb-3">
                        <i class="<?php echo $page['page_icon']; ?> text-gray-500 mr-2"></i>
                        <h4 class="text-md font-medium text-gray-800"><?php echo $page['page_display_name']; ?></h4>
                        <span class="ml-2 text-sm text-gray-500"><?php echo $page['page_description']; ?></span>
                    </div>
                    
                    <div class="ml-6">
                        <?php 
                        $actions = getActionsByPage($page['id']);
                        foreach ($actions as $action): 
                        ?>
                        <div class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded mb-2">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full bg-gray-300 mr-3 action-indicator" 
                                      id="indicator-<?php echo $page['id']; ?>-<?php echo $action['id']; ?>"></span>
                                <span class="text-sm font-medium text-gray-700"><?php echo $action['action_display_name']; ?></span>
                                <span class="ml-2 text-xs text-gray-500">(<?php echo $action['action_type']; ?>)</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" 
                                       class="permission-checkbox sr-only" 
                                       name="permissions[]" 
                                       value="<?php echo $page['id']; ?>_<?php echo $action['id']; ?>"
                                       data-page="<?php echo $page['id']; ?>"
                                       data-action="<?php echo $action['id']; ?>"
                                       id="perm-<?php echo $page['id']; ?>-<?php echo $action['id']; ?>">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Action Buttons -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <span id="selectedCount">0</span> permissions selected for <span id="selectedRoleName">Ward Administrator</span>
                </div>
                <div class="flex space-x-4">
                    <button type="button" onclick="selectAll()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        <i class="fas fa-check-square mr-2"></i>Select All
                    </button>
                    <button type="button" onclick="selectNone()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        <i class="fas fa-square mr-2"></i>Select None
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-save mr-2"></i>Save Permissions
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let currentRole = 'admin';
let rolePermissions = <?php echo json_encode($role_permissions); ?>;

function selectRole(role) {
    currentRole = role;
    document.getElementById('selectedRole').value = role;
    document.getElementById('selectedRoleName').textContent = getRoleDisplayName(role);
    
    // Update tab appearance
    document.querySelectorAll('.role-tab').forEach(tab => {
        tab.classList.remove('border-blue-500', 'bg-blue-50');
        tab.classList.add('border-gray-200');
    });
    document.getElementById('tab-' + role).classList.remove('border-gray-200');
    document.getElementById('tab-' + role).classList.add('border-blue-500', 'bg-blue-50');
    
    // Load permissions for this role
    loadRolePermissions(role);
    updateSelectedCount();
}

function getRoleDisplayName(role) {
    const roles = {
        'super_admin': 'Super Administrator',
        'admin': 'Ward Administrator',
        'weo': 'Ward Executive Officer',
        'veo': 'Village Executive Officer'
    };
    return roles[role] || role;
}

function loadRolePermissions(role) {
    // Clear all checkboxes
    document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
        checkbox.checked = false;
        updateIndicator(checkbox);
    });
    
    // Set permissions for this role
    if (rolePermissions[role]) {
        rolePermissions[role].forEach(permission => {
            if (permission.can_access) {
                const checkbox = document.getElementById('perm-' + permission.page_name + '-' + permission.action_name);
                if (checkbox) {
                    checkbox.checked = true;
                    updateIndicator(checkbox);
                }
            }
        });
    }
}

function updateIndicator(checkbox) {
    const pageId = checkbox.dataset.page;
    const actionId = checkbox.dataset.action;
    const indicator = document.getElementById('indicator-' + pageId + '-' + actionId);
    
    if (checkbox.checked) {
        indicator.classList.remove('bg-gray-300');
        indicator.classList.add('bg-green-500');
    } else {
        indicator.classList.remove('bg-green-500');
        indicator.classList.add('bg-gray-300');
    }
}

function selectAll() {
    document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
        checkbox.checked = true;
        updateIndicator(checkbox);
    });
    updateSelectedCount();
}

function selectNone() {
    document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
        checkbox.checked = false;
        updateIndicator(checkbox);
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const selected = document.querySelectorAll('.permission-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = selected;
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Load initial permissions
    loadRolePermissions('admin');
    updateSelectedCount();
    
    // Add change listeners to checkboxes
    document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateIndicator(this);
            updateSelectedCount();
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>