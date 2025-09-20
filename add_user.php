<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user has permission to manage users
if (!canAccessPage('users')) {
    header('Location: unauthorized.php');
    exit();
}

$page_title = 'Add New User';

$message = '';
$error = '';

// Get accessible wards and villages for assignment based on user role
$wards = getAccessibleWards();
$villages = getAccessibleVillages();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name']);
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $nida_number = sanitizeInput($_POST['nida_number']);
    $assigned_ward_id = !empty($_POST['assigned_ward_id']) ? (int)$_POST['assigned_ward_id'] : null;
    $assigned_village_id = !empty($_POST['assigned_village_id']) ? (int)$_POST['assigned_village_id'] : null;
    
    // Validation
    if (empty($full_name) || empty($username) || empty($password) || empty($role) || empty($nida_number)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif (!in_array($role, ['admin', 'weo', 'veo'])) {
        $error = 'Invalid role selected';
    } elseif ($role === 'veo' && (!$assigned_ward_id || !$assigned_village_id)) {
        $error = 'Both Ward and Street/Village assignment are required for VEO role';
    } elseif (in_array($role, ['admin', 'weo']) && !$assigned_ward_id) {
        $error = 'Ward assignment is required for Admin and WEO roles';
    } else {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists';
            } else {
                // Check if NIDA number already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE nida_number = ?");
                $stmt->execute([$nida_number]);
                if ($stmt->fetch()) {
                    $error = 'NIDA number already exists';
                } else {
                    // Insert new user
                    $hashed_password = hashPassword($password);
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, role, nida_number, assigned_ward_id, assigned_village_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$full_name, $username, $hashed_password, $role, $nida_number, $assigned_ward_id, $assigned_village_id]);
                    
                    $message = 'User created successfully';
                    // Clear form
                    $full_name = $username = $nida_number = '';
                    $role = '';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred';
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Add New User</h1>
        
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
        
        <form method="POST" class="space-y-6" id="userForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2"></i>Full Name *
                    </label>
                    <input type="text" id="full_name" name="full_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                </div>
                
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-at mr-2"></i>Username *
                    </label>
                    <input type="text" id="username" name="username" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2"></i>Password *
                    </label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2"></i>Confirm Password *
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user-tag mr-2"></i>Role *
                    </label>
                    <select id="role" name="role" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select Role</option>
                        <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                        <option value="weo" <?php echo (isset($_POST['role']) && $_POST['role'] === 'weo') ? 'selected' : ''; ?>>Ward Executive Officer (WEO)</option>
                        <option value="veo" <?php echo (isset($_POST['role']) && $_POST['role'] === 'veo') ? 'selected' : ''; ?>>Village Executive Officer (VEO)</option>
                    </select>
                </div>
                
                <div>
                    <label for="nida_number" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-id-card mr-2"></i>NIDA Number *
                    </label>
                    <input type="text" id="nida_number" name="nida_number" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo isset($_POST['nida_number']) ? htmlspecialchars($_POST['nida_number']) : ''; ?>">
                </div>
            </div>
            
            <!-- Location Assignment -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-map-marker-alt mr-2"></i>Location Assignment
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="assigned_ward_id" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-building mr-2"></i>Assigned Ward *
                        </label>
                        <select id="assigned_ward_id" name="assigned_ward_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select Ward</option>
                            <?php foreach ($wards as $ward): ?>
                                <option value="<?php echo $ward['id']; ?>" 
                                        <?php echo (isset($_POST['assigned_ward_id']) && $_POST['assigned_ward_id'] == $ward['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ward['ward_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="village_assignment_field" class="hidden">
                        <label for="assigned_village_id" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-home mr-2"></i>Assigned Street/Village *
                        </label>
                        <select id="assigned_village_id" name="assigned_village_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select Street/Village</option>
                            <?php foreach ($villages as $village): ?>
                                <option value="<?php echo $village['id']; ?>" 
                                        data-ward-id="<?php echo $village['ward_id']; ?>"
                                        <?php echo (isset($_POST['assigned_village_id']) && $_POST['assigned_village_id'] == $village['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($village['village_name'] . ' (' . $village['ward_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Required for VEO role</p>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4">
                <a href="users.php" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-save mr-2"></i>Create User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize form state on page load
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const villageField = document.getElementById('village_assignment_field');
    
    // Check if a role is already selected (form submission with errors)
    if (roleSelect.value === 'veo') {
        villageField.classList.remove('hidden');
    } else {
        villageField.classList.add('hidden');
    }
});

// Ward-Village relationship handling
document.getElementById('assigned_ward_id').addEventListener('change', function() {
    const selectedWardId = this.value;
    const villageSelect = document.getElementById('assigned_village_id');
    
    // Clear current selection
    villageSelect.innerHTML = '<option value="">Select Street/Village (Optional for Admin/WEO)</option>';
    
    // Filter villages by selected ward
    const allVillages = <?php echo json_encode($villages); ?>;
    allVillages.forEach(village => {
        if (village.ward_id == selectedWardId) {
            const option = document.createElement('option');
            option.value = village.id;
            option.textContent = village.village_name;
            option.setAttribute('data-ward-id', village.ward_id);
            villageSelect.appendChild(option);
        }
    });
});

// Role-based validation and field visibility
document.getElementById('role').addEventListener('change', function() {
    const role = this.value;
    const wardSelect = document.getElementById('assigned_ward_id');
    const villageField = document.getElementById('village_assignment_field');
    const villageSelect = document.getElementById('assigned_village_id');
    
    if (role === 'veo') {
        // Show village assignment field for VEO
        villageField.classList.remove('hidden');
        wardSelect.required = true;
        villageSelect.required = true;
    } else {
        // Hide village assignment field for Admin/WEO
        villageField.classList.add('hidden');
        wardSelect.required = true;
        villageSelect.required = false;
        villageSelect.value = ''; // Clear selection when hidden
    }
});

document.getElementById('userForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const role = document.getElementById('role').value;
    const wardId = document.getElementById('assigned_ward_id').value;
    const villageId = document.getElementById('assigned_village_id').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match');
        return false;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long');
        return false;
    }
    
    if (role === 'veo') {
        if (!wardId || !villageId) {
            e.preventDefault();
            alert('Both Ward and Street/Village assignment are required for VEO role');
            return false;
        }
    } else {
        if (!wardId) {
            e.preventDefault();
            alert('Ward assignment is required for Admin and WEO roles');
            return false;
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
