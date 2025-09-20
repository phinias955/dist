<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Residences';

$message = '';
$error = '';

// Handle residence deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $residence_id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("UPDATE residences SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$residence_id]);
        $message = 'Residence deactivated successfully';
    } catch (PDOException $e) {
        $error = 'Error deactivating residence';
    }
}

// Handle residence activation
if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
    $residence_id = (int)$_GET['activate'];
    
    try {
        $stmt = $pdo->prepare("UPDATE residences SET status = 'active' WHERE id = ?");
        $stmt->execute([$residence_id]);
        $message = 'Residence activated successfully';
    } catch (PDOException $e) {
        $error = 'Error activating residence';
    }
}

// Get residences based on user location permissions
try {
    $residences = getAccessibleResidences();
} catch (PDOException $e) {
    $error = 'Database error occurred';
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">Residences</h1>
        <a href="add_residence.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
            <i class="fas fa-plus mr-2"></i>Add New Residence
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
    
    <!-- Residences Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resident Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIDA Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ward</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Street/Village</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                        <?php if (canViewAllData()): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">By</th>
                        <?php endif; ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($residences as $residence): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-home text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($residence['resident_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($residence['occupation'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($residence['nida_number']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($residence['ward_name'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($residence['village_name'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($residence['phone'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php 
                                switch($residence['status']) {
                                    case 'active': echo 'bg-green-100 text-green-800'; break;
                                    case 'inactive': echo 'bg-red-100 text-red-800'; break;
                                    case 'moved': echo 'bg-yellow-100 text-yellow-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?php echo ucfirst($residence['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo formatDate($residence['registered_at']); ?>
                        </td>
                        <?php if (canViewAllData()): ?>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($residence['registered_by_name'] ?? 'Unknown'); ?>
                        </td>
                        <?php endif; ?>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="view_residence.php?id=<?php echo $residence['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_residence.php?id=<?php echo $residence['id']; ?>" 
                                   class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($residence['status'] === 'active'): ?>
                                    <a href="?delete=<?php echo $residence['id']; ?>" 
                                       class="text-red-600 hover:text-red-900"
                                       onclick="return confirmDelete('Are you sure you want to deactivate this residence?')">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="?activate=<?php echo $residence['id']; ?>" 
                                       class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-check"></i>
                                    </a>
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
