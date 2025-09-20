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
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            .page-break { page-break-before: always; }
        }
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            line-height: 1.4;
        }
        h1 { 
            color: #333; 
            border-bottom: 2px solid #333; 
            padding-bottom: 10px; 
            margin-bottom: 20px;
        }
        h2 { 
            color: #666; 
            margin-top: 30px; 
            margin-bottom: 15px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
            font-size: 10px;
        }
        th, td { 
            border: 1px solid #333; 
            padding: 4px; 
            text-align: left; 
            vertical-align: top;
        }
        th { 
            background-color: #f5f5f5; 
            font-weight: bold; 
            font-size: 9px;
        }
        .header-info { 
            margin: 20px 0; 
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        .header-info p { 
            margin: 5px 0; 
            font-size: 13px;
        }
        .print-instructions {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            font-size: 12px;
        }
        .print-button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 0;
        }
        .print-button:hover {
            background-color: #0056b3;
        }
    </style>
    <script>
        function printReport() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="no-print">
        <div class="print-instructions">
            <strong>Instructions:</strong> Click the "Print Report" button below to open the print dialog. 
            In the print dialog, select "Save as PDF" as your destination to generate a PDF file.
        </div>
        <button class="print-button" onclick="printReport()">🖨️ Print Report</button>
    </div>
    
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
                    <td>' . date('Y-m-d', strtotime($row['registered_at'] ?? 'now')) . '</td>
                </tr>';
            }
            
            $html .= '</tbody>
            </table>';
        } else {
            $html .= '<p>No data available for the selected criteria.</p>';
        }
        
        $html .= '</body></html>';
        
        // Set headers for HTML display (not PDF)
        header('Content-Type: text/html; charset=UTF-8');
        
        // Output HTML that can be printed as PDF by browser
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
                date('Y-m-d', strtotime($data_row['registered_at'] ?? 'now'))
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
                date('Y-m-d', strtotime($data_row['registered_at'] ?? 'now'))
            ]);
        }
        
        fclose($output);
        exit();
    }
}

include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $report_title; ?> - Residence Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #6c757d;
            --success: #198754;
            --info: #0dcaf0;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #212529;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --border-radius: 12px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fb;
            color: var(--gray-800);
            line-height: 1.6;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Hero Section */
        .dashboard-hero {
            background: linear-gradient(135deg, var(--primary) 0%, #5e72e4 100%);
            border-radius: var(--border-radius);
            padding: 30px;
            color: white;
            margin-bottom: 24px;
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
        }

        .hero-pattern {
            position: absolute;
            top: -50px;
            right: -50px;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .hero-pattern::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 50px;
            margin-bottom: 16px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .hero-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .title-highlight {
            color: #ffd166;
        }

        .hero-description {
            font-size: 16px;
            opacity: 0.9;
            max-width: 600px;
            margin-bottom: 24px;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .cta-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .cta-button.primary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .cta-button.primary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .cta-button.secondary {
            background: transparent;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .cta-button.secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        /* Data Table */
        .data-table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, var(--gray-800), var(--gray-700));
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .record-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .modern-table thead th {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid var(--gray-200);
        }

        .modern-table tbody tr {
            transition: var(--transition);
            border-bottom: 1px solid var(--gray-200);
        }

        .modern-table tbody tr:hover {
            background: var(--gray-50);
        }

        .modern-table tbody td {
            padding: 16px 12px;
            vertical-align: middle;
        }

        .data-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .data-badge.primary {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .data-badge.success {
            background: rgba(25, 135, 84, 0.1);
            color: var(--success);
        }

        .data-badge.warning {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .data-badge.info {
            background: rgba(13, 202, 240, 0.1);
            color: var(--info);
        }

        .data-badge.danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .data-badge.secondary {
            background: rgba(108, 117, 125, 0.1);
            color: var(--secondary);
        }

        .phone-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .phone-link:hover {
            color: var(--primary-dark);
            text-decoration: none;
        }

        .nida-code {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 4px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            font-weight: 600;
        }

        .avatar-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            margin-right: 8px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .dashboard-hero {
                padding: 20px;
            }
            
            .hero-title {
                font-size: 24px;
            }
            
            .hero-actions {
                flex-direction: column;
            }
            
            .modern-table {
                font-size: 12px;
            }
            
            .modern-table thead th,
            .modern-table tbody td {
                padding: 12px 8px;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Hero Section -->
        <div class="dashboard-hero fade-in">
            <div class="hero-background">
                <div class="hero-pattern"></div>
            </div>
            <div class="hero-content">
                <div class="hero-badge">
                    <span class="badge-icon">🏠</span>
                    <span class="badge-text">Detailed Residence Report</span>
                </div>
                <h1 class="hero-title">
                    <?php echo $report_title; ?>
                    <span class="title-highlight">Details</span>
                </h1>
                <p class="hero-description">
                    <?php echo $report_subtitle; ?><br>
                    Comprehensive residence information with detailed analytics.
                </p>
                <div class="hero-actions">
                    <a href="?export=pdf" class="cta-button primary">
                        <i class="fas fa-file-pdf"></i>
                        <span>Export PDF</span>
                    </a>
                    <a href="?export=excel" class="cta-button secondary">
                        <i class="fas fa-file-excel"></i>
                        <span>Export Excel</span>
                    </a>
                    <a href="?export=csv" class="cta-button secondary">
                        <i class="fas fa-file-csv"></i>
                        <span>Export CSV</span>
                    </a>
                    <a href="reports_dashboard.php" class="cta-button secondary">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Reports</span>
                    </a>
                </div>
            </div>
        </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
             
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                        </div>
                    <?php else: ?>
                        <div class="row mb-3">
                           
                            <div class="col-md-6 text-right">
                                <p class="text-muted">
                                    <strong>Generated on:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
                                    <strong>Generated by:</strong> <?php echo $_SESSION['username']; ?> (<?php echo getRoleDisplayName($user_role); ?>)<br>
                                    <strong>Total Records:</strong> <?php echo count($report_data); ?>
                                </p>
                            </div>
                        </div>

                        <?php if (!empty($report_data)): ?>
                            <div class="data-table-container fade-in">
                                <div class="table-header">
                                    <h3>
                                        <i class="fas fa-table me-2"></i>
                                        Detailed Residence Data
                                    </h3>
                                    <span class="record-count"><?php echo count($report_data); ?> records</span>
                                </div>
                                <div class="table-wrapper">
                                    <table class="modern-table">
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
                                                <th>Education</th>
                                                <th>Employment</th>
                                                <th>Family</th>
                                                <th>Email</th>
                                                <th>Registered</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                                <tr>
                                                    <td>
                                                        <span class="data-badge primary"><?php echo htmlspecialchars($row['ward_name']); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="data-badge info"><?php echo htmlspecialchars($row['village_name']); ?></span>
                                                    </td>
                                                    <td class="fw-medium"><?php echo htmlspecialchars($row['resident_name']); ?></td>
                                                    <td>
                                                        <span class="data-badge success"><?php echo htmlspecialchars($row['house_no']); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="data-badge <?php echo strtolower($row['gender']) === 'male' ? 'info' : 'warning'; ?>">
                                                            <i class="fas fa-<?php echo $row['gender'] === 'Male' ? 'mars' : 'venus'; ?> me-1"></i>
                                                            <?php echo htmlspecialchars($row['gender']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['date_of_birth']); ?></td>
                                                    <td>
                                                        <code class="nida-code"><?php echo htmlspecialchars($row['nida_number']); ?></code>
                                                    </td>
                                                    <td>
                                                        <a href="tel:<?php echo htmlspecialchars($row['phone']); ?>" class="phone-link">
                                                            <i class="fas fa-phone me-1"></i>
                                                            <?php echo htmlspecialchars($row['phone']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['occupation']); ?></td>
                                                    <td>
                                                        <span class="data-badge <?php echo strtolower($row['ownership']) === 'owner' ? 'success' : 'secondary'; ?>">
                                                            <i class="fas fa-<?php echo $row['ownership'] === 'Owner' ? 'key' : 'key-off'; ?> me-1"></i>
                                                            <?php echo htmlspecialchars($row['ownership']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['education_level']); ?></td>
                                                    <td>
                                                        <span class="data-badge <?php echo strtolower($row['employment_status']) === 'employed' ? 'success' : 'danger'; ?>">
                                                            <i class="fas fa-<?php echo $row['employment_status'] === 'Employed' ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                                            <?php echo htmlspecialchars($row['employment_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="data-badge info">
                                                            <i class="fas fa-users me-1"></i>
                                                            <?php echo $row['family_member_count']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($row['registered_at'] ?? 'now')); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
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
