<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Transfer Details';

$error = '';

// Get transfer ID from URL
$transfer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$transfer_id) {
    header('Location: transfer_status.php');
    exit();
}

// Get detailed transfer information
try {
    $stmt = $pdo->prepare("
        SELECT rt.*, 
               r.house_no, r.resident_name, r.nida_number, r.phone, r.email, r.occupation,
               fw.ward_name as from_ward_name, fw.ward_code as from_ward_code,
               fv.village_name as from_village_name, fv.village_code as from_village_code,
               tw.ward_name as to_ward_name, tw.ward_code as to_ward_code,
               tv.village_name as to_village_name, tv.village_code as to_village_code,
               u.full_name as requested_by_name, u.role as requested_by_role, u.username as requested_by_username,
               weo.full_name as weo_approved_by_name, weo.username as weo_approved_by_username,
               ward_admin.full_name as ward_approved_by_name, ward_admin.username as ward_approved_by_username,
               veo.full_name as veo_accepted_by_name, veo.username as veo_accepted_by_username,
               rejected_user.full_name as rejected_by_name, rejected_user.username as rejected_by_username
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
        WHERE rt.id = ?
    ");
    
    $stmt->execute([$transfer_id]);
    $transfer = $stmt->fetch();
    
    if (!$transfer) {
        header('Location: transfer_status.php');
        exit();
    }
    
    // Check if user can view this transfer
    if (!isSuperAdmin()) {
        $user_location = getUserLocationInfo();
        $can_view = false;
        
        if ($_SESSION['user_role'] === 'veo') {
            $can_view = ($transfer['from_village_id'] == $user_location['village_id'] || 
                        $transfer['to_village_id'] == $user_location['village_id']);
        } elseif ($_SESSION['user_role'] === 'weo' || $_SESSION['user_role'] === 'admin') {
            $can_view = ($transfer['from_ward_id'] == $user_location['ward_id'] || 
                        $transfer['to_ward_id'] == $user_location['ward_id']);
        }
        
        if (!$can_view) {
            header('Location: transfer_status.php');
            exit();
        }
    }
    
} catch (PDOException $e) {
    header('Location: transfer_status.php');
    exit();
}

// Function to get status information
function getStatusInfo($transfer) {
    $status = $transfer['status'];
    $stage = '';
    $waiting_for = '';
    $next_action = '';
    $progress_percentage = 0;
    
    switch ($status) {
        case 'pending_approval':
            if ($transfer['transfer_type'] === 'veo') {
                $stage = 'Stage 1: WEO Approval';
                $waiting_for = 'WEO from ' . $transfer['from_ward_name'];
                $next_action = 'WEO needs to approve the transfer request';
                $progress_percentage = 25;
            } elseif ($transfer['transfer_type'] === 'ward_admin') {
                $stage = 'Stage 1: Receiving Ward Approval';
                $waiting_for = 'Ward Admin from ' . $transfer['to_ward_name'];
                $next_action = 'Receiving ward admin needs to approve the transfer';
                $progress_percentage = 25;
            } else {
                $stage = 'Stage 1: Initial Request';
                $waiting_for = 'System Processing';
                $next_action = 'Transfer request submitted';
                $progress_percentage = 25;
            }
            break;
            
        case 'weo_approved':
            $stage = 'Stage 2: Receiving Ward Approval';
            $waiting_for = 'Ward Admin from ' . $transfer['to_ward_name'];
            $next_action = 'Receiving ward admin needs to approve the transfer';
            $progress_percentage = 50;
            break;
            
        case 'ward_approved':
            $stage = 'Stage 3: VEO Acceptance';
            $waiting_for = 'VEO from ' . $transfer['to_village_name'];
            $next_action = 'Receiving VEO needs to accept the transfer';
            $progress_percentage = 75;
            break;
            
        case 'veo_accepted':
            $stage = 'Stage 4: Transfer Completed';
            $waiting_for = 'Completed';
            $next_action = 'Residence has been successfully transferred';
            $progress_percentage = 100;
            break;
            
        case 'completed':
            $stage = 'Completed';
            $waiting_for = 'Completed';
            $next_action = 'Transfer process completed successfully';
            $progress_percentage = 100;
            break;
            
        case 'rejected':
            $stage = 'Rejected';
            $waiting_for = 'Rejected by ' . $transfer['rejected_by_name'];
            $next_action = 'Transfer request was rejected';
            $progress_percentage = 0;
            break;
    }
    
    return [
        'stage' => $stage,
        'waiting_for' => $waiting_for,
        'next_action' => $next_action,
        'progress_percentage' => $progress_percentage
    ];
}

$statusInfo = getStatusInfo($transfer);

include 'includes/header.php';
?>

<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Transfer Details</h1>
                <p class="text-gray-600">Transfer ID: #<?php echo $transfer['id']; ?></p>
            </div>
            <div class="flex space-x-2">
                <a href="transfer_status.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Status
                </a>
                <?php if ($transfer['status'] !== 'completed' && $transfer['status'] !== 'rejected'): ?>
                <a href="transfer_approvals.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-exchange-alt mr-2"></i>Manage Approvals
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Status Badge -->
        <div class="mb-4">
            <span class="px-3 py-1 rounded-full text-sm font-medium
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
        </div>
        
        <!-- Progress Bar -->
        <div class="mb-6">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-gray-700">Progress</span>
                <span class="text-sm text-gray-500"><?php echo $statusInfo['progress_percentage']; ?>%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                     style="width: <?php echo $statusInfo['progress_percentage']; ?>%"></div>
            </div>
        </div>
    </div>
    
    <!-- Residence Information -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-home mr-2"></i>Residence Information
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-600">House Number</label>
                <p class="text-gray-900 font-mono"><?php echo htmlspecialchars($transfer['house_no']); ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-600">Resident Name</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($transfer['resident_name']); ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-600">NIDA Number</label>
                <p class="text-gray-900 font-mono"><?php echo htmlspecialchars($transfer['nida_number']); ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-600">Phone</label>
                <p class="text-gray-900"><?php echo $transfer['phone'] ? htmlspecialchars($transfer['phone']) : 'N/A'; ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-600">Email</label>
                <p class="text-gray-900"><?php echo $transfer['email'] ? htmlspecialchars($transfer['email']) : 'N/A'; ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-600">Occupation</label>
                <p class="text-gray-900"><?php echo $transfer['occupation'] ? htmlspecialchars($transfer['occupation']) : 'N/A'; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Transfer Details -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-exchange-alt mr-2"></i>Transfer Details
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- From Location -->
            <div class="bg-red-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-red-800 mb-3">
                    <i class="fas fa-arrow-left mr-2"></i>From Location
                </h3>
                <div class="space-y-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-600">Ward</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($transfer['from_ward_name'] . ' (' . $transfer['from_ward_code'] . ')'); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600">Street/Village</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($transfer['from_village_name'] . ' (' . $transfer['from_village_code'] . ')'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- To Location -->
            <div class="bg-green-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-green-800 mb-3">
                    <i class="fas fa-arrow-right mr-2"></i>To Location
                </h3>
                <div class="space-y-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-600">Ward</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($transfer['to_ward_name'] . ' (' . $transfer['to_ward_code'] . ')'); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600">Street/Village</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($transfer['to_village_name'] . ' (' . $transfer['to_village_code'] . ')'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transfer Reason -->
        <div class="mt-6">
            <label class="block text-sm font-medium text-gray-600 mb-2">Transfer Reason</label>
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-gray-900"><?php echo htmlspecialchars($transfer['transfer_reason']); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Approval Timeline -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-clock mr-2"></i>Approval Timeline
        </h2>
        
        <div class="space-y-4">
            <!-- Request Submitted -->
            <div class="flex items-center">
                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-medium mr-4">
                    <i class="fas fa-plus"></i>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900">Transfer Request Submitted</h3>
                        <span class="text-xs text-gray-500"><?php echo date('M j, Y \a\t g:i A', strtotime($transfer['created_at'])); ?></span>
                    </div>
                    <p class="text-sm text-gray-600">
                        Requested by <?php echo htmlspecialchars($transfer['requested_by_name']); ?> 
                        (<?php echo ucfirst(str_replace('_', ' ', $transfer['requested_by_role'])); ?>)
                    </p>
                </div>
            </div>
            
            <!-- WEO Approval -->
            <?php if ($transfer['weo_approved_at']): ?>
            <div class="flex items-center">
                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-medium mr-4">
                    <i class="fas fa-check"></i>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900">WEO Approval</h3>
                        <span class="text-xs text-gray-500"><?php echo date('M j, Y \a\t g:i A', strtotime($transfer['weo_approved_at'])); ?></span>
                    </div>
                    <p class="text-sm text-gray-600">
                        Approved by <?php echo htmlspecialchars($transfer['weo_approved_by_name']); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Ward Approval -->
            <?php if ($transfer['ward_approved_at']): ?>
            <div class="flex items-center">
                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-medium mr-4">
                    <i class="fas fa-check"></i>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900">Ward Approval</h3>
                        <span class="text-xs text-gray-500"><?php echo date('M j, Y \a\t g:i A', strtotime($transfer['ward_approved_at'])); ?></span>
                    </div>
                    <p class="text-sm text-gray-600">
                        Approved by <?php echo htmlspecialchars($transfer['ward_approved_by_name']); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- VEO Acceptance -->
            <?php if ($transfer['veo_accepted_at']): ?>
            <div class="flex items-center">
                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-medium mr-4">
                    <i class="fas fa-check"></i>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900">VEO Acceptance</h3>
                        <span class="text-xs text-gray-500"><?php echo date('M j, Y \a\t g:i A', strtotime($transfer['veo_accepted_at'])); ?></span>
                    </div>
                    <p class="text-sm text-gray-600">
                        Accepted by <?php echo htmlspecialchars($transfer['veo_accepted_by_name']); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Rejection -->
            <?php if ($transfer['rejected_at']): ?>
            <div class="flex items-center">
                <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center text-white text-sm font-medium mr-4">
                    <i class="fas fa-times"></i>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-center">
                        <h3 class="text-sm font-medium text-gray-900">Transfer Rejected</h3>
                        <span class="text-xs text-gray-500"><?php echo date('M j, Y \a\t g:i A', strtotime($transfer['rejected_at'])); ?></span>
                    </div>
                    <p class="text-sm text-gray-600">
                        Rejected by <?php echo htmlspecialchars($transfer['rejected_by_name']); ?>
                    </p>
                    <?php if ($transfer['rejection_reason']): ?>
                    <p class="text-sm text-red-600 mt-1">
                        <strong>Reason:</strong> <?php echo htmlspecialchars($transfer['rejection_reason']); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Current Status -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-info-circle mr-2"></i>Current Status
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-600">Current Stage</label>
                <p class="text-lg font-semibold text-gray-900"><?php echo $statusInfo['stage']; ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-600">Waiting For</label>
                <p class="text-lg font-semibold text-gray-900"><?php echo $statusInfo['waiting_for']; ?></p>
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-600">Next Action</label>
                <p class="text-gray-900"><?php echo $statusInfo['next_action']; ?></p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
