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
        <div class="bg-white shadow-lg w-64 min-h-screen">
            <div class="p-6 border-b">
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
            <div class="p-4 border-b bg-gray-50">
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
            <nav class="mt-4">
                <ul class="space-y-1 px-4">
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
                        <a href="reports.php" class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
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
                    <h2 class="text-xl font-semibold text-gray-800"><?php echo $page_title; ?></h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">
                            <i class="fas fa-calendar mr-1"></i>
                            <?php echo date('M d, Y'); ?>
                        </span>
                        <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                            <i class="fas fa-bell text-gray-600"></i>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
