<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Transfer Residence';

$message = '';
$error = '';

// Get residence ID from URL
$residence_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$residence_id) {
    header('Location: residences.php');
    exit();
}

// Get residence data
try {
    $stmt = $pdo->prepare("
        SELECT r.*, w.ward_name, w.ward_code, v.village_name, v.village_code
        FROM residences r
        LEFT JOIN wards w ON r.ward_id = w.id
        LEFT JOIN villages v ON r.village_id = v.id
        WHERE r.id = ?
    ");
    $stmt->execute([$residence_id]);
    $residence = $stmt->fetch();
     
    // Check for existing active transfer
    $stmt = $pdo->prepare("
        SELECT rt.id, rt.status, rt.transfer_type, 
               tw.ward_name as to_ward_name, tv.village_name as to_village_name,
               rt.created_at
        FROM residence_transfers rt
        LEFT JOIN wards tw ON rt.to_ward_id = tw.id
        LEFT JOIN villages tv ON rt.to_village_id = tv.id
        WHERE rt.residence_id = ? 
        AND rt.status IN ('pending_approval', 'weo_approved', 'ward_approved', 'veo_accepted')
        ORDER BY rt.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$residence_id]);
    $existing_transfer = $stmt->fetch();
    
    if (!$residence) {
        header('Location: residences.php');
        exit();
    }
    
    // Check if user can transfer this residence
    if (!isSuperAdmin()) {
        $user_location = getUserLocationInfo();
        $can_transfer = false;
        
        if ($_SESSION['user_role'] === 'veo') {
            $can_transfer = ($residence['village_id'] == $user_location['village_id']);
        } elseif ($_SESSION['user_role'] === 'admin') {
            $can_transfer = ($residence['ward_id'] == $user_location['ward_id']);
        }
        
        if (!$can_transfer) {
            header('Location: residences.php');
            exit();
        }
    }
} catch (PDOException $e) {
    header('Location: residences.php');
    exit();
}

// Get all wards and villages for transfer options
$all_wards = getAllWards();
$all_villages = getAllVillages();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_ward_id = (int)$_POST['new_ward_id'];
    $new_village_id = (int)$_POST['new_village_id'];
    $transfer_reason = sanitizeInput($_POST['transfer_reason']);
    
    // Validation
    if (!$new_ward_id || !$new_village_id) {
        $error = 'Please select both ward and village for transfer';
    } elseif ($new_ward_id == $residence['ward_id'] && $new_village_id == $residence['village_id']) {
        $error = 'Cannot transfer to the same location';
    } elseif (empty($transfer_reason)) {
        $error = 'Transfer reason is required';
    } else {
        // Check if residence already has an active transfer
        $stmt = $pdo->prepare("
            SELECT rt.id, rt.status, rt.transfer_type, 
                   tw.ward_name as to_ward_name, tv.village_name as to_village_name
            FROM residence_transfers rt
            LEFT JOIN wards tw ON rt.to_ward_id = tw.id
            LEFT JOIN villages tv ON rt.to_village_id = tv.id
            WHERE rt.residence_id = ? 
            AND rt.status IN ('pending_approval', 'weo_approved', 'ward_approved', 'veo_accepted')
        ");
        $stmt->execute([$residence_id]);
        $existing_transfer = $stmt->fetch();
        
        if ($existing_transfer) {
            $error = 'This residence already has an active transfer request to ' . 
                    $existing_transfer['to_ward_name'] . ' - ' . $existing_transfer['to_village_name'] . 
                    '. Only one transfer per residence is allowed at a time.';
        } else {
        try {
            // Check if the target village exists and is in the selected ward
            $stmt = $pdo->prepare("SELECT id FROM villages WHERE id = ? AND ward_id = ?");
            $stmt->execute([$new_village_id, $new_ward_id]);
            if (!$stmt->fetch()) {
                $error = 'Selected village does not belong to the selected ward';
            } else {
                // Determine transfer type and approval requirements
                $transfer_type = 'pending_approval';
                $requires_approval = true;
                
                if (isSuperAdmin()) {
                    // Super admin can transfer directly
                    $transfer_type = 'direct';
                    $requires_approval = false;
                } elseif ($_SESSION['user_role'] === 'admin') {
                    // Ward admin needs receiving ward approval
                    $transfer_type = 'ward_admin';
                    $requires_approval = true;
                } elseif ($_SESSION['user_role'] === 'veo') {
                    // VEO needs WEO approval, then receiving ward approval, then receiving VEO acceptance
                    $transfer_type = 'veo';
                    $requires_approval = true;
                }
                
                if ($requires_approval) {
                    // Create transfer request
                    $stmt = $pdo->prepare("
                        INSERT INTO residence_transfers 
                        (residence_id, from_ward_id, from_village_id, to_ward_id, to_village_id, 
                         transfer_type, transfer_reason, requested_by, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval', NOW())
                    ");
                    $stmt->execute([
                        $residence_id, 
                        $residence['ward_id'], 
                        $residence['village_id'], 
                        $new_ward_id, 
                        $new_village_id, 
                        $transfer_type, 
                        $transfer_reason, 
                        $_SESSION['user_id']
                    ]);
                    
                    $message = 'Transfer request submitted successfully. It will be reviewed by the appropriate authorities.';
                } else {
                    // Direct transfer for super admin
                    $stmt = $pdo->prepare("UPDATE residences SET ward_id = ?, village_id = ? WHERE id = ?");
                    $stmt->execute([$new_ward_id, $new_village_id, $residence_id]);
                    
                    $message = 'Residence transferred successfully.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred';
        }
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Transfer Residence</h1>
        
        <!-- Current Residence Info -->
        <div class="bg-gray-50 p-4 rounded-lg mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Current Residence Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <span class="text-sm font-medium text-gray-600">House Number:</span>
                    <p class="text-gray-900"><?php echo htmlspecialchars($residence['house_no']); ?></p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-600">Resident Name:</span>
                    <p class="text-gray-900"><?php echo htmlspecialchars($residence['resident_name']); ?></p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-600">Current Ward:</span>
                    <p class="text-gray-900"><?php echo htmlspecialchars($residence['ward_name'] . ' (' . $residence['ward_code'] . ')'); ?></p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-600">Current Village:</span>
                    <p class="text-gray-900"><?php echo htmlspecialchars($residence['village_name'] . ' (' . $residence['village_code'] . ')'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 alert">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Existing Transfer Warning -->
        <?php if ($existing_transfer): ?>
        <div class="bg-orange-100 border border-orange-400 text-orange-700 px-4 py-3 rounded mb-4">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <div>
                    <strong>Active Transfer Exists!</strong>
                    <p class="text-sm mt-1">
                        This residence already has an active transfer request to 
                        <strong><?php echo htmlspecialchars($existing_transfer['to_ward_name'] . ' - ' . $existing_transfer['to_village_name']); ?></strong>
                        (Status: <?php echo ucfirst(str_replace('_', ' ', $existing_transfer['status'])); ?>)
                    </p>
                    <p class="text-sm mt-1">
                        Only one transfer per residence is allowed at a time. 
                        <a href="transfer_details.php?id=<?php echo $existing_transfer['id']; ?>" class="underline hover:no-underline">
                            View existing transfer details
                        </a>
                    </p>
                    <?php if (isSuperAdmin() || $_SESSION['user_role'] === 'admin'): ?>
                    <div class="mt-3">
                        <button type="button" onclick="showTransferForm()" 
                                class="bg-orange-600 text-white px-4 py-2 rounded-md hover:bg-orange-700 transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Create New Transfer (Admin Override)
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Transfer Form -->
        <form method="POST" class="space-y-6" id="transferForm" <?php echo $existing_transfer ? 'style="display: none;"' : ''; ?>>
            <div class="bg-blue-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-exchange-alt mr-2"></i>Transfer Details
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="new_ward_id" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-building mr-2"></i>Transfer to Ward *
                        </label>
                        <select id="new_ward_id" name="new_ward_id" required>
                            <option value="">Select Ward</option>
                            <?php foreach ($all_wards as $ward): ?>
                                <option value="<?php echo $ward['id']; ?>" 
                                        <?php echo (isset($new_ward_id) && $new_ward_id == $ward['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ward['ward_name'] . ' (' . $ward['ward_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="new_village_id" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-home mr-2"></i>Transfer to Street/Village *
                        </label>
                        <select id="new_village_id" name="new_village_id" required>
                            <option value="">Select Street/Village</option>
                            <?php foreach ($all_villages as $village): ?>
                                <option value="<?php echo $village['id']; ?>" 
                                        data-ward-id="<?php echo $village['ward_id']; ?>"
                                        <?php echo (isset($new_village_id) && $new_village_id == $village['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($village['village_name'] . ' (' . $village['village_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label for="transfer_reason" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-comment mr-2"></i>Transfer Reason *
                    </label>
                    <textarea id="transfer_reason" name="transfer_reason" rows="3" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Please provide a reason for this transfer..."><?php echo htmlspecialchars($transfer_reason ?? ''); ?></textarea>
                </div>
            </div>
            
            <!-- Transfer Process Info -->
            <div class="bg-yellow-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">
                    <i class="fas fa-info-circle mr-2"></i>Transfer Process
                </h3>
                <?php if (isSuperAdmin()): ?>
                    <p class="text-gray-700">As a Super Administrator, this transfer will be executed immediately without requiring approval.</p>
                <?php elseif ($_SESSION['user_role'] === 'admin'): ?>
                    <p class="text-gray-700">As a Ward Administrator, this transfer will require approval from the receiving ward before execution.</p>
                <?php elseif ($_SESSION['user_role'] === 'veo'): ?>
                    <p class="text-gray-700">As a VEO, this transfer will require approval from your WEO, then acceptance from the receiving ward, and finally acceptance from the receiving VEO.</p>
                <?php endif; ?>
            </div>
            
            <div class="flex justify-end space-x-4">
                <a href="view_residence.php?id=<?php echo $residence['id']; ?>" 
                   class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded-md hover:bg-orange-700 transition duration-200">
                    <i class="fas fa-exchange-alt mr-2"></i>Submit Transfer Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Ward-Village filtering
document.getElementById('new_ward_id').addEventListener('change', function() {
    const wardId = this.value;
    const villageSelect = document.getElementById('new_village_id');
    
    // Clear current options
    villageSelect.innerHTML = '<option value="">Select Street/Village</option>';
    
    if (wardId) {
        // Filter villages by selected ward
        const allVillages = <?php echo json_encode($all_villages); ?>;
        const wardVillages = allVillages.filter(village => village.ward_id == wardId);
        
        wardVillages.forEach(village => {
            const option = document.createElement('option');
            option.value = village.id;
            option.textContent = village.village_name + ' (' + village.village_code + ')';
            villageSelect.appendChild(option);
        });
    }
});

// Show transfer form (admin override)
function showTransferForm() {
    document.getElementById('transferForm').style.display = 'block';
    document.querySelector('.bg-orange-100').style.display = 'none';
}

// Form validation
document.getElementById('transferForm').addEventListener('submit', function(e) {
    const newWardId = document.getElementById('new_ward_id').value;
    const newVillageId = document.getElementById('new_village_id').value;
    const transferReason = document.getElementById('transfer_reason').value.trim();
    
    if (!newWardId || !newVillageId) {
        e.preventDefault();
        alert('Please select both ward and street/village for transfer');
        return false;
    }
    
    if (!transferReason) {
        e.preventDefault();
        alert('Please provide a reason for the transfer');
        return false;
    }
    
    // Check if transferring to same location
    const currentWardId = <?php echo $residence['ward_id']; ?>;
    const currentVillageId = <?php echo $residence['village_id']; ?>;
    
    if (newWardId == currentWardId && newVillageId == currentVillageId) {
        e.preventDefault();
        alert('Cannot transfer to the same location');
        return false;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
