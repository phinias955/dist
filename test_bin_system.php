<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Only for testing - remove in production
if (!isSuperAdmin()) {
    die('This is a test page. Only Super Admin can access.');
}

$page_title = 'Test Bin System';

// Test moving a user to bin
if (isset($_GET['test_user'])) {
    $user_id = (int)$_GET['test_user'];
    
    if (moveUserToBin($user_id, $_SESSION['user_id'], 'deleted')) {
        echo "<div style='background: green; color: white; padding: 10px; margin: 10px 0;'>User moved to bin successfully!</div>";
    } else {
        echo "<div style='background: red; color: white; padding: 10px; margin: 10px 0;'>Failed to move user to bin!</div>";
    }
}

// Test moving a residence to bin
if (isset($_GET['test_residence'])) {
    $residence_id = (int)$_GET['test_residence'];
    
    if (moveResidenceToBin($residence_id, $_SESSION['user_id'], 'deleted')) {
        echo "<div style='background: green; color: white; padding: 10px; margin: 10px 0;'>Residence moved to bin successfully!</div>";
    } else {
        echo "<div style='background: red; color: white; padding: 10px; margin: 10px 0;'>Failed to move residence to bin!</div>";
    }
}

// Get some test data
try {
    // Get first few users
    $stmt = $pdo->query("SELECT id, full_name, username, role FROM users WHERE id > 1 LIMIT 5");
    $test_users = $stmt->fetchAll();
    
    // Get first few residences
    $stmt = $pdo->query("SELECT id, house_no, resident_name FROM residences LIMIT 5");
    $test_residences = $stmt->fetchAll();
    
    // Get bin statistics
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bin_users");
    $bin_users_count = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bin_residences");
    $bin_residences_count = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bin_family_members");
    $bin_family_members_count = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $test_users = $test_residences = [];
    $bin_users_count = $bin_residences_count = $bin_family_members_count = 0;
}

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="space-y-6">
        <h1 class="text-2xl font-bold text-gray-800">Test Bin System</h1>
        
        <!-- Bin Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800">Bin Users</h3>
                <p class="text-3xl font-bold text-red-600"><?php echo $bin_users_count; ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800">Bin Residences</h3>
                <p class="text-3xl font-bold text-orange-600"><?php echo $bin_residences_count; ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800">Bin Family Members</h3>
                <p class="text-3xl font-bold text-yellow-600"><?php echo $bin_family_members_count; ?></p>
            </div>
        </div>
        
        <!-- Test Users -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Test Users (Click to move to bin)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($test_users as $user): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                    <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p class="text-sm text-gray-600">@<?php echo htmlspecialchars($user['username']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo ucfirst($user['role']); ?></p>
                    <a href="?test_user=<?php echo $user['id']; ?>" 
                       class="inline-block mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                        Move to Bin
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Test Residences -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Test Residences (Click to move to bin)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($test_residences as $residence): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                    <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($residence['house_no']); ?></h4>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($residence['resident_name']); ?></p>
                    <a href="?test_residence=<?php echo $residence['id']; ?>" 
                       class="inline-block mt-2 bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600">
                        Move to Bin
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Links to Bin Management -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Bin Management</h3>
            <div class="flex space-x-4">
                <a href="bin_management.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    View Bin Management
                </a>
                <a href="bin_users.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                    View Bin Users
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
