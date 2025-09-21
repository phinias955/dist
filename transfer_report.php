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

// Get transfer data based on user role
$report_data = [];
$report_title = '';
$report_subtitle = '';

try { 
    if ($user_role === 'super_admin') {
        // Super Admin sees all transfers
        $report_title = 'Transfer Report - System Wide';
        $report_subtitle = 'All Residence Transfers';
        
        $stmt = $pdo->query("
            SELECT rt.*, 
                   r.resident_name, r.house_no,
                   fw.ward_name as from_ward_name, fv.village_name as from_village_name,
                   tw.ward_name as to_ward_name, tv.village_name as to_village_name,
                   u1.username as requested_by_username,
                   u2.username as weo_approved_by_username,
                   u3.username as ward_approved_by_username,
                   u4.username as veo_accepted_by_username
            FROM residence_transfers rt
            JOIN residences r ON rt.residence_id = r.id
            JOIN wards fw ON rt.from_ward_id = fw.id
            JOIN villages fv ON rt.from_village_id = fv.id
            JOIN wards tw ON rt.to_ward_id = tw.id
            JOIN villages tv ON rt.to_village_id = tv.id
            LEFT JOIN users u1 ON rt.requested_by = u1.id
            LEFT JOIN users u2 ON rt.weo_approved_by = u2.id
            LEFT JOIN users u3 ON rt.ward_approved_by = u3.id
            LEFT JOIN users u4 ON rt.veo_accepted_by = u4.id
            ORDER BY rt.created_at DESC
        ");
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'admin') {
        // Admin sees transfers from their ward
        $report_title = 'Transfer Report - Ward Administration';
        $report_subtitle = 'Ward: ' . $user_location['ward_name'];
        
        $stmt = $pdo->prepare("
            SELECT rt.*, 
                   r.resident_name, r.house_no,
                   fw.ward_name as from_ward_name, fv.village_name as from_village_name,
                   tw.ward_name as to_ward_name, tv.village_name as to_village_name,
                   u1.username as requested_by_username,
                   u2.username as weo_approved_by_username,
                   u3.username as ward_approved_by_username,
                   u4.username as veo_accepted_by_username
            FROM residence_transfers rt
            JOIN residences r ON rt.residence_id = r.id
            JOIN wards fw ON rt.from_ward_id = fw.id
            JOIN villages fv ON rt.from_village_id = fv.id
            JOIN wards tw ON rt.to_ward_id = tw.id
            JOIN villages tv ON rt.to_village_id = tv.id
            LEFT JOIN users u1 ON rt.requested_by = u1.id
            LEFT JOIN users u2 ON rt.weo_approved_by = u2.id
            LEFT JOIN users u3 ON rt.ward_approved_by = u3.id
            LEFT JOIN users u4 ON rt.veo_accepted_by = u4.id
            WHERE rt.from_ward_id = ? OR rt.to_ward_id = ?
            ORDER BY rt.created_at DESC
        ");
        $stmt->execute([$user_location['ward_id'], $user_location['ward_id']]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'weo') {
        // WEO sees transfers from their ward
        $report_title = 'Transfer Report - Ward Executive Officer';
        $report_subtitle = 'Ward: ' . $user_location['ward_name'];
        
        $stmt = $pdo->prepare("
            SELECT rt.*, 
                   r.resident_name, r.house_no,
                   fw.ward_name as from_ward_name, fv.village_name as from_village_name,
                   tw.ward_name as to_ward_name, tv.village_name as to_village_name,
                   u1.username as requested_by_username,
                   u2.username as weo_approved_by_username,
                   u3.username as ward_approved_by_username,
                   u4.username as veo_accepted_by_username
            FROM residence_transfers rt
            JOIN residences r ON rt.residence_id = r.id
            JOIN wards fw ON rt.from_ward_id = fw.id
            JOIN villages fv ON rt.from_village_id = fv.id
            JOIN wards tw ON rt.to_ward_id = tw.id
            JOIN villages tv ON rt.to_village_id = tv.id
            LEFT JOIN users u1 ON rt.requested_by = u1.id
            LEFT JOIN users u2 ON rt.weo_approved_by = u2.id
            LEFT JOIN users u3 ON rt.ward_approved_by = u3.id
            LEFT JOIN users u4 ON rt.veo_accepted_by = u4.id
            WHERE rt.from_ward_id = ? OR rt.to_ward_id = ?
            ORDER BY rt.created_at DESC
        ");
        $stmt->execute([$user_location['ward_id'], $user_location['ward_id']]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'veo') {
        // VEO sees transfers from their village
        $report_title = 'Transfer Report - Village Executive Officer';
        $report_subtitle = 'Village: ' . $user_location['village_name'];
        
        $stmt = $pdo->prepare("
            SELECT rt.*, 
                   r.resident_name, r.house_no,
                   fw.ward_name as from_ward_name, fv.village_name as from_village_name,
                   tw.ward_name as to_ward_name, tv.village_name as to_village_name,
                   u1.username as requested_by_username,
                   u2.username as weo_approved_by_username,
                   u3.username as ward_approved_by_username,
                   u4.username as veo_accepted_by_username
            FROM residence_transfers rt
            JOIN residences r ON rt.residence_id = r.id
            JOIN wards fw ON rt.from_ward_id = fw.id
            JOIN villages fv ON rt.from_village_id = fv.id
            JOIN wards tw ON rt.to_ward_id = tw.id
            JOIN villages tv ON rt.to_village_id = tv.id
            LEFT JOIN users u1 ON rt.requested_by = u1.id
            LEFT JOIN users u2 ON rt.weo_approved_by = u2.id
            LEFT JOIN users u3 ON rt.ward_approved_by = u3.id
            LEFT JOIN users u4 ON rt.veo_accepted_by = u4.id
            WHERE rt.from_village_id = ? OR rt.to_village_id = ?
            ORDER BY rt.created_at DESC
        ");
        $stmt->execute([$user_location['village_id'], $user_location['village_id']]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error_message = "Error generating report: " . $e->getMessage();
}

// Function to get status display
function getStatusDisplay($status) {
    $status_map = [
        'pending_approval' => 'Pending WEO Approval',
        'weo_approved' => 'WEO Approved',
        'ward_approved' => 'Ward Admin Approved',
        'veo_accepted' => 'VEO Accepted',
        'completed' => 'Completed',
        'rejected' => 'Rejected'
    ];
    return $status_map[$status] ?? $status;
}

// Function to get status color
function getStatusColor($status) {
    $color_map = [
        'pending_approval' => 'warning',
        'weo_approved' => 'info',
        'ward_approved' => 'primary',
        'veo_accepted' => 'success',
        'completed' => 'success',
        'rejected' => 'danger'
    ];
    return $color_map[$status] ?? 'secondary';
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
                $report_description = 'Comprehensive transfer information covering all wards and villages.';
                break;
            case 'admin':
                $report_heading = 'Ward Registration Report - Ward Administration';
                $report_description = 'Transfer information for ward-level administration.';
                break;
            case 'weo':
                $report_heading = 'Ward Registration Report - Ward Executive Officer';
                $report_description = 'Transfer information for ward management.';
                break;
            case 'veo':
                $report_heading = 'Ward Registration Report - Village Executive Officer';
                $report_description = 'Transfer information for village management.';
                break;
            default:
                $report_heading = 'Ward Registration Report';
                $report_description = 'Transfer information report.';
        }
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Ward Registration System');
        $pdf->SetAuthor($_SESSION['username']);
        $pdf->SetTitle($report_heading);
        $pdf->SetSubject('Transfer Report');
        $pdf->SetKeywords('Ward, Registration, Transfer, Report');
        
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
            $pdf->Cell(0, 10, 'Transfer Information', 0, 1, 'L');
            $pdf->Ln(5);
            
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetTextColor(0);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.3);
            $pdf->SetFont('helvetica', 'B');
            
            // Table headers
            $pdf->Cell(20, 8, 'Transfer ID', 1, 0, 'C', 1);
            $pdf->Cell(30, 8, 'Resident Name', 1, 0, 'C', 1);
            $pdf->Cell(20, 8, 'House No', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'From Location', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'To Location', 1, 0, 'C', 1);
            $pdf->Cell(25, 8, 'Status', 1, 0, 'C', 1);
            $pdf->Cell(25, 8, 'Requested By', 1, 0, 'C', 1);
            $pdf->Cell(25, 8, 'Request Date', 1, 0, 'C', 1);
            $pdf->Cell(30, 8, 'Reason', 1, 1, 'C', 1);
            
            $pdf->SetFont('helvetica', '', 8);
            foreach ($report_data as $row) {
                $pdf->Cell(20, 8, $row['id'], 1, 0, 'C', 0);
                $pdf->Cell(30, 8, $row['resident_name'], 1, 0, 'L', 0);
                $pdf->Cell(20, 8, $row['house_no'], 1, 0, 'C', 0);
                $pdf->Cell(40, 8, $row['from_ward_name'] . ', ' . $row['from_village_name'], 1, 0, 'L', 0);
                $pdf->Cell(40, 8, $row['to_ward_name'] . ', ' . $row['to_village_name'], 1, 0, 'L', 0);
                $pdf->Cell(25, 8, getStatusDisplay($row['status']), 1, 0, 'C', 0);
                $pdf->Cell(25, 8, $row['requested_by_username'], 1, 0, 'C', 0);
                $pdf->Cell(25, 8, date('Y-m-d H:i', strtotime($row['created_at'])), 1, 0, 'C', 0);
                $pdf->Cell(30, 8, $row['transfer_reason'], 1, 1, 'L', 0);
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
        // Generate Excel report
        $filename = 'transfer_report_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Create Excel-compatible format
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\">\n";
        echo "<Worksheet ss:Name=\"Transfer Report\">\n";
        echo "<Table>\n";
        
        // Header row
        echo "<Row>\n";
        $headers = ['Transfer ID', 'Resident Name', 'House No', 'From Location', 'To Location', 
                   'Status', 'Requested By', 'Request Date', 'Reason'];
        
        foreach ($headers as $header) {
            echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($header) . "</Data></Cell>\n";
        }
        echo "</Row>\n";
        
        // Data rows
        foreach ($report_data as $data_row) {
            echo "<Row>\n";
            $values = [
                $data_row['id'], $data_row['resident_name'], $data_row['house_no'],
                $data_row['from_ward_name'] . ', ' . $data_row['from_village_name'],
                $data_row['to_ward_name'] . ', ' . $data_row['to_village_name'],
                getStatusDisplay($data_row['status']), $data_row['requested_by_username'],
                date('Y-m-d H:i', strtotime($data_row['created_at'])), $data_row['reason']
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
        $filename = 'Ward_Registration_Report_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Add BOM for UTF-8 support in Excel
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Write report header
        fputcsv($output, array('Ward Registration System - Transfer Report'));
        fputcsv($output, array('Generated on: ' . date('Y-m-d H:i:s')));
        fputcsv($output, array('Generated by: ' . $_SESSION['username'] . ' (' . getRoleDisplayName($user_role) . ')'));
        fputcsv($output, array('Scope: ' . $report_subtitle));
        fputcsv($output, array(''));
        
        // CSV headers
        fputcsv($output, array('TRANSFER INFORMATION'));
        fputcsv($output, array('Transfer ID', 'Resident Name', 'House No', 'From Location', 'To Location', 
                             'Status', 'Requested By', 'Request Date', 'Reason'));
        
        // Data rows
        foreach ($report_data as $data_row) {
            fputcsv($output, array(
                $data_row['id'], $data_row['resident_name'], $data_row['house_no'],
                $data_row['from_ward_name'] . ', ' . $data_row['from_village_name'],
                $data_row['to_ward_name'] . ', ' . $data_row['to_village_name'],
                getStatusDisplay($data_row['status']), $data_row['requested_by_username'],
                date('Y-m-d H:i', strtotime($data_row['created_at'])), $data_row['transfer_reason']
            ));
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

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: rgba(255, 193, 7, 0.2);
            color: #b8860b;
        }

        .status-badge.approved {
            background: rgba(25, 135, 84, 0.2);
            color: var(--success);
        }

        .status-badge.completed {
            background: rgba(13, 202, 240, 0.2);
            color: var(--info);
        }

        .status-badge.rejected {
            background: rgba(220, 53, 69, 0.2);
            color: var(--danger);
        }

        .location-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .location-info .data-badge {
            font-size: 11px;
            padding: 2px 6px;
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
                    <span class="badge-icon">🔄</span>
                    <span class="badge-text">Transfer Report</span>
                </div>
                <h1 class="hero-title">
                    <?php echo $report_title; ?>
                    <span class="title-highlight">Transfers</span>
                </h1>
                <p class="hero-description">
                    <?php echo $report_subtitle; ?><br>
                    Track all residence transfers and movements across different locations.
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
                            <div class="col-md-6">
  
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
                            <div class="data-table-container fade-in">
                                <div class="table-header">
                                    <h3>
                                        <i class="fas fa-exchange-alt me-2"></i>
                                        Transfer Report Data
                                    </h3>
                                    <span class="record-count"><?php echo count($report_data); ?> transfers</span>
                                </div>
                                <div class="table-wrapper">
                                    <table class="modern-table">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-hashtag me-2"></i>Transfer ID</th>
                                                <th><i class="fas fa-user me-2"></i>Resident Name</th>
                                                <th><i class="fas fa-home me-2"></i>House No</th>
                                                <th><i class="fas fa-map-marker-alt me-2"></i>From Location</th>
                                                <th><i class="fas fa-arrow-right me-2"></i>To Location</th>
                                                <th><i class="fas fa-info-circle me-2"></i>Status</th>
                                                <th><i class="fas fa-user-tie me-2"></i>Requested By</th>
                                                <th><i class="fas fa-calendar me-2"></i>Request Date</th>
                                                <th><i class="fas fa-comment me-2"></i>Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                                <tr>
                                                    <td>
                                                        <span class="data-badge primary">#<?php echo $row['id']; ?></span>
                                                    </td>
                                                    <td class="fw-medium"><?php echo htmlspecialchars($row['resident_name']); ?></td>
                                                    <td>
                                                        <span class="data-badge success"><?php echo htmlspecialchars($row['house_no']); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="location-info">
                                                            <div class="data-badge warning"><?php echo htmlspecialchars($row['from_ward_name']); ?></div>
                                                            <div class="data-badge info"><?php echo htmlspecialchars($row['from_village_name']); ?></div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="location-info">
                                                            <div class="data-badge warning"><?php echo htmlspecialchars($row['to_ward_name']); ?></div>
                                                            <div class="data-badge info"><?php echo htmlspecialchars($row['to_village_name']); ?></div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                                            <i class="fas fa-<?php echo getStatusIcon($row['status']); ?> me-1"></i>
                                                            <?php echo getStatusDisplay($row['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['requested_by_username']); ?></td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($row['transfer_reason'] ?? 'N/A'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h5>No Data Available</h5>
                                <p>No transfer data found for the selected criteria.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
