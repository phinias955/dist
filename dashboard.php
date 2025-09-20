<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Dashboard';

// Get statistics based on user role
$stats = [];

try {
    if (canViewAllData()) {
        // Get total users
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
        $stats['total_users'] = $stmt->fetch()['total'];
        
        // Get total residences
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM residences WHERE status = 'active'");
        $stats['total_residences'] = $stmt->fetch()['total'];
        
        // Get recent registrations
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM residences WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_registrations'] = $stmt->fetch()['total'];
        
        // Get users by role
        $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role");
        $stats['users_by_role'] = $stmt->fetchAll();
    } else {
        // For WEO and VEO, only show their own data
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM residences WHERE registered_by = ? AND status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $stats['total_residences'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM residences WHERE registered_by = ? AND registered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute([$_SESSION['user_id']]);
        $stats['recent_registrations'] = $stmt->fetch()['total'];
    }
    
    // Get recent residences based on user location access
    $all_residences = getAccessibleResidences();
    $recent_residences = array_slice($all_residences, 0, 5);
    
} catch (PDOException $e) {
    $error = "Database error occurred";
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Welcome Message -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-6 rounded-lg shadow-lg">
        <h1 class="text-2xl font-bold mb-2">Welcome back, <?php echo $_SESSION['user_name']; ?>!</h1>
        <p class="text-blue-100">You are logged in as <?php echo getRoleDisplayName($_SESSION['user_role']); ?></p>
    </div>
    
    <!-- User Location Information -->
    <?php 
    $user_location = getUserLocationInfo();
    if ($user_location): 
    ?>
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-map-marker-alt mr-2"></i>Your Assignment
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="flex items-center p-4 bg-blue-50 rounded-lg">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <i class="fas fa-building text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Assigned Ward</p>
                    <p class="text-lg font-semibold text-gray-900">
                        <?php echo htmlspecialchars($user_location['ward_name']); ?>
                        <?php if (!empty($user_location['ward_code'])): ?>
                        <span class="text-sm text-gray-500">(<?php echo htmlspecialchars($user_location['ward_code']); ?>)</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <?php if ($user_location['village_name']): ?>
            <div class="flex items-center p-4 bg-green-50 rounded-lg">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                    <i class="fas fa-home text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Assigned Street/Village</p>
                    <p class="text-lg font-semibold text-gray-900">
                        <?php echo htmlspecialchars($user_location['village_name']); ?>
                        <?php if (!empty($user_location['village_code'])): ?>
                        <span class="text-sm text-gray-500">(<?php echo htmlspecialchars($user_location['village_code']); ?>)</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
            <p class="text-sm text-gray-600">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>Your Access Level:</strong> 
                <?php 
                switch($_SESSION['user_role']) {
                    case 'super_admin':
                        echo 'Full system access - can manage all wards and villages';
                        break;
                    case 'admin':
                        echo 'Ward-level access - can manage data for your assigned ward only';
                        break;
                    case 'weo':
                        echo 'Ward-level access - can view all streets/villages in your assigned ward';
                        break;
                    case 'veo':
                        echo 'Village-level access - can manage data for your assigned street/village only';
                        break;
                }
                ?>
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php if (canViewAllData()): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Users</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_users'] ?? 0; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-building text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Residences</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_residences'] ?? 0; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">This Week</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['recent_registrations'] ?? 0; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Active Status</p>
                    <p class="text-2xl font-bold text-gray-900">Active</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="add_residence.php" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition duration-200">
                <i class="fas fa-plus-circle text-blue-600 text-xl mr-3"></i>
                <span class="text-blue-800 font-medium">Add Residence</span>
            </a>
            
            <a href="residences.php" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition duration-200">
                <i class="fas fa-list text-green-600 text-xl mr-3"></i>
                <span class="text-green-800 font-medium">View Residences</span>
            </a>
            
            <?php if (canManageUsers()): ?>
            <a href="users.php" class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition duration-200">
                <i class="fas fa-users text-purple-600 text-xl mr-3"></i>
                <span class="text-purple-800 font-medium">Manage Users</span>
            </a>
            <?php endif; ?>
            
            <a href="profile.php" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
                <i class="fas fa-user-cog text-gray-600 text-xl mr-3"></i>
                <span class="text-gray-800 font-medium">Profile Settings</span>
            </a>
        </div>
    </div>
    
    <!-- Recent Residences -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Residences</h3>
        <?php if (empty($recent_residences)): ?>
            <p class="text-gray-500 text-center py-8">No residences registered yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resident Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIDA Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ward</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Street/Village</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                            <?php if (canViewAllData()): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">By</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_residences as $residence): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($residence['resident_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($residence['nida_number']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($residence['ward_name'] ?? 'N/A'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($residence['village_name'] ?? 'N/A'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo formatDate($residence['registered_at']); ?>
                            </td>
                            <?php if (canViewAllData()): ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($residence['registered_by_name'] ?? 'Unknown'); ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
