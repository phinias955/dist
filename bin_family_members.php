<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user has permission to manage bin
if (!isSuperAdmin() && $_SESSION['user_role'] !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

$page_title = 'Deleted Family Members - Bin Management';

$message = '';
$error = '';

// Handle restore operation
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    
    try {
        // Get family member data from bin
        $stmt = $pdo->prepare("SELECT * FROM bin_family_members WHERE id = ?");
        $stmt->execute([$id]);
        $member_data = $stmt->fetch();
        
        if ($member_data) {
            // Find the residence by house number and location
            $stmt = $pdo->prepare("
                SELECT r.id FROM residences r
                JOIN wards w ON r.ward_id = w.id
                JOIN villages v ON r.village_id = v.id
                WHERE r.house_no = ? AND w.ward_name = ? AND v.village_name = ?
            ");
            $stmt->execute([$member_data['residence_house_no'], $member_data['ward_name'], $member_data['village_name']]);
            $residence = $stmt->fetch();
            
            if ($residence) {
                // Check if NIDA already exists in active family members
                if (!empty($member_data['nida_number'])) {
                    $stmt = $pdo->prepare("SELECT id FROM family_members WHERE nida_number = ?");
                    $stmt->execute([$member_data['nida_number']]);
                    $existing_nida = $stmt->fetch();
                    
                    if ($existing_nida) {
                        $error = 'Cannot restore family member: NIDA number already exists in active family members.';
                    } else {
                        // Insert back to family_members table
                        $stmt = $pdo->prepare("
                            INSERT INTO family_members (residence_id, name, gender, date_of_birth, nida_number, 
                                                       relationship, is_minor, phone, email, occupation, 
                                                       education_level, employment_status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $residence['id'],
                            $member_data['name'],
                            $member_data['gender'],
                            $member_data['date_of_birth'],
                            $member_data['nida_number'],
                            $member_data['relationship'],
                            $member_data['is_minor'],
                            $member_data['phone'],
                            $member_data['email'],
                            $member_data['occupation'],
                            $member_data['education_level'],
                            $member_data['employment_status'],
                            $member_data['original_created_at']
                        ]);
                        
                        // Remove from bin
                        $stmt = $pdo->prepare("DELETE FROM bin_family_members WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        // Log operation
                        $stmt = $pdo->prepare("
                            INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                            VALUES ('restore', 'family_member', ?, ?, ?, 'Family member restored from bin')
                        ");
                        $stmt->execute([$member_data['original_family_member_id'], $id, $_SESSION['user_id']]);
                        
                        $message = 'Family member "' . $member_data['name'] . '" has been restored successfully.';
                    }
                } else {
                    // Insert back to family_members table (no NIDA check needed)
                    $stmt = $pdo->prepare("
                        INSERT INTO family_members (residence_id, name, gender, date_of_birth, nida_number, 
                                                   relationship, is_minor, phone, email, occupation, 
                                                   education_level, employment_status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $residence['id'],
                        $member_data['name'],
                        $member_data['gender'],
                        $member_data['date_of_birth'],
                        $member_data['nida_number'],
                        $member_data['relationship'],
                        $member_data['is_minor'],
                        $member_data['phone'],
                        $member_data['email'],
                        $member_data['occupation'],
                        $member_data['education_level'],
                        $member_data['employment_status'],
                        $member_data['original_created_at']
                    ]);
                    
                    // Remove from bin
                    $stmt = $pdo->prepare("DELETE FROM bin_family_members WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Log operation
                    $stmt = $pdo->prepare("
                        INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                        VALUES ('restore', 'family_member', ?, ?, ?, 'Family member restored from bin')
                    ");
                    $stmt->execute([$member_data['original_family_member_id'], $id, $_SESSION['user_id']]);
                    
                    $message = 'Family member "' . $member_data['name'] . '" has been restored successfully.';
                }
            } else {
                $error = 'Cannot restore family member: Residence not found. Please restore the residence first.';
            }
        } else {
            $error = 'Family member not found in bin.';
        }
    } catch (PDOException $e) {
        $error = 'Error restoring family member: ' . $e->getMessage();
    }
}

// Handle permanent deletion
if (isset($_GET['permanent_delete']) && is_numeric($_GET['permanent_delete'])) {
    $id = (int)$_GET['permanent_delete'];
    
    try {
        // Get family member data before deletion
        $stmt = $pdo->prepare("SELECT name, original_family_member_id FROM bin_family_members WHERE id = ?");
        $stmt->execute([$id]);
        $member_data = $stmt->fetch();
        
        if ($member_data) {
            // Log operation before deletion
            $stmt = $pdo->prepare("
                INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                VALUES ('permanent_delete', 'family_member', ?, ?, ?, 'Family member permanently deleted from bin')
            ");
            $stmt->execute([$member_data['original_family_member_id'], $id, $_SESSION['user_id']]);
            
            // Permanently delete
            $stmt = $pdo->prepare("DELETE FROM bin_family_members WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = 'Family member "' . $member_data['name'] . '" has been permanently deleted.';
        } else {
            $error = 'Family member not found in bin.';
        }
    } catch (PDOException $e) {
        $error = 'Error permanently deleting family member: ' . $e->getMessage();
    }
}

// Get deleted family members
try {
    if (isSuperAdmin()) {
        // Super admin can see all deleted family members
        $stmt = $pdo->query("
            SELECT * FROM bin_family_members 
            ORDER BY deleted_at DESC
        ");
    } else {
        // Ward Admin can only see deleted family members from their ward
        $user_location = getUserLocationInfo();
        $stmt = $pdo->prepare("
            SELECT bfm.* FROM bin_family_members bfm
            JOIN bin_residences br ON bfm.original_residence_id = br.original_residence_id
            WHERE br.ward_id = ?
            ORDER BY bfm.deleted_at DESC
        ");
        $stmt->execute([$user_location['ward_id']]);
    }
    $deleted_family_members = $stmt->fetchAll();
} catch (PDOException $e) {
    $deleted_family_members = [];
    $error = 'Error loading deleted family members: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-users mr-2"></i>Deleted Family Members
                </h1>
                <p class="text-gray-600">Manage deleted family members in the bin</p>
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
                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-users text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Deleted</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo count($deleted_family_members); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-male text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Males</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo count(array_filter($deleted_family_members, function($member) { return $member['gender'] === 'male'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-pink-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-female text-pink-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Females</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo count(array_filter($deleted_family_members, function($member) { return $member['gender'] === 'female'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-child text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Minors</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo count(array_filter($deleted_family_members, function($member) { return $member['is_minor'] == 1; })); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Deleted Family Members Table -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-users mr-2"></i>Deleted Family Members
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    <?php if (isSuperAdmin()): ?>
                        All deleted family members in the system
                    <?php else: ?>
                        Deleted family members from your ward only
                    <?php endif; ?>
                </p>
            </div>
            
            <?php if (empty($deleted_family_members)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-users text-4xl mb-4"></i>
                    <p class="text-lg">No deleted family members found</p>
                    <p class="text-sm">All family members are currently active</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Relationship</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Residence</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIDA Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deleted Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($deleted_family_members as $member): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
                                            <i class="fas fa-user text-yellow-600"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($member['name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo ucfirst($member['gender']); ?> • 
                                                <?php echo $member['date_of_birth'] ? date('M d, Y', strtotime($member['date_of_birth'])) : 'No DOB'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        <?php echo ucfirst($member['relationship']); ?>
                                    </span>
                                    <?php if ($member['is_minor']): ?>
                                    <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Minor
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-home mr-2 text-gray-400"></i>
                                        <?php echo htmlspecialchars($member['residence_house_no']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-map-marker-alt text-yellow-500 mr-2"></i>
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($member['ward_name'] ?? 'Unknown'); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($member['village_name'] ?? 'Unknown'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                    <?php echo $member['nida_number'] ? htmlspecialchars($member['nida_number']) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-clock mr-2 text-gray-400"></i>
                                        <?php echo formatDate($member['deleted_at']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="?restore=<?php echo $member['id']; ?>" 
                                           class="text-green-600 hover:text-green-900"
                                           onclick="return confirm('Are you sure you want to restore this family member?')"
                                           title="Restore Family Member">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                        <a href="?permanent_delete=<?php echo $member['id']; ?>" 
                                           class="text-red-600 hover:text-red-900"
                                           onclick="return confirm('Are you sure you want to permanently delete this family member? This action cannot be undone.')"
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
