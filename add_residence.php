<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Add New Residence';

$message = '';
$error = '';

// Get accessible wards and villages for the current user
$accessible_wards = getAccessibleWards();
$accessible_villages = getAccessibleVillages();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resident_name = sanitizeInput($_POST['resident_name']);
    $nida_number = sanitizeInput($_POST['nida_number']);
    $address = sanitizeInput($_POST['address']);
    $phone = sanitizeInput($_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    $occupation = sanitizeInput($_POST['occupation']);
    $family_members = (int)$_POST['family_members'];
    $ward_id = (int)$_POST['ward_id'];
    $village_id = (int)$_POST['village_id'];
    
    // Validation
    if (empty($resident_name) || empty($nida_number) || empty($address)) {
        $error = 'Name, NIDA number, and address are required';
    } elseif ($family_members < 1) {
        $error = 'Family members must be at least 1';
    } elseif (!$ward_id || !$village_id) {
        $error = 'Ward and Street/Village selection are required';
    } elseif (!canAccessWard($ward_id) || !canAccessVillage($village_id)) {
        $error = 'You do not have permission to register residences in the selected location';
    } else {
        try {
            // Check if NIDA number already exists
            $stmt = $pdo->prepare("SELECT id FROM residences WHERE nida_number = ?");
            $stmt->execute([$nida_number]);
            if ($stmt->fetch()) {
                $error = 'NIDA number already exists in the system';
            } else {
                // Insert new residence
                $stmt = $pdo->prepare("INSERT INTO residences (resident_name, nida_number, address, phone, email, occupation, family_members, ward_id, village_id, registered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$resident_name, $nida_number, $address, $phone, $email, $occupation, $family_members, $ward_id, $village_id, $_SESSION['user_id']]);
                
                $message = 'Residence registered successfully';
                // Clear form
                $resident_name = $nida_number = $address = $phone = $email = $occupation = '';
                $family_members = 1;
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
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Add New Residence</h1>
        
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
        
        <form method="POST" class="space-y-6" id="residenceForm">
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
                               value="<?php echo isset($_POST['resident_name']) ? htmlspecialchars($_POST['resident_name']) : ''; ?>">
                    </div>
                    
                    <div>
                        <label for="nida_number" class="block text-sm font-medium text-gray-700 mb-2">
                            NIDA Number *
                        </label>
                        <input type="text" id="nida_number" name="nida_number" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo isset($_POST['nida_number']) ? htmlspecialchars($_POST['nida_number']) : ''; ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Phone Number
                        </label>
                        <input type="tel" id="phone" name="phone"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                        </label>
                        <input type="email" id="email" name="email"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label for="occupation" class="block text-sm font-medium text-gray-700 mb-2">
                            Occupation
                        </label>
                        <input type="text" id="occupation" name="occupation"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo isset($_POST['occupation']) ? htmlspecialchars($_POST['occupation']) : ''; ?>">
                    </div>
                    
                    <div>
                        <label for="family_members" class="block text-sm font-medium text-gray-700 mb-2">
                            Family Members *
                        </label>
                        <input type="number" id="family_members" name="family_members" min="1" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo isset($_POST['family_members']) ? $_POST['family_members'] : '1'; ?>">
                    </div>
                </div>
            </div>
            
            <!-- Location Information -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-map-marker-alt mr-2"></i>Location Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <div>
                        <label for="ward_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Ward *
                        </label>
                        <select id="ward_id" name="ward_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select Ward</option>
                            <?php foreach ($accessible_wards as $ward): ?>
                                <option value="<?php echo $ward['id']; ?>" 
                                        <?php echo (isset($_POST['ward_id']) && $_POST['ward_id'] == $ward['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ward['ward_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="village_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Street/Village *
                        </label>
                        <select id="village_id" name="village_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select Street/Village</option>
                            <?php foreach ($accessible_villages as $village): ?>
                                <option value="<?php echo $village['id']; ?>" 
                                        data-ward-id="<?php echo $village['ward_id']; ?>"
                                        <?php echo (isset($_POST['village_id']) && $_POST['village_id'] == $village['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($village['village_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                        Full Address *
                    </label>
                    <textarea id="address" name="address" rows="3" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Enter complete address including street, ward, district, region..."><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4">
                <a href="residences.php" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-save mr-2"></i>Register Residence
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Ward-Village relationship handling
document.getElementById('ward_id').addEventListener('change', function() {
    const selectedWardId = this.value;
    const villageSelect = document.getElementById('village_id');
    
    // Clear current selection
    villageSelect.innerHTML = '<option value="">Select Street/Village</option>';
    
    // Filter villages by selected ward
    const allVillages = <?php echo json_encode($accessible_villages); ?>;
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

document.getElementById('residenceForm').addEventListener('submit', function(e) {
    const familyMembers = document.getElementById('family_members').value;
    const wardId = document.getElementById('ward_id').value;
    const villageId = document.getElementById('village_id').value;
    
    if (familyMembers < 1) {
        e.preventDefault();
        alert('Family members must be at least 1');
        return false;
    }
    
    if (!wardId || !villageId) {
        e.preventDefault();
        alert('Please select both Ward and Street/Village');
        return false;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
