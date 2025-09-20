<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is data collector
if ($_SESSION['user_role'] !== 'data_collector') {
    header('Location: ../unauthorized.php');
    exit();
}

$page_title = 'Residences';

$message = '';
$error = '';

// Get user location info
$user_location = getUserLocationInfo();

// Get residences collected by this data collector
$residences = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.*, v.village_name, w.ward_name,
               (SELECT COUNT(*) FROM family_members WHERE residence_id = r.id) as family_count
        FROM residences r
        LEFT JOIN villages v ON r.village_id = v.id
        LEFT JOIN wards w ON v.ward_id = w.id
        WHERE r.registered_by = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $residences = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error loading residences: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Residences</h1>
            <p class="text-gray-600">Your collected residence data</p>
        </div>
        <a href="add_residence.php" class="btn-mobile bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
            <i class="fas fa-plus mr-2"></i>Add New
        </a>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white p-4 rounded-xl shadow-sm card-mobile">
            <div class="flex items-center">
                <div class="bg-blue-100 p-3 rounded-lg">
                    <i class="fas fa-building text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900"><?php echo count($residences); ?></p>
                    <p class="text-sm text-gray-600">Total Residences</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm card-mobile">
            <div class="flex items-center">
                <div class="bg-green-100 p-3 rounded-lg">
                    <i class="fas fa-users text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900"><?php echo array_sum(array_column($residences, 'family_count')); ?></p>
                    <p class="text-sm text-gray-600">Family Members</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Residences List -->
    <?php if (!empty($residences)): ?>
    <div class="space-y-3">
        <?php foreach ($residences as $residence): ?>
        <div class="bg-white p-4 rounded-xl shadow-sm card-mobile">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center mb-2">
                        <h3 class="font-semibold text-gray-900 text-lg"><?php echo htmlspecialchars($residence['resident_name']); ?></h3>
                        <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                            <?php echo htmlspecialchars($residence['house_no']); ?>
                        </span>
                    </div>
                    
                    <div class="space-y-1 text-sm text-gray-600">
                        <p><i class="fas fa-map-marker-alt mr-2"></i><?php echo htmlspecialchars($residence['village_name'] . ', ' . $residence['ward_name']); ?></p>
                        <p><i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($residence['phone'] ?: 'No phone'); ?></p>
                        <p><i class="fas fa-calendar mr-2"></i>Collected: <?php echo formatDate($residence['created_at']); ?></p>
                    </div>
                    
                    <div class="flex items-center mt-3 space-x-4">
                        <div class="flex items-center text-sm text-gray-500">
                            <i class="fas fa-users mr-1"></i>
                            <span><?php echo $residence['family_count']; ?> family member<?php echo $residence['family_count'] != 1 ? 's' : ''; ?></span>
                        </div>
                        
                        <div class="flex items-center text-sm text-gray-500">
                            <i class="fas fa-<?php echo $residence['gender'] == 'Male' ? 'mars' : 'venus'; ?> mr-1"></i>
                            <span><?php echo htmlspecialchars($residence['gender']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-col space-y-2 ml-4">
                    <a href="view_residence.php?id=<?php echo $residence['id']; ?>" class="btn-mobile bg-blue-100 text-blue-700 px-3 py-2 rounded-lg hover:bg-blue-200 transition duration-200 text-center">
                        <i class="fas fa-eye mr-1"></i>View
                    </a>
                    <a href="edit_residence.php?id=<?php echo $residence['id']; ?>" class="btn-mobile bg-green-100 text-green-700 px-3 py-2 rounded-lg hover:bg-green-200 transition duration-200 text-center">
                        <i class="fas fa-edit mr-1"></i>Edit
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="bg-white p-8 rounded-xl shadow-sm card-mobile text-center">
        <i class="fas fa-building text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Residences Found</h3>
        <p class="text-gray-500 mb-6">You haven't collected any residence data yet.</p>
        <a href="add_residence.php" class="btn-mobile bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200 inline-flex items-center">
            <i class="fas fa-plus mr-2"></i>Start Collecting Data
        </a>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
