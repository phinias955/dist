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
    if (isSuperAdmin()) {
        // Super admin can see all residences
        $stmt = $pdo->query("
            SELECT r.*, w.ward_name, w.ward_code, v.village_name, v.village_code,
                   u.full_name as registered_by_name,
                   rt.status as transfer_status, rt.id as transfer_id,
                   tw.ward_name as transfer_to_ward, tv.village_name as transfer_to_village
            FROM residences r
            LEFT JOIN wards w ON r.ward_id = w.id
            LEFT JOIN villages v ON r.village_id = v.id
            LEFT JOIN users u ON r.registered_by = u.id
            LEFT JOIN residence_transfers rt ON r.id = rt.residence_id 
                AND rt.status IN ('pending_approval', 'weo_approved', 'ward_approved', 'veo_accepted')
            LEFT JOIN wards tw ON rt.to_ward_id = tw.id
            LEFT JOIN villages tv ON rt.to_village_id = tv.id
            ORDER BY r.registered_at DESC
        ");
    } else {
        // Location-based access control for other roles
        $user_location = getUserLocationInfo();
        
        if ($_SESSION['user_role'] === 'veo') {
            // VEO can only see residences from their assigned village
            if (!empty($user_location['village_id'])) {
                $stmt = $pdo->prepare("
                    SELECT r.*, w.ward_name, w.ward_code, v.village_name, v.village_code,
                           u.full_name as registered_by_name,
                           rt.status as transfer_status, rt.id as transfer_id,
                           tw.ward_name as transfer_to_ward, tv.village_name as transfer_to_village
                    FROM residences r
                    LEFT JOIN wards w ON r.ward_id = w.id
                    LEFT JOIN villages v ON r.village_id = v.id
                    LEFT JOIN users u ON r.registered_by = u.id
                    LEFT JOIN residence_transfers rt ON r.id = rt.residence_id 
                        AND rt.status IN ('pending_approval', 'weo_approved', 'ward_approved', 'veo_accepted')
                    LEFT JOIN wards tw ON rt.to_ward_id = tw.id
                    LEFT JOIN villages tv ON rt.to_village_id = tv.id
                    WHERE r.village_id = ?
                    ORDER BY r.registered_at DESC
                ");
                $stmt->execute([$user_location['village_id']]);
            } else {
                $residences = []; // No village assigned
            }
        } elseif ($_SESSION['user_role'] === 'weo' || $_SESSION['user_role'] === 'admin') {
            // WEO and Admin can see residences from their assigned ward
            if (!empty($user_location['ward_id'])) {
                $stmt = $pdo->prepare("
                    SELECT r.*, w.ward_name, w.ward_code, v.village_name, v.village_code,
                           u.full_name as registered_by_name,
                           rt.status as transfer_status, rt.id as transfer_id,
                           tw.ward_name as transfer_to_ward, tv.village_name as transfer_to_village
                    FROM residences r
                    LEFT JOIN wards w ON r.ward_id = w.id
                    LEFT JOIN villages v ON r.village_id = v.id
                    LEFT JOIN users u ON r.registered_by = u.id
                    LEFT JOIN residence_transfers rt ON r.id = rt.residence_id 
                        AND rt.status IN ('pending_approval', 'weo_approved', 'ward_approved', 'veo_accepted')
                    LEFT JOIN wards tw ON rt.to_ward_id = tw.id
                    LEFT JOIN villages tv ON rt.to_village_id = tv.id
                    WHERE r.ward_id = ?
                    ORDER BY r.registered_at DESC
                ");
                $stmt->execute([$user_location['ward_id']]);
            } else {
                $residences = []; // No ward assigned
            }
        } else {
            $residences = []; // Unknown role
        }
    }
    
    // Only fetch if $stmt is defined
    if (isset($stmt)) {
        $residences = $stmt->fetchAll();
    } else {
        $residences = [];
    }
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">House No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resident Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIDA Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ward</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Street/Village</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transfer</th>
        <?php if (canViewAllData()): ?>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">By</th>
        <?php endif; ?>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($residences as $residence): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($residence['house_no']); ?>
                        </td>
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
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $residence['gender'] === 'male' ? 'bg-blue-100 text-blue-800' : ($residence['gender'] === 'female' ? 'bg-pink-100 text-pink-800' : 'bg-gray-100 text-gray-800'); ?>">
                                <?php echo ucfirst($residence['gender']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($residence['nida_number']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($residence['phone'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($residence['ward_name'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($residence['village_name'] ?? 'N/A'); ?>
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
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($residence['transfer_status']): ?>
                                <div class="flex items-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php
                                        switch($residence['transfer_status']) {
                                            case 'pending_approval': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'weo_approved': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'ward_approved': echo 'bg-purple-100 text-purple-800'; break;
                                            case 'veo_accepted': echo 'bg-green-100 text-green-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $residence['transfer_status'])); ?>
                                    </span>
                                    <div class="ml-2 text-xs text-gray-500">
                                        to <?php echo htmlspecialchars($residence['transfer_to_ward'] . ' - ' . $residence['transfer_to_village']); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-400 text-sm">No transfer</span>
                            <?php endif; ?>
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
                                   class="text-blue-600 hover:text-blue-900" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_residence.php?id=<?php echo $residence['id']; ?>" 
                                   class="text-green-600 hover:text-green-900" title="Edit Residence">
                                    <i class="fas fa-edit"></i>
                                </a>
                <a href="family_members.php?id=<?php echo $residence['id']; ?>"
                   class="text-purple-600 hover:text-purple-900" title="Manage Family Members">
                    <i class="fas fa-users"></i>
                </a>
                <a href="transfer_residence.php?id=<?php echo $residence['id']; ?>"
                   class="text-orange-600 hover:text-orange-900" title="Transfer Residence">
                    <i class="fas fa-exchange-alt"></i>
                </a>
                                <?php if ($residence['status'] === 'active'): ?>
                                    <a href="?delete=<?php echo $residence['id']; ?>" 
                                       class="text-red-600 hover:text-red-900" title="Deactivate Residence"
                                       onclick="return confirmDelete('Are you sure you want to deactivate this residence?')">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="?activate=<?php echo $residence['id']; ?>" 
                                       class="text-green-600 hover:text-green-900" title="Activate Residence">
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
