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

// Get residence data with location information
try {
    $stmt = $pdo->prepare("
        SELECT r.*, w.ward_name, w.ward_code, v.village_name, v.village_code
        FROM residences r
        LEFT JOIN wards w ON r.ward_id = w.id
        LEFT JOIN villages v ON r.village_id = v.id
        WHERE r.id = ?
    ");
    $stmt->execute([$residence_id]);
    $residence = $stmt->fetch();
    
    if (!$residence) {
        header('Location: residences.php');
        exit();
    }
    
    // Check if user can access this residence
    if (!isSuperAdmin()) {
        $user_location = getUserLocationInfo();
        $can_access = false;
        
        if ($_SESSION['user_role'] === 'veo') {
            $can_access = ($residence['village_id'] == $user_location['village_id']);
        } elseif ($_SESSION['user_role'] === 'weo' || $_SESSION['user_role'] === 'admin') {
            $can_access = ($residence['ward_id'] == $user_location['ward_id']);
        }
        
        if (!$can_access) {
            header('Location: residences.php');
            exit();
        }
    }
} catch (PDOException $e) {
    header('Location: residences.php');
    exit();
}

// Get accessible wards and villages for the current user
$accessible_wards = getAccessibleWards();
$accessible_villages = getAccessibleVillages();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $house_no = sanitizeInput($_POST['house_no']);
    $resident_name = sanitizeInput($_POST['resident_name']);
    $gender = $_POST['gender'];
    $date_of_birth = $_POST['date_of_birth'];
    $nida_number = sanitizeInput($_POST['nida_number']);
    $phone = sanitizeInput($_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    $occupation = sanitizeInput($_POST['occupation']);
    $ownership = $_POST['ownership'];
    $family_members = (int)$_POST['family_members'];
    $education_level = $_POST['education_level'];
    $employment_status = $_POST['employment_status'];
    $ward_id = (int)$_POST['ward_id'];
    $village_id = (int)$_POST['village_id'];
    $status = $_POST['status'];
    
    // Validate form data
    $validation = validateFormData($_POST, ['house_no', 'resident_name', 'nida_number', 'date_of_birth']);
    
    if (!$validation['valid']) {
        $error = implode(', ', $validation['errors']);
    } elseif ($family_members < 1) {
        $error = 'Family members must be at least 1';
    } elseif (!$ward_id || !$village_id) {
        $error = 'Ward and Street/Village selection are required';
    } elseif (!canAccessWard($ward_id) || !canAccessVillage($village_id)) {
        $error = 'You do not have permission to register residences in the selected location';
    } else {
        // Use cleaned data
        $nida_number = $validation['data']['nida_number'];
        $phone = $validation['data']['phone'] ?? '';
        try {
            // Check if NIDA number already exists in another residence
            $stmt = $pdo->prepare("SELECT id FROM residences WHERE nida_number = ? AND id != ? AND status = 'active'");
            $stmt->execute([$nida_number, $residence_id]);
            if ($stmt->fetch()) {
                $error = 'NIDA number already exists in another residence';
            } else {
                // Update residence
                $stmt = $pdo->prepare("UPDATE residences SET house_no = ?, resident_name = ?, gender = ?, date_of_birth = ?, nida_number = ?, phone = ?, email = ?, occupation = ?, ownership = ?, family_members = ?, education_level = ?, employment_status = ?, ward_id = ?, village_id = ?, status = ? WHERE id = ?");
                $stmt->execute([$house_no, $resident_name, $gender, $date_of_birth, $nida_number, $phone, $email, $occupation, $ownership, $family_members, $education_level, $employment_status, $ward_id, $village_id, $status, $residence_id]);
                
                $message = 'Residence updated successfully';
                
                // Update residence data for display
                $residence['house_no'] = $house_no;
                $residence['resident_name'] = $resident_name;
                $residence['gender'] = $gender;
                $residence['date_of_birth'] = $date_of_birth;
                $residence['nida_number'] = $nida_number;
                $residence['phone'] = $phone;
                $residence['email'] = $email;
                $residence['occupation'] = $occupation;
                $residence['ownership'] = $ownership;
                $residence['family_members'] = $family_members;
                $residence['education_level'] = $education_level;
                $residence['employment_status'] = $employment_status;
                $residence['ward_id'] = $ward_id;
                $residence['village_id'] = $village_id;
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
                        <label for="house_no" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-home mr-2"></i>House Number *
                        </label>
                        <input type="text" id="house_no" name="house_no" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($residence['house_no']); ?>">
                    </div>
                    
                    <div>
                        <label for="resident_name" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user mr-2"></i>Full Name *
                        </label>
                        <input type="text" id="resident_name" name="resident_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($residence['resident_name']); ?>">
                    </div>
                    
                    <div>
                        <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-venus-mars mr-2"></i>Gender *
                        </label>
                        <select id="gender" name="gender" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="male" <?php echo $residence['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $residence['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $residence['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-2"></i>Date of Birth *
                        </label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo $residence['date_of_birth']; ?>">
                    </div>
                    
                    <div>
                        <label for="nida_number" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-id-card mr-2"></i>NIDA Number *
                        </label>
                        <input type="text" id="nida_number" name="nida_number" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($residence['nida_number']); ?>"
                               maxlength="20" placeholder="Enter 20-digit NIDA number">
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-phone mr-2"></i>Phone Number
                        </label>
                        <input type="tel" id="phone" name="phone"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($residence['phone'] ?? ''); ?>"
                               maxlength="10" placeholder="Enter 10-digit phone number">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope mr-2"></i>Email Address
                        </label>
                        <input type="email" id="email" name="email"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($residence['email'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label for="occupation" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-briefcase mr-2"></i>Occupation
                        </label>
                        <input type="text" id="occupation" name="occupation"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($residence['occupation'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Residence Information -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-home mr-2"></i>Residence Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="ownership" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-key mr-2"></i>Ownership *
                        </label>
                        <select id="ownership" name="ownership" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="owner" <?php echo $residence['ownership'] === 'owner' ? 'selected' : ''; ?>>Owner</option>
                            <option value="tenant" <?php echo $residence['ownership'] === 'tenant' ? 'selected' : ''; ?>>Tenant</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="family_members" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-users mr-2"></i>Number of Family Members *
                        </label>
                        <input type="number" id="family_members" name="family_members" min="1" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo $residence['family_members']; ?>">
                    </div>
                    
                    <div>
                        <label for="education_level" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-graduation-cap mr-2"></i>Education Level
                        </label>
                        <select id="education_level" name="education_level"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="none" <?php echo $residence['education_level'] === 'none' ? 'selected' : ''; ?>>No Formal Education</option>
                            <option value="primary" <?php echo $residence['education_level'] === 'primary' ? 'selected' : ''; ?>>Primary</option>
                            <option value="secondary" <?php echo $residence['education_level'] === 'secondary' ? 'selected' : ''; ?>>Secondary</option>
                            <option value="diploma" <?php echo $residence['education_level'] === 'diploma' ? 'selected' : ''; ?>>Diploma</option>
                            <option value="degree" <?php echo $residence['education_level'] === 'degree' ? 'selected' : ''; ?>>Degree</option>
                            <option value="masters" <?php echo $residence['education_level'] === 'masters' ? 'selected' : ''; ?>>Masters</option>
                            <option value="phd" <?php echo $residence['education_level'] === 'phd' ? 'selected' : ''; ?>>PhD</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="employment_status" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-briefcase mr-2"></i>Employment Status
                        </label>
                        <select id="employment_status" name="employment_status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="unemployed" <?php echo $residence['employment_status'] === 'unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                            <option value="employed" <?php echo $residence['employment_status'] === 'employed' ? 'selected' : ''; ?>>Employed</option>
                            <option value="self_employed" <?php echo $residence['employment_status'] === 'self_employed' ? 'selected' : ''; ?>>Self Employed</option>
                            <option value="student" <?php echo $residence['employment_status'] === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="retired" <?php echo $residence['employment_status'] === 'retired' ? 'selected' : ''; ?>>Retired</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Location Information -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-map-marker-alt mr-2"></i>Location Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="ward_id" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-building mr-2"></i>Ward *
                        </label>
                        <select id="ward_id" name="ward_id" required>
                            <option value="">Select Ward</option>
                            <?php foreach ($accessible_wards as $ward): ?>
                                <option value="<?php echo $ward['id']; ?>" 
                                        <?php echo $residence['ward_id'] == $ward['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ward['ward_name'] . ' (' . $ward['ward_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="village_id" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-home mr-2"></i>Street/Village *
                        </label>
                        <select id="village_id" name="village_id" required>
                            <option value="">Select Street/Village</option>
                            <?php 
                            // Filter villages based on current ward
                            $current_ward_villages = array_filter($accessible_villages, function($village) use ($residence) {
                                return $village['ward_id'] == $residence['ward_id'];
                            });
                            foreach ($current_ward_villages as $village): 
                            ?>
                                <option value="<?php echo $village['id']; ?>" 
                                        <?php echo $residence['village_id'] == $village['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($village['village_name'] . ' (' . $village['village_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Status Information -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-info-circle mr-2"></i>Status Information
                </h3>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-toggle-on mr-2"></i>Status *
                    </label>
                    <select id="status" name="status" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="active" <?php echo $residence['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $residence['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="moved" <?php echo $residence['status'] === 'moved' ? 'selected' : ''; ?>>Moved</option>
                        <option value="pending_approval" <?php echo $residence['status'] === 'pending_approval' ? 'selected' : ''; ?>>Pending Approval</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4">
                <a href="residences.php" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition duration-200">
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
// Ward-Village filtering
document.getElementById('ward_id').addEventListener('change', function() {
    const wardId = this.value;
    const villageSelect = document.getElementById('village_id');
    
    // Clear current options
    villageSelect.innerHTML = '<option value="">Select Street/Village</option>';
    
    if (wardId) {
        // Filter villages by selected ward
        const allVillages = <?php echo json_encode($accessible_villages); ?>;
        const wardVillages = allVillages.filter(village => village.ward_id == wardId);
        
        wardVillages.forEach(village => {
            const option = document.createElement('option');
            option.value = village.id;
            option.textContent = village.village_name + ' (' + village.village_code + ')';
            villageSelect.appendChild(option);
        });
    }
});

// Form validation
document.getElementById('editResidenceForm').addEventListener('submit', function(e) {
    const wardId = document.getElementById('ward_id').value;
    const villageId = document.getElementById('village_id').value;
    const familyMembers = document.getElementById('family_members').value;
    
    if (!wardId || !villageId) {
        e.preventDefault();
        alert('Please select both Ward and Street/Village');
        return false;
    }
    
    if (familyMembers < 1) {
        e.preventDefault();
        alert('Family members must be at least 1');
        return false;
    }
});
</script>

<script src="js/validation.js"></script>

<?php include 'includes/footer.php'; ?>