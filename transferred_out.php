<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Transferred Out Residences';

// Check if user has permission to view this page
if (!in_array($_SESSION['user_role'], ['veo', 'weo', 'admin', 'super_admin'])) {
    header('Location: dashboard.php');
    exit();
}

$user_location = getUserLocationInfo();
$transferred_residences = [];

try {
    if (isSuperAdmin()) {
        // Super Admin can see all transferred residences
        $stmt = $pdo->prepare("
            SELECT rt.*, 
                   r.house_no, r.resident_name, r.nida_number, r.phone,
                   fw.ward_name as from_ward_name, fw.ward_code as from_ward_code,
                   fv.village_name as from_village_name, fv.village_code as from_village_code,
                   tw.ward_name as to_ward_name, tw.ward_code as to_ward_code,
                   tv.village_name as to_village_name, tv.village_code as to_village_code,
                   u.full_name as requested_by_name, u.role as requested_by_role,
                   weo.full_name as weo_approved_by_name,
                   ward_admin.full_name as ward_approved_by_name,
                   veo.full_name as veo_accepted_by_name
            FROM residence_transfers rt
            LEFT JOIN residences r ON rt.residence_id = r.id
            LEFT JOIN wards fw ON rt.from_ward_id = fw.id
            LEFT JOIN villages fv ON rt.from_village_id = fv.id
            LEFT JOIN wards tw ON rt.to_ward_id = tw.id
            LEFT JOIN villages tv ON rt.to_village_id = tv.id
            LEFT JOIN users u ON rt.requested_by = u.id
            LEFT JOIN users weo ON rt.weo_approved_by = weo.id
            LEFT JOIN users ward_admin ON rt.ward_approved_by = ward_admin.id
            LEFT JOIN users veo ON rt.veo_accepted_by = veo.id
            WHERE rt.status = 'completed'
            ORDER BY rt.veo_accepted_at DESC
        ");
        $stmt->execute();
    } else {
        // Location-based filtering
        if ($_SESSION['user_role'] === 'veo') {
            // VEO can see residences transferred out of their village
            $stmt = $pdo->prepare("
                SELECT rt.*, 
                       r.house_no, r.resident_name, r.nida_number, r.phone,
                       fw.ward_name as from_ward_name, fw.ward_code as from_ward_code,
                       fv.village_name as from_village_name, fv.village_code as from_village_code,
                       tw.ward_name as to_ward_name, tw.ward_code as to_ward_code,
                       tv.village_name as to_village_name, tv.village_code as to_village_code,
                       u.full_name as requested_by_name, u.role as requested_by_role,
                       weo.full_name as weo_approved_by_name,
                       ward_admin.full_name as ward_approved_by_name,
                       veo.full_name as veo_accepted_by_name
                FROM residence_transfers rt
                LEFT JOIN residences r ON rt.residence_id = r.id
                LEFT JOIN wards fw ON rt.from_ward_id = fw.id
                LEFT JOIN villages fv ON rt.from_village_id = fv.id
                LEFT JOIN wards tw ON rt.to_ward_id = tw.id
                LEFT JOIN villages tv ON rt.to_village_id = tv.id
                LEFT JOIN users u ON rt.requested_by = u.id
                LEFT JOIN users weo ON rt.weo_approved_by = weo.id
                LEFT JOIN users ward_admin ON rt.ward_approved_by = ward_admin.id
                LEFT JOIN users veo ON rt.veo_accepted_by = veo.id
                WHERE rt.from_village_id = ? AND rt.status = 'completed'
                ORDER BY rt.veo_accepted_at DESC
            ");
            $stmt->execute([$user_location['village_id']]);
        } elseif ($_SESSION['user_role'] === 'weo' || $_SESSION['user_role'] === 'admin') {
            // WEO and Ward Admin can see residences transferred out of their ward
            $stmt = $pdo->prepare("
                SELECT rt.*, 
                       r.house_no, r.resident_name, r.nida_number, r.phone,
                       fw.ward_name as from_ward_name, fw.ward_code as from_ward_code,
                       fv.village_name as from_village_name, fv.village_code as from_village_code,
                       tw.ward_name as to_ward_name, tw.ward_code as to_ward_code,
                       tv.village_name as to_village_name, tv.village_code as to_village_code,
                       u.full_name as requested_by_name, u.role as requested_by_role,
                       weo.full_name as weo_approved_by_name,
                       ward_admin.full_name as ward_approved_by_name,
                       veo.full_name as veo_accepted_by_name
                FROM residence_transfers rt
                LEFT JOIN residences r ON rt.residence_id = r.id
                LEFT JOIN wards fw ON rt.from_ward_id = fw.id
                LEFT JOIN villages fv ON rt.from_village_id = fv.id
                LEFT JOIN wards tw ON rt.to_ward_id = tw.id
                LEFT JOIN villages tv ON rt.to_village_id = tv.id
                LEFT JOIN users u ON rt.requested_by = u.id
                LEFT JOIN users weo ON rt.weo_approved_by = weo.id
                LEFT JOIN users ward_admin ON rt.ward_approved_by = ward_admin.id
                LEFT JOIN users veo ON rt.veo_accepted_by = veo.id
                WHERE rt.from_ward_id = ? AND rt.status = 'completed'
                ORDER BY rt.veo_accepted_at DESC
            ");
            $stmt->execute([$user_location['ward_id']]);
        }
    }
    
    $transferred_residences = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Database error occurred';
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Transferred Out Residences</h1>
            <p class="text-gray-600">
                <?php 
                if (isSuperAdmin()) {
                    echo 'All residences that have been transferred out of their original locations';
                } elseif ($_SESSION['user_role'] === 'veo') {
                    echo 'Residences transferred out of ' . htmlspecialchars($user_location['village_name']);
                } else {
                    echo 'Residences transferred out of ' . htmlspecialchars($user_location['ward_name']);
                }
                ?>
            </p>
        </div>
        <div class="flex items-center space-x-4">
            <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full">
                <?php echo count($transferred_residences); ?> Transferred
            </span>
        </div>
    </div>
    
    <!-- Messages -->
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded alert">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($transferred_residences)): ?>
    <div class="bg-white p-8 rounded-lg shadow-md text-center">
        <div class="text-gray-400 text-6xl mb-4">
            <i class="fas fa-exchange-alt"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No Transferred Residences</h3>
        <p class="text-gray-500">
            <?php 
            if (isSuperAdmin()) {
                echo 'No residences have been transferred out yet.';
            } elseif ($_SESSION['user_role'] === 'veo') {
                echo 'No residences have been transferred out of your village yet.';
            } else {
                echo 'No residences have been transferred out of your ward yet.';
            }
            ?>
        </p>
    </div>
    <?php else: ?>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-600">
                    <i class="fas fa-arrow-right text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Transferred</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo count($transferred_residences); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-calendar text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">This Month</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?php 
                        $this_month = array_filter($transferred_residences, function($residence) {
                            return date('Y-m', strtotime($residence['veo_accepted_at'])) === date('Y-m');
                        });
                        echo count($this_month);
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-map-marker-alt text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Unique Destinations</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?php 
                        $destinations = array_unique(array_map(function($residence) {
                            return $residence['to_ward_name'] . ' - ' . $residence['to_village_name'];
                        }, $transferred_residences));
                        echo count($destinations);
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transferred Residences Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Transfer History</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Residence</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transfer Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transferred By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($transferred_residences as $residence): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-home text-blue-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($residence['house_no']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($residence['resident_name']); ?>
                                    </div>
                                    <?php if (!empty($residence['nida_number'])): ?>
                                    <div class="text-xs text-gray-400">
                                        NIDA: <?php echo htmlspecialchars($residence['nida_number']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo htmlspecialchars($residence['from_ward_name']); ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($residence['from_village_name']); ?>
                            </div>
                            <?php if (!empty($residence['from_ward_code'])): ?>
                            <div class="text-xs text-gray-400">
                                <?php echo htmlspecialchars($residence['from_ward_code']); ?> - <?php echo htmlspecialchars($residence['from_village_code']); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo htmlspecialchars($residence['to_ward_name']); ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($residence['to_village_name']); ?>
                            </div>
                            <?php if (!empty($residence['to_ward_code'])): ?>
                            <div class="text-xs text-gray-400">
                                <?php echo htmlspecialchars($residence['to_ward_code']); ?> - <?php echo htmlspecialchars($residence['to_village_code']); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                <?php 
                                switch($residence['transfer_type']) {
                                    case 'veo': echo 'bg-blue-100 text-blue-800'; break;
                                    case 'ward_admin': echo 'bg-green-100 text-green-800'; break;
                                    case 'super_admin': echo 'bg-purple-100 text-purple-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $residence['transfer_type'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($residence['requested_by_name'] ?? 'Unknown'); ?>
                            <div class="text-xs text-gray-400">
                                <?php echo ucfirst(str_replace('_', ' ', $residence['requested_by_role'] ?? '')); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M j, Y', strtotime($residence['veo_accepted_at'])); ?>
                            <div class="text-xs text-gray-400">
                                <?php echo date('g:i A', strtotime($residence['veo_accepted_at'])); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="viewTransferDetails(<?php echo $residence['id']; ?>)" 
                                    class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Destination Breakdown -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-chart-pie mr-2"></i>Transfers by Destination
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php 
            $destination_counts = [];
            foreach ($transferred_residences as $residence) {
                $destination = $residence['to_ward_name'] . ' - ' . $residence['to_village_name'];
                if (!isset($destination_counts[$destination])) {
                    $destination_counts[$destination] = 0;
                }
                $destination_counts[$destination]++;
            }
            arsort($destination_counts);
            ?>
            <?php foreach ($destination_counts as $destination => $count): ?>
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600"><?php echo htmlspecialchars($destination); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-bold text-gray-900"><?php echo $count; ?></p>
                        <p class="text-xs text-gray-500">residences</p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<script>
function viewTransferDetails(transferId) {
    window.location.href = 'transfer_details.php?id=' + transferId;
}
</script>

<?php include 'includes/footer.php'; ?>
