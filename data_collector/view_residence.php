<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is data collector
if ($_SESSION['user_role'] !== 'data_collector') {
    header('Location: ../unauthorized.php');
    exit();
}

$page_title = 'View Residence';

$residence_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$residence = null;
$family_members = [];

if ($residence_id > 0) {
    try {
        // Get residence details
        $stmt = $pdo->prepare("
            SELECT r.*, v.village_name, w.ward_name
            FROM residences r
            LEFT JOIN villages v ON r.village_id = v.id
            LEFT JOIN wards w ON v.ward_id = w.id
            WHERE r.id = ? AND r.registered_by = ? AND r.status = 'approved'
        ");
        $stmt->execute([$residence_id, $_SESSION['user_id']]);
        $residence = $stmt->fetch();
        
        if ($residence) {
            // Get family members
            $stmt = $pdo->prepare("
                SELECT * FROM family_members 
                WHERE residence_id = ? 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$residence_id]);
            $family_members = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error = "Error loading residence: " . $e->getMessage();
    }
}

if (!$residence) {
    $error = "Residence not found or access denied.";
}

include 'includes/header.php';
?>

<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Residence Details</h1>
            <p class="text-gray-600">View collected residence data</p>
        </div>
        <div class="flex space-x-2">
            <a href="edit_residence.php?id=<?php echo $residence_id; ?>" class="btn-mobile bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                <i class="fas fa-edit mr-2"></i>Edit
            </a>
            <a href="residences.php" class="btn-mobile bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-200">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <?php if ($residence): ?>
    <!-- Personal Information -->
    <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-user mr-2"></i>Personal Information
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-600">Full Name</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['resident_name']); ?></p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600">House Number</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['house_no']); ?></p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600">Gender</p>
                <p class="font-medium text-gray-900">
                    <i class="fas fa-<?php echo $residence['gender'] == 'Male' ? 'mars' : 'venus'; ?> mr-1"></i>
                    <?php echo htmlspecialchars($residence['gender']); ?>
                </p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600">Date of Birth</p>
                <p class="font-medium text-gray-900"><?php echo formatDate($residence['date_of_birth']); ?></p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600">NIDA Number</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['nida_number']); ?></p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600">Phone Number</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['phone'] ?: 'Not provided'); ?></p>
            </div>
        </div>
    </div>

    <!-- Professional Information -->
    <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-briefcase mr-2"></i>Professional Information
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-600">Occupation</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['occupation']); ?></p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600">Ownership</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['ownership']); ?></p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600">Employment Status</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['employment_status']); ?></p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600">Education Level</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['education_level']); ?></p>
            </div>
            
            <?php if ($residence['email']): ?>
            <div class="md:col-span-2">
                <p class="text-sm text-gray-600">Email</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['email']); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Location Information -->
    <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-map-marker-alt mr-2"></i>Location Information
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-600">Ward</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['ward_name']); ?></p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600">Village</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['village_name']); ?></p>
            </div>
        </div>
    </div>

    <!-- Family Members -->
    <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-users mr-2"></i>Family Members (<?php echo count($family_members); ?>)
            </h2>
            <a href="add_family_members.php?residence_id=<?php echo $residence_id; ?>" class="btn-mobile bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                <i class="fas fa-plus mr-2"></i>Add Member
            </a>
        </div>
        
        <?php if (!empty($family_members)): ?>
        <div class="space-y-3">
            <?php foreach ($family_members as $member): ?>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center mb-2">
                            <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($member['member_name']); ?></h3>
                            <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                <?php echo htmlspecialchars($member['relationship']); ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600">
                            <div>
                                <i class="fas fa-<?php echo $member['gender'] == 'Male' ? 'mars' : 'venus'; ?> mr-1"></i>
                                <?php echo htmlspecialchars($member['gender']); ?>
                            </div>
                            
                            <div>
                                <i class="fas fa-calendar mr-1"></i>
                                <?php echo formatDate($member['date_of_birth']); ?>
                            </div>
                            
                            <?php if ($member['nida_number']): ?>
                            <div>
                                <i class="fas fa-id-card mr-1"></i>
                                <?php echo htmlspecialchars($member['nida_number']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($member['phone']): ?>
                            <div>
                                <i class="fas fa-phone mr-1"></i>
                                <?php echo htmlspecialchars($member['phone']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($member['occupation']): ?>
                            <div>
                                <i class="fas fa-briefcase mr-1"></i>
                                <?php echo htmlspecialchars($member['occupation']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($member['education_level']): ?>
                            <div>
                                <i class="fas fa-graduation-cap mr-1"></i>
                                <?php echo htmlspecialchars($member['education_level']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-users text-4xl mb-4"></i>
            <p>No family members added yet</p>
            <a href="add_family_members.php?residence_id=<?php echo $residence_id; ?>" class="text-blue-600 hover:text-blue-700 font-medium">
                Add family members <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Collection Information -->
    <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-info-circle mr-2"></i>Collection Information
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-600">Collected By</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600">Collection Date</p>
                <p class="font-medium text-gray-900"><?php echo formatDate($residence['created_at']); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
