<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is data collector
if ($_SESSION['user_role'] !== 'data_collector') {
    header('Location: ../unauthorized.php');
    exit();
}

$page_title = 'Dashboard';

// Get user location info
$user_location = getUserLocationInfo();

// Get statistics
$stats = [];

try {
    // Total residences collected by this data collector
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM residences 
        WHERE registered_by = ? AND status = 'approved'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['total_residences'] = $stmt->fetch()['total'];

    // Residences collected this week
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM residences 
        WHERE registered_by = ? 
        AND status = 'approved' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['this_week'] = $stmt->fetch()['total'];

    // Total family members collected
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM family_members fm
        JOIN residences r ON fm.residence_id = r.id
        WHERE r.registered_by = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['total_family_members'] = $stmt->fetch()['total'];

    // Recent residences
    $stmt = $pdo->prepare("
        SELECT r.*, v.village_name, w.ward_name
        FROM residences r
        LEFT JOIN villages v ON r.village_id = v.id
        LEFT JOIN wards w ON v.ward_id = w.id
        WHERE r.registered_by = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_residences = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error loading statistics: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="space-y-4">
    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 rounded-xl card-mobile">
        <div class="flex items-center">
            <div class="flex-1">
                <h1 class="text-2xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                <p class="text-blue-100">Data Collection Dashboard</p>
                <?php if ($user_location): ?>
                <p class="text-sm text-blue-200 mt-2">
                    <i class="fas fa-map-marker-alt mr-1"></i>
                    <?php echo htmlspecialchars($user_location['ward_name'] . ', ' . $user_location['village_name']); ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="text-right">
                <i class="fas fa-mobile-alt text-4xl text-blue-200"></i>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white p-4 rounded-xl shadow-sm card-mobile">
            <div class="flex items-center">
                <div class="bg-blue-100 p-3 rounded-lg">
                    <i class="fas fa-building text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_residences']; ?></p>
                    <p class="text-sm text-gray-600">Total Residences</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm card-mobile">
            <div class="flex items-center">
                <div class="bg-green-100 p-3 rounded-lg">
                    <i class="fas fa-calendar-week text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['this_week']; ?></p>
                    <p class="text-sm text-gray-600">This Week</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm card-mobile">
            <div class="flex items-center">
                <div class="bg-purple-100 p-3 rounded-lg">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_family_members']; ?></p>
                    <p class="text-sm text-gray-600">Family Members</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm card-mobile">
            <div class="flex items-center">
                <div class="bg-orange-100 p-3 rounded-lg">
                    <i class="fas fa-chart-line text-orange-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900"><?php echo round(($stats['this_week'] / max($stats['total_residences'], 1)) * 100); ?>%</p>
                    <p class="text-sm text-gray-600">Weekly Progress</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-bolt mr-2"></i>Quick Actions
        </h2>
        <div class="grid grid-cols-2 gap-4">
            <a href="add_residence.php" class="btn-mobile bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition duration-200 flex items-center justify-center">
                <i class="fas fa-plus mr-2"></i>
                Add Residence
            </a>
            <a href="residences.php" class="btn-mobile bg-green-600 text-white rounded-xl hover:bg-green-700 transition duration-200 flex items-center justify-center">
                <i class="fas fa-list mr-2"></i>
                View Residences
            </a>
        </div>
    </div>

    <!-- Recent Residences -->
    <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-clock mr-2"></i>Recent Residences
            </h2>
            <a href="residences.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <?php if (!empty($recent_residences)): ?>
        <div class="space-y-3">
            <?php foreach ($recent_residences as $residence): ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex-1">
                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['resident_name']); ?></p>
                    <p class="text-sm text-gray-600">
                        <?php echo htmlspecialchars($residence['house_no']); ?> - 
                        <?php echo htmlspecialchars($residence['village_name']); ?>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500"><?php echo formatDate($residence['created_at']); ?></p>
                    <a href="view_residence.php?id=<?php echo $residence['id']; ?>" class="text-blue-600 hover:text-blue-700 text-sm">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-building text-4xl mb-4"></i>
            <p>No residences collected yet</p>
            <a href="add_residence.php" class="text-blue-600 hover:text-blue-700 font-medium">
                Start collecting data <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Data Collection Tips -->
    <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-xl card-mobile">
        <div class="flex items-start">
            <i class="fas fa-lightbulb text-yellow-600 text-xl mr-3 mt-1"></i>
            <div>
                <h3 class="font-semibold text-yellow-800 mb-2">Data Collection Tips</h3>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• Always verify NIDA numbers with official documents</li>
                    <li>• Take clear photos of important documents</li>
                    <li>• Ensure all required fields are completed</li>
                    <li>• Double-check family member relationships</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
