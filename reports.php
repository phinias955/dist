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
        // Include TCPDF library
        require_once('vendor/autoload.php');
        
        // Generate dynamic heading based on user role
        $report_heading = '';
        $report_description = '';
        
        switch($user_role) {
            case 'super_admin':
                $report_heading = 'Ward Registration Report - System Wide';
                $report_description = 'Comprehensive overview covering all wards and villages in the system.';
                break;
            case 'admin':
                $report_heading = 'Ward Registration Report - Ward Administration';
                $report_description = 'Detailed overview for ward-level administration.';
                break;
            case 'weo':
                $report_heading = 'Ward Registration Report - Ward Executive Officer';
                $report_description = 'Comprehensive overview for ward management.';
                break;
            case 'veo':
                $report_heading = 'Ward Registration Report - Village Executive Officer';
                $report_description = 'Local overview for village management.';
                break;
            default:
                $report_heading = 'Ward Registration Report';
                $report_description = 'Overview report for residence registrations.';
        }
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Ward Registration System');
        $pdf->SetAuthor($_SESSION['username']);
        $pdf->SetTitle($report_heading);
        $pdf->SetSubject('Overview Report');
        $pdf->SetKeywords('Ward, Registration, Overview, Report');
        
        // Set default header data
        $pdf->SetHeaderData('', 0, $report_heading, $report_description);
        
        // Set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        
        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        
        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        
        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', 'B', 16);
        
        // Report title
        $pdf->Cell(0, 15, $report_heading, 0, 1, 'C');
        $pdf->Ln(5);
        
        // Report info
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'L');
        $pdf->Cell(0, 8, 'Generated by: ' . $_SESSION['username'] . ' (' . getRoleDisplayName($user_role) . ')', 0, 1, 'L');
        $pdf->Cell(0, 8, 'Scope: ' . $report_subtitle, 0, 1, 'L');
        $pdf->Ln(10);
        
        // Data table
        if (!empty($report_data)) {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Data Summary', 0, 1, 'L');
            $pdf->Ln(5);
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetTextColor(0);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.3);
            $pdf->SetFont('helvetica', 'B');
            
            // Table headers based on role
            if ($user_role === 'veo') {
                // VEO sees detailed residence data
                $pdf->Cell(30, 8, 'Resident Name', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'House No', 1, 0, 'C', 1);
                $pdf->Cell(15, 8, 'Gender', 1, 0, 'C', 1);
                $pdf->Cell(25, 8, 'Date of Birth', 1, 0, 'C', 1);
                $pdf->Cell(30, 8, 'NIDA Number', 1, 0, 'C', 1);
                $pdf->Cell(25, 8, 'Phone', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Occupation', 1, 0, 'C', 1);
                $pdf->Cell(15, 8, 'Ownership', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Education', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Employment', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Family Members', 1, 1, 'C', 1);
                
                $pdf->SetFont('helvetica', '', 8);
                foreach ($report_data as $row) {
                    $pdf->Cell(30, 8, $row['resident_name'], 1, 0, 'L', 0);
                    $pdf->Cell(20, 8, $row['house_no'], 1, 0, 'C', 0);
                    $pdf->Cell(15, 8, $row['gender'], 1, 0, 'C', 0);
                    $pdf->Cell(25, 8, $row['date_of_birth'], 1, 0, 'C', 0);
                    $pdf->Cell(30, 8, $row['nida_number'], 1, 0, 'C', 0);
                    $pdf->Cell(25, 8, $row['phone'], 1, 0, 'C', 0);
                    $pdf->Cell(20, 8, $row['occupation'], 1, 0, 'C', 0);
                    $pdf->Cell(15, 8, $row['ownership'], 1, 0, 'C', 0);
                    $pdf->Cell(20, 8, $row['education_level'], 1, 0, 'C', 0);
                    $pdf->Cell(20, 8, $row['employment_status'], 1, 0, 'C', 0);
                    $pdf->Cell(20, 8, $row['family_member_count'], 1, 1, 'C', 0);
                }
            } else {
                // Other roles see summary data
                $pdf->Cell(60, 8, 'Location', 1, 0, 'C', 1);
                $pdf->Cell(30, 8, 'Residences', 1, 0, 'C', 1);
                $pdf->Cell(30, 8, 'Family Members', 1, 1, 'C', 1);
                
                $pdf->SetFont('helvetica', '');
                foreach ($report_data as $row) {
                    $pdf->Cell(60, 8, $row['village_name'] ?? $row['ward_name'], 1, 0, 'L', 0);
                    $pdf->Cell(30, 8, $row['residence_count'], 1, 0, 'C', 0);
                    $pdf->Cell(30, 8, $row['family_member_count'], 1, 1, 'C', 0);
                }
            }
        } else {
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 10, 'No data available for the selected criteria.', 0, 1, 'C');
        }
        
        // Footer
        $pdf->SetY(-15);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'Generated by Ward Registration System on ' . date('Y-m-d H:i:s'), 0, 0, 'C');
        
        // Output PDF
        $filename = 'Ward_Registration_Report_' . date('Y-m-d_H-i-s') . '.pdf';
        $pdf->Output($filename, 'D');
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
        $filename = 'Ward_Registration_Report_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Add BOM for UTF-8 support in Excel
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Write report header
        fputcsv($output, array('Ward Registration System - Overview Report'));
        fputcsv($output, array('Generated on: ' . date('Y-m-d H:i:s')));
        fputcsv($output, array('Generated by: ' . $_SESSION['username'] . ' (' . getRoleDisplayName($user_role) . ')'));
        fputcsv($output, array('Scope: ' . $report_subtitle));
        fputcsv($output, array(''));
        
        // CSV headers
        if ($user_role === 'veo') {
            fputcsv($output, array('DETAILED RESIDENCE DATA'));
            fputcsv($output, array('Resident Name', 'House No', 'Gender', 'Date of Birth', 'NIDA Number', 
                                 'Phone', 'Occupation', 'Ownership', 'Education Level', 'Employment Status', 'Family Members'));
        } else {
            fputcsv($output, array('WARD AND VILLAGE SUMMARY'));
            fputcsv($output, array('Location', 'Residences', 'Family Members'));
        }
        
        // Data rows
        foreach ($report_data as $data_row) {
            if ($user_role === 'veo') {
                fputcsv($output, array($data_row['resident_name'], $data_row['house_no'], $data_row['gender'], 
                                    $data_row['date_of_birth'], $data_row['nida_number'], $data_row['phone'],
                                    $data_row['occupation'], $data_row['ownership'], $data_row['education_level'],
                                    $data_row['employment_status'], $data_row['family_member_count']));
            } else {
                fputcsv($output, array($data_row['village_name'] ?? $data_row['ward_name'], 
                                    $data_row['residence_count'], $data_row['family_member_count']));
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
