<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Transfer Status';

$message = '';
$error = '';

// Get transfer requests with detailed status information based on user role
try {
    if (isSuperAdmin()) {
        // Super admin can see all transfers
        $stmt = $pdo->query("
            SELECT rt.*, 
                   r.house_no, r.resident_name, r.nida_number,
                   fw.ward_name as from_ward_name, fw.ward_code as from_ward_code,
                   fv.village_name as from_village_name, fv.village_code as from_village_code,
                   tw.ward_name as to_ward_name, tw.ward_code as to_ward_code,
                   tv.village_name as to_village_name, tv.village_code as to_village_code,
                   u.full_name as requested_by_name, u.role as requested_by_role,
                   weo.full_name as weo_approved_by_name,
                   ward_admin.full_name as ward_approved_by_name,
                   veo.full_name as veo_accepted_by_name,
                   rejected_user.full_name as rejected_by_name
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
            LEFT JOIN users rejected_user ON rt.rejected_by = rejected_user.id
            ORDER BY rt.created_at DESC
        ");
    } else {
        // Role-based filtering
        $user_location = getUserLocationInfo();
        
        if ($_SESSION['user_role'] === 'weo') {
            // WEO can see transfers from their ward
            $stmt = $pdo->prepare("
                SELECT rt.*, 
                       r.house_no, r.resident_name, r.nida_number,
                       fw.ward_name as from_ward_name, fw.ward_code as from_ward_code,
                       fv.village_name as from_village_name, fv.village_code as from_village_code,
                       tw.ward_name as to_ward_name, tw.ward_code as to_ward_code,
                       tv.village_name as to_village_name, tv.village_code as to_village_code,
                       u.full_name as requested_by_name, u.role as requested_by_role,
                       weo.full_name as weo_approved_by_name,
                       ward_admin.full_name as ward_approved_by_name,
                       veo.full_name as veo_accepted_by_name,
                       rejected_user.full_name as rejected_by_name
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
                LEFT JOIN users rejected_user ON rt.rejected_by = rejected_user.id
                WHERE rt.from_ward_id = ?
                ORDER BY rt.created_at DESC
            ");
            $stmt->execute([$user_location['ward_id']]);
        } elseif ($_SESSION['user_role'] === 'admin') {
            // Ward admin can see transfers to their ward
            $stmt = $pdo->prepare("
                SELECT rt.*, 
                       r.house_no, r.resident_name, r.nida_number,
                       fw.ward_name as from_ward_name, fw.ward_code as from_ward_code,
                       fv.village_name as from_village_name, fv.village_code as from_village_code,
                       tw.ward_name as to_ward_name, tw.ward_code as to_ward_code,
                       tv.village_name as to_village_name, tv.village_code as to_village_code,
                       u.full_name as requested_by_name, u.role as requested_by_role,
                       weo.full_name as weo_approved_by_name,
                       ward_admin.full_name as ward_approved_by_name,
                       veo.full_name as veo_accepted_by_name,
                       rejected_user.full_name as rejected_by_name
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
                LEFT JOIN users rejected_user ON rt.rejected_by = rejected_user.id
                WHERE rt.to_ward_id = ?
                ORDER BY rt.created_at DESC
            ");
            $stmt->execute([$user_location['ward_id']]);
        } elseif ($_SESSION['user_role'] === 'veo') {
            // VEO can see transfers to their village, but only after WEO approval (for VEO transfers) or ward approval (for ward admin transfers)
            $stmt = $pdo->prepare("
                SELECT rt.*, 
                       r.house_no, r.resident_name, r.nida_number,
                       fw.ward_name as from_ward_name, fw.ward_code as from_ward_code,
                       fv.village_name as from_village_name, fv.village_code as from_village_code,
                       tw.ward_name as to_ward_name, tw.ward_code as to_ward_code,
                       tv.village_name as to_village_name, tv.village_code as to_village_code,
                       u.full_name as requested_by_name, u.role as requested_by_role,
                       weo.full_name as weo_approved_by_name,
                       ward_admin.full_name as ward_approved_by_name,
                       veo.full_name as veo_accepted_by_name,
                       rejected_user.full_name as rejected_by_name
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
                LEFT JOIN users rejected_user ON rt.rejected_by = rejected_user.id
                WHERE rt.to_village_id = ?
                AND (
                    (rt.transfer_type = 'veo' AND rt.status IN ('weo_approved', 'ward_approved', 'veo_accepted', 'completed', 'rejected'))
                    OR 
                    (rt.transfer_type = 'ward_admin' AND rt.status IN ('ward_approved', 'veo_accepted', 'completed', 'rejected'))
                    OR
                    (rt.transfer_type = 'super_admin' AND rt.status IN ('completed', 'rejected'))
                )
                ORDER BY rt.created_at DESC
            ");
            $stmt->execute([$user_location['village_id']]);
        }
    }
    
    $transfers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error occurred';
    $transfers = [];
}

// Function to get status information
function getStatusInfo($transfer) {
    $status = $transfer['status'];
    $stage = '';
    $waiting_for = '';
    $next_action = '';
    
    switch ($status) {
        case 'pending_approval':
            if ($transfer['transfer_type'] === 'veo') {
                $stage = 'Stage 1: WEO Approval';
                $waiting_for = 'WEO from ' . $transfer['from_ward_name'];
                $next_action = 'WEO needs to approve the transfer request';
            } elseif ($transfer['transfer_type'] === 'ward_admin') {
                $stage = 'Stage 1: Receiving Ward Approval';
                $waiting_for = 'Ward Admin from ' . $transfer['to_ward_name'];
                $next_action = 'Receiving ward admin needs to approve the transfer';
            } else {
                $stage = 'Stage 1: Initial Request';
                $waiting_for = 'System Processing';
                $next_action = 'Transfer request submitted';
            }
            break;
            
        case 'weo_approved':
            $stage = 'Stage 2: Receiving Ward Approval';
            $waiting_for = 'Ward Admin from ' . $transfer['to_ward_name'];
            $next_action = 'Receiving ward admin needs to approve the transfer';
            break;
            
        case 'ward_approved':
            $stage = 'Stage 3: VEO Acceptance';
            $waiting_for = 'VEO from ' . $transfer['to_village_name'];
            $next_action = 'Receiving VEO needs to accept the transfer';
            break;
            
        case 'veo_accepted':
            $stage = 'Stage 4: Transfer Completed';
            $waiting_for = 'Completed';
            $next_action = 'Residence has been successfully transferred';
            break;
            
        case 'completed':
            $stage = 'Completed';
            $waiting_for = 'Completed';
            $next_action = 'Transfer process completed successfully';
            break;
            
        case 'rejected':
            $stage = 'Rejected';
            $waiting_for = 'Rejected by ' . $transfer['rejected_by_name'];
            $next_action = 'Transfer request was rejected';
            break;
    }
    
    return [
        'stage' => $stage,
        'waiting_for' => $waiting_for,
        'next_action' => $next_action
    ];
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Transfer Status</h1>
            <p class="text-gray-600">View all residence transfer requests and their approval status</p>
        </div>
        <div class="flex space-x-2">
            <a href="transfer_approvals.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                <i class="fas fa-exchange-alt mr-2"></i>Manage Approvals
            </a>
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
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <?php
        $stats = [
            'pending_approval' => 0,
            'weo_approved' => 0,
            'ward_approved' => 0,
            'veo_accepted' => 0,
            'completed' => 0,
            'rejected' => 0
        ];
        
        foreach ($transfers as $transfer) {
            $stats[$transfer['status']]++;
        }
        ?>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                    <i class="fas fa-clock text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Pending Approval</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['pending_approval']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <i class="fas fa-user-check text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">WEO Approved</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['weo_approved']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                    <i class="fas fa-building text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Ward Approved</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['ward_approved']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Completed</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['completed'] + $stats['veo_accepted']; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transfer Requests Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">All Transfer Requests</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Residence</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transfer Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stage</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waiting For</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($transfers as $transfer): ?>
                    <?php $statusInfo = getStatusInfo($transfer); ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-home text-blue-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($transfer['house_no']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($transfer['resident_name']); ?>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        NIDA: <?php echo htmlspecialchars($transfer['nida_number']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <div class="flex items-center mb-1">
                                    <i class="fas fa-arrow-right text-gray-400 mr-2"></i>
                                    <span class="font-medium">From:</span>
                                </div>
                                <div class="text-xs text-gray-600 ml-4">
                                    <?php echo htmlspecialchars($transfer['from_ward_name'] . ' - ' . $transfer['from_village_name']); ?>
                                </div>
                                <div class="flex items-center mt-2 mb-1">
                                    <i class="fas fa-arrow-right text-gray-400 mr-2"></i>
                                    <span class="font-medium">To:</span>
                                </div>
                                <div class="text-xs text-gray-600 ml-4">
                                    <?php echo htmlspecialchars($transfer['to_ward_name'] . ' - ' . $transfer['to_village_name']); ?>
                                </div>
                            </div>
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm">
                                <div class="font-medium text-gray-900"><?php echo $statusInfo['stage']; ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?php echo $statusInfo['next_action']; ?></div>
                            </div>
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo $statusInfo['waiting_for']; ?>
                            </div>
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo htmlspecialchars($transfer['requested_by_name']); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo ucfirst(str_replace('_', ' ', $transfer['requested_by_role'])); ?>
                            </div>
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <div class="flex space-x-1">
                                        <?php
                                        $stages = ['pending_approval', 'weo_approved', 'ward_approved', 'veo_accepted', 'completed'];
                                        $currentStage = array_search($transfer['status'], $stages);
                                        if ($currentStage === false) $currentStage = -1;
                                        
                                        for ($i = 0; $i < 4; $i++):
                                            $isActive = $i <= $currentStage;
                                            $isCurrent = $i == $currentStage;
                                        ?>
                                        <div class="flex items-center">
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium
                                                <?php echo $isActive ? 'bg-green-500 text-white' : 'bg-gray-300 text-gray-600'; ?>">
                                                <?php echo $i + 1; ?>
                                            </div>
                                            <?php if ($i < 3): ?>
                                            <div class="w-8 h-1 mx-1 <?php echo $isActive ? 'bg-green-500' : 'bg-gray-300'; ?>"></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button onclick="viewTransferDetails(<?php echo $transfer['id']; ?>)" 
                                        class="text-blue-600 hover:text-blue-900" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($transfer['status'] !== 'completed' && $transfer['status'] !== 'rejected'): ?>
                                <button onclick="viewApprovalStatus(<?php echo $transfer['id']; ?>)" 
                                        class="text-orange-600 hover:text-orange-900" title="View Approval Status">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if (empty($transfers)): ?>
    <div class="text-center py-12">
        <i class="fas fa-exchange-alt text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No Transfer Requests</h3>
        <p class="text-gray-500">There are no residence transfer requests at the moment.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Transfer Details Modal -->
<div id="transferDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Transfer Details</h3>
                    <button onclick="closeTransferDetailsModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div id="transferDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewTransferDetails(transferId) {
    window.location.href = 'transfer_details.php?id=' + transferId;
}

function viewApprovalStatus(transferId) {
    window.location.href = 'transfer_details.php?id=' + transferId;
}

function closeTransferDetailsModal() {
    document.getElementById('transferDetailsModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('transferDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeTransferDetailsModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
