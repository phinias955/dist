<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireRole('super_admin');

$page_title = 'Ward Management';

$message = '';
$error = '';

// Handle ward operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_ward') {
        $ward_name = sanitizeInput($_POST['ward_name']);
        $ward_code = sanitizeInput($_POST['ward_code']);
        $description = sanitizeInput($_POST['description']);
        
        if (empty($ward_name) || empty($ward_code)) {
            $error = 'Ward name and code are required';
        } else {
            if (createWard($ward_name, $ward_code, $description)) {
                $message = 'Ward created successfully';
            } else {
                $error = 'Error creating ward. Ward code may already exist.';
            }
        }
    } elseif ($action === 'update_ward') {
        $ward_id = (int)$_POST['ward_id'];
        $ward_name = sanitizeInput($_POST['ward_name']);
        $ward_code = sanitizeInput($_POST['ward_code']);
        $description = sanitizeInput($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($ward_name) || empty($ward_code)) {
            $error = 'Ward name and code are required';
        } else {
            if (updateWard($ward_id, $ward_name, $ward_code, $description, $is_active)) {
                $message = 'Ward updated successfully';
            } else {
                $error = 'Error updating ward';
            }
        }
    }
}

// Handle ward deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $ward_id = (int)$_GET['delete'];
    
    if (deleteWard($ward_id)) {
        $message = 'Ward deleted successfully';
    } else {
        $error = 'Error deleting ward. Ward may have associated villages.';
    }
}

// Get all wards
$wards = getAllWards();

include 'includes/header.php';
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">Ward Management</h1>
        <button onclick="openCreateModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
            <i class="fas fa-plus mr-2"></i>Add New Ward
        </button>
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
    
    <!-- Wards Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ward Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ward Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($wards as $ward): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-building text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($ward['ward_name']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($ward['ward_code']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <div class="max-w-xs truncate">
                                <?php echo htmlspecialchars($ward['description'] ?: 'No description'); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $ward['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $ward['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($ward['created_by_name'] ?? 'Unknown'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($ward)); ?>)" 
                                        class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="village_management.php?ward_id=<?php echo $ward['id']; ?>" 
                                   class="text-green-600 hover:text-green-900"
                                   title="Manage Streets/Villages">
                                    <i class="fas fa-home"></i>
                                </a>
                                <a href="?delete=<?php echo $ward['id']; ?>" 
                                   class="text-red-600 hover:text-red-900"
                                   onclick="return confirmDelete('Are you sure you want to delete this ward? This will also delete all associated villages.')"
                                   title="Delete Ward">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit Ward Modal -->
<div id="wardModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Add New Ward</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" id="wardForm">
                <input type="hidden" name="action" id="formAction" value="create_ward">
                <input type="hidden" name="ward_id" id="wardId" value="">
                
                <div class="space-y-4">
                    <div>
                        <label for="ward_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Ward Name *
                        </label>
                        <input type="text" id="ward_name" name="ward_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="ward_code" class="block text-sm font-medium text-gray-700 mb-2">
                            Ward Code *
                        </label>
                        <input type="text" id="ward_code" name="ward_code" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                            Description
                        </label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div id="statusField" class="hidden">
                        <div class="flex items-center space-x-3">
                            <input type="checkbox" id="is_active" name="is_active" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_active" class="text-sm font-medium text-gray-700">
                                Active Ward
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal()" 
                            class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                        Save Ward
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add New Ward';
    document.getElementById('formAction').value = 'create_ward';
    document.getElementById('wardId').value = '';
    document.getElementById('wardForm').reset();
    document.getElementById('statusField').classList.add('hidden');
    document.getElementById('wardModal').classList.remove('hidden');
}

function openEditModal(ward) {
    document.getElementById('modalTitle').textContent = 'Edit Ward';
    document.getElementById('formAction').value = 'update_ward';
    document.getElementById('wardId').value = ward.id;
    document.getElementById('ward_name').value = ward.ward_name;
    document.getElementById('ward_code').value = ward.ward_code;
    document.getElementById('description').value = ward.description || '';
    document.getElementById('is_active').checked = ward.is_active == 1;
    document.getElementById('statusField').classList.remove('hidden');
    document.getElementById('wardModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('wardModal').classList.add('hidden');
}
</script>

<?php include 'includes/footer.php'; ?>
