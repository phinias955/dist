<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user has permission to manage bin
if (!isSuperAdmin() && $_SESSION['user_role'] !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

$page_title = 'Deleted Locations - Bin Management';

$message = '';
$error = '';

// Handle restore operation
if (isset($_GET['restore']) && isset($_GET['type']) && is_numeric($_GET['id'])) {
    $type = $_GET['type'];
    $id = (int)$_GET['id'];
    
    try {
        if ($type === 'ward') {
            // Restore ward
            $stmt = $pdo->prepare("SELECT * FROM bin_wards WHERE id = ?");
            $stmt->execute([$id]);
            $ward_data = $stmt->fetch();
            
            if ($ward_data) {
                // Check if ward code already exists
                $stmt = $pdo->prepare("SELECT id FROM wards WHERE ward_code = ?");
                $stmt->execute([$ward_data['ward_code']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $error = 'Cannot restore ward: Ward code already exists.';
                } else {
                    // Insert back to wards table
                    $stmt = $pdo->prepare("
                        INSERT INTO wards (ward_name, ward_code, description, is_active, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $ward_data['ward_name'],
                        $ward_data['ward_code'],
                        $ward_data['description'],
                        $ward_data['is_active'],
                        $ward_data['deleted_by'],
                        $ward_data['original_created_at']
                    ]);
                    
                    // Remove from bin
                    $stmt = $pdo->prepare("DELETE FROM bin_wards WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Log operation
                    $stmt = $pdo->prepare("
                        INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                        VALUES ('restore', 'ward', ?, ?, ?, 'Ward restored from bin')
                    ");
                    $stmt->execute([$ward_data['original_ward_id'], $id, $_SESSION['user_id']]);
                    
                    $message = 'Ward "' . $ward_data['ward_name'] . '" has been restored successfully.';
                }
            }
        } elseif ($type === 'village') {
            // Restore village
            $stmt = $pdo->prepare("SELECT * FROM bin_villages WHERE id = ?");
            $stmt->execute([$id]);
            $village_data = $stmt->fetch();
            
            if ($village_data) {
                // Check if village code already exists
                $stmt = $pdo->prepare("SELECT id FROM villages WHERE village_code = ?");
                $stmt->execute([$village_data['village_code']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $error = 'Cannot restore village: Village code already exists.';
                } else {
                    // Find the ward by name
                    $stmt = $pdo->prepare("SELECT id FROM wards WHERE ward_name = ?");
                    $stmt->execute([$village_data['ward_name']]);
                    $ward = $stmt->fetch();
                    
                    if ($ward) {
                        // Insert back to villages table
                        $stmt = $pdo->prepare("
                            INSERT INTO villages (village_name, village_code, ward_id, description, is_active, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $village_data['village_name'],
                            $village_data['village_code'],
                            $ward['id'],
                            $village_data['description'],
                            $village_data['is_active'],
                            $village_data['deleted_by'],
                            $village_data['original_created_at']
                        ]);
                        
                        // Remove from bin
                        $stmt = $pdo->prepare("DELETE FROM bin_villages WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        // Log operation
                        $stmt = $pdo->prepare("
                            INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                            VALUES ('restore', 'village', ?, ?, ?, 'Village restored from bin')
                        ");
                        $stmt->execute([$village_data['original_village_id'], $id, $_SESSION['user_id']]);
                        
                        $message = 'Village "' . $village_data['village_name'] . '" has been restored successfully.';
                    } else {
                        $error = 'Cannot restore village: Parent ward not found. Please restore the ward first.';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Error restoring location: ' . $e->getMessage();
    }
}

// Handle permanent deletion
if (isset($_GET['permanent_delete']) && isset($_GET['type']) && is_numeric($_GET['id'])) {
    $type = $_GET['type'];
    $id = (int)$_GET['id'];
    
    try {
        if ($type === 'ward') {
            $stmt = $pdo->prepare("SELECT ward_name, original_ward_id FROM bin_wards WHERE id = ?");
            $stmt->execute([$id]);
            $ward_data = $stmt->fetch();
            
            if ($ward_data) {
                // Log operation before deletion
                $stmt = $pdo->prepare("
                    INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                    VALUES ('permanent_delete', 'ward', ?, ?, ?, 'Ward permanently deleted from bin')
                ");
                $stmt->execute([$ward_data['original_ward_id'], $id, $_SESSION['user_id']]);
                
                // Permanently delete
                $stmt = $pdo->prepare("DELETE FROM bin_wards WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = 'Ward "' . $ward_data['ward_name'] . '" has been permanently deleted.';
            }
        } elseif ($type === 'village') {
            $stmt = $pdo->prepare("SELECT village_name, original_village_id FROM bin_villages WHERE id = ?");
            $stmt->execute([$id]);
            $village_data = $stmt->fetch();
            
            if ($village_data) {
                // Log operation before deletion
                $stmt = $pdo->prepare("
                    INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                    VALUES ('permanent_delete', 'village', ?, ?, ?, 'Village permanently deleted from bin')
                ");
                $stmt->execute([$village_data['original_village_id'], $id, $_SESSION['user_id']]);
                
                // Permanently delete
                $stmt = $pdo->prepare("DELETE FROM bin_villages WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = 'Village "' . $village_data['village_name'] . '" has been permanently deleted.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Error permanently deleting location: ' . $e->getMessage();
    }
}

// Get deleted locations
try {
    // Get deleted wards
    $stmt = $pdo->query("SELECT *, 'ward' as location_type FROM bin_wards ORDER BY deleted_at DESC");
    $deleted_wards = $stmt->fetchAll();
    
    // Get deleted villages
    $stmt = $pdo->query("SELECT *, 'village' as location_type FROM bin_villages ORDER BY deleted_at DESC");
    $deleted_villages = $stmt->fetchAll();
    
    // Combine all locations
    $deleted_locations = array_merge($deleted_wards, $deleted_villages);
    
    // Sort by deletion date
    usort($deleted_locations, function($a, $b) {
        return strtotime($b['deleted_at']) - strtotime($a['deleted_at']);
    });
    
} catch (PDOException $e) {
    $deleted_locations = [];
    $error = 'Error loading deleted locations: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-map-marker-alt mr-2"></i>Deleted Locations
                </h1>
                <p class="text-gray-600">Manage deleted wards and villages in the bin</p>
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-map-marker-alt text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Deleted Wards</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo count(array_filter($deleted_locations, function($loc) { return $loc['location_type'] === 'ward'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-home text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Deleted Villages</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo count(array_filter($deleted_locations, function($loc) { return $loc['location_type'] === 'village'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Deleted Locations Table -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-map-marker-alt mr-2"></i>Deleted Locations
                </h3>
                <p class="text-sm text-gray-600 mt-1">All deleted wards and villages in the system</p>
            </div>
            
            <?php if (empty($deleted_locations)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-map-marker-alt text-4xl mb-4"></i>
                    <p class="text-lg">No deleted locations found</p>
                    <p class="text-sm">All locations are currently active</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent/Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deleted Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($deleted_locations as $location): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $location['location_type'] === 'ward' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                        <i class="fas <?php echo $location['location_type'] === 'ward' ? 'fa-map-marker-alt' : 'fa-home'; ?> mr-1"></i>
                                        <?php echo ucfirst($location['location_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 <?php echo $location['location_type'] === 'ward' ? 'bg-blue-100' : 'bg-green-100'; ?> rounded-full flex items-center justify-center mr-4">
                                            <i class="fas <?php echo $location['location_type'] === 'ward' ? 'fa-map-marker-alt text-blue-600' : 'fa-home text-green-600'; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($location['location_type'] === 'ward' ? $location['ward_name'] : $location['village_name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                    <?php echo htmlspecialchars($location['location_type'] === 'ward' ? $location['ward_code'] : $location['village_code']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($location['location_type'] === 'village'): ?>
                                        <div class="flex items-center">
                                            <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>
                                            <?php echo htmlspecialchars($location['ward_name'] ?? 'Unknown Ward'); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($location['description'] ?? 'No description'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $location['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $location['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-clock mr-2 text-gray-400"></i>
                                        <?php echo formatDate($location['deleted_at']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="?restore=<?php echo $location['id']; ?>&type=<?php echo $location['location_type']; ?>" 
                                           class="text-green-600 hover:text-green-900"
                                           onclick="return confirm('Are you sure you want to restore this location?')"
                                           title="Restore Location">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                        <a href="?permanent_delete=<?php echo $location['id']; ?>&type=<?php echo $location['location_type']; ?>" 
                                           class="text-red-600 hover:text-red-900"
                                           onclick="return confirm('Are you sure you want to permanently delete this location? This action cannot be undone.')"
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
