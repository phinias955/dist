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

// Get report data based on user role
$report_data = [];
$report_title = '';
$report_subtitle = '';

try {
    if ($user_role === 'super_admin') {
        // Super Admin sees all data
        $report_title = 'System Overview Report';
        $report_subtitle = 'All Wards and Villages';
        
        // Get all wards and villages
        $stmt = $pdo->query("
            SELECT w.ward_name, COUNT(DISTINCT v.id) as village_count, 
                   COUNT(DISTINCT r.id) as residence_count,
                   COUNT(DISTINCT fm.id) as family_member_count
            FROM wards w
            LEFT JOIN villages v ON w.id = v.ward_id
            LEFT JOIN residences r ON v.id = r.village_id AND r.status = 'active'
            LEFT JOIN family_members fm ON r.id = fm.residence_id
            GROUP BY w.id, w.ward_name
            ORDER BY w.ward_name
        ");
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'admin') {
        // Admin sees their ward data
        $report_title = 'Ward Administration Report';
        $report_subtitle = 'Ward: ' . $user_location['ward_name'];
        
        $stmt = $pdo->prepare("
            SELECT v.village_name, COUNT(DISTINCT r.id) as residence_count,
                   COUNT(DISTINCT fm.id) as family_member_count
            FROM villages v
            LEFT JOIN residences r ON v.id = r.village_id AND r.status = 'active'
            LEFT JOIN family_members fm ON r.id = fm.residence_id
            WHERE v.ward_id = ?
            GROUP BY v.id, v.village_name
            ORDER BY v.village_name
        ");
        $stmt->execute([$user_location['ward_id']]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'weo') {
        // WEO sees their ward data
        $report_title = 'Ward Executive Officer Report';
        $report_subtitle = 'Ward: ' . $user_location['ward_name'];
        
        $stmt = $pdo->prepare("
            SELECT v.village_name, COUNT(DISTINCT r.id) as residence_count,
                   COUNT(DISTINCT fm.id) as family_member_count
            FROM villages v
            LEFT JOIN residences r ON v.id = r.village_id AND r.status = 'active'
            LEFT JOIN family_members fm ON r.id = fm.residence_id
            WHERE v.ward_id = ?
            GROUP BY v.id, v.village_name
            ORDER BY v.village_name
        ");
        $stmt->execute([$user_location['ward_id']]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'veo') {
        // VEO sees their village data
        $report_title = 'Village Executive Officer Report';
        $report_subtitle = 'Village: ' . $user_location['village_name'];
        
        $stmt = $pdo->prepare("
            SELECT r.resident_name, r.house_no, r.gender, r.date_of_birth,
                   r.nida_number, r.phone, r.occupation, r.ownership,
                   r.education_level, r.employment_status,
                   COUNT(fm.id) as family_member_count
            FROM residences r
            LEFT JOIN family_members fm ON r.id = fm.residence_id
            WHERE r.village_id = ? AND r.status = 'active'
            GROUP BY r.id, r.resident_name, r.house_no, r.gender, r.date_of_birth,
                     r.nida_number, r.phone, r.occupation, r.ownership,
                     r.education_level, r.employment_status
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
        // Generate print-friendly HTML for PDF conversion
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
            font-size: 12px;
        }
        th, td { 
            border: 1px solid #333; 
            padding: 6px; 
            text-align: left; 
            vertical-align: top;
        }
        th { 
            background-color: #f5f5f5; 
            font-weight: bold; 
            font-size: 11px;
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
                    <tr>';
            
            // Table headers based on role
            if ($user_role === 'veo') {
                $html .= '<th>Resident Name</th><th>House No</th><th>Gender</th><th>Date of Birth</th>';
                $html .= '<th>NIDA Number</th><th>Phone</th><th>Occupation</th><th>Ownership</th>';
                $html .= '<th>Education Level</th><th>Employment Status</th><th>Family Members</th>';
            } else {
                $html .= '<th>Location</th><th>Residences</th><th>Family Members</th>';
            }
            
            $html .= '</tr>
                </thead>
                <tbody>';
            
            foreach ($report_data as $row) {
                $html .= '<tr>';
                if ($user_role === 'veo') {
                    $html .= '<td>' . htmlspecialchars($row['resident_name']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['house_no']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['gender']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['date_of_birth']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['nida_number']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['phone']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['occupation']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['ownership']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['education_level']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['employment_status']) . '</td>';
                    $html .= '<td>' . $row['family_member_count'] . '</td>';
                } else {
                    $html .= '<td>' . htmlspecialchars($row['village_name'] ?? $row['ward_name']) . '</td>';
                    $html .= '<td>' . $row['residence_count'] . '</td>';
                    $html .= '<td>' . $row['family_member_count'] . '</td>';
                }
                $html .= '</tr>';
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
        // Generate Excel report using CSV format with .xlsx extension
        $filename = 'residence_report_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Create a simple Excel-compatible format
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\">\n";
        echo "<Worksheet ss:Name=\"Residence Report\">\n";
        echo "<Table>\n";
        
        // Header row
        echo "<Row>\n";
        if ($user_role === 'veo') {
            $headers = ['Resident Name', 'House No', 'Gender', 'Date of Birth', 'NIDA Number', 
                       'Phone', 'Occupation', 'Ownership', 'Education Level', 'Employment Status', 'Family Members'];
        } else {
            $headers = ['Location', 'Residences', 'Family Members'];
        }
        
        foreach ($headers as $header) {
            echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($header) . "</Data></Cell>\n";
        }
        echo "</Row>\n";
        
        // Data rows
        foreach ($report_data as $data_row) {
            echo "<Row>\n";
            if ($user_role === 'veo') {
                $values = [$data_row['resident_name'], $data_row['house_no'], $data_row['gender'], 
                          $data_row['date_of_birth'], $data_row['nida_number'], $data_row['phone'],
                          $data_row['occupation'], $data_row['ownership'], $data_row['education_level'],
                          $data_row['employment_status'], $data_row['family_member_count']];
            } else {
                $values = [$data_row['village_name'] ?? $data_row['ward_name'], 
                          $data_row['residence_count'], $data_row['family_member_count']];
            }
            
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
        $filename = 'residence_report_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        if ($user_role === 'veo') {
            fputcsv($output, ['Resident Name', 'House No', 'Gender', 'Date of Birth', 'NIDA Number', 
                             'Phone', 'Occupation', 'Ownership', 'Education Level', 'Employment Status', 'Family Members']);
        } else {
            fputcsv($output, ['Location', 'Residences', 'Family Members']);
        }
        
        // Data rows
        foreach ($report_data as $data_row) {
            if ($user_role === 'veo') {
                fputcsv($output, [$data_row['resident_name'], $data_row['house_no'], $data_row['gender'], 
                                $data_row['date_of_birth'], $data_row['nida_number'], $data_row['phone'],
                                $data_row['occupation'], $data_row['ownership'], $data_row['education_level'],
                                $data_row['employment_status'], $data_row['family_member_count']]);
            } else {
                fputcsv($output, [$data_row['village_name'] ?? $data_row['ward_name'], 
                                $data_row['residence_count'], $data_row['family_member_count']]);
            }
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
    <title>Residence Reports</title>
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

        .reports-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header Styles */
        .reports-header {
            background: linear-gradient(135deg, var(--primary) 0%, #5e72e4 100%);
            border-radius: var(--border-radius);
            padding: 25px 30px;
            color: white;
            margin-bottom: 24px;
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
        }

        .reports-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .reports-header::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .header-content {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-title h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .header-title p {
            font-size: 16px;
            opacity: 0.9;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .export-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
        }

        .export-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 20px;
        }

        .stat-card:nth-child(1) .stat-icon {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .stat-card:nth-child(2) .stat-icon {
            background: rgba(25, 135, 84, 0.1);
            color: var(--success);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--gray-800);
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray-600);
            font-weight: 500;
        }

        /* Report Content */
        .report-content {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .report-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .report-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-800);
        }

        .report-info {
            display: flex;
            gap: 15px;
            font-size: 14px;
            color: var(--gray-600);
        }

        .report-info span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background-color: var(--gray-100);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 14px;
            border-bottom: 1px solid var(--gray-200);
        }

        .data-table td {
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
            font-size: 14px;
            color: var(--gray-700);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
            background-color: var(--gray-100);
        }

        /* Badges and Status */
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-primary {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .badge-success {
            background: rgba(25, 135, 84, 0.1);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .badge-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .badge-info {
            background: rgba(13, 202, 240, 0.1);
            color: var(--info);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: var(--gray-300);
        }

        .empty-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--gray-600);
        }

        .empty-desc {
            color: var(--gray-500);
            max-width: 500px;
            margin: 0 auto 30px;
        }

        .empty-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .empty-actions .btn:hover {
            background: var(--primary-dark);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .reports-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .report-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .report-info {
                flex-direction: column;
                gap: 8px;
            }
        }

        @media (max-width: 576px) {
            .reports-header {
                padding: 20px;
            }
            
            .header-title h1 {
                font-size: 24px;
            }
            
            .export-btn {
                padding: 8px 12px;
                font-size: 14px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-value {
                font-size: 24px;
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

        /* Loading State */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .report-content, .report-content * {
                visibility: visible;
            }
            .report-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="reports-container">
        <!-- Header Section -->
        <div class="reports-header fade-in">
            <div class="header-content">
                <div class="header-title">
                    <h1><i class="fas fa-chart-line me-2"></i> <?php echo $report_title; ?></h1>
                    <p><?php echo $report_subtitle; ?></p>
                </div>
                <div class="header-actions">
                    <a href="?export=pdf" class="export-btn">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="?export=excel" class="export-btn">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="?export=csv" class="export-btn">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                    <a href="reports_dashboard.php" class="export-btn">
                        <i class="fas fa-arrow-left"></i> Back to Reports
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card fade-in" style="animation-delay: 0.1s;">
                <div class="stat-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-value"><?php echo count($report_data); ?></div>
                <div class="stat-label">Total Records</div>
            </div>
            <div class="stat-card fade-in" style="animation-delay: 0.2s;">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-value"><?php echo date('M d, Y'); ?></div>
                <div class="stat-label">Report Date</div>
            </div>
            <div class="stat-card fade-in" style="animation-delay: 0.3s;">
                <div class="stat-icon">
                    <i class="fas fa-user-tag"></i>
                </div>
                <div class="stat-value"><?php echo getRoleDisplayName($user_role); ?></div>
                <div class="stat-label">Your Role</div>
            </div>
            <div class="stat-card fade-in" style="animation-delay: 0.4s;">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo date('H:i'); ?></div>
                <div class="stat-label">Current Time</div>
            </div>
        </div>

        <!-- Report Content -->
        <div class="report-content fade-in" style="animation-delay: 0.5s;">
            <div class="report-header">
                <h2 class="report-title">Detailed Report Data</h2>
                <div class="report-info">
                    <span><i class="fas fa-user-circle"></i> <?php echo $_SESSION['username']; ?></span>
                    <span><i class="fas fa-clock"></i> <?php echo date('F j, Y, g:i a'); ?></span>
                    <span><i class="fas fa-list"></i> <?php echo count($report_data); ?> records</span>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h3 class="empty-title">Error Generating Report</h3>
                    <p class="empty-desc"><?php echo $error_message; ?></p>
                    <div class="empty-actions">
                        <a href="reports_dashboard.php" class="btn">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            <?php elseif (!empty($report_data)): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <?php if ($user_role === 'veo'): ?>
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
                                    <th>Family Members</th>
                                <?php else: ?>
                                    <th>Location</th>
                                    <th>Residences</th>
                                    <th>Family Members</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $index => $row): ?>
                                <tr>
                                    <?php if ($user_role === 'veo'): ?>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-2">
                                                    <?php echo strtoupper(substr($row['resident_name'], 0, 1)); ?>
                                                </div>
                                                <?php echo htmlspecialchars($row['resident_name']); ?>
                                            </div>
                                        </td>
                                        <td><span class="badge badge-primary"><?php echo htmlspecialchars($row['house_no']); ?></span></td>
                                        <td>
                                            <span class="badge <?php echo $row['gender'] === 'Male' ? 'badge-info' : 'badge-warning'; ?>">
                                                <?php echo htmlspecialchars($row['gender']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['date_of_birth']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nida_number']); ?></td>
                                        <td>
                                            <a href="tel:<?php echo htmlspecialchars($row['phone']); ?>" class="text-primary">
                                                <i class="fas fa-phone-alt me-1"></i> <?php echo htmlspecialchars($row['phone']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['occupation']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $row['ownership'] === 'Owner' ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo htmlspecialchars($row['ownership']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['education_level']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $row['employment_status'] === 'Employed' ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo htmlspecialchars($row['employment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary">
                                                <i class="fas fa-users me-1"></i> <?php echo $row['family_member_count']; ?>
                                            </span>
                                        </td>
                                    <?php else: ?>
                                        <td>
                                            <i class="fas fa-map-marker-alt text-danger me-2"></i>
                                            <?php echo htmlspecialchars($row['village_name'] ?? $row['ward_name']); ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <i class="fas fa-home me-1"></i> <?php echo $row['residence_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-success">
                                                <i class="fas fa-users me-1"></i> <?php echo $row['family_member_count']; ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3 class="empty-title">No Data Available</h3>
                    <p class="empty-desc">No records found for the selected criteria. This could be because no residences have been registered yet or there was an issue retrieving the data.</p>
                    <div class="empty-actions">
                        <a href="reports_dashboard.php" class="btn">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="dashboard.php" class="btn" style="background: var(--secondary);">
                            <i class="fas fa-home"></i> Go to Dashboard
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
