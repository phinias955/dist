<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Profile';

$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name']);
    $username = sanitizeInput($_POST['username']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($full_name) || empty($username)) {
        $error = 'Name and username are required';
    } else {
        try {
            // Check if username is already taken by another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error = 'Username already taken';
            } else {
                // If password change is requested
                if (!empty($new_password)) {
                    if (empty($current_password)) {
                        $error = 'Current password is required to change password';
                    } else {
                        // Verify current password
                        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user = $stmt->fetch();
                        
                        if (!verifyPassword($current_password, $user['password'])) {
                            $error = 'Current password is incorrect';
                        } elseif ($new_password !== $confirm_password) {
                            $error = 'New passwords do not match';
                        } elseif (strlen($new_password) < 6) {
                            $error = 'New password must be at least 6 characters long';
                        } else {
                            // Update with new password
                            $hashed_password = hashPassword($new_password);
                            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, password = ? WHERE id = ?");
                            $stmt->execute([$full_name, $username, $hashed_password, $_SESSION['user_id']]);
                            
                            $_SESSION['user_name'] = $full_name;
                            $_SESSION['username'] = $username;
                            $message = 'Profile updated successfully';
                        }
                    }
                } else {
                    // Update without password change
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ? WHERE id = ?");
                    $stmt->execute([$full_name, $username, $_SESSION['user_id']]);
                    
                    $_SESSION['user_name'] = $full_name;
                    $_SESSION['username'] = $username;
                    $message = 'Profile updated successfully';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred';
        }
    }
}

// Get current user data
try {
    $stmt = $pdo->prepare("SELECT full_name, username, role, nida_number, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Database error occurred';
}

include 'includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Profile Settings</h1>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 alert">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6" id="profileForm">
            <!-- Personal Information -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-user mr-2"></i>Personal Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Full Name *
                        </label>
                        <input type="text" id="full_name" name="full_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($user['full_name']); ?>">
                    </div>
                    
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username *
                        </label>
                        <input type="text" id="username" name="username" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($user['username']); ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Role
                        </label>
                        <div class="px-3 py-2 bg-gray-100 rounded-md text-gray-700">
                            <?php echo getRoleDisplayName($user['role']); ?>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            NIDA Number
                        </label>
                        <div class="px-3 py-2 bg-gray-100 rounded-md text-gray-700">
                            <?php echo htmlspecialchars($user['nida_number']); ?>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Member Since
                    </label>
                    <div class="px-3 py-2 bg-gray-100 rounded-md text-gray-700">
                        <?php echo formatDate($user['created_at']); ?>
                    </div>
                </div>
            </div>
            
            <!-- Password Change -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-lock mr-2"></i>Change Password
                </h3>
                <p class="text-sm text-gray-600 mb-4">Leave password fields empty if you don't want to change your password.</p>
                
                <div class="space-y-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Current Password
                        </label>
                        <input type="password" id="current_password" name="current_password"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                New Password
                            </label>
                            <input type="password" id="new_password" name="new_password"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm New Password
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-save mr-2"></i>Update Profile
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const currentPassword = document.getElementById('current_password').value;
    
    // If new password is provided, validate
    if (newPassword) {
        if (!currentPassword) {
            e.preventDefault();
            alert('Current password is required to change password');
            return false;
        }
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New passwords do not match');
            return false;
        }
        
        if (newPassword.length < 6) {
            e.preventDefault();
            alert('New password must be at least 6 characters long');
            return false;
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
