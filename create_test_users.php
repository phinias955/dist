<?php
require_once 'config/database.php';

echo "<h2>Creating Test Users...</h2>";

try {
    // Create test users
    $users = [
        [
            'full_name' => 'Super Administrator',
            'username' => 'superadmin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'super_admin',
            'nida_number' => '12345678901234567890',
            'assigned_ward_id' => 1,
            'assigned_village_id' => null,
            'is_active' => 1
        ],
        [
            'full_name' => 'Ward Administrator',
            'username' => 'admin1',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'nida_number' => '12345678901234567891',
            'assigned_ward_id' => 1,
            'assigned_village_id' => null,
            'is_active' => 1
        ],
        [
            'full_name' => 'Ward Executive Officer',
            'username' => 'weo1',
            'password' => password_hash('weo123', PASSWORD_DEFAULT),
            'role' => 'weo',
            'nida_number' => '12345678901234567892',
            'assigned_ward_id' => 1,
            'assigned_village_id' => null,
            'is_active' => 1
        ],
        [
            'full_name' => 'Village Executive Officer',
            'username' => 'veo1',
            'password' => password_hash('veo123', PASSWORD_DEFAULT),
            'role' => 'veo',
            'nida_number' => '12345678901234567893',
            'assigned_ward_id' => 1,
            'assigned_village_id' => 1,
            'is_active' => 1
        ],
        [
            'full_name' => 'Data Collector',
            'username' => 'datacollector1',
            'password' => password_hash('collector123', PASSWORD_DEFAULT),
            'role' => 'data_collector',
            'nida_number' => '12345678901234567894',
            'assigned_ward_id' => 1,
            'assigned_village_id' => 1,
            'is_active' => 1
        ]
    ];

    foreach ($users as $user) {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$user['username']]);
        
        if ($stmt->fetch()) {
            echo "<p>✅ User '{$user['username']}' already exists</p>";
        } else {
            // Insert user
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, username, password, role, nida_number, assigned_ward_id, assigned_village_id, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $user['full_name'],
                $user['username'],
                $user['password'],
                $user['role'],
                $user['nida_number'],
                $user['assigned_ward_id'],
                $user['assigned_village_id'],
                $user['is_active']
            ])) {
                echo "<p>✅ Created user '{$user['username']}' with role '{$user['role']}'</p>";
            } else {
                echo "<p>❌ Failed to create user '{$user['username']}'</p>";
            }
        }
    }

    echo "<h3>Login Credentials:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Role</th><th>Username</th><th>Password</th><th>Access URL</th></tr>";
    echo "<tr><td>Super Admin</td><td>superadmin</td><td>admin123</td><td><a href='index.php'>Main System</a></td></tr>";
    echo "<tr><td>Ward Admin</td><td>admin1</td><td>admin123</td><td><a href='index.php'>Main System</a></td></tr>";
    echo "<tr><td>WEO</td><td>weo1</td><td>weo123</td><td><a href='index.php'>Main System</a></td></tr>";
    echo "<tr><td>VEO</td><td>veo1</td><td>veo123</td><td><a href='index.php'>Main System</a></td></tr>";
    echo "<tr><td>Data Collector</td><td>datacollector1</td><td>collector123</td><td><a href='data_collector/login.php'>Mobile System</a></td></tr>";
    echo "</table>";

} catch (PDOException $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
