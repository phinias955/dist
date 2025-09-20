<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'View Residence';

$error = '';

// Get residence ID from URL
$residence_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$residence_id) {
    header('Location: residences.php');
    exit();
}

// Get residence data
try {
    if (canViewAllData()) {
        $stmt = $pdo->prepare("SELECT r.*, u.full_name as registered_by_name FROM residences r 
                              LEFT JOIN users u ON r.registered_by = u.id 
                              WHERE r.id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM residences WHERE id = ? AND registered_by = ?");
        $stmt->execute([$residence_id, $_SESSION['user_id']]);
    }
    
    if (!canViewAllData()) {
        $stmt->execute([$residence_id]);
    }
    
    $residence = $stmt->fetch();
    
    if (!$residence) {
        header('Location: residences.php');
        exit();
    }
} catch (PDOException $e) {
    header('Location: residences.php');
    exit();
}

include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Residence Details</h1>
            <div class="flex space-x-2">
                <a href="edit_residence.php?id=<?php echo $residence['id']; ?>" 
                   class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-edit mr-2"></i>Edit
                </a>
                <a href="residences.php" 
                   class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>
        
        <!-- Residence Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Personal Information -->
            <div class="space-y-6">
                <div class="bg-gray-50 p-6 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-user mr-2"></i>Personal Information
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Full Name</label>
                            <p class="text-lg text-gray-900"><?php echo htmlspecialchars($residence['resident_name']); ?></p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-600">NIDA Number</label>
                            <p class="text-lg text-gray-900"><?php echo htmlspecialchars($residence['nida_number']); ?></p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Phone Number</label>
                            <p class="text-lg text-gray-900"><?php echo htmlspecialchars($residence['phone'] ?? 'Not provided'); ?></p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Email Address</label>
                            <p class="text-lg text-gray-900"><?php echo htmlspecialchars($residence['email'] ?? 'Not provided'); ?></p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Occupation</label>
                            <p class="text-lg text-gray-900"><?php echo htmlspecialchars($residence['occupation'] ?? 'Not provided'); ?></p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Family Members</label>
                            <p class="text-lg text-gray-900"><?php echo $residence['family_members']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Address and Status Information -->
            <div class="space-y-6">
                <div class="bg-gray-50 p-6 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-map-marker-alt mr-2"></i>Address Information
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Full Address</label>
                            <p class="text-lg text-gray-900 whitespace-pre-line"><?php echo htmlspecialchars($residence['address']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 p-6 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-info-circle mr-2"></i>Registration Information
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Status</label>
                            <span class="px-3 py-1 text-sm font-semibold rounded-full 
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
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Registration Date</label>
                            <p class="text-lg text-gray-900"><?php echo formatDate($residence['registered_at']); ?></p>
                        </div>
                        
                        <?php if (canViewAllData()): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Registered By</label>
                            <p class="text-lg text-gray-900"><?php echo htmlspecialchars($residence['registered_by_name'] ?? 'Unknown'); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="mt-8 pt-6 border-t border-gray-200">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    Last updated: <?php echo formatDate($residence['registered_at']); ?>
                </div>
                <div class="flex space-x-2">
                    <a href="edit_residence.php?id=<?php echo $residence['id']; ?>" 
                       class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200">
                        <i class="fas fa-edit mr-2"></i>Edit Residence
                    </a>
                    <?php if ($residence['status'] === 'active'): ?>
                        <a href="residences.php?delete=<?php echo $residence['id']; ?>" 
                           class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition duration-200"
                           onclick="return confirmDelete('Are you sure you want to deactivate this residence?')">
                            <i class="fas fa-times mr-2"></i>Deactivate
                        </a>
                    <?php else: ?>
                        <a href="residences.php?activate=<?php echo $residence['id']; ?>" 
                           class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200">
                            <i class="fas fa-check mr-2"></i>Activate
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
