<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['user_role']));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            // First check if user exists (regardless of active status)
            $stmt = $pdo->prepare("SELECT id, full_name, username, password, role, nida_number, is_active FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Check if account is active
                if (!$user['is_active']) {
                    $error = 'Account is locked. Please contact your Ward or Village Administrator for assistance.';
                } elseif (verifyPassword($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['nida_number'] = $user['nida_number'];
                    
                    header('Location: ' . getDashboardUrl($user['role']));
                    exit();
                } else {
                    $error = 'Invalid username or password';
                }
            } else {
                $error = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residence Register System - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <div class="text-center mb-8">
            <div class="mx-auto w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-home text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Residence Register</h1>
            <p class="text-gray-600 mt-2">Sign in to your account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error; ?>
                <?php if (strpos($error, 'Account is locked') !== false): ?>
                    <div class="mt-2 text-sm">
                        <p class="font-medium">Contact Information:</p>
                        <ul class="list-disc list-inside mt-1 space-y-1">
                            <li>Ward Executive Officer (WEO)</li>
                            <li>Village Executive Officer (VEO)</li>
                            <li>System Administrator</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-user mr-2"></i>Username
                </label>
                <input type="text" id="username" name="username" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-lock mr-2"></i>Password
                </label>
                <input type="password" id="password" name="password" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                <i class="fas fa-sign-in-alt mr-2"></i>Sign In
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Default credentials: <br>
                <strong>Super Admin:</strong> superadmin / password<br>
                <strong>Admin:</strong> admin / password
            </p>
        </div>
    </div>
</body>
</html>
