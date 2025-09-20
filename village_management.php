<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireRole('super_admin');

$page_title = 'Street/Village Management';

$message = '';
$error = '';

$selected_ward_id = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;

// Handle village operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_village') {
        $village_name = sanitizeInput($_POST['village_name']);
        $village_code = sanitizeInput($_POST['village_code']);
        $ward_id = (int)$_POST['ward_id'];
        $description = sanitizeInput($_POST['description']);
        
        if (empty($village_name) || empty($village_code) || !$ward_id) {
            $error = 'Village name, code, and ward are required';
        } else {
            if (createVillage($village_name, $village_code, $ward_id, $description)) {
                $message = 'Village created successfully';
            } else {
                $error = 'Error creating village. Village code may already exist.';
            }
        }
    } elseif ($action === 'update_village') {
        $village_id = (int)$_POST['village_id'];
        $village_name = sanitizeInput($_POST['village_name']);
        $village_code = sanitizeInput($_POST['village_code']);
        $ward_id = (int)$_POST['ward_id'];
        $description = sanitizeInput($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($village_name) || empty($village_code) || !$ward_id) {
            $error = 'Village name, code, and ward are required';
        } else {
            if (updateVillage($village_id, $village_name, $village_code, $ward_id, $description, $is_active)) {
                $message = 'Village updated successfully';
            } else {
                $error = 'Error updating village';
            }
        }
    }
}

// Handle village deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $village_id = (int)$_GET['delete'];
    
    if (deleteVillage($village_id)) {
        $message = 'Village deleted successfully';
    } else {
        $error = 'Error deleting village';
    }
}

// Get all wards for dropdown
$wards = getAllWards();

// Get villages (filtered by ward if selected)
if ($selected_ward_id) {
    $villages = getVillagesByWard($selected_ward_id);
    // Add ward info to villages
    foreach ($villages as &$village) {
        $ward = getWardById($village['ward_id']);
        $village['ward_name'] = $ward ? $ward['ward_name'] : 'Unknown';
    }
} else {
    $villages = getAllVillages();
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">Street/Village Management</h1>
        <button onclick="openCreateModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
            <i class="fas fa-plus mr-2"></i>Add New Street/Village
        </button>
    </div>
    
    <!-- Ward Filter -->
    <div class="bg-white rounded-lg shadow-md p-4">
        <div class="flex items-center space-x-4">
            <label for="ward_filter" class="text-sm font-medium text-gray-700">Filter by Ward:</label>
            <select id="ward_filter" onchange="filterByWard()" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Wards</option>
                <?php foreach ($wards as $ward): ?>
                    <option value="<?php echo $ward['id']; ?>" <?php echo $selected_ward_id == $ward['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ward['ward_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($selected_ward_id): ?>
                <a href="village_management.php" class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-times mr-1"></i>Clear Filter
                </a>
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
    
    <!-- Villages Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Street/Village Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Street/Village Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ward</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($villages as $village): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-home text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($village['village_name']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($village['village_code']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($village['ward_name'] ?? 'Unknown'); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <div class="max-w-xs truncate">
                                <?php echo htmlspecialchars($village['description'] ?: 'No description'); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $village['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $village['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($village)); ?>)" 
                                        class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?php echo $village['id']; ?>" 
                                   class="text-red-600 hover:text-red-900"
                                   onclick="return confirmDelete('Are you sure you want to delete this village?')"
                                   title="Delete Village">
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

<!-- Create/Edit Village Modal -->
<div id="villageModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Add New Street/Village</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" id="villageForm">
                <input type="hidden" name="action" id="formAction" value="create_village">
                <input type="hidden" name="village_id" id="villageId" value="">
                
                <div class="space-y-4">
                    <div>
                        <label for="village_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Street/Village Name *
                        </label>
                        <input type="text" id="village_name" name="village_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="village_code" class="block text-sm font-medium text-gray-700 mb-2">
                        Street/Village Code *
                        </label>
                        <input type="text" id="village_code" name="village_code" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="ward_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Ward *
                        </label>
                        <select id="ward_id" name="ward_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select Ward</option>
                            <?php foreach ($wards as $ward): ?>
                                <option value="<?php echo $ward['id']; ?>"><?php echo htmlspecialchars($ward['ward_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
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
                                Active Village
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
                        Save Street/Village
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filterByWard() {
    const wardId = document.getElementById('ward_filter').value;
    if (wardId) {
        window.location.href = 'village_management.php?ward_id=' + wardId;
    } else {
        window.location.href = 'village_management.php';
    }
}

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add New Street/Village';
    document.getElementById('formAction').value = 'create_village';
    document.getElementById('villageId').value = '';
    document.getElementById('villageForm').reset();
    document.getElementById('statusField').classList.add('hidden');
    document.getElementById('villageModal').classList.remove('hidden');
}

function openEditModal(village) {
    document.getElementById('modalTitle').textContent = 'Edit Street/Village';
    document.getElementById('formAction').value = 'update_village';
    document.getElementById('villageId').value = village.id;
    document.getElementById('village_name').value = village.village_name;
    document.getElementById('village_code').value = village.village_code;
    document.getElementById('ward_id').value = village.ward_id;
    document.getElementById('description').value = village.description || '';
    document.getElementById('is_active').checked = village.is_active == 1;
    document.getElementById('statusField').classList.remove('hidden');
    document.getElementById('villageModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('villageModal').classList.add('hidden');
}
</script>

<?php include 'includes/footer.php'; ?>
