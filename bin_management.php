<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user has permission to manage bin
if (!isSuperAdmin() && $_SESSION['user_role'] !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

$page_title = 'Bin Management';

$message = '';
$error = '';

// Handle restore operations
if (isset($_GET['restore']) && isset($_GET['type']) && isset($_GET['id'])) {
    $type = $_GET['type'];
    $id = (int)$_GET['id'];
    
    try {
        if ($type === 'user') {
            // Restore user
            $stmt = $pdo->prepare("SELECT * FROM bin_users WHERE id = ?");
            $stmt->execute([$id]);
            $user_data = $stmt->fetch();
            
            if ($user_data) {
                // Insert back to users table
                $stmt = $pdo->prepare("
                    INSERT INTO users (full_name, username, password, role, nida_number, assigned_ward_id, assigned_village_id, created_at, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $user_data['full_name'],
                    $user_data['username'],
                    $user_data['password'],
                    $user_data['role'],
                    $user_data['nida_number'],
                    $user_data['assigned_ward_id'],
                    $user_data['assigned_village_id'],
                    $user_data['original_created_at']
                ]);
                
                // Remove from bin
                $stmt = $pdo->prepare("DELETE FROM bin_users WHERE id = ?");
                $stmt->execute([$id]);
                
                // Log operation
                $stmt = $pdo->prepare("
                    INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                    VALUES ('restore', 'user', ?, ?, ?, 'User restored from bin')
                ");
                $stmt->execute([$user_data['original_user_id'], $id, $_SESSION['user_id']]);
                
                $message = 'User "' . $user_data['full_name'] . '" has been restored successfully.';
            }
        } elseif ($type === 'residence') {
            // Restore residence
            $stmt = $pdo->prepare("SELECT * FROM bin_residences WHERE id = ?");
            $stmt->execute([$id]);
            $residence_data = $stmt->fetch();
            
            if ($residence_data) {
                // Insert back to residences table
                $stmt = $pdo->prepare("
                    INSERT INTO residences (house_no, resident_name, gender, date_of_birth, nida_number, phone, email, occupation, ownership, family_members, education_level, employment_status, ward_id, village_id, registered_by, registered_at, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    $residence_data['house_no'],
                    $residence_data['resident_name'],
                    $residence_data['gender'],
                    $residence_data['date_of_birth'],
                    $residence_data['nida_number'],
                    $residence_data['phone'],
                    $residence_data['email'],
                    $residence_data['occupation'],
                    $residence_data['ownership'],
                    $residence_data['family_members'],
                    $residence_data['education_level'],
                    $residence_data['employment_status'],
                    $residence_data['ward_id'],
                    $residence_data['village_id'],
                    $residence_data['registered_by'],
                    $residence_data['original_registered_at']
                ]);
                
                // Remove from bin
                $stmt = $pdo->prepare("DELETE FROM bin_residences WHERE id = ?");
                $stmt->execute([$id]);
                
                // Log operation
                $stmt = $pdo->prepare("
                    INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                    VALUES ('restore', 'residence', ?, ?, ?, 'Residence restored from bin')
                ");
                $stmt->execute([$residence_data['original_residence_id'], $id, $_SESSION['user_id']]);
                
                $message = 'Residence "' . $residence_data['house_no'] . '" has been restored successfully.';
            }
        } elseif ($type === 'family_member') {
            // Restore family member
            $stmt = $pdo->prepare("SELECT * FROM bin_family_members WHERE id = ?");
            $stmt->execute([$id]);
            $member_data = $stmt->fetch();
            
            if ($member_data) {
                // Get the residence ID (assuming it exists)
                $stmt = $pdo->prepare("SELECT id FROM residences WHERE house_no = ? AND ward_id = ? AND village_id = ?");
                $stmt->execute([$member_data['residence_house_no'], $member_data['ward_id'], $member_data['village_id']]);
                $residence = $stmt->fetch();
                
                if ($residence) {
                    // Insert back to family_members table
                    $stmt = $pdo->prepare("
                        INSERT INTO family_members (residence_id, name, gender, date_of_birth, nida_number, relationship, is_minor, phone, email, occupation, education_level, employment_status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $residence['id'],
                        $member_data['name'],
                        $member_data['gender'],
                        $member_data['date_of_birth'],
                        $member_data['nida_number'],
                        $member_data['relationship'],
                        $member_data['is_minor'],
                        $member_data['phone'],
                        $member_data['email'],
                        $member_data['occupation'],
                        $member_data['education_level'],
                        $member_data['employment_status'],
                        $member_data['original_created_at']
                    ]);
                    
                    // Remove from bin
                    $stmt = $pdo->prepare("DELETE FROM bin_family_members WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Log operation
                    $stmt = $pdo->prepare("
                        INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                        VALUES ('restore', 'family_member', ?, ?, ?, 'Family member restored from bin')
                    ");
                    $stmt->execute([$member_data['original_family_member_id'], $id, $_SESSION['user_id']]);
                    
                    $message = 'Family member "' . $member_data['name'] . '" has been restored successfully.';
                } else {
                    $error = 'Cannot restore family member: Residence not found.';
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Error restoring item: ' . $e->getMessage();
    }
}

// Handle permanent deletion
if (isset($_GET['permanent_delete']) && isset($_GET['type']) && isset($_GET['id'])) {
    $type = $_GET['type'];
    $id = (int)$_GET['id'];
    
    try {
        if ($type === 'user') {
            $stmt = $pdo->prepare("SELECT full_name FROM bin_users WHERE id = ?");
            $stmt->execute([$id]);
            $user_data = $stmt->fetch();
            
            if ($user_data) {
                // Log operation before deletion
                $stmt = $pdo->prepare("
                    INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                    VALUES ('permanent_delete', 'user', ?, ?, ?, 'User permanently deleted from bin')
                ");
                $stmt->execute([$user_data['original_user_id'], $id, $_SESSION['user_id']]);
                
                // Permanently delete
                $stmt = $pdo->prepare("DELETE FROM bin_users WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = 'User "' . $user_data['full_name'] . '" has been permanently deleted.';
            }
        } elseif ($type === 'residence') {
            $stmt = $pdo->prepare("SELECT house_no FROM bin_residences WHERE id = ?");
            $stmt->execute([$id]);
            $residence_data = $stmt->fetch();
            
            if ($residence_data) {
                // Log operation before deletion
                $stmt = $pdo->prepare("
                    INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                    VALUES ('permanent_delete', 'residence', ?, ?, ?, 'Residence permanently deleted from bin')
                ");
                $stmt->execute([$residence_data['original_residence_id'], $id, $_SESSION['user_id']]);
                
                // Permanently delete
                $stmt = $pdo->prepare("DELETE FROM bin_residences WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = 'Residence "' . $residence_data['house_no'] . '" has been permanently deleted.';
            }
        } elseif ($type === 'family_member') {
            $stmt = $pdo->prepare("SELECT name FROM bin_family_members WHERE id = ?");
            $stmt->execute([$id]);
            $member_data = $stmt->fetch();
            
            if ($member_data) {
                // Log operation before deletion
                $stmt = $pdo->prepare("
                    INSERT INTO bin_operations (operation_type, data_type, original_id, bin_id, performed_by, notes)
                    VALUES ('permanent_delete', 'family_member', ?, ?, ?, 'Family member permanently deleted from bin')
                ");
                $stmt->execute([$member_data['original_family_member_id'], $id, $_SESSION['user_id']]);
                
                // Permanently delete
                $stmt = $pdo->prepare("DELETE FROM bin_family_members WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = 'Family member "' . $member_data['name'] . '" has been permanently deleted.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Error permanently deleting item: ' . $e->getMessage();
    }
}

// Get bin statistics
try {
    // Count deleted users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bin_users");
    $deleted_users_count = $stmt->fetch()['count'];
    
    // Count deleted residences
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bin_residences");
    $deleted_residences_count = $stmt->fetch()['count'];
    
    // Count deleted family members
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bin_family_members");
    $deleted_family_members_count = $stmt->fetch()['count'];
    
    // Count deleted wards
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bin_wards");
    $deleted_wards_count = $stmt->fetch()['count'];
    
    // Count deleted villages
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bin_villages");
    $deleted_villages_count = $stmt->fetch()['count'];
    
    // Get recent deleted users
    $stmt = $pdo->query("
        SELECT *, 'user' as data_type FROM bin_users 
        ORDER BY deleted_at DESC 
        LIMIT 10
    ");
    $recent_users = $stmt->fetchAll();
    
    // Get recent deleted residences
    $stmt = $pdo->query("
        SELECT *, 'residence' as data_type FROM bin_residences 
        ORDER BY deleted_at DESC 
        LIMIT 10
    ");
    $recent_residences = $stmt->fetchAll();
    
    // Get recent deleted family members
    $stmt = $pdo->query("
        SELECT *, 'family_member' as data_type FROM bin_family_members 
        ORDER BY deleted_at DESC 
        LIMIT 10
    ");
    $recent_family_members = $stmt->fetchAll();
    
    // Combine recent items
    $recent_items = array_merge($recent_users, $recent_residences, $recent_family_members);
    
    // Sort by deletion date
    usort($recent_items, function($a, $b) {
        return strtotime($b['deleted_at']) - strtotime($a['deleted_at']);
    });
    
} catch (PDOException $e) {
    $error = 'Error loading bin data: ' . $e->getMessage();
    $deleted_users_count = $deleted_residences_count = $deleted_family_members_count = $deleted_wards_count = $deleted_villages_count = 0;
    $recent_items = [];
}

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-trash-alt mr-2"></i>Bin Management
                </h1>
                <p class="text-gray-600">Manage all deleted data in the system</p>
            </div>
            <div class="flex items-center space-x-4">
                <?php if (isSuperAdmin()): ?>
                <span class="bg-purple-100 text-purple-800 text-sm font-medium px-3 py-1 rounded-full">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Super Admin Access
                </span>
                <?php else: ?>
                <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full">
                    <i class="fas fa-user-tie mr-1"></i>
                    Ward Admin Access
                </span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded alert">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-times text-red-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Deleted Users</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $deleted_users_count; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-building text-orange-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Deleted Residences</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $deleted_residences_count; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-users text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Deleted Family Members</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $deleted_family_members_count; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-map-marker-alt text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Deleted Wards</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $deleted_wards_count; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-home text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Deleted Villages</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $deleted_villages_count; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-bolt mr-2"></i>Quick Actions
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <a href="bin_users.php" class="bg-red-50 hover:bg-red-100 p-4 rounded-lg border border-red-200 transition duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-user-times text-red-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-800">Manage Users</p>
                            <p class="text-sm text-gray-600">View and restore deleted users</p>
                        </div>
                    </div>
                </a>
                
                <a href="bin_residences.php" class="bg-orange-50 hover:bg-orange-100 p-4 rounded-lg border border-orange-200 transition duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-building text-orange-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-800">Manage Residences</p>
                            <p class="text-sm text-gray-600">View and restore deleted residences</p>
                        </div>
                    </div>
                </a>
                
                <a href="bin_family_members.php" class="bg-yellow-50 hover:bg-yellow-100 p-4 rounded-lg border border-yellow-200 transition duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-users text-yellow-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-800">Manage Family Members</p>
                            <p class="text-sm text-gray-600">View and restore deleted family members</p>
                        </div>
                    </div>
                </a>
                
                <a href="bin_locations.php" class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-200 transition duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-map-marker-alt text-blue-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-800">Manage Locations</p>
                            <p class="text-sm text-gray-600">View and restore deleted wards/villages</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Recent Deletions -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-clock mr-2"></i>Recent Deletions
                </h3>
                <p class="text-sm text-gray-600 mt-1">Latest deleted items across all categories</p>
            </div>
            
            <?php if (empty($recent_items)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-trash text-4xl mb-4"></i>
                    <p class="text-lg">No deleted items found</p>
                    <p class="text-sm">All data is currently active</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name/Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deleted Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach (array_slice($recent_items, 0, 10) as $item): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $type_colors = [
                                        'user' => 'bg-red-100 text-red-800',
                                        'residence' => 'bg-orange-100 text-orange-800',
                                        'family_member' => 'bg-yellow-100 text-yellow-800'
                                    ];
                                    $type_icons = [
                                        'user' => 'fas fa-user-times',
                                        'residence' => 'fas fa-building',
                                        'family_member' => 'fas fa-users'
                                    ];
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $type_colors[$item['data_type']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                        <i class="<?php echo $type_icons[$item['data_type']] ?? 'fas fa-question'; ?> mr-1"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $item['data_type'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php
                                        if ($item['data_type'] === 'user') {
                                            echo htmlspecialchars($item['full_name']);
                                        } elseif ($item['data_type'] === 'residence') {
                                            echo htmlspecialchars($item['house_no']);
                                        } elseif ($item['data_type'] === 'family_member') {
                                            echo htmlspecialchars($item['name']);
                                        }
                                        ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php
                                        if ($item['data_type'] === 'user') {
                                            echo '@' . htmlspecialchars($item['username']);
                                        } elseif ($item['data_type'] === 'residence') {
                                            echo htmlspecialchars($item['resident_name']);
                                        } elseif ($item['data_type'] === 'family_member') {
                                            echo htmlspecialchars($item['relationship']);
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php
                                    if ($item['data_type'] === 'user') {
                                        echo htmlspecialchars($item['ward_name'] ?? 'Not assigned');
                                    } elseif ($item['data_type'] === 'residence') {
                                        echo htmlspecialchars($item['ward_name'] ?? 'Unknown') . ' - ' . htmlspecialchars($item['village_name'] ?? 'Unknown');
                                    } elseif ($item['data_type'] === 'family_member') {
                                        echo htmlspecialchars($item['ward_name'] ?? 'Unknown') . ' - ' . htmlspecialchars($item['village_name'] ?? 'Unknown');
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex items-center">
                                        <i class="fas fa-clock mr-2 text-gray-400"></i>
                                        <?php echo formatDate($item['deleted_at']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="?restore=<?php echo $item['id']; ?>&type=<?php echo $item['data_type']; ?>" 
                                           class="text-green-600 hover:text-green-900"
                                           onclick="return confirm('Are you sure you want to restore this item?')"
                                           title="Restore">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                        <a href="?permanent_delete=<?php echo $item['id']; ?>&type=<?php echo $item['data_type']; ?>" 
                                           class="text-red-600 hover:text-red-900"
                                           onclick="return confirm('Are you sure you want to permanently delete this item? This action cannot be undone.')"
                                           title="Permanent Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmRestore(message) {
    return confirm(message);
}

function confirmPermanentDelete(message) {
    return confirm(message);
}
</script>

<?php include 'includes/footer.php'; ?>
