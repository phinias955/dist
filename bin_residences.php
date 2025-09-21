<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user has permission to manage bin
if (!isSuperAdmin() && $_SESSION['user_role'] !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

$page_title = 'Deleted Residences - Bin Management';

$message = '';
$error = '';

// Handle restore operation
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    
    try {
        // Get residence data from bin
        $stmt = $pdo->prepare("SELECT * FROM bin_residences WHERE id = ?");
        $stmt->execute([$id]);
        $residence_data = $stmt->fetch();
        
        if ($residence_data) {
            // Check if house number already exists in the same ward/village
            $stmt = $pdo->prepare("SELECT id FROM residences WHERE house_no = ? AND ward_id = ? AND village_id = ?");
            $stmt->execute([$residence_data['house_no'], $residence_data['ward_id'], $residence_data['village_id']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $error = 'Cannot restore residence: House number already exists in the same ward/village.';
            } else {
                // Insert back to residences table
                $stmt = $pdo->prepare("
                    INSERT INTO residences (house_no, resident_name, gender, date_of_birth, nida_number, phone, email, 
                                          occupation, ownership, family_members, education_level, employment_status, 
                                          ward_id, village_id, registered_by, registered_at, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    $residence_data['house_no'],
                    $residence_data['resident_name'],
                    $residence_data['gender'],
                    $residence_data['date_of_birth'],
                    $residence_data['nida_number'],
                    $residence_data['phone'],
                    $residence_data['email'],
                    $residence_data['occupation'],
                    $residence_data['ownership'],
                    $residence_data['family_members'],
                    $residence_data['education_level'],
                    $residence_data['employment_status'],
                    $residence_data['ward_id'],
                    $residence_data['village_id'],
                    $residence_data['registered_by'],
                    $residence_data['original_registered_at']
                ]);
                
                $new_residence_id = $pdo->lastInsertId();
                
                // Restore family members
                $stmt = $pdo->prepare("
                    SELECT * FROM bin_family_members 
                    WHERE original_residence_id = ? AND deletion_reason = 'residence_deleted'
                ");
                $stmt->execute([$residence_data['original_residence_id']]);
                $family_members = $stmt->fetchAll();
                
                foreach ($family_members as $member) {
                    $stmt = $pdo->prepare("
                        INSERT INTO family_members (residence_id, name, gender, date_of_birth, nida_number, 
                                                   relationship, is_minor, phone, email, occupation, 
                                                   education_level, employment_status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $new_residence_id,
                        $member['name'],
                        $member['gender'],
                        $member['date_of_birth'],
                        $member['nida_number'],
                        $member['relationship'],
                        $member['is_minor'],
                        $member['phone'],
                        $member['email'],
                        $member['occupation'],
                        $member['education_level'],
                        $member['employment_status'],
                        $member['original_created_at']
                    ]);
                }
                
                // Remove from bin
                $stmt = $pdo->prepare("DELETE FROM bin_residences WHERE id = ?");
                $stmt->execute([$id]);
                
                // Remove family members from bin
                $stmt = $pdo->prepare("
                    DELETE FROM bin_family_members 
                    WHERE original_residence_id = ? AND deletion_reason = 'residence_deleted'
                ");
                $stmt->execute([$residence_data['original_residence_id']]);
                
                // Log operation
                $stmt = $pdo->prepare("
                    INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                    VALUES ('restore', 'residence', ?, ?, ?, 'Residence restored from bin')
                ");
                $stmt->execute([$residence_data['original_residence_id'], $id, $_SESSION['user_id']]);
                
                $message = 'Residence "' . $residence_data['house_no'] . '" and its family members have been restored successfully.';
            }
        } else {
            $error = 'Residence not found in bin.';
        }
    } catch (PDOException $e) {
        $error = 'Error restoring residence: ' . $e->getMessage();
    }
}

// Handle permanent deletion
if (isset($_GET['permanent_delete']) && is_numeric($_GET['permanent_delete'])) {
    $id = (int)$_GET['permanent_delete'];
    
    try {
        // Get residence data before deletion
        $stmt = $pdo->prepare("SELECT house_no, original_residence_id FROM bin_residences WHERE id = ?");
        $stmt->execute([$id]);
        $residence_data = $stmt->fetch();
        
        if ($residence_data) {
            // Log operation before deletion
            $stmt = $pdo->prepare("
                INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                VALUES ('permanent_delete', 'residence', ?, ?, ?, 'Residence permanently deleted from bin')
            ");
            $stmt->execute([$residence_data['original_residence_id'], $id, $_SESSION['user_id']]);
            
            // Permanently delete family members first
            $stmt = $pdo->prepare("
                DELETE FROM bin_family_members 
                WHERE original_residence_id = ? AND deletion_reason = 'residence_deleted'
            ");
            $stmt->execute([$residence_data['original_residence_id']]);
            
            // Permanently delete residence
            $stmt = $pdo->prepare("DELETE FROM bin_residences WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = 'Residence "' . $residence_data['house_no'] . '" has been permanently deleted.';
        } else {
            $error = 'Residence not found in bin.';
        }
    } catch (PDOException $e) {
        $error = 'Error permanently deleting residence: ' . $e->getMessage();
    }
}

// Get deleted residences
try {
    if (isSuperAdmin()) {
        // Super admin can see all deleted residences
        $stmt = $pdo->query("
            SELECT * FROM bin_residences 
            ORDER BY deleted_at DESC
        ");
    } else {
        // Ward Admin can only see deleted residences from their ward
        $user_location = getUserLocationInfo();
        $stmt = $pdo->prepare("
            SELECT * FROM bin_residences 
            WHERE ward_id = ?
            ORDER BY deleted_at DESC
        ");
        $stmt->execute([$user_location['ward_id']]);
    }
    $deleted_residences = $stmt->fetchAll();
} catch (PDOException $e) {
    $deleted_residences = [];
    $error = 'Error loading deleted residences: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-building mr-2"></i>Deleted Residences
                </h1>
                <p class="text-gray-600">Manage deleted residences in the bin</p>
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
                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-building text-orange-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Deleted</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo count($deleted_residences); ?></p>
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
                        <p class="text-sm font-medium text-gray-500">Owners</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo count(array_filter($deleted_residences, function($res) { return $res['ownership'] === 'owner'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-key text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Tenants</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo count(array_filter($deleted_residences, function($res) { return $res['ownership'] === 'tenant'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-users text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Family Members</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo array_sum(array_column($deleted_residences, 'family_members')); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Deleted Residences Table -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-building mr-2"></i>Deleted Residences
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    <?php if (isSuperAdmin()): ?>
                        All deleted residences in the system
                    <?php else: ?>
                        Deleted residences from your ward only
                    <?php endif; ?>
                </p>
            </div>
            
            <?php if (empty($deleted_residences)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-building text-4xl mb-4"></i>
                    <p class="text-lg">No deleted residences found</p>
                    <p class="text-sm">All residences are currently active</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">House Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resident</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ownership</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Family Members</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deleted Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($deleted_residences as $residence): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center mr-4">
                                            <i class="fas fa-building text-orange-600"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($residence['house_no']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($residence['resident_name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($residence['resident_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo ucfirst($residence['gender']); ?> • 
                                        <?php echo date('M d, Y', strtotime($residence['date_of_birth'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-map-marker-alt text-orange-500 mr-2"></i>
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($residence['ward_name'] ?? 'Unknown'); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($residence['village_name'] ?? 'Unknown'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $residence['ownership'] === 'owner' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                        <?php echo ucfirst($residence['ownership']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-users mr-2 text-gray-400"></i>
                                        <?php echo $residence['family_members']; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-clock mr-2 text-gray-400"></i>
                                        <?php echo formatDate($residence['deleted_at']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="?restore=<?php echo $residence['id']; ?>" 
                                           class="text-green-600 hover:text-green-900"
                                           onclick="return confirm('Are you sure you want to restore this residence and its family members?')"
                                           title="Restore Residence">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                        <a href="?permanent_delete=<?php echo $residence['id']; ?>" 
                                           class="text-red-600 hover:text-red-900"
                                           onclick="return confirm('Are you sure you want to permanently delete this residence? This action cannot be undone.')"
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
