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

// Get residence data with location and family information
try {
    $stmt = $pdo->prepare("
        SELECT r.*, w.ward_name, w.ward_code, v.village_name, v.village_code,
               u.full_name as registered_by_name
        FROM residences r
        LEFT JOIN wards w ON r.ward_id = w.id
        LEFT JOIN villages v ON r.village_id = v.id
        LEFT JOIN users u ON r.registered_by = u.id
        WHERE r.id = ?
    ");
    $stmt->execute([$residence_id]);
    $residence = $stmt->fetch();
    
    if (!$residence) {
        header('Location: residences.php');
        exit();
    }
    
    // Check if user can access this residence
    if (!isSuperAdmin()) {
        $user_location = getUserLocationInfo();
        $can_access = false;
        
        if ($_SESSION['user_role'] === 'veo') {
            $can_access = ($residence['village_id'] == $user_location['village_id']);
        } elseif ($_SESSION['user_role'] === 'weo' || $_SESSION['user_role'] === 'admin') {
            $can_access = ($residence['ward_id'] == $user_location['ward_id']);
        }
        
        if (!$can_access) {
            header('Location: residences.php');
            exit();
        }
    }
    
    // Get family members
    $stmt = $pdo->prepare("SELECT * FROM family_members WHERE residence_id = ? ORDER BY created_at ASC");
    $stmt->execute([$residence_id]);
    $family_members = $stmt->fetchAll();
    
} catch (PDOException $e) {
    header('Location: residences.php');
    exit();
}

include 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Residence Details</h1>
                    <p class="text-gray-600">House No: <?php echo htmlspecialchars($residence['house_no']); ?></p>
                </div>
                <div class="flex space-x-2">
                    <a href="edit_residence.php?id=<?php echo $residence['id']; ?>" 
                       class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-edit mr-2"></i>Edit
                    </a>
                    <a href="family_members.php?id=<?php echo $residence['id']; ?>" 
                       class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition duration-200">
                        <i class="fas fa-users mr-2"></i>Family Members
                    </a>
                    <a href="transfer_residence.php?id=<?php echo $residence['id']; ?>" 
                       class="bg-orange-600 text-white px-4 py-2 rounded-md hover:bg-orange-700 transition duration-200">
                        <i class="fas fa-exchange-alt mr-2"></i>Transfer
                    </a>
                    <a href="residences.php" 
                       class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back
                    </a>
                </div>
            </div>
            
            <!-- Status Badge -->
            <div class="mb-6">
                <span class="px-3 py-1 rounded-full text-sm font-medium
                    <?php 
                    switch($residence['status']) {
                        case 'active': echo 'bg-green-100 text-green-800'; break;
                        case 'inactive': echo 'bg-red-100 text-red-800'; break;
                        case 'moved': echo 'bg-yellow-100 text-yellow-800'; break;
                        case 'pending_approval': echo 'bg-blue-100 text-blue-800'; break;
                        default: echo 'bg-gray-100 text-gray-800';
                    }
                    ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $residence['status'])); ?>
                </span>
            </div>
        </div>
        
        <!-- Personal Information -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-user mr-2"></i>Personal Information
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-600">Full Name</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($residence['resident_name']); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Gender</label>
                    <p class="text-gray-900">
                        <span class="px-2 py-1 rounded-full text-xs font-medium
                            <?php echo $residence['gender'] === 'male' ? 'bg-blue-100 text-blue-800' : ($residence['gender'] === 'female' ? 'bg-pink-100 text-pink-800' : 'bg-gray-100 text-gray-800'); ?>">
                            <?php echo ucfirst($residence['gender']); ?>
                        </span>
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Date of Birth</label>
                    <p class="text-gray-900"><?php echo date('F j, Y', strtotime($residence['date_of_birth'])); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">NIDA Number</label>
                    <p class="text-gray-900 font-mono"><?php echo htmlspecialchars($residence['nida_number']); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Phone Number</label>
                    <p class="text-gray-900"><?php echo $residence['phone'] ? htmlspecialchars($residence['phone']) : 'N/A'; ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Email Address</label>
                    <p class="text-gray-900"><?php echo $residence['email'] ? htmlspecialchars($residence['email']) : 'N/A'; ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Occupation</label>
                    <p class="text-gray-900"><?php echo $residence['occupation'] ? htmlspecialchars($residence['occupation']) : 'N/A'; ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Education Level</label>
                    <p class="text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $residence['education_level'])); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Employment Status</label>
                    <p class="text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $residence['employment_status'])); ?></p>
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
                    <p class="text-gray-900 font-mono"><?php echo htmlspecialchars($residence['house_no']); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Ownership</label>
                    <p class="text-gray-900">
                        <span class="px-2 py-1 rounded-full text-xs font-medium
                            <?php echo $residence['ownership'] === 'owner' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                            <?php echo ucfirst($residence['ownership']); ?>
                        </span>
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Family Members</label>
                    <p class="text-gray-900"><?php echo $residence['family_members']; ?> members</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Ward</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($residence['ward_name'] . ' (' . $residence['ward_code'] . ')'); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Street/Village</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($residence['village_name'] . ' (' . $residence['village_code'] . ')'); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Registered By</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($residence['registered_by_name'] ?? 'Unknown'); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-600">Registration Date</label>
                    <p class="text-gray-900"><?php echo date('F j, Y \a\t g:i A', strtotime($residence['registered_at'])); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Family Members -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-users mr-2"></i>Family Members (<?php echo count($family_members); ?>)
            </h2>
            
            <?php if (empty($family_members)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-users text-4xl mb-4"></i>
                    <p>No family members added yet.</p>
                    <a href="family_members.php?id=<?php echo $residence['id']; ?>" 
                       class="text-blue-600 hover:underline mt-2 inline-block">
                        Add family members
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Relationship</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIDA Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Occupation</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($family_members as $member): ?>
                            <?php
                            $birth_date = new DateTime($member['date_of_birth']);
                            $today = new DateTime();
                            $age = $today->diff($birth_date)->y;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-gray-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($member['name']); ?>
                                            </div>
                                            <?php if ($member['is_minor']): ?>
                                            <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded">Minor</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo ucfirst($member['gender']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $age; ?> years
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo ucfirst($member['relationship']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $member['nida_number'] ? htmlspecialchars($member['nida_number']) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $member['occupation'] ? htmlspecialchars($member['occupation']) : 'N/A'; ?>
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

<?php include 'includes/footer.php'; ?>