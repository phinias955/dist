<?php
if (!isset($page_title)) {
    $page_title = 'Dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Residence Register System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#1E40AF'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div id="sidebar" class="bg-white shadow-lg w-64 h-screen flex flex-col transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out fixed lg:static z-50">
            <div class="p-6 border-b flex-shrink-0">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-home text-white"></i>
                    </div>
                    <div class="ml-3">
                        <h1 class="text-lg font-bold text-gray-800">Residence Register</h1>
                        <p class="text-sm text-gray-600">Management System</p>
                    </div>
                </div>
            </div>
            
            <!-- User Info -->
            <div class="p-4 border-b bg-gray-50 flex-shrink-0">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-gray-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-800"><?php echo $_SESSION['user_name']; ?></p>
                        <p class="text-xs text-gray-600"><?php echo getRoleDisplayName($_SESSION['user_role']); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="flex-1 overflow-y-auto">
                <ul class="space-y-1 px-4 py-4">
                    <li>
                        <a href="dashboard.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                    </li>
                    
                    <?php if (canAccessPage('users')): ?>
                    <li>
                        <a href="users.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-users mr-3"></i>
                            Manage Users
                        </a>
                    </li>
                    <?php if (isSuperAdmin() || $_SESSION['user_role'] === 'admin'): ?>
                    <li>
                        <a href="deleted_users.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-red-50 hover:text-red-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'deleted_users.php' ? 'bg-red-50 text-red-700' : ''; ?>">
                            <i class="fas fa-user-times mr-3"></i>
                            Deleted Users
                        </a>
                    </li>
                    <li>
                        <a href="bin_management.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-orange-50 hover:text-orange-700 transition duration-200 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['bin_management.php', 'bin_users.php', 'bin_residences.php', 'bin_family_members.php', 'bin_locations.php']) ? 'bg-orange-50 text-orange-700' : ''; ?>">
                            <i class="fas fa-trash-alt mr-3"></i>
                            Bin Management
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (canAccessPage('residences')): ?>
                    <li>
                        <a href="residences.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'residences.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-building mr-3"></i>
                            Residences
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (canAccessPage('add_residence')): ?>
                    <li>
                        <a href="add_residence.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'add_residence.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-plus-circle mr-3"></i>
                            Add Residence
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (canAccessPage('reports')): ?>
                    <li>
                        <a href="reports_dashboard.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['reports_dashboard.php', 'reports.php', 'detailed_residence_report.php', 'family_members_report.php', 'transfer_report.php', 'statistics_report.php']) ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-chart-bar mr-3"></i>
                            Reports
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (canAccessPage('transfer_approvals')): ?>
                    <li>
                        <a href="transfer_approvals.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'transfer_approvals.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-exchange-alt mr-3"></i>
                            Transfer Approvals
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (canAccessPage('transfer_status')): ?>
                    <li>
                        <a href="transfer_status.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'transfer_status.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-chart-line mr-3"></i>
                            Transfer Status
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (canAccessPage('transferred_out')): ?>
                    <li>
                        <a href="transferred_out.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'transferred_out.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-arrow-right mr-3"></i>
                            Transferred Out
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (canAccessPage('permissions')): ?>
                    <li>
                        <a href="permissions.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'permissions.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-shield-alt mr-3"></i>
                            Role Permissions
                        </a>
                    </li>
                    <li>
                        <a href="user_permissions.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'user_permissions.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-user-shield mr-3"></i>
                            User Permissions
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (canAccessPage('system_settings') || canAccessPage('ward_management') || canAccessPage('village_management')): ?>
                    <li class="pt-4 border-t">
                        <span class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">System Management</span>
                    </li>
                    <?php if (canAccessPage('system_settings')): ?>
                    <li>
                        <a href="system_settings.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'system_settings.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-cog mr-3"></i>
                            System Settings
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (canAccessPage('ward_management')): ?>
                    <li>
                        <a href="ward_management.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'ward_management.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-building mr-3"></i>
                            Ward Management
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (canAccessPage('village_management')): ?>
                    <li>
                        <a href="village_management.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'village_management.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-home mr-3"></i>
                            Street/Village Management
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <li class="pt-4 border-t">
                        <a href="profile.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                            <i class="fas fa-user-cog mr-3"></i>
                            Profile
                        </a>
                    </li>
                    
                    <li>
                        <a href="logout.php" class="flex items-center px-4 py-2 text-red-600 rounded-lg hover:bg-red-50 hover:text-red-700 transition duration-200">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="bg-white shadow-sm border-b px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <!-- Mobile menu button -->
                        <button id="mobile-menu-button" class="lg:hidden mr-4 p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h2 class="text-xl font-semibold text-gray-800"><?php echo $page_title; ?></h2>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600 hidden sm:block">
                            <i class="fas fa-calendar mr-1"></i>
                            <?php echo date('M d, Y'); ?>
                        </span>
                        <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                            <i class="fas fa-bell text-gray-600"></i>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Mobile menu overlay -->
            <div id="mobile-menu-overlay" class="fixed inset-0 bg-gray-600 bg-opacity-75 z-40 lg:hidden hidden"></div>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
 
 