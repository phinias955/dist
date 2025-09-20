<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is data collector
if ($_SESSION['user_role'] !== 'data_collector') {
    header('Location: ../unauthorized.php');
    exit();
}

$page_title = 'Add Residence';

$message = '';
$error = '';

// Get user location info
$user_location = getUserLocationInfo();

// Get wards and villages for dropdowns
$wards = [];
$villages = [];

try {
    $stmt = $pdo->query("SELECT id, ward_name FROM wards ORDER BY ward_name");
    $wards = $stmt->fetchAll();
    
    if ($user_location && $user_location['ward_id']) {
        $stmt = $pdo->prepare("SELECT id, village_name FROM villages WHERE ward_id = ? ORDER BY village_name");
        $stmt->execute([$user_location['ward_id']]);
        $villages = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = "Error loading location data: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resident_name = trim($_POST['resident_name'] ?? '');
    $house_no = trim($_POST['house_no'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $nida_number = trim($_POST['nida_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $ownership = $_POST['ownership'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $education_level = $_POST['education_level'] ?? '';
    $employment_status = $_POST['employment_status'] ?? '';
    $ward_id = (int)($_POST['ward_id'] ?? 0);
    $village_id = (int)($_POST['village_id'] ?? 0);
    
    // Validation
    if (empty($resident_name)) {
        $error = "Resident name is required.";
    } elseif (empty($house_no)) {
        $error = "House number is required.";
    } elseif (empty($gender)) {
        $error = "Gender is required.";
    } elseif (empty($date_of_birth)) {
        $error = "Date of birth is required.";
    } elseif (empty($nida_number)) {
        $error = "NIDA number is required.";
    } elseif (empty($phone)) {
        $error = "Phone number is required.";
    } elseif (empty($occupation)) {
        $error = "Occupation is required.";
    } elseif (empty($ownership)) {
        $error = "Ownership status is required.";
    } elseif (empty($education_level)) {
        $error = "Education level is required.";
    } elseif (empty($employment_status)) {
        $error = "Employment status is required.";
    } elseif ($ward_id <= 0) {
        $error = "Please select a ward.";
    } elseif ($village_id <= 0) {
        $error = "Please select a village.";
    } else {
        // Check if NIDA number already exists
        try {
            $stmt = $pdo->prepare("SELECT id FROM residences WHERE nida_number = ? AND status = 'approved'");
            $stmt->execute([$nida_number]);
            if ($stmt->fetch()) {
                $error = "A residence with this NIDA number already exists.";
            } else {
                // Insert residence
                $stmt = $pdo->prepare("
                    INSERT INTO residences (
                        resident_name, house_no, gender, date_of_birth, nida_number, 
                        phone, occupation, ownership, email, education_level, 
                        employment_status, ward_id, village_id, registered_by, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')
                ");
                
                if ($stmt->execute([
                    $resident_name, $house_no, $gender, $date_of_birth, $nida_number,
                    $phone, $occupation, $ownership, $email, $education_level,
                    $employment_status, $ward_id, $village_id, $_SESSION['user_id']
                ])) {
                    $residence_id = $pdo->lastInsertId();
                    $message = "Residence added successfully!";
                    
                    // Redirect to add family members
                    header("Location: add_family_members.php?residence_id=" . $residence_id);
                    exit();
                } else {
                    $error = "Error adding residence. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Add Residence</h1>
            <p class="text-gray-600">Collect residence data</p>
        </div>
        <a href="residences.php" class="btn-mobile bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-200">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" class="space-y-6">
        <!-- Personal Information -->
        <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-user mr-2"></i>Personal Information
            </h2>
            
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                    <input type="text" name="resident_name" value="<?php echo htmlspecialchars($_POST['resident_name'] ?? ''); ?>" 
                           class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">House Number *</label>
                    <input type="text" name="house_no" value="<?php echo htmlspecialchars($_POST['house_no'] ?? ''); ?>" 
                           class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Gender *</label>
                        <select name="gender" class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo ($_POST['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($_POST['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth *</label>
                        <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>" 
                               class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">NIDA Number *</label>
                    <input type="text" name="nida_number" value="<?php echo htmlspecialchars($_POST['nida_number'] ?? ''); ?>" 
                           class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                           class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>
            </div>
        </div>

        <!-- Professional Information -->
        <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-briefcase mr-2"></i>Professional Information
            </h2>
            
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Occupation *</label>
                    <input type="text" name="occupation" value="<?php echo htmlspecialchars($_POST['occupation'] ?? ''); ?>" 
                           class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ownership *</label>
                        <select name="ownership" class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Select Ownership</option>
                            <option value="Owner" <?php echo ($_POST['ownership'] ?? '') === 'Owner' ? 'selected' : ''; ?>>Owner</option>
                            <option value="Tenant" <?php echo ($_POST['ownership'] ?? '') === 'Tenant' ? 'selected' : ''; ?>>Tenant</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employment Status *</label>
                        <select name="employment_status" class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Select Status</option>
                            <option value="Employed" <?php echo ($_POST['employment_status'] ?? '') === 'Employed' ? 'selected' : ''; ?>>Employed</option>
                            <option value="Unemployed" <?php echo ($_POST['employment_status'] ?? '') === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                            <option value="Self-employed" <?php echo ($_POST['employment_status'] ?? '') === 'Self-employed' ? 'selected' : ''; ?>>Self-employed</option>
                            <option value="Student" <?php echo ($_POST['employment_status'] ?? '') === 'Student' ? 'selected' : ''; ?>>Student</option>
                            <option value="Retired" <?php echo ($_POST['employment_status'] ?? '') === 'Retired' ? 'selected' : ''; ?>>Retired</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Education Level *</label>
                    <select name="education_level" class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select Education Level</option>
                        <option value="No formal education" <?php echo ($_POST['education_level'] ?? '') === 'No formal education' ? 'selected' : ''; ?>>No formal education</option>
                        <option value="Primary" <?php echo ($_POST['education_level'] ?? '') === 'Primary' ? 'selected' : ''; ?>>Primary</option>
                        <option value="Secondary" <?php echo ($_POST['education_level'] ?? '') === 'Secondary' ? 'selected' : ''; ?>>Secondary</option>
                        <option value="Diploma" <?php echo ($_POST['education_level'] ?? '') === 'Diploma' ? 'selected' : ''; ?>>Diploma</option>
                        <option value="Bachelor's degree" <?php echo ($_POST['education_level'] ?? '') === 'Bachelor\'s degree' ? 'selected' : ''; ?>>Bachelor's degree</option>
                        <option value="Master's degree" <?php echo ($_POST['education_level'] ?? '') === 'Master\'s degree' ? 'selected' : ''; ?>>Master's degree</option>
                        <option value="PhD" <?php echo ($_POST['education_level'] ?? '') === 'PhD' ? 'selected' : ''; ?>>PhD</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email (Optional)</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </div>

        <!-- Location Information -->
        <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-map-marker-alt mr-2"></i>Location Information
            </h2>
            
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ward *</label>
                    <select name="ward_id" id="ward_id" class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select Ward</option>
                        <?php foreach ($wards as $ward): ?>
                        <option value="<?php echo $ward['id']; ?>" <?php echo ($_POST['ward_id'] ?? '') == $ward['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ward['ward_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Village *</label>
                    <select name="village_id" id="village_id" class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select Village</option>
                        <?php foreach ($villages as $village): ?>
                        <option value="<?php echo $village['id']; ?>" <?php echo ($_POST['village_id'] ?? '') == $village['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($village['village_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end space-x-4">
            <a href="residences.php" class="btn-mobile bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition duration-200">
                <i class="fas fa-times mr-2"></i>Cancel
            </a>
            <button type="submit" class="btn-mobile bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                <i class="fas fa-save mr-2"></i>Save & Add Family
            </button>
        </div>
    </form>
</div>

<script>
// Ward-Village dependency
document.getElementById('ward_id').addEventListener('change', function() {
    const wardId = this.value;
    const villageSelect = document.getElementById('village_id');
    
    if (wardId) {
        // Fetch villages for selected ward
        fetch(`../api/get_villages.php?ward_id=${wardId}`)
            .then(response => response.json())
            .then(data => {
                villageSelect.innerHTML = '<option value="">Select Village</option>';
                data.forEach(village => {
                    const option = document.createElement('option');
                    option.value = village.id;
                    option.textContent = village.village_name;
                    villageSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading villages:', error);
                villageSelect.innerHTML = '<option value="">Error loading villages</option>';
            });
    } else {
        villageSelect.innerHTML = '<option value="">Select Village</option>';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
