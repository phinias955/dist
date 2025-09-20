<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Collector Login</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom Mobile Styles -->
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        
        .input-mobile {
            font-size: 16px;
            padding: 16px;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .input-mobile:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-mobile {
            min-height: 48px;
            padding: 16px;
            font-size: 16px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-mobile:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-8 text-center">
                <div class="mb-4">
                    <i class="fas fa-mobile-alt text-4xl mb-2"></i>
                </div>
                <h1 class="text-2xl font-bold mb-2">Data Collection</h1>
                <p class="text-blue-100">Mobile Data Collection System</p>
            </div>
            
            <!-- Login Form -->
            <div class="p-8">
                <?php
                session_start();
                require_once '../config/database.php';
                require_once '../includes/functions.php';
                
                $error = '';
                $message = '';
                
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $username = trim($_POST['username'] ?? '');
                    $password = $_POST['password'] ?? '';
                    
                    if (empty($username) || empty($password)) {
                        $error = "Please enter both username and password.";
                    } else {
                        try {
                            $stmt = $pdo->prepare("
                                SELECT id, full_name, username, password, role, is_active, assigned_ward_id, assigned_village_id
                                FROM users 
                                WHERE username = ? AND role = 'data_collector'
                            ");
                            $stmt->execute([$username]);
                            $user = $stmt->fetch();
                            
                            if ($user && password_verify($password, $user['password'])) {
                                if (!$user['is_active']) {
                                    $error = "Your account is locked. Please contact your supervisor.";
                                } else {
                                    // Set session variables
                                    $_SESSION['user_id'] = $user['id'];
                                    $_SESSION['full_name'] = $user['full_name'];
                                    $_SESSION['username'] = $user['username'];
                                    $_SESSION['user_role'] = $user['role'];
                                    $_SESSION['assigned_ward_id'] = $user['assigned_ward_id'];
                                    $_SESSION['assigned_village_id'] = $user['assigned_village_id'];
                                    
                                    // Redirect to dashboard
                                    header('Location: dashboard.php');
                                    exit();
                                }
                            } else {
                                $error = "Invalid username or password.";
                            }
                        } catch (PDOException $e) {
                            $error = "Login error. Please try again.";
                        }
                    }
                }
                ?>
                
                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user mr-2"></i>Username
                        </label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                               class="input-mobile w-full" placeholder="Enter your username" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2"></i>Password
                        </label>
                        <input type="password" name="password" 
                               class="input-mobile w-full" placeholder="Enter your password" required>
                    </div>
                    
                    <button type="submit" class="btn-mobile w-full bg-blue-600 text-white hover:bg-blue-700">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                    </button>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        Data Collection System
                    </p>
                    <p class="text-xs text-gray-500 mt-2">
                        For data collectors only
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Back to Main System -->
    <div class="fixed bottom-4 left-4 right-4 text-center">
        <a href="../index.php" class="inline-flex items-center text-white text-sm bg-black bg-opacity-20 px-4 py-2 rounded-full hover:bg-opacity-30 transition duration-200">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Main System
        </a>
    </div>
</body>
</html>
