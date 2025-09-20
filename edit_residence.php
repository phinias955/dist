<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Edit Residence';

$message = '';
$error = '';

// Get residence ID from URL
$residence_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$residence_id) {
    header('Location: residences.php');
    exit();
}

// Get residence data
try {
    if (canViewAllData()) {
        $stmt = $pdo->prepare("SELECT * FROM residences WHERE id = ?");
        $stmt->execute([$residence_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM residences WHERE id = ? AND registered_by = ?");
        $stmt->execute([$residence_id, $_SESSION['user_id']]);
    }
    
    $residence = $stmt->fetch();
    
    if (!$residence) {
        header('Location: residences.php');
        exit();
    }
} catch (PDOException $e) {
    header('Location: residences.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resident_name = sanitizeInput($_POST['resident_name']);
    $nida_number = sanitizeInput($_POST['nida_number']);
    $address = sanitizeInput($_POST['address']);
    $phone = sanitizeInput($_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    $occupation = sanitizeInput($_POST['occupation']);
    $family_members = (int)$_POST['family_members'];
    $status = $_POST['status'];
    
    // Validation
    if (empty($resident_name) || empty($nida_number) || empty($address)) {
        $error = 'Name, NIDA number, and address are required';
    } elseif ($family_members < 1) {
        $error = 'Family members must be at least 1';
    } elseif (!in_array($status, ['active', 'inactive', 'moved'])) {
        $error = 'Invalid status selected';
    } else {
        try {
            // Check if NIDA number is already taken by another residence
            $stmt = $pdo->prepare("SELECT id FROM residences WHERE nida_number = ? AND id != ?");
            $stmt->execute([$nida_number, $residence_id]);
            if ($stmt->fetch()) {
                $error = 'NIDA number already exists in the system';
            } else {
                // Update residence
                $stmt = $pdo->prepare("UPDATE residences SET resident_name = ?, nida_number = ?, address = ?, phone = ?, email = ?, occupation = ?, family_members = ?, status = ? WHERE id = ?");
                $stmt->execute([$resident_name, $nida_number, $address, $phone, $email, $occupation, $family_members, $status, $residence_id]);
                
                $message = 'Residence updated successfully';
                
                // Update residence data for display
                $residence['resident_name'] = $resident_name;
                $residence['nida_number'] = $nida_number;
                $residence['address'] = $address;
                $residence['phone'] = $phone;
                $residence['email'] = $email;
                $residence['occupation'] = $occupation;
                $residence['family_members'] = $family_members;
                $residence['status'] = $status;
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred';
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Residence</h1>
        
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
        
        <form method="POST" class="space-y-6" id="editResidenceForm">
            <!-- Personal Information -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-user mr-2"></i>Personal Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="resident_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Full Name *
                        </label>
                        <input type="text" id="resident_name" name="resident_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($residence['resident_name']); ?>">
                    </div>
                    
                    <div>
                        <label for="nida_number" class="block text-sm font-medium text-gray-700 mb-2">
                            NIDA Number *
                        </label>
                        <input type="text" id="nida_number" name="nida_number" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($residence['nida_number']); ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Phone Number
                        </label>
                        <input type="tel" id="phone" name="phone"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($residence['phone'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                        </label>
                        <input type="email" id="email" name="email"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($residence['email'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label for="occupation" class="block text-sm font-medium text-gray-700 mb-2">
                            Occupation
                        </label>
                        <input type="text" id="occupation" name="occupation"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($residence['occupation'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label for="family_members" class="block text-sm font-medium text-gray-700 mb-2">
                            Family Members *
                        </label>
                        <input type="number" id="family_members" name="family_members" min="1" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo $residence['family_members']; ?>">
                    </div>
                </div>
            </div>
            
            <!-- Address Information -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-map-marker-alt mr-2"></i>Address Information
                </h3>
                
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                        Full Address *
                    </label>
                    <textarea id="address" name="address" rows="3" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Enter complete address including street, ward, district, region..."><?php echo htmlspecialchars($residence['address']); ?></textarea>
                </div>
            </div>
            
            <!-- Status Information -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-info-circle mr-2"></i>Status Information
                </h3>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        Status *
                    </label>
                    <select id="status" name="status" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="active" <?php echo $residence['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $residence['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="moved" <?php echo $residence['status'] === 'moved' ? 'selected' : ''; ?>>Moved</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4">
                <a href="view_residence.php?id=<?php echo $residence['id']; ?>" 
                   class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-save mr-2"></i>Update Residence
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('editResidenceForm').addEventListener('submit', function(e) {
    const familyMembers = document.getElementById('family_members').value;
    
    if (familyMembers < 1) {
        e.preventDefault();
        alert('Family members must be at least 1');
        return false;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
