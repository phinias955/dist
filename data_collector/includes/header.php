<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Data Collection System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom Mobile Styles -->
    <style>
        /* Mobile-first responsive design */
        @media (max-width: 768px) {
            .mobile-menu {
                position: fixed;
                top: 0;
                left: -100%;
                width: 80%;
                height: 100vh;
                background: white;
                z-index: 1000;
                transition: left 0.3s ease;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }
            
            .mobile-menu.open {
                left: 0;
            }
            
            .mobile-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 999;
                display: none;
            }
            
            .mobile-overlay.show {
                display: block;
            }
            
            .mobile-header {
                position: sticky;
                top: 0;
                z-index: 100;
                background: white;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
        }
        
        /* Touch-friendly buttons */
        .btn-mobile {
            min-height: 44px;
            min-width: 44px;
            padding: 12px 16px;
            font-size: 16px;
        }
        
        /* Form inputs optimized for mobile */
        .input-mobile {
            font-size: 16px;
            padding: 12px 16px;
            border-radius: 8px;
        }
        
        /* Card spacing for mobile */
        .card-mobile {
            margin-bottom: 16px;
            border-radius: 12px;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Mobile Header -->
    <div class="mobile-header lg:hidden">
        <div class="flex items-center justify-between p-4">
            <div class="flex items-center">
                <button id="mobile-menu-btn" class="btn-mobile text-gray-700 hover:text-blue-600">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="ml-3 text-lg font-semibold text-gray-800">
                    <?php echo isset($page_title) ? $page_title : 'Data Collection'; ?>
                </h1>
            </div>
            <div class="flex items-center space-x-2">
                <a href="profile.php" class="btn-mobile text-gray-700 hover:text-blue-600">
                    <i class="fas fa-user text-lg"></i>
                </a>
                <a href="../logout.php" class="btn-mobile text-red-600 hover:text-red-700">
                    <i class="fas fa-sign-out-alt text-lg"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div id="mobile-overlay" class="mobile-overlay"></div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="mobile-menu">
        <div class="p-4">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-gray-800">Menu</h2>
                <button id="close-mobile-menu" class="btn-mobile text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="space-y-2">
                <a href="dashboard.php" class="flex items-center p-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition duration-200">
                    <i class="fas fa-tachometer-alt mr-3 text-lg"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="residences.php" class="flex items-center p-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition duration-200">
                    <i class="fas fa-building mr-3 text-lg"></i>
                    <span>Residences</span>
                </a>
                
                <a href="add_residence.php" class="flex items-center p-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition duration-200">
                    <i class="fas fa-plus-circle mr-3 text-lg"></i>
                    <span>Add Residence</span>
                </a>
                
                <a href="family_members.php" class="flex items-center p-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition duration-200">
                    <i class="fas fa-users mr-3 text-lg"></i>
                    <span>Family Members</span>
                </a>
                
                <a href="profile.php" class="flex items-center p-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition duration-200">
                    <i class="fas fa-user mr-3 text-lg"></i>
                    <span>Profile</span>
                </a>
                
                <a href="../logout.php" class="flex items-center p-3 text-red-600 hover:bg-red-50 rounded-lg transition duration-200">
                    <i class="fas fa-sign-out-alt mr-3 text-lg"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Desktop Sidebar (hidden on mobile) -->
    <div class="hidden lg:flex lg:flex-col lg:w-64 lg:bg-white lg:shadow-lg lg:min-h-screen">
        <div class="p-6">
            <h1 class="text-xl font-bold text-gray-800 mb-2">
                <i class="fas fa-mobile-alt mr-2"></i>Data Collection
            </h1>
            <p class="text-sm text-gray-600">Mobile Data Collection System</p>
        </div>
        
        <nav class="flex-1 px-4 pb-4">
            <ul class="space-y-2">
                <li>
                    <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                        <i class="fas fa-tachometer-alt mr-3"></i>
                        Dashboard
                    </a>
                </li>
                
                <li>
                    <a href="residences.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'residences.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                        <i class="fas fa-building mr-3"></i>
                        Residences
                    </a>
                </li>
                
                <li>
                    <a href="add_residence.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'add_residence.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                        <i class="fas fa-plus-circle mr-3"></i>
                        Add Residence
                    </a>
                </li>
                
                <li>
                    <a href="family_members.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'family_members.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                        <i class="fas fa-users mr-3"></i>
                        Family Members
                    </a>
                </li>
                
                <li class="pt-4 border-t">
                    <a href="profile.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'bg-blue-50 text-blue-700' : ''; ?>">
                        <i class="fas fa-user mr-3"></i>
                        Profile
                    </a>
                </li>
                
                <li>
                    <a href="../logout.php" class="flex items-center px-4 py-3 text-red-600 rounded-lg hover:bg-red-50 transition duration-200">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        Logout
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="lg:ml-64">
        <div class="p-4 lg:p-8">
            <!-- Content will be inserted here -->
