<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requirePermission('view_reports');

$page_title = 'Reports';

$error = '';

// Get statistics
try {
    // Total users by role
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role");
    $users_by_role = $stmt->fetchAll();
    
    // Total residences by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM residences GROUP BY status");
    $residences_by_status = $stmt->fetchAll();
    
    // Monthly registrations (last 6 months)
    $stmt = $pdo->query("SELECT 
        DATE_FORMAT(registered_at, '%Y-%m') as month,
        COUNT(*) as count 
        FROM residences 
        WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(registered_at, '%Y-%m')
        ORDER BY month");
    $monthly_registrations = $stmt->fetchAll();
    
    // Top registering users
    $stmt = $pdo->query("SELECT 
        u.full_name,
        u.role,
        COUNT(r.id) as total_registrations
        FROM users u
        LEFT JOIN residences r ON u.id = r.registered_by
        WHERE u.is_active = 1
        GROUP BY u.id
        ORDER BY total_registrations DESC
        LIMIT 10");
    $top_users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Database error occurred';
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">System Reports</h1>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded alert">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Users</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?php echo array_sum(array_column($users_by_role, 'count')); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-building text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Residences</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?php echo array_sum(array_column($residences_by_status, 'count')); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">This Month</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?php 
                        $current_month = date('Y-m');
                        $this_month = array_filter($monthly_registrations, function($item) use ($current_month) {
                            return $item['month'] === $current_month;
                        });
                        echo !empty($this_month) ? reset($this_month)['count'] : 0;
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Active Residences</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?php 
                        $active = array_filter($residences_by_status, function($item) {
                            return $item['status'] === 'active';
                        });
                        echo !empty($active) ? reset($active)['count'] : 0;
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Users by Role -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Users by Role</h3>
            <div class="space-y-3">
                <?php foreach ($users_by_role as $role_data): ?>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">
                        <?php echo getRoleDisplayName($role_data['role']); ?>
                    </span>
                    <div class="flex items-center">
                        <div class="w-32 bg-gray-200 rounded-full h-2 mr-3">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo ($role_data['count'] / array_sum(array_column($users_by_role, 'count'))) * 100; ?>%"></div>
                        </div>
                        <span class="text-sm font-bold text-gray-900"><?php echo $role_data['count']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Residences by Status -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Residences by Status</h3>
            <div class="space-y-3">
                <?php foreach ($residences_by_status as $status_data): ?>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">
                        <?php echo ucfirst($status_data['status']); ?>
                    </span>
                    <div class="flex items-center">
                        <div class="w-32 bg-gray-200 rounded-full h-2 mr-3">
                            <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo ($status_data['count'] / array_sum(array_column($residences_by_status, 'count'))) * 100; ?>%"></div>
                        </div>
                        <span class="text-sm font-bold text-gray-900"><?php echo $status_data['count']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Top Users -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Registering Users</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registrations</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($top_users as $user): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?php echo getRoleDisplayName($user['role']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $user['total_registrations']; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
