<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireRole('super_admin');

$page_title = 'System Settings';

$message = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settings = $_POST['settings'] ?? [];
    
    try {
        $success_count = 0;
        foreach ($settings as $setting_key => $setting_value) {
            if (updateSystemSetting($setting_key, $setting_value)) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            $message = "Successfully updated {$success_count} setting(s)";
        } else {
            $error = 'No settings were updated';
        }
    } catch (Exception $e) {
        $error = 'Error updating settings';
    }
}

// Get all system settings
$settings = getAllSystemSettings();

include 'includes/header.php';
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">System Settings</h1>
        <div class="text-sm text-gray-600">
            <i class="fas fa-cog mr-2"></i>
            Configure system behavior and preferences
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
    
    <form method="POST" class="space-y-6">
        <input type="hidden" name="update_settings" value="1">
        
        <!-- General Settings -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-cog mr-2"></i>General Settings
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($settings as $setting): ?>
                    <?php if (in_array($setting['setting_key'], ['system_name', 'system_version', 'notification_email'])): ?>
                        <div>
                            <label for="setting_<?php echo $setting['setting_key']; ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $setting['setting_name']; ?>
                            </label>
                            <input type="text" 
                                   id="setting_<?php echo $setting['setting_key']; ?>" 
                                   name="settings[<?php echo $setting['setting_key']; ?>]" 
                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1"><?php echo $setting['description']; ?></p>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- System Behavior -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-sliders-h mr-2"></i>System Behavior
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($settings as $setting): ?>
                    <?php if (in_array($setting['setting_key'], ['max_residences_per_user', 'session_timeout'])): ?>
                        <div>
                            <label for="setting_<?php echo $setting['setting_key']; ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $setting['setting_name']; ?>
                            </label>
                            <input type="number" 
                                   id="setting_<?php echo $setting['setting_key']; ?>" 
                                   name="settings[<?php echo $setting['setting_key']; ?>]" 
                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1"><?php echo $setting['description']; ?></p>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Feature Toggles -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-toggle-on mr-2"></i>Feature Toggles
            </h3>
            
            <div class="space-y-4">
                <?php foreach ($settings as $setting): ?>
                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900"><?php echo $setting['setting_name']; ?></h4>
                                <p class="text-xs text-gray-500"><?php echo $setting['description']; ?></p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" 
                                       name="settings[<?php echo $setting['setting_key']; ?>]" 
                                       value="true"
                                       <?php echo $setting['setting_value'] === 'true' ? 'checked' : ''; ?>
                                       class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Backup Settings -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-database mr-2"></i>Backup & Maintenance
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($settings as $setting): ?>
                    <?php if (in_array($setting['setting_key'], ['backup_frequency', 'maintenance_mode'])): ?>
                        <div>
                            <label for="setting_<?php echo $setting['setting_key']; ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $setting['setting_name']; ?>
                            </label>
                            <?php if ($setting['setting_key'] === 'backup_frequency'): ?>
                                <select id="setting_<?php echo $setting['setting_key']; ?>" 
                                        name="settings[<?php echo $setting['setting_key']; ?>]"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="daily" <?php echo $setting['setting_value'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo $setting['setting_value'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo $setting['setting_value'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            <?php else: ?>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           name="settings[<?php echo $setting['setting_key']; ?>]" 
                                           value="true"
                                           <?php echo $setting['setting_value'] === 'true' ? 'checked' : ''; ?>
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mt-1"><?php echo $setting['description']; ?></p>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="flex justify-end">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
