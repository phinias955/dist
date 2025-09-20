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

// Get detailed residence data based on user role
$report_data = [];
$report_title = '';
$report_subtitle = '';

try {
    if ($user_role === 'super_admin') {
        // Super Admin sees all data
        $report_title = 'Detailed Residence Report - System Wide';
        $report_subtitle = 'All Wards and Villages';
        
        $stmt = $pdo->query("
            SELECT r.*, v.village_name, w.ward_name,
                   COUNT(fm.id) as family_member_count
            FROM residences r
            LEFT JOIN villages v ON r.village_id = v.id
            LEFT JOIN wards w ON v.ward_id = w.id
            LEFT JOIN family_members fm ON r.id = fm.residence_id
            WHERE r.status = 'active'
            GROUP BY r.id
            ORDER BY w.ward_name, v.village_name, r.resident_name
        ");
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'admin') {
        // Admin sees their ward data
        $report_title = 'Detailed Residence Report - Ward Administration';
        $report_subtitle = 'Ward: ' . $user_location['ward_name'];
        
        $stmt = $pdo->prepare("
            SELECT r.*, v.village_name, w.ward_name,
                   COUNT(fm.id) as family_member_count
            FROM residences r
            LEFT JOIN villages v ON r.village_id = v.id
            LEFT JOIN wards w ON v.ward_id = w.id
            LEFT JOIN family_members fm ON r.id = fm.residence_id
            WHERE v.ward_id = ? AND r.status = 'active'
            GROUP BY r.id
            ORDER BY v.village_name, r.resident_name
        ");
        $stmt->execute([$user_location['ward_id']]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'weo') {
        // WEO sees their ward data
        $report_title = 'Detailed Residence Report - Ward Executive Officer';
        $report_subtitle = 'Ward: ' . $user_location['ward_name'];
        
        $stmt = $pdo->prepare("
            SELECT r.*, v.village_name, w.ward_name,
                   COUNT(fm.id) as family_member_count
            FROM residences r
            LEFT JOIN villages v ON r.village_id = v.id
            LEFT JOIN wards w ON v.ward_id = w.id
            LEFT JOIN family_members fm ON r.id = fm.residence_id
            WHERE v.ward_id = ? AND r.status = 'active'
            GROUP BY r.id
            ORDER BY v.village_name, r.resident_name
        ");
        $stmt->execute([$user_location['ward_id']]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'veo') {
        // VEO sees their village data
        $report_title = 'Detailed Residence Report - Village Executive Officer';
        $report_subtitle = 'Village: ' . $user_location['village_name'];
        
        $stmt = $pdo->prepare("
            SELECT r.*, v.village_name, w.ward_name,
                   COUNT(fm.id) as family_member_count
            FROM residences r
            LEFT JOIN villages v ON r.village_id = v.id
            LEFT JOIN wards w ON v.ward_id = w.id
            LEFT JOIN family_members fm ON r.id = fm.residence_id
            WHERE r.village_id = ? AND r.status = 'active'
            GROUP BY r.id
            ORDER BY r.resident_name
        ");
        $stmt->execute([$user_location['village_id']]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error_message = "Error generating report: " . $e->getMessage();
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
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 11px; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header-info { margin: 20px 0; }
        .header-info p { margin: 5px 0; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <h1>' . $report_title . '</h1>
    <div class="header-info">
        <p><strong>Generated on:</strong> ' . date('Y-m-d H:i:s') . '</p>
        <p><strong>Generated by:</strong> ' . $_SESSION['username'] . ' (' . getRoleDisplayName($user_role) . ')</p>
        <p><strong>Scope:</strong> ' . $report_subtitle . '</p>
    </div>';
        
        if (!empty($report_data)) {
            $html .= '<table>
                <thead>
                    <tr>
                        <th>Ward</th>
                        <th>Village</th>
                        <th>Resident Name</th>
                        <th>House No</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>NIDA Number</th>
                        <th>Phone</th>
                        <th>Occupation</th>
                        <th>Ownership</th>
                        <th>Education Level</th>
                        <th>Employment Status</th>
                        <th>Family Members</th>
                        <th>Email</th>
                        <th>Registered Date</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($report_data as $row) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($row['ward_name']) . '</td>
                    <td>' . htmlspecialchars($row['village_name']) . '</td>
                    <td>' . htmlspecialchars($row['resident_name']) . '</td>
                    <td>' . htmlspecialchars($row['house_no']) . '</td>
                    <td>' . htmlspecialchars($row['gender']) . '</td>
                    <td>' . htmlspecialchars($row['date_of_birth']) . '</td>
                    <td>' . htmlspecialchars($row['nida_number']) . '</td>
                    <td>' . htmlspecialchars($row['phone']) . '</td>
                    <td>' . htmlspecialchars($row['occupation']) . '</td>
                    <td>' . htmlspecialchars($row['ownership']) . '</td>
                    <td>' . htmlspecialchars($row['education_level']) . '</td>
                    <td>' . htmlspecialchars($row['employment_status']) . '</td>
                    <td>' . $row['family_member_count'] . '</td>
                    <td>' . htmlspecialchars($row['email']) . '</td>
                    <td>' . date('Y-m-d', strtotime($row['created_at'])) . '</td>
                </tr>';
            }
            
            $html .= '</tbody>
            </table>';
        } else {
            $html .= '<p>No data available for the selected criteria.</p>';
        }
        
        $html .= '</body></html>';
        
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="detailed_residence_report_' . date('Y-m-d_H-i-s') . '.pdf"');
        
        // For now, output HTML that can be printed as PDF by browser
        echo $html;
        exit();
        
    } elseif ($export_format === 'excel') {
        // Generate Excel report
        $filename = 'detailed_residence_report_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Create Excel-compatible format
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\">\n";
        echo "<Worksheet ss:Name=\"Detailed Residence Report\">\n";
        echo "<Table>\n";
        
        // Header row
        echo "<Row>\n";
        $headers = ['Ward', 'Village', 'Resident Name', 'House No', 'Gender', 'Date of Birth', 
                   'NIDA Number', 'Phone', 'Occupation', 'Ownership', 'Education Level', 
                   'Employment Status', 'Family Members', 'Email', 'Registered Date'];
        
        foreach ($headers as $header) {
            echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($header) . "</Data></Cell>\n";
        }
        echo "</Row>\n";
        
        // Data rows
        foreach ($report_data as $data_row) {
            echo "<Row>\n";
            $values = [
                $data_row['ward_name'], $data_row['village_name'], $data_row['resident_name'], 
                $data_row['house_no'], $data_row['gender'], $data_row['date_of_birth'],
                $data_row['nida_number'], $data_row['phone'], $data_row['occupation'], 
                $data_row['ownership'], $data_row['education_level'], $data_row['employment_status'],
                $data_row['family_member_count'], $data_row['email'], 
                date('Y-m-d', strtotime($data_row['created_at']))
            ];
            
            foreach ($values as $value) {
                echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($value) . "</Data></Cell>\n";
            }
            echo "</Row>\n";
        }
        
        echo "</Table>\n";
        echo "</Worksheet>\n";
        echo "</Workbook>\n";
        exit();
        
    } elseif ($export_format === 'csv') {
        // Generate CSV report
        $filename = 'detailed_residence_report_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, ['Ward', 'Village', 'Resident Name', 'House No', 'Gender', 'Date of Birth', 
                         'NIDA Number', 'Phone', 'Occupation', 'Ownership', 'Education Level', 
                         'Employment Status', 'Family Members', 'Email', 'Registered Date']);
        
        // Data rows
        foreach ($report_data as $data_row) {
            fputcsv($output, [
                $data_row['ward_name'], $data_row['village_name'], $data_row['resident_name'], 
                $data_row['house_no'], $data_row['gender'], $data_row['date_of_birth'],
                $data_row['nida_number'], $data_row['phone'], $data_row['occupation'], 
                $data_row['ownership'], $data_row['education_level'], $data_row['employment_status'],
                $data_row['family_member_count'], $data_row['email'], 
                date('Y-m-d', strtotime($data_row['created_at']))
            ]);
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
                <h4 class="page-title">Detailed Residence Report</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="reports_dashboard.php">Reports</a></li>
                        <li class="breadcrumb-item active">Detailed Residence Report</li>
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
                                    <strong>Generated by:</strong> <?php echo $_SESSION['username']; ?> (<?php echo getRoleDisplayName($user_role); ?>)<br>
                                    <strong>Total Records:</strong> <?php echo count($report_data); ?>
                                </p>
                            </div>
                        </div>

                        <?php if (!empty($report_data)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Ward</th>
                                            <th>Village</th>
                                            <th>Resident Name</th>
                                            <th>House No</th>
                                            <th>Gender</th>
                                            <th>Date of Birth</th>
                                            <th>NIDA Number</th>
                                            <th>Phone</th>
                                            <th>Occupation</th>
                                            <th>Ownership</th>
                                            <th>Education Level</th>
                                            <th>Employment Status</th>
                                            <th>Family Members</th>
                                            <th>Email</th>
                                            <th>Registered Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['ward_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['village_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['resident_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['house_no']); ?></td>
                                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                                <td><?php echo htmlspecialchars($row['date_of_birth']); ?></td>
                                                <td><?php echo htmlspecialchars($row['nida_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                                <td><?php echo htmlspecialchars($row['occupation']); ?></td>
                                                <td><?php echo htmlspecialchars($row['ownership']); ?></td>
                                                <td><?php echo htmlspecialchars($row['education_level']); ?></td>
                                                <td><?php echo htmlspecialchars($row['employment_status']); ?></td>
                                                <td><?php echo $row['family_member_count']; ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h5>No Data Available</h5>
                                <p>No detailed residence data found for the selected criteria.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
