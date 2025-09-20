<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Manage Family Members';

$message = '';
$error = '';

// Get residence ID
$residence_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$residence_id) {
    header('Location: residences.php');
    exit();
}

// Get residence information
try {
    $stmt = $pdo->prepare("
        SELECT r.*, w.ward_name, v.village_name 
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

// Handle family member addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_family_member'])) {
    $name = sanitizeInput($_POST['name']);
    $gender = $_POST['gender'];
    $date_of_birth = $_POST['date_of_birth'];
    $nida_number = sanitizeInput($_POST['nida_number']);
    $relationship = $_POST['relationship'];
    $phone = sanitizeInput($_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    $occupation = sanitizeInput($_POST['occupation']);
    $education_level = $_POST['education_level'];
    $employment_status = $_POST['employment_status'];
    
    // Calculate age to determine if minor
    $birth_date = new DateTime($date_of_birth);
    $today = new DateTime();
    $age = $today->diff($birth_date)->y;
    $is_minor = $age < 18;
    
    // Validation
    if (empty($name) || empty($gender) || empty($date_of_birth) || empty($relationship)) {
        $error = 'Name, gender, date of birth, and relationship are required';
    } elseif (!$is_minor && empty($nida_number)) {
        $error = 'NIDA number is required for family members 18 years and above';
    } else {
        try {
            // Check if NIDA number already exists (for adults)
            if (!$is_minor && !empty($nida_number)) {
                $stmt = $pdo->prepare("SELECT id FROM residences WHERE nida_number = ? AND status = 'active'");
                $stmt->execute([$nida_number]);
                if ($stmt->fetch()) {
                    $error = 'NIDA number already exists in the system';
                } else {
                    // Check deleted residences
                    $stmt = $pdo->prepare("SELECT id, resident_name, house_no FROM deleted_residences WHERE nida_number = ?");
                    $stmt->execute([$nida_number]);
                    $deleted_residence = $stmt->fetch();
                    if ($deleted_residence) {
                        $error = 'This NIDA number was previously registered but deleted. Please contact administrator to restore or use a different NIDA number.';
                    }
                }
            }
            
            if (empty($error)) {
                // Insert family member
                $stmt = $pdo->prepare("INSERT INTO family_members (residence_id, name, gender, date_of_birth, nida_number, relationship, is_minor, phone, email, occupation, education_level, employment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$residence_id, $name, $gender, $date_of_birth, $nida_number, $relationship, $is_minor, $phone, $email, $occupation, $education_level, $employment_status]);
                
                $message = 'Family member added successfully';
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred';
        }
    }
}

// Handle family member deletion
if (isset($_GET['delete_member']) && is_numeric($_GET['delete_member'])) {
    $member_id = (int)$_GET['delete_member'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM family_members WHERE id = ? AND residence_id = ?");
        $stmt->execute([$member_id, $residence_id]);
        $message = 'Family member removed successfully';
    } catch (PDOException $e) {
        $error = 'Error removing family member';
    }
}

// Get family members
try {
    $stmt = $pdo->prepare("SELECT * FROM family_members WHERE residence_id = ? ORDER BY created_at ASC");
    $stmt->execute([$residence_id]);
    $family_members = $stmt->fetchAll();
} catch (PDOException $e) {
    $family_members = [];
}

include 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="space-y-6">
        <!-- Residence Information -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Family Members</h1>
            
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Residence Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <span class="text-sm font-medium text-gray-600">House Number:</span>
                        <p class="text-gray-900"><?php echo htmlspecialchars($residence['house_no']); ?></p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-600">Resident Name:</span>
                        <p class="text-gray-900"><?php echo htmlspecialchars($residence['resident_name']); ?></p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-600">Location:</span>
                        <p class="text-gray-900"><?php echo htmlspecialchars($residence['ward_name'] . ' - ' . $residence['village_name']); ?></p>
                    </div>
                </div>
            </div>
            
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
            
            <!-- Add Family Member Form -->
            <div class="bg-blue-50 p-4 rounded-lg mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-plus mr-2"></i>Add Family Member
                </h3>
                
                <form method="POST" class="space-y-4" id="addFamilyMemberForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input type="text" id="name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender *</label>
                            <select id="gender" name="gender" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth *</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="relationship" class="block text-sm font-medium text-gray-700 mb-1">Relationship *</label>
                            <select id="relationship" name="relationship" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Relationship</option>
                                <option value="spouse">Spouse</option>
                                <option value="child">Child</option>
                                <option value="parent">Parent</option>
                                <option value="sibling">Sibling</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="nida_number" class="block text-sm font-medium text-gray-700 mb-1">NIDA Number</label>
                            <input type="text" id="nida_number" name="nida_number"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Required for 18+ years</p>
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="tel" id="phone" name="phone"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="email" name="email"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="occupation" class="block text-sm font-medium text-gray-700 mb-1">Occupation</label>
                            <input type="text" id="occupation" name="occupation"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="education_level" class="block text-sm font-medium text-gray-700 mb-1">Education Level</label>
                            <select id="education_level" name="education_level"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="none">No Formal Education</option>
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                                <option value="diploma">Diploma</option>
                                <option value="degree">Degree</option>
                                <option value="masters">Masters</option>
                                <option value="phd">PhD</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="employment_status" class="block text-sm font-medium text-gray-700 mb-1">Employment Status</label>
                            <select id="employment_status" name="employment_status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="unemployed">Unemployed</option>
                                <option value="employed">Employed</option>
                                <option value="self_employed">Self Employed</option>
                                <option value="student">Student</option>
                                <option value="retired">Retired</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="add_family_member" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Add Family Member
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Family Members List -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <h3 class="text-lg font-semibold text-gray-800 p-4 bg-gray-50 border-b">
                    <i class="fas fa-users mr-2"></i>Family Members (<?php echo count($family_members); ?>)
                </h3>
                
                <?php if (empty($family_members)): ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-users text-4xl mb-4"></i>
                        <p>No family members added yet.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Relationship</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIDA Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Occupation</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($family_members as $member): ?>
                                <?php
                                $birth_date = new DateTime($member['date_of_birth']);
                                $today = new DateTime();
                                $age = $today->diff($birth_date)->y;
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-user text-gray-600 text-sm"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($member['name']); ?>
                                                </div>
                                                <?php if ($member['is_minor']): ?>
                                                <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded">Minor</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo ucfirst($member['gender']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $age; ?> years
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo ucfirst($member['relationship']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $member['nida_number'] ? htmlspecialchars($member['nida_number']) : 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $member['occupation'] ? htmlspecialchars($member['occupation']) : 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="?id=<?php echo $residence_id; ?>&delete_member=<?php echo $member['id']; ?>" 
                                           class="text-red-600 hover:text-red-900"
                                           onclick="return confirm('Are you sure you want to remove this family member?')"
                                           title="Remove Family Member">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-6 flex justify-between">
                <a href="residences.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Residences
                </a>
                <a href="view_residence.php?id=<?php echo $residence_id; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-eye mr-2"></i>View Residence Details
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-validate NIDA requirement for adults
document.getElementById('date_of_birth').addEventListener('change', function() {
    const birthDate = new Date(this.value);
    const today = new Date();
    const age = today.getFullYear() - birthDate.getFullYear();
    const nidaField = document.getElementById('nida_number');
    
    if (age >= 18) {
        nidaField.required = true;
        nidaField.parentElement.querySelector('p').textContent = 'Required for 18+ years';
    } else {
        nidaField.required = false;
        nidaField.parentElement.querySelector('p').textContent = 'Not required for minors';
    }
});

// Form validation
document.getElementById('addFamilyMemberForm').addEventListener('submit', function(e) {
    const birthDate = new Date(document.getElementById('date_of_birth').value);
    const today = new Date();
    const age = today.getFullYear() - birthDate.getFullYear();
    const nidaNumber = document.getElementById('nida_number').value;
    
    if (age >= 18 && !nidaNumber.trim()) {
        e.preventDefault();
        alert('NIDA number is required for family members 18 years and above');
        return false;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
