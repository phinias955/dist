<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Transfer Approvals';

$message = '';
$error = '';

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transfer_id = (int)$_POST['transfer_id'];
    $action = $_POST['action'];
    $reason = sanitizeInput($_POST['reason'] ?? '');
    
    try {
        // Get transfer details
        $stmt = $pdo->prepare("
            SELECT rt.*, r.house_no, r.resident_name, 
                   fw.ward_name as from_ward_name, fv.village_name as from_village_name,
                   tw.ward_name as to_ward_name, tv.village_name as to_village_name
            FROM residence_transfers rt
            LEFT JOIN residences r ON rt.residence_id = r.id
            LEFT JOIN wards fw ON rt.from_ward_id = fw.id
            LEFT JOIN villages fv ON rt.from_village_id = fv.id
            LEFT JOIN wards tw ON rt.to_ward_id = tw.id
            LEFT JOIN villages tv ON rt.to_village_id = tv.id
            WHERE rt.id = ?
        ");
        $stmt->execute([$transfer_id]);
        $transfer = $stmt->fetch();
        
        if (!$transfer) {
            $error = 'Transfer request not found';
        } else {
            // Check if user can approve this transfer
            $can_approve = false;
            $user_location = getUserLocationInfo();
            
            if (isSuperAdmin()) {
                $can_approve = true;
            } elseif ($_SESSION['user_role'] === 'weo' && $transfer['from_ward_id'] == $user_location['ward_id']) {
                $can_approve = true;
            } elseif ($_SESSION['user_role'] === 'admin' && $transfer['to_ward_id'] == $user_location['ward_id']) {
                $can_approve = true;
            } elseif ($_SESSION['user_role'] === 'veo' && $transfer['to_village_id'] == $user_location['village_id']) {
                $can_approve = true;
            }
            
            if (!$can_approve) {
                $error = 'You do not have permission to approve this transfer';
            } else {
                if ($action === 'approve') {
                    // Handle approval based on current status and user role
                    if ($transfer['status'] === 'pending_approval') {
                        if ($_SESSION['user_role'] === 'weo') {
                            // WEO approval
                            $stmt = $pdo->prepare("UPDATE residence_transfers SET status = 'weo_approved', weo_approved_by = ?, weo_approved_at = NOW() WHERE id = ?");
                            $stmt->execute([$_SESSION['user_id'], $transfer_id]);
                            $message = 'Transfer approved by WEO. Waiting for receiving ward approval.';
                        } elseif ($_SESSION['user_role'] === 'admin') {
                            // Ward admin approval
                            $stmt = $pdo->prepare("UPDATE residence_transfers SET status = 'ward_approved', ward_approved_by = ?, ward_approved_at = NOW() WHERE id = ?");
                            $stmt->execute([$_SESSION['user_id'], $transfer_id]);
                            $message = 'Transfer approved by Ward Administrator. Waiting for VEO acceptance.';
                        } elseif ($_SESSION['user_role'] === 'veo') {
                            // VEO acceptance
                            $stmt = $pdo->prepare("UPDATE residence_transfers SET status = 'veo_accepted', veo_accepted_by = ?, veo_accepted_at = NOW() WHERE id = ?");
                            $stmt->execute([$_SESSION['user_id'], $transfer_id]);
                            
                            // Complete the transfer
                            $stmt = $pdo->prepare("UPDATE residences SET ward_id = ?, village_id = ? WHERE id = ?");
                            $stmt->execute([$transfer['to_ward_id'], $transfer['to_village_id'], $transfer['residence_id']]);
                            
                            $stmt = $pdo->prepare("UPDATE residence_transfers SET status = 'completed' WHERE id = ?");
                            $stmt->execute([$transfer_id]);
                            
                            $message = 'Transfer accepted and completed successfully.';
                        }
                    } elseif ($transfer['status'] === 'weo_approved' && $_SESSION['user_role'] === 'admin') {
                        // Ward admin approval after WEO approval
                        $stmt = $pdo->prepare("UPDATE residence_transfers SET status = 'ward_approved', ward_approved_by = ?, ward_approved_at = NOW() WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id'], $transfer_id]);
                        $message = 'Transfer approved by Ward Administrator. Waiting for VEO acceptance.';
                    } elseif ($transfer['status'] === 'ward_approved' && $_SESSION['user_role'] === 'veo') {
                        // VEO acceptance after ward approval
                        $stmt = $pdo->prepare("UPDATE residence_transfers SET status = 'veo_accepted', veo_accepted_by = ?, veo_accepted_at = NOW() WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id'], $transfer_id]);
                        
                        // Complete the transfer
                        $stmt = $pdo->prepare("UPDATE residences SET ward_id = ?, village_id = ? WHERE id = ?");
                        $stmt->execute([$transfer['to_ward_id'], $transfer['to_village_id'], $transfer['residence_id']]);
                        
                        $stmt = $pdo->prepare("UPDATE residence_transfers SET status = 'completed' WHERE id = ?");
                        $stmt->execute([$transfer_id]);
                        
                        $message = 'Transfer accepted and completed successfully.';
                    }
                } elseif ($action === 'reject') {
                    // Handle rejection
                    if (empty($reason)) {
                        $error = 'Rejection reason is required';
                    } else {
                        $stmt = $pdo->prepare("UPDATE residence_transfers SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ? WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id'], $reason, $transfer_id]);
                        $message = 'Transfer request rejected.';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error occurred';
    }
}

// Get transfer requests based on user role
try {
    $user_location = getUserLocationInfo();
    
    if (isSuperAdmin()) {
        // Super admin can see all transfers
        $stmt = $pdo->query("
            SELECT rt.*, r.house_no, r.resident_name, 
                   fw.ward_name as from_ward_name, fv.village_name as from_village_name,
                   tw.ward_name as to_ward_name, tv.village_name as to_village_name,
                   u.full_name as requested_by_name
            FROM residence_transfers rt
            LEFT JOIN residences r ON rt.residence_id = r.id
            LEFT JOIN wards fw ON rt.from_ward_id = fw.id
            LEFT JOIN villages fv ON rt.from_village_id = fv.id
            LEFT JOIN wards tw ON rt.to_ward_id = tw.id
            LEFT JOIN villages tv ON rt.to_village_id = tv.id
            LEFT JOIN users u ON rt.requested_by = u.id
            ORDER BY rt.created_at DESC
        ");
    } else {
        // Location-based filtering
        if ($_SESSION['user_role'] === 'weo') {
            // WEO can see transfers from their ward
            $stmt = $pdo->prepare("
                SELECT rt.*, r.house_no, r.resident_name, 
                       fw.ward_name as from_ward_name, fv.village_name as from_village_name,
                       tw.ward_name as to_ward_name, tv.village_name as to_village_name,
                       u.full_name as requested_by_name
                FROM residence_transfers rt
                LEFT JOIN residences r ON rt.residence_id = r.id
                LEFT JOIN wards fw ON rt.from_ward_id = fw.id
                LEFT JOIN villages fv ON rt.from_village_id = fv.id
                LEFT JOIN wards tw ON rt.to_ward_id = tw.id
                LEFT JOIN villages tv ON rt.to_village_id = tv.id
                LEFT JOIN users u ON rt.requested_by = u.id
                WHERE rt.from_ward_id = ?
                ORDER BY rt.created_at DESC
            ");
            $stmt->execute([$user_location['ward_id']]);
        } elseif ($_SESSION['user_role'] === 'admin') {
            // Ward admin can see transfers to their ward
            $stmt = $pdo->prepare("
                SELECT rt.*, r.house_no, r.resident_name, 
                       fw.ward_name as from_ward_name, fv.village_name as from_village_name,
                       tw.ward_name as to_ward_name, tv.village_name as to_village_name,
                       u.full_name as requested_by_name
                FROM residence_transfers rt
                LEFT JOIN residences r ON rt.residence_id = r.id
                LEFT JOIN wards fw ON rt.from_ward_id = fw.id
                LEFT JOIN villages fv ON rt.from_village_id = fv.id
                LEFT JOIN wards tw ON rt.to_ward_id = tw.id
                LEFT JOIN villages tv ON rt.to_village_id = tv.id
                LEFT JOIN users u ON rt.requested_by = u.id
                WHERE rt.to_ward_id = ?
                ORDER BY rt.created_at DESC
            ");
            $stmt->execute([$user_location['ward_id']]);
        } elseif ($_SESSION['user_role'] === 'veo') {
            // VEO can see transfers to their village
            $stmt = $pdo->prepare("
                SELECT rt.*, r.house_no, r.resident_name, 
                       fw.ward_name as from_ward_name, fv.village_name as from_village_name,
                       tw.ward_name as to_ward_name, tv.village_name as to_village_name,
                       u.full_name as requested_by_name
                FROM residence_transfers rt
                LEFT JOIN residences r ON rt.residence_id = r.id
                LEFT JOIN wards fw ON rt.from_ward_id = fw.id
                LEFT JOIN villages fv ON rt.from_village_id = fv.id
                LEFT JOIN wards tw ON rt.to_ward_id = tw.id
                LEFT JOIN villages tv ON rt.to_village_id = tv.id
                LEFT JOIN users u ON rt.requested_by = u.id
                WHERE rt.to_village_id = ?
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

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">Transfer Approvals</h1>
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
    
    <!-- Transfer Requests Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Residence</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($transfers as $transfer): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($transfer['house_no']); ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($transfer['resident_name']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div><?php echo htmlspecialchars($transfer['from_ward_name']); ?></div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($transfer['from_village_name']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div><?php echo htmlspecialchars($transfer['to_ward_name']); ?></div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($transfer['to_village_name']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo ucfirst(str_replace('_', ' ', $transfer['transfer_type'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php 
                                switch($transfer['status']) {
                                    case 'pending_approval': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'weo_approved': echo 'bg-blue-100 text-blue-800'; break;
                                    case 'ward_approved': echo 'bg-purple-100 text-purple-800'; break;
                                    case 'veo_accepted': echo 'bg-green-100 text-green-800'; break;
                                    case 'completed': echo 'bg-green-100 text-green-800'; break;
                                    case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $transfer['status'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($transfer['requested_by_name'] ?? 'Unknown'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M j, Y', strtotime($transfer['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <?php if ($transfer['status'] !== 'completed' && $transfer['status'] !== 'rejected'): ?>
                            <div class="flex space-x-2">
                                <button onclick="openApprovalModal(<?php echo $transfer['id']; ?>, 'approve')" 
                                        class="text-green-600 hover:text-green-900" title="Approve">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button onclick="openApprovalModal(<?php echo $transfer['id']; ?>, 'reject')" 
                                        class="text-red-600 hover:text-red-900" title="Reject">
                                    <i class="fas fa-times"></i>
                                </button>
                                <button onclick="viewTransferDetails(<?php echo $transfer['id']; ?>)" 
                                        class="text-blue-600 hover:text-blue-900" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php else: ?>
                            <button onclick="viewTransferDetails(<?php echo $transfer['id']; ?>)" 
                                    class="text-blue-600 hover:text-blue-900" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div id="approvalModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <form method="POST" id="approvalForm">
                <input type="hidden" id="modalTransferId" name="transfer_id">
                <input type="hidden" id="modalAction" name="action">
                
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4" id="modalTitle">
                        Approve Transfer
                    </h3>
                    
                    <div class="mb-4">
                        <label for="modalReason" class="block text-sm font-medium text-gray-700 mb-2">
                            <span id="reasonLabel">Reason</span>
                        </label>
                        <textarea id="modalReason" name="reason" rows="3" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Enter reason..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeApprovalModal()" 
                                class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" id="modalSubmitBtn"
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            Submit
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openApprovalModal(transferId, action) {
    document.getElementById('modalTransferId').value = transferId;
    document.getElementById('modalAction').value = action;
    
    const modal = document.getElementById('approvalModal');
    const title = document.getElementById('modalTitle');
    const reasonLabel = document.getElementById('reasonLabel');
    const reasonField = document.getElementById('modalReason');
    const submitBtn = document.getElementById('modalSubmitBtn');
    
    if (action === 'approve') {
        title.textContent = 'Approve Transfer';
        reasonLabel.textContent = 'Approval Comments (Optional)';
        reasonField.placeholder = 'Enter any comments about this approval...';
        reasonField.required = false;
        submitBtn.textContent = 'Approve';
        submitBtn.className = 'bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700';
    } else {
        title.textContent = 'Reject Transfer';
        reasonLabel.textContent = 'Rejection Reason *';
        reasonField.placeholder = 'Please provide a reason for rejection...';
        reasonField.required = true;
        submitBtn.textContent = 'Reject';
        submitBtn.className = 'bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700';
    }
    
    modal.classList.remove('hidden');
}

function closeApprovalModal() {
    document.getElementById('approvalModal').classList.add('hidden');
    document.getElementById('modalReason').value = '';
}

function viewTransferDetails(transferId) {
    // This would open a detailed view of the transfer
    alert('Transfer details view - ID: ' + transferId);
}

// Close modal when clicking outside
document.getElementById('approvalModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeApprovalModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
