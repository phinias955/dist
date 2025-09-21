<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is data collector
if ($_SESSION['user_role'] !== 'data_collector') {
    header('Location: ../unauthorized.php');
    exit();
}

$page_title = 'Add Family Members';

$message = '';
$error = '';

$residence_id = isset($_GET['residence_id']) ? (int)$_GET['residence_id'] : 0;

// Get residence details
$residence = null;
if ($residence_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, v.village_name, w.ward_name
            FROM residences r
            LEFT JOIN villages v ON r.village_id = v.id
            LEFT JOIN wards w ON v.ward_id = w.id
            WHERE r.id = ? AND r.registered_by = ? AND r.status = 'approved'
        ");
        $stmt->execute([$residence_id, $_SESSION['user_id']]);
        $residence = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "Error loading residence: " . $e->getMessage();
    }
}

if (!$residence) {
    $error = "Residence not found or access denied.";
}

// Get existing family members
$family_members = [];
if ($residence) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM family_members 
            WHERE residence_id = ? 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$residence_id]);
        $family_members = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Error loading family members: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $residence) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_member') {
        $member_name = trim($_POST['member_name'] ?? '');
        $relationship = $_POST['relationship'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $nida_number = trim($_POST['nida_number'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $education_level = $_POST['education_level'] ?? '';
        $employment_status = $_POST['employment_status'] ?? '';
        
        // Calculate age
        $age = 0;
        if ($date_of_birth) {
            $age = date_diff(date_create($date_of_birth), date_create('today'))->y;
        }
        
        // Validate form data
        $required_fields = ['member_name', 'relationship', 'gender', 'date_of_birth'];
        if ($age >= 18) {
            $required_fields[] = 'nida_number';
        }
        
        $validation = validateFormData($_POST, $required_fields);
        
        if (!$validation['valid']) {
            $error = implode(', ', $validation['errors']);
        } elseif ($age >= 18 && empty($phone)) {
            $error = "Phone number is required for members 18 years and above.";
        } elseif ($age >= 18 && empty($occupation)) {
            $error = "Occupation is required for members 18 years and above.";
        } elseif ($age >= 18 && empty($education_level)) {
            $error = "Education level is required for members 18 years and above.";
        } elseif ($age >= 18 && empty($employment_status)) {
            $error = "Employment status is required for members 18 years and above.";
        } else {
            // Use cleaned data
            $nida_number = $validation['data']['nida_number'] ?? '';
            $phone = $validation['data']['phone'] ?? '';
            
            // Check if NIDA number already exists (for adults)
            if ($age >= 18 && !empty($nida_number)) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT fm.id FROM family_members fm
                        JOIN residences r ON fm.residence_id = r.id
                        WHERE fm.nida_number = ? AND r.status = 'approved'
                    ");
                    $stmt->execute([$nida_number]);
                    if ($stmt->fetch()) {
                        $error = "A family member with this NIDA number already exists in another residence.";
                    }
                } catch (PDOException $e) {
                    $error = "Error checking NIDA number: " . $e->getMessage();
                }
            }
            
            if (empty($error)) {
                // Insert family member
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO family_members (
                            residence_id, member_name, relationship, gender, date_of_birth,
                            nida_number, phone, occupation, education_level, employment_status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([
                        $residence_id, $member_name, $relationship, $gender, $date_of_birth,
                        $age >= 18 ? $nida_number : null,
                        $age >= 18 ? $phone : null,
                        $age >= 18 ? $occupation : null,
                        $age >= 18 ? $education_level : null,
                        $age >= 18 ? $employment_status : null
                    ])) {
                        $message = "Family member added successfully!";
                        // Refresh family members
                        $stmt = $pdo->prepare("
                            SELECT * FROM family_members 
                            WHERE residence_id = ? 
                            ORDER BY created_at ASC
                        ");
                        $stmt->execute([$residence_id]);
                        $family_members = $stmt->fetchAll();
                    } else {
                        $error = "Error adding family member. Please try again.";
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete_member') {
        $member_id = (int)($_POST['member_id'] ?? 0);
        if ($member_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM family_members WHERE id = ? AND residence_id = ?");
                if ($stmt->execute([$member_id, $residence_id])) {
                    $message = "Family member deleted successfully!";
                    // Refresh family members
                    $stmt = $pdo->prepare("
                        SELECT * FROM family_members 
                        WHERE residence_id = ? 
                        ORDER BY created_at ASC
                    ");
                    $stmt->execute([$residence_id]);
                    $family_members = $stmt->fetchAll();
                } else {
                    $error = "Error deleting family member.";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'finish') {
        header("Location: residences.php");
        exit();
    }
}

include 'includes/header.php';
?>

<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Add Family Members</h1>
            <p class="text-gray-600">Complete residence data collection</p>
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

    <?php if ($residence): ?>
    <!-- Residence Info -->
    <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-building mr-2"></i>Residence Information
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-600">Resident Name</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['resident_name']); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-600">House Number</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['house_no']); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Location</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($residence['village_name'] . ', ' . $residence['ward_name']); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Family Members</p>
                <p class="font-medium text-gray-900"><?php echo count($family_members); ?> member<?php echo count($family_members) != 1 ? 's' : ''; ?></p>
            </div>
        </div>
    </div>

    <!-- Add Family Member Form -->
    <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-user-plus mr-2"></i>Add Family Member
        </h2>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_member">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                    <input type="text" name="member_name" value="<?php echo htmlspecialchars($_POST['member_name'] ?? ''); ?>" 
                           class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Relationship *</label>
                    <select name="relationship" class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select Relationship</option>
                        <option value="Spouse" <?php echo ($_POST['relationship'] ?? '') === 'Spouse' ? 'selected' : ''; ?>>Spouse</option>
                        <option value="Child" <?php echo ($_POST['relationship'] ?? '') === 'Child' ? 'selected' : ''; ?>>Child</option>
                        <option value="Parent" <?php echo ($_POST['relationship'] ?? '') === 'Parent' ? 'selected' : ''; ?>>Parent</option>
                        <option value="Sibling" <?php echo ($_POST['relationship'] ?? '') === 'Sibling' ? 'selected' : ''; ?>>Sibling</option>
                        <option value="Other" <?php echo ($_POST['relationship'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
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
            
            <!-- Adult fields (18+) -->
            <div id="adult-fields" class="space-y-4" style="display: none;">
                <div class="border-t pt-4">
                    <h3 class="text-md font-medium text-gray-800 mb-3">Additional Information (18+ years)</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">NIDA Number *</label>
                            <input type="text" name="nida_number" value="<?php echo htmlspecialchars($_POST['nida_number'] ?? ''); ?>" 
                                   class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   maxlength="20" placeholder="Enter 20-digit NIDA number">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                   class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   maxlength="10" placeholder="Enter 10-digit phone number">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Occupation *</label>
                            <input type="text" name="occupation" value="<?php echo htmlspecialchars($_POST['occupation'] ?? ''); ?>" 
                                   class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employment Status *</label>
                            <select name="employment_status" class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                        <select name="education_level" class="input-mobile w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="btn-mobile bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add Member
                </button>
            </div>
        </form>
    </div>

    <!-- Existing Family Members -->
    <?php if (!empty($family_members)): ?>
    <div class="bg-white p-6 rounded-xl shadow-sm card-mobile">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-users mr-2"></i>Family Members (<?php echo count($family_members); ?>)
        </h2>
        
        <div class="space-y-3">
            <?php foreach ($family_members as $member): ?>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div class="flex-1">
                    <div class="flex items-center">
                        <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($member['member_name']); ?></h3>
                        <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                            <?php echo htmlspecialchars($member['relationship']); ?>
                        </span>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        <span class="mr-4">
                            <i class="fas fa-<?php echo $member['gender'] == 'Male' ? 'mars' : 'venus'; ?> mr-1"></i>
                            <?php echo htmlspecialchars($member['gender']); ?>
                        </span>
                        <span class="mr-4">
                            <i class="fas fa-calendar mr-1"></i>
                            <?php echo formatDate($member['date_of_birth']); ?>
                        </span>
                        <?php if ($member['nida_number']): ?>
                        <span class="mr-4">
                            <i class="fas fa-id-card mr-1"></i>
                            <?php echo htmlspecialchars($member['nida_number']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="POST" class="ml-4">
                    <input type="hidden" name="action" value="delete_member">
                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                    <button type="submit" class="btn-mobile bg-red-100 text-red-700 px-3 py-2 rounded-lg hover:bg-red-200 transition duration-200" 
                            onclick="return confirm('Are you sure you want to delete this family member?')">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Finish Button -->
    <div class="flex justify-center">
        <form method="POST" class="inline">
            <input type="hidden" name="action" value="finish">
            <button type="submit" class="btn-mobile bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition duration-200">
                <i class="fas fa-check mr-2"></i>Finish Data Collection
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
// Show/hide adult fields based on age
document.querySelector('input[name="date_of_birth"]').addEventListener('change', function() {
    const dateOfBirth = new Date(this.value);
    const today = new Date();
    const age = today.getFullYear() - dateOfBirth.getFullYear();
    const monthDiff = today.getMonth() - dateOfBirth.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dateOfBirth.getDate())) {
        age--;
    }
    
    const adultFields = document.getElementById('adult-fields');
    if (age >= 18) {
        adultFields.style.display = 'block';
        // Make adult fields required
        adultFields.querySelectorAll('input, select').forEach(field => {
            field.required = true;
        });
    } else {
        adultFields.style.display = 'none';
        // Make adult fields not required
        adultFields.querySelectorAll('input, select').forEach(field => {
            field.required = false;
            field.value = '';
        });
    }
});
</script>

<script src="../js/validation.js"></script>

<?php include 'includes/footer.php'; ?>
