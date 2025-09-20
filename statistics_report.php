<?php
session_start();
require_once 'includes/functions.php';
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user can access reports
if (!canAccessPage('reports')) {
    header('Location: unauthorized.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_location = getUserLocationInfo($user_id);

// Get statistics data based on user role
$stats_data = [];
$report_title = '';
$report_subtitle = '';

try {
    if ($user_role === 'super_admin') {
        // Super Admin sees all statistics
        $report_title = 'Statistics Report - System Wide';
        $report_subtitle = 'All Wards and Villages';
        
        // Basic counts
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM residences WHERE status = 'active'");
        $stats_data['total_residences'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM family_members");
        $stats_data['total_family_members'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM wards");
        $stats_data['total_wards'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM villages");
        $stats_data['total_villages'] = $stmt->fetch()['total'];
        
        // Gender distribution
        $stmt = $pdo->query("
            SELECT gender, COUNT(*) as count 
            FROM residences 
            WHERE status = 'active' AND gender IS NOT NULL 
            GROUP BY gender
        ");
        $stats_data['gender_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ownership distribution
        $stmt = $pdo->query("
            SELECT ownership, COUNT(*) as count 
            FROM residences 
            WHERE status = 'active' AND ownership IS NOT NULL 
            GROUP BY ownership
        ");
        $stats_data['ownership_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Education level distribution
        $stmt = $pdo->query("
            SELECT education_level, COUNT(*) as count 
            FROM residences 
            WHERE status = 'active' AND education_level IS NOT NULL 
            GROUP BY education_level
        ");
        $stats_data['education_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Employment status distribution
        $stmt = $pdo->query("
            SELECT employment_status, COUNT(*) as count 
            FROM residences 
            WHERE status = 'active' AND employment_status IS NOT NULL 
            GROUP BY employment_status
        ");
        $stats_data['employment_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ward-wise distribution
        $stmt = $pdo->query("
            SELECT w.ward_name, COUNT(r.id) as residence_count, COUNT(fm.id) as family_count
            FROM wards w
            LEFT JOIN villages v ON w.id = v.ward_id
            LEFT JOIN residences r ON v.id = r.village_id AND r.status = 'active'
            LEFT JOIN family_members fm ON r.id = fm.residence_id
            GROUP BY w.id, w.ward_name
            ORDER BY residence_count DESC
        ");
        $stats_data['ward_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'admin' || $user_role === 'weo') {
        // Admin/WEO sees ward statistics
        $report_title = 'Statistics Report - Ward ' . ($user_role === 'admin' ? 'Administration' : 'Executive Officer');
        $report_subtitle = 'Ward: ' . $user_location['ward_name'];
        
        // Basic counts for ward
        $stmt = $pdo->prepare("
            SELECT COUNT(r.id) as total 
            FROM residences r 
            JOIN villages v ON r.village_id = v.id 
            WHERE v.ward_id = ? AND r.status = 'active'
        ");
        $stmt->execute([$user_location['ward_id']]);
        $stats_data['total_residences'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(fm.id) as total 
            FROM family_members fm 
            JOIN residences r ON fm.residence_id = r.id 
            JOIN villages v ON r.village_id = v.id 
            WHERE v.ward_id = ?
        ");
        $stmt->execute([$user_location['ward_id']]);
        $stats_data['total_family_members'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(v.id) as total 
            FROM villages v 
            WHERE v.ward_id = ?
        ");
        $stmt->execute([$user_location['ward_id']]);
        $stats_data['total_villages'] = $stmt->fetch()['total'];
        
        // Gender distribution for ward
        $stmt = $pdo->prepare("
            SELECT r.gender, COUNT(*) as count 
            FROM residences r 
            JOIN villages v ON r.village_id = v.id 
            WHERE v.ward_id = ? AND r.status = 'active' AND r.gender IS NOT NULL 
            GROUP BY r.gender
        ");
        $stmt->execute([$user_location['ward_id']]);
        $stats_data['gender_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ownership distribution for ward
        $stmt = $pdo->prepare("
            SELECT r.ownership, COUNT(*) as count 
            FROM residences r 
            JOIN villages v ON r.village_id = v.id 
            WHERE v.ward_id = ? AND r.status = 'active' AND r.ownership IS NOT NULL 
            GROUP BY r.ownership
        ");
        $stmt->execute([$user_location['ward_id']]);
        $stats_data['ownership_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Village-wise distribution
        $stmt = $pdo->prepare("
            SELECT v.village_name, COUNT(r.id) as residence_count, COUNT(fm.id) as family_count
            FROM villages v
            LEFT JOIN residences r ON v.id = r.village_id AND r.status = 'active'
            LEFT JOIN family_members fm ON r.id = fm.residence_id
            WHERE v.ward_id = ?
            GROUP BY v.id, v.village_name
            ORDER BY residence_count DESC
        ");
        $stmt->execute([$user_location['ward_id']]);
        $stats_data['village_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'veo') {
        // VEO sees village statistics
        $report_title = 'Statistics Report - Village Executive Officer';
        $report_subtitle = 'Village: ' . $user_location['village_name'];
        
        // Basic counts for village
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM residences 
            WHERE village_id = ? AND status = 'active'
        ");
        $stmt->execute([$user_location['village_id']]);
        $stats_data['total_residences'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(fm.id) as total 
            FROM family_members fm 
            JOIN residences r ON fm.residence_id = r.id 
            WHERE r.village_id = ?
        ");
        $stmt->execute([$user_location['village_id']]);
        $stats_data['total_family_members'] = $stmt->fetch()['total'];
        
        // Gender distribution for village
        $stmt = $pdo->prepare("
            SELECT gender, COUNT(*) as count 
            FROM residences 
            WHERE village_id = ? AND status = 'active' AND gender IS NOT NULL 
            GROUP BY gender
        ");
        $stmt->execute([$user_location['village_id']]);
        $stats_data['gender_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ownership distribution for village
        $stmt = $pdo->prepare("
            SELECT ownership, COUNT(*) as count 
            FROM residences 
            WHERE village_id = ? AND status = 'active' AND ownership IS NOT NULL 
            GROUP BY ownership
        ");
        $stmt->execute([$user_location['village_id']]);
        $stats_data['ownership_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Education level distribution for village
        $stmt = $pdo->prepare("
            SELECT education_level, COUNT(*) as count 
            FROM residences 
            WHERE village_id = ? AND status = 'active' AND education_level IS NOT NULL 
            GROUP BY education_level
        ");
        $stmt->execute([$user_location['village_id']]);
        $stats_data['education_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Employment status distribution for village
        $stmt = $pdo->prepare("
            SELECT employment_status, COUNT(*) as count 
            FROM residences 
            WHERE village_id = ? AND status = 'active' AND employment_status IS NOT NULL 
            GROUP BY employment_status
        ");
        $stmt->execute([$user_location['village_id']]);
        $stats_data['employment_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error_message = "Error generating statistics: " . $e->getMessage();
}

// Handle export requests
if (isset($_GET['export'])) {
    $export_format = $_GET['export'];
    
    if ($export_format === 'pdf') {
        // Generate PDF report
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . $report_title . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; }
        h3 { color: #888; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 11px; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header-info { margin: 20px 0; }
        .header-info p { margin: 5px 0; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0; }
        .stat-card { border: 1px solid #ddd; padding: 15px; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #333; }
        .stat-label { color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <h1>' . $report_title . '</h1>
    <div class="header-info">
        <p><strong>Generated on:</strong> ' . date('Y-m-d H:i:s') . '</p>
        <p><strong>Generated by:</strong> ' . $_SESSION['username'] . ' (' . getRoleDisplayName($user_role) . ')</p>
        <p><strong>Scope:</strong> ' . $report_subtitle . '</p>
    </div>';
        
        // Basic statistics
        $html .= '<div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">' . ($stats_data['total_residences'] ?? 0) . '</div>
                <div class="stat-label">Total Residences</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . ($stats_data['total_family_members'] ?? 0) . '</div>
                <div class="stat-label">Total Family Members</div>
            </div>';
        
        if (isset($stats_data['total_wards'])) {
            $html .= '<div class="stat-card">
                <div class="stat-number">' . $stats_data['total_wards'] . '</div>
                <div class="stat-label">Total Wards</div>
            </div>';
        }
        
        if (isset($stats_data['total_villages'])) {
            $html .= '<div class="stat-card">
                <div class="stat-number">' . $stats_data['total_villages'] . '</div>
                <div class="stat-label">Total Villages</div>
            </div>';
        }
        
        $html .= '</div>';
        
        // Gender distribution
        if (!empty($stats_data['gender_distribution'])) {
            $html .= '<h3>Gender Distribution</h3>
            <table>
                <thead><tr><th>Gender</th><th>Count</th><th>Percentage</th></tr></thead>
                <tbody>';
            
            $total_gender = array_sum(array_column($stats_data['gender_distribution'], 'count'));
            foreach ($stats_data['gender_distribution'] as $row) {
                $percentage = $total_gender > 0 ? round(($row['count'] / $total_gender) * 100, 2) : 0;
                $html .= '<tr><td>' . htmlspecialchars($row['gender']) . '</td><td>' . $row['count'] . '</td><td>' . $percentage . '%</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        
        // Ownership distribution
        if (!empty($stats_data['ownership_distribution'])) {
            $html .= '<h3>Ownership Distribution</h3>
            <table>
                <thead><tr><th>Ownership</th><th>Count</th><th>Percentage</th></tr></thead>
                <tbody>';
            
            $total_ownership = array_sum(array_column($stats_data['ownership_distribution'], 'count'));
            foreach ($stats_data['ownership_distribution'] as $row) {
                $percentage = $total_ownership > 0 ? round(($row['count'] / $total_ownership) * 100, 2) : 0;
                $html .= '<tr><td>' . htmlspecialchars($row['ownership']) . '</td><td>' . $row['count'] . '</td><td>' . $percentage . '%</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        
        // Education distribution
        if (!empty($stats_data['education_distribution'])) {
            $html .= '<h3>Education Level Distribution</h3>
            <table>
                <thead><tr><th>Education Level</th><th>Count</th><th>Percentage</th></tr></thead>
                <tbody>';
            
            $total_education = array_sum(array_column($stats_data['education_distribution'], 'count'));
            foreach ($stats_data['education_distribution'] as $row) {
                $percentage = $total_education > 0 ? round(($row['count'] / $total_education) * 100, 2) : 0;
                $html .= '<tr><td>' . htmlspecialchars($row['education_level']) . '</td><td>' . $row['count'] . '</td><td>' . $percentage . '%</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        
        // Employment distribution
        if (!empty($stats_data['employment_distribution'])) {
            $html .= '<h3>Employment Status Distribution</h3>
            <table>
                <thead><tr><th>Employment Status</th><th>Count</th><th>Percentage</th></tr></thead>
                <tbody>';
            
            $total_employment = array_sum(array_column($stats_data['employment_distribution'], 'count'));
            foreach ($stats_data['employment_distribution'] as $row) {
                $percentage = $total_employment > 0 ? round(($row['count'] / $total_employment) * 100, 2) : 0;
                $html .= '<tr><td>' . htmlspecialchars($row['employment_status']) . '</td><td>' . $row['count'] . '</td><td>' . $percentage . '%</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        
        // Ward/Village distribution
        if (!empty($stats_data['ward_distribution'])) {
            $html .= '<h3>Ward Distribution</h3>
            <table>
                <thead><tr><th>Ward</th><th>Residences</th><th>Family Members</th></tr></thead>
                <tbody>';
            
            foreach ($stats_data['ward_distribution'] as $row) {
                $html .= '<tr><td>' . htmlspecialchars($row['ward_name']) . '</td><td>' . $row['residence_count'] . '</td><td>' . $row['family_count'] . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        
        if (!empty($stats_data['village_distribution'])) {
            $html .= '<h3>Village Distribution</h3>
            <table>
                <thead><tr><th>Village</th><th>Residences</th><th>Family Members</th></tr></thead>
                <tbody>';
            
            foreach ($stats_data['village_distribution'] as $row) {
                $html .= '<tr><td>' . htmlspecialchars($row['village_name']) . '</td><td>' . $row['residence_count'] . '</td><td>' . $row['family_count'] . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        
        $html .= '</body></html>';
        
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="statistics_report_' . date('Y-m-d_H-i-s') . '.pdf"');
        
        // For now, output HTML that can be printed as PDF by browser
        echo $html;
        exit();
        
    } elseif ($export_format === 'excel') {
        // Generate Excel report
        $filename = 'statistics_report_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Create Excel-compatible format
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\">\n";
        echo "<Worksheet ss:Name=\"Statistics Report\">\n";
        echo "<Table>\n";
        
        // Basic statistics
        echo "<Row><Cell><Data ss:Type=\"String\">Basic Statistics</Data></Cell></Row>\n";
        echo "<Row><Cell><Data ss:Type=\"String\">Total Residences</Data></Cell><Cell><Data ss:Type=\"Number\">" . ($stats_data['total_residences'] ?? 0) . "</Data></Cell></Row>\n";
        echo "<Row><Cell><Data ss:Type=\"String\">Total Family Members</Data></Cell><Cell><Data ss:Type=\"Number\">" . ($stats_data['total_family_members'] ?? 0) . "</Data></Cell></Row>\n";
        
        if (isset($stats_data['total_wards'])) {
            echo "<Row><Cell><Data ss:Type=\"String\">Total Wards</Data></Cell><Cell><Data ss:Type=\"Number\">" . $stats_data['total_wards'] . "</Data></Cell></Row>\n";
        }
        
        if (isset($stats_data['total_villages'])) {
            echo "<Row><Cell><Data ss:Type=\"String\">Total Villages</Data></Cell><Cell><Data ss:Type=\"Number\">" . $stats_data['total_villages'] . "</Data></Cell></Row>\n";
        }
        
        echo "<Row></Row>\n"; // Empty row
        
        // Gender distribution
        if (!empty($stats_data['gender_distribution'])) {
            echo "<Row><Cell><Data ss:Type=\"String\">Gender Distribution</Data></Cell></Row>\n";
            echo "<Row><Cell><Data ss:Type=\"String\">Gender</Data></Cell><Cell><Data ss:Type=\"String\">Count</Data></Cell><Cell><Data ss:Type=\"String\">Percentage</Data></Cell></Row>\n";
            
            $total_gender = array_sum(array_column($stats_data['gender_distribution'], 'count'));
            foreach ($stats_data['gender_distribution'] as $row) {
                $percentage = $total_gender > 0 ? round(($row['count'] / $total_gender) * 100, 2) : 0;
                echo "<Row><Cell><Data ss:Type=\"String\">" . htmlspecialchars($row['gender']) . "</Data></Cell><Cell><Data ss:Type=\"Number\">" . $row['count'] . "</Data></Cell><Cell><Data ss:Type=\"Number\">" . $percentage . "</Data></Cell></Row>\n";
            }
            echo "<Row></Row>\n"; // Empty row
        }
        
        // Ownership distribution
        if (!empty($stats_data['ownership_distribution'])) {
            echo "<Row><Cell><Data ss:Type=\"String\">Ownership Distribution</Data></Cell></Row>\n";
            echo "<Row><Cell><Data ss:Type=\"String\">Ownership</Data></Cell><Cell><Data ss:Type=\"String\">Count</Data></Cell><Cell><Data ss:Type=\"String\">Percentage</Data></Cell></Row>\n";
            
            $total_ownership = array_sum(array_column($stats_data['ownership_distribution'], 'count'));
            foreach ($stats_data['ownership_distribution'] as $row) {
                $percentage = $total_ownership > 0 ? round(($row['count'] / $total_ownership) * 100, 2) : 0;
                echo "<Row><Cell><Data ss:Type=\"String\">" . htmlspecialchars($row['ownership']) . "</Data></Cell><Cell><Data ss:Type=\"Number\">" . $row['count'] . "</Data></Cell><Cell><Data ss:Type=\"Number\">" . $percentage . "</Data></Cell></Row>\n";
            }
            echo "<Row></Row>\n"; // Empty row
        }
        
        echo "</Table>\n";
        echo "</Worksheet>\n";
        echo "</Workbook>\n";
        exit();
        
    } elseif ($export_format === 'csv') {
        // Generate CSV report
        $filename = 'statistics_report_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Basic statistics
        fputcsv($output, ['Basic Statistics']);
        fputcsv($output, ['Total Residences', $stats_data['total_residences'] ?? 0]);
        fputcsv($output, ['Total Family Members', $stats_data['total_family_members'] ?? 0]);
        
        if (isset($stats_data['total_wards'])) {
            fputcsv($output, ['Total Wards', $stats_data['total_wards']]);
        }
        
        if (isset($stats_data['total_villages'])) {
            fputcsv($output, ['Total Villages', $stats_data['total_villages']]);
        }
        
        fputcsv($output, []); // Empty row
        
        // Gender distribution
        if (!empty($stats_data['gender_distribution'])) {
            fputcsv($output, ['Gender Distribution']);
            fputcsv($output, ['Gender', 'Count', 'Percentage']);
            
            $total_gender = array_sum(array_column($stats_data['gender_distribution'], 'count'));
            foreach ($stats_data['gender_distribution'] as $row) {
                $percentage = $total_gender > 0 ? round(($row['count'] / $total_gender) * 100, 2) : 0;
                fputcsv($output, [$row['gender'], $row['count'], $percentage]);
            }
            fputcsv($output, []); // Empty row
        }
        
        // Ownership distribution
        if (!empty($stats_data['ownership_distribution'])) {
            fputcsv($output, ['Ownership Distribution']);
            fputcsv($output, ['Ownership', 'Count', 'Percentage']);
            
            $total_ownership = array_sum(array_column($stats_data['ownership_distribution'], 'count'));
            foreach ($stats_data['ownership_distribution'] as $row) {
                $percentage = $total_ownership > 0 ? round(($row['count'] / $total_ownership) * 100, 2) : 0;
                fputcsv($output, [$row['ownership'], $row['count'], $percentage]);
            }
        }
        
        fclose($output);
        exit();
    }
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Statistics Report</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="reports_dashboard.php">Reports</a></li>
                        <li class="breadcrumb-item active">Statistics Report</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><?php echo $report_title; ?></h4>
                    <p class="text-muted"><?php echo $report_subtitle; ?></p>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                        </div>
                    <?php else: ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h5>Export Options</h5>
                                <div class="btn-group" role="group">
                                    <a href="?export=pdf" class="btn btn-danger">
                                        <i class="mdi mdi-file-pdf"></i> Export PDF
                                    </a>
                                    <a href="?export=excel" class="btn btn-success">
                                        <i class="mdi mdi-file-excel"></i> Export Excel
                                    </a>
                                    <a href="?export=csv" class="btn btn-info">
                                        <i class="mdi mdi-file-csv"></i> Export CSV
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">
                                <p class="text-muted">
                                    <strong>Generated on:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
                                    <strong>Generated by:</strong> <?php echo $_SESSION['username']; ?> (<?php echo getRoleDisplayName($user_role); ?>)
                                </p>
                            </div>
                        </div>

                        <!-- Basic Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h3 class="text-primary"><?php echo $stats_data['total_residences'] ?? 0; ?></h3>
                                        <p class="text-muted">Total Residences</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h3 class="text-success"><?php echo $stats_data['total_family_members'] ?? 0; ?></h3>
                                        <p class="text-muted">Total Family Members</p>
                                    </div>
                                </div>
                            </div>
                            <?php if (isset($stats_data['total_wards'])): ?>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h3 class="text-info"><?php echo $stats_data['total_wards']; ?></h3>
                                        <p class="text-muted">Total Wards</p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($stats_data['total_villages'])): ?>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h3 class="text-warning"><?php echo $stats_data['total_villages']; ?></h3>
                                        <p class="text-muted">Total Villages</p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Gender Distribution -->
                        <?php if (!empty($stats_data['gender_distribution'])): ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Gender Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Gender</th>
                                                        <th>Count</th>
                                                        <th>Percentage</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $total_gender = array_sum(array_column($stats_data['gender_distribution'], 'count'));
                                                    foreach ($stats_data['gender_distribution'] as $row): 
                                                        $percentage = $total_gender > 0 ? round(($row['count'] / $total_gender) * 100, 2) : 0;
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                                        <td><?php echo $row['count']; ?></td>
                                                        <td><?php echo $percentage; ?>%</td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Ownership Distribution -->
                        <?php if (!empty($stats_data['ownership_distribution'])): ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Ownership Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Ownership</th>
                                                        <th>Count</th>
                                                        <th>Percentage</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $total_ownership = array_sum(array_column($stats_data['ownership_distribution'], 'count'));
                                                    foreach ($stats_data['ownership_distribution'] as $row): 
                                                        $percentage = $total_ownership > 0 ? round(($row['count'] / $total_ownership) * 100, 2) : 0;
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['ownership']); ?></td>
                                                        <td><?php echo $row['count']; ?></td>
                                                        <td><?php echo $percentage; ?>%</td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Education Distribution -->
                        <?php if (!empty($stats_data['education_distribution'])): ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Education Level Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Education Level</th>
                                                        <th>Count</th>
                                                        <th>Percentage</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $total_education = array_sum(array_column($stats_data['education_distribution'], 'count'));
                                                    foreach ($stats_data['education_distribution'] as $row): 
                                                        $percentage = $total_education > 0 ? round(($row['count'] / $total_education) * 100, 2) : 0;
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['education_level']); ?></td>
                                                        <td><?php echo $row['count']; ?></td>
                                                        <td><?php echo $percentage; ?>%</td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Employment Distribution -->
                        <?php if (!empty($stats_data['employment_distribution'])): ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Employment Status Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Employment Status</th>
                                                        <th>Count</th>
                                                        <th>Percentage</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $total_employment = array_sum(array_column($stats_data['employment_distribution'], 'count'));
                                                    foreach ($stats_data['employment_distribution'] as $row): 
                                                        $percentage = $total_employment > 0 ? round(($row['count'] / $total_employment) * 100, 2) : 0;
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['employment_status']); ?></td>
                                                        <td><?php echo $row['count']; ?></td>
                                                        <td><?php echo $percentage; ?>%</td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Ward Distribution -->
                        <?php if (!empty($stats_data['ward_distribution'])): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Ward Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Ward</th>
                                                        <th>Residences</th>
                                                        <th>Family Members</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($stats_data['ward_distribution'] as $row): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['ward_name']); ?></td>
                                                        <td><?php echo $row['residence_count']; ?></td>
                                                        <td><?php echo $row['family_count']; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Village Distribution -->
                        <?php if (!empty($stats_data['village_distribution'])): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Village Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Village</th>
                                                        <th>Residences</th>
                                                        <th>Family Members</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($stats_data['village_distribution'] as $row): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['village_name']); ?></td>
                                                        <td><?php echo $row['residence_count']; ?></td>
                                                        <td><?php echo $row['family_count']; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
