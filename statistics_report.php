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
        // Include TCPDF library
        require_once('vendor/autoload.php');
        
        // Generate dynamic heading based on user role
        $report_heading = '';
        $report_description = '';
        
        switch($user_role) {
            case 'super_admin':
                $report_heading = 'Ward Registration Report - System Wide';
                $report_description = 'Comprehensive statistics and analytics covering all wards and villages in the system.';
                break;
            case 'admin':
                $report_heading = 'Ward Registration Report - Ward Administration';
                $report_description = 'Detailed statistics and analytics for ward-level administration.';
                break;
            case 'weo':
                $report_heading = 'Ward Registration Report - Ward Executive Officer';
                $report_description = 'Comprehensive statistics and analytics for ward management.';
                break;
            case 'veo':
                $report_heading = 'Ward Registration Report - Village Executive Officer';
                $report_description = 'Local statistics and analytics for village management.';
                break;
            default:
                $report_heading = 'Ward Registration Report';
                $report_description = 'Statistics and analytics report for residence registrations.';
        }
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Ward Registration System');
        $pdf->SetAuthor($_SESSION['username']);
        $pdf->SetTitle($report_heading);
        $pdf->SetSubject('Statistics Report');
        $pdf->SetKeywords('Ward, Registration, Statistics, Report');
        
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
        
        // Statistics overview
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Statistics Overview', 0, 1, 'L');
        $pdf->Ln(5);
        
        // Create statistics table
        $pdf->SetFont('helvetica', '', 10);
        $stats_data_array = array(
            array('Metric', 'Count'),
            array('Total Residences', $stats_data['total_residences'] ?? 0),
            array('Total Family Members', $stats_data['total_family_members'] ?? 0)
        );
        
        if (isset($stats_data['total_wards'])) {
            $stats_data_array[] = array('Total Wards', $stats_data['total_wards']);
        }
        
        if (isset($stats_data['total_villages'])) {
            $stats_data_array[] = array('Total Villages', $stats_data['total_villages']);
        }
        
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor(0);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);
        $pdf->SetFont('helvetica', 'B');
        
        // Header
        $pdf->Cell(80, 8, 'Metric', 1, 0, 'C', 1);
        $pdf->Cell(40, 8, 'Count', 1, 1, 'C', 1);
        
        $pdf->SetFont('helvetica', '');
        foreach ($stats_data_array as $row) {
            if ($row[0] !== 'Metric') {
                $pdf->Cell(80, 8, $row[0], 1, 0, 'L', 0);
                $pdf->Cell(40, 8, $row[1], 1, 1, 'C', 0);
            }
        }
        
        $pdf->Ln(10);
        
        // Gender Distribution
        if (!empty($stats_data['gender_distribution'])) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'Gender Distribution', 0, 1, 'L');
            $pdf->Ln(3);
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(60, 8, 'Gender', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'Count', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'Percentage', 1, 1, 'C', 1);
            
            $pdf->SetFont('helvetica', '');
            $total_gender = array_sum(array_column($stats_data['gender_distribution'], 'count'));
            foreach ($stats_data['gender_distribution'] as $row) {
                $percentage = $total_gender > 0 ? round(($row['count'] / $total_gender) * 100, 2) : 0;
                $pdf->Cell(60, 8, $row['gender'], 1, 0, 'L', 0);
                $pdf->Cell(40, 8, $row['count'], 1, 0, 'C', 0);
                $pdf->Cell(40, 8, $percentage . '%', 1, 1, 'C', 0);
            }
            $pdf->Ln(10);
        }
        
        // Ownership Distribution
        if (!empty($stats_data['ownership_distribution'])) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'Ownership Distribution', 0, 1, 'L');
            $pdf->Ln(3);
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(60, 8, 'Ownership', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'Count', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'Percentage', 1, 1, 'C', 1);
            
            $pdf->SetFont('helvetica', '');
            $total_ownership = array_sum(array_column($stats_data['ownership_distribution'], 'count'));
            foreach ($stats_data['ownership_distribution'] as $row) {
                $percentage = $total_ownership > 0 ? round(($row['count'] / $total_ownership) * 100, 2) : 0;
                $pdf->Cell(60, 8, $row['ownership'], 1, 0, 'L', 0);
                $pdf->Cell(40, 8, $row['count'], 1, 0, 'C', 0);
                $pdf->Cell(40, 8, $percentage . '%', 1, 1, 'C', 0);
            }
            $pdf->Ln(10);
        }
        
        // Education Distribution
        if (!empty($stats_data['education_distribution'])) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'Education Level Distribution', 0, 1, 'L');
            $pdf->Ln(3);
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(80, 8, 'Education Level', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'Count', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'Percentage', 1, 1, 'C', 1);
            
            $pdf->SetFont('helvetica', '');
            $total_education = array_sum(array_column($stats_data['education_distribution'], 'count'));
            foreach ($stats_data['education_distribution'] as $row) {
                $percentage = $total_education > 0 ? round(($row['count'] / $total_education) * 100, 2) : 0;
                $pdf->Cell(80, 8, $row['education_level'], 1, 0, 'L', 0);
                $pdf->Cell(40, 8, $row['count'], 1, 0, 'C', 0);
                $pdf->Cell(40, 8, $percentage . '%', 1, 1, 'C', 0);
            }
            $pdf->Ln(10);
        }
        
        // Employment Distribution
        if (!empty($stats_data['employment_distribution'])) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'Employment Status Distribution', 0, 1, 'L');
            $pdf->Ln(3);
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(80, 8, 'Employment Status', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'Count', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'Percentage', 1, 1, 'C', 1);
            
            $pdf->SetFont('helvetica', '');
            $total_employment = array_sum(array_column($stats_data['employment_distribution'], 'count'));
            foreach ($stats_data['employment_distribution'] as $row) {
                $percentage = $total_employment > 0 ? round(($row['count'] / $total_employment) * 100, 2) : 0;
                $pdf->Cell(80, 8, $row['employment_status'], 1, 0, 'L', 0);
                $pdf->Cell(40, 8, $row['count'], 1, 0, 'C', 0);
                $pdf->Cell(40, 8, $percentage . '%', 1, 1, 'C', 0);
            }
            $pdf->Ln(10);
        }
        
        // Ward Distribution
        if (!empty($stats_data['ward_distribution'])) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'Ward Distribution', 0, 1, 'L');
            $pdf->Ln(3);
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(80, 8, 'Ward', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'Residences', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'Family Members', 1, 1, 'C', 1);
            
            $pdf->SetFont('helvetica', '');
            foreach ($stats_data['ward_distribution'] as $row) {
                $pdf->Cell(80, 8, $row['ward_name'], 1, 0, 'L', 0);
                $pdf->Cell(40, 8, $row['residence_count'], 1, 0, 'C', 0);
                $pdf->Cell(40, 8, $row['family_count'], 1, 1, 'C', 0);
            }
            $pdf->Ln(10);
        }
        
        // Village Distribution
        if (!empty($stats_data['village_distribution'])) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'Village Distribution', 0, 1, 'L');
            $pdf->Ln(3);
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(80, 8, 'Village', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'Residences', 1, 0, 'C', 1);
            $pdf->Cell(40, 8, 'Family Members', 1, 1, 'C', 1);
            
            $pdf->SetFont('helvetica', '');
            foreach ($stats_data['village_distribution'] as $row) {
                $pdf->Cell(80, 8, $row['village_name'], 1, 0, 'L', 0);
                $pdf->Cell(40, 8, $row['residence_count'], 1, 0, 'C', 0);
                $pdf->Cell(40, 8, $row['family_count'], 1, 1, 'C', 0);
            }
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
        $filename = 'Ward_Registration_Report_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Add BOM for UTF-8 support in Excel
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Write report header
        fputcsv($output, array('Ward Registration System - Statistics Report'));
        fputcsv($output, array('Generated on: ' . date('Y-m-d H:i:s')));
        fputcsv($output, array('Generated by: ' . $_SESSION['username'] . ' (' . getRoleDisplayName($user_role) . ')'));
        fputcsv($output, array('Scope: ' . $report_subtitle));
        fputcsv($output, array(''));
        
        // Basic statistics
        fputcsv($output, array('STATISTICS OVERVIEW'));
        fputcsv($output, array('Metric', 'Count'));
        fputcsv($output, array('Total Residences', $stats_data['total_residences'] ?? 0));
        fputcsv($output, array('Total Family Members', $stats_data['total_family_members'] ?? 0));
        
        if (isset($stats_data['total_wards'])) {
            fputcsv($output, array('Total Wards', $stats_data['total_wards']));
        }
        
        if (isset($stats_data['total_villages'])) {
            fputcsv($output, array('Total Villages', $stats_data['total_villages']));
        }
        
        fputcsv($output, array(''));
        
        // Gender distribution
        if (!empty($stats_data['gender_distribution'])) {
            fputcsv($output, array('GENDER DISTRIBUTION'));
            fputcsv($output, array('Gender', 'Count', 'Percentage'));
            
            $total_gender = array_sum(array_column($stats_data['gender_distribution'], 'count'));
            foreach ($stats_data['gender_distribution'] as $row) {
                $percentage = $total_gender > 0 ? round(($row['count'] / $total_gender) * 100, 2) : 0;
                fputcsv($output, array($row['gender'], $row['count'], $percentage . '%'));
            }
            fputcsv($output, array(''));
        }
        
        // Ownership distribution
        if (!empty($stats_data['ownership_distribution'])) {
            fputcsv($output, array('OWNERSHIP DISTRIBUTION'));
            fputcsv($output, array('Ownership', 'Count', 'Percentage'));
            
            $total_ownership = array_sum(array_column($stats_data['ownership_distribution'], 'count'));
            foreach ($stats_data['ownership_distribution'] as $row) {
                $percentage = $total_ownership > 0 ? round(($row['count'] / $total_ownership) * 100, 2) : 0;
                fputcsv($output, array($row['ownership'], $row['count'], $percentage . '%'));
            }
            fputcsv($output, array(''));
        }
        
        // Education distribution
        if (!empty($stats_data['education_distribution'])) {
            fputcsv($output, array('EDUCATION LEVEL DISTRIBUTION'));
            fputcsv($output, array('Education Level', 'Count', 'Percentage'));
            
            $total_education = array_sum(array_column($stats_data['education_distribution'], 'count'));
            foreach ($stats_data['education_distribution'] as $row) {
                $percentage = $total_education > 0 ? round(($row['count'] / $total_education) * 100, 2) : 0;
                fputcsv($output, array($row['education_level'], $row['count'], $percentage . '%'));
            }
            fputcsv($output, array(''));
        }
        
        // Employment distribution
        if (!empty($stats_data['employment_distribution'])) {
            fputcsv($output, array('EMPLOYMENT STATUS DISTRIBUTION'));
            fputcsv($output, array('Employment Status', 'Count', 'Percentage'));
            
            $total_employment = array_sum(array_column($stats_data['employment_distribution'], 'count'));
            foreach ($stats_data['employment_distribution'] as $row) {
                $percentage = $total_employment > 0 ? round(($row['count'] / $total_employment) * 100, 2) : 0;
                fputcsv($output, array($row['employment_status'], $row['count'], $percentage . '%'));
            }
            fputcsv($output, array(''));
        }
        
        // Ward distribution
        if (!empty($stats_data['ward_distribution'])) {
            fputcsv($output, array('WARD DISTRIBUTION'));
            fputcsv($output, array('Ward', 'Residences', 'Family Members'));
            
            foreach ($stats_data['ward_distribution'] as $row) {
                fputcsv($output, array($row['ward_name'], $row['residence_count'], $row['family_count']));
            }
            fputcsv($output, array(''));
        }
        
        // Village distribution
        if (!empty($stats_data['village_distribution'])) {
            fputcsv($output, array('VILLAGE DISTRIBUTION'));
            fputcsv($output, array('Village', 'Residences', 'Family Members'));
            
            foreach ($stats_data['village_distribution'] as $row) {
                fputcsv($output, array($row['village_name'], $row['residence_count'], $row['family_count']));
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

        /* Statistics Overview */
        .stats-overview {
            margin-bottom: 32px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: var(--transition);
            border-left: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .stat-card.primary {
            border-left-color: var(--primary);
        }

        .stat-card.success {
            border-left-color: var(--success);
        }

        .stat-card.info {
            border-left-color: var(--info);
        }

        .stat-card.warning {
            border-left-color: var(--warning);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-card.primary .stat-icon {
            background: linear-gradient(135deg, var(--primary), #5e72e4);
        }

        .stat-card.success .stat-icon {
            background: linear-gradient(135deg, var(--success), #20c997);
        }

        .stat-card.info .stat-icon {
            background: linear-gradient(135deg, var(--info), #17a2b8);
        }

        .stat-card.warning .stat-icon {
            background: linear-gradient(135deg, var(--warning), #fd7e14);
        }

        .stat-content h3 {
            font-size: 32px;
            font-weight: 700;
            margin: 0 0 4px 0;
            color: var(--gray-800);
        }

        .stat-content p {
            font-size: 14px;
            color: var(--gray-600);
            margin: 0;
            font-weight: 500;
        }

        /* Distribution Sections */
        .distribution-section {
            margin-bottom: 32px;
        }

        .distribution-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }

        .distribution-card.full-width {
            grid-column: 1 / -1;
        }

        .distribution-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .distribution-card .card-header {
            background: linear-gradient(135deg, var(--gray-800), var(--gray-700));
            color: white;
            padding: 20px;
            margin: 0;
        }

        .distribution-card .card-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .distribution-card .card-body {
            padding: 0;
        }

        .modern-table-container {
            overflow-x: auto;
        }

        .count-badge {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .percentage-bar {
            position: relative;
            background: var(--gray-200);
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            min-width: 100px;
        }

        .percentage-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #5e72e4);
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .percentage-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-700);
        }

        /* Charts Section */
        .charts-section {
            margin-bottom: 32px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }

        .chart-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .chart-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .chart-card.full-width {
            grid-column: 1 / -1;
        }

        .chart-header {
            background: linear-gradient(135deg, var(--gray-800), var(--gray-700));
            color: white;
            padding: 20px;
            margin: 0;
        }

        .chart-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chart-body {
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 300px;
        }

        .chart-body canvas {
            max-width: 100%;
            height: auto;
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
                    <span class="badge-icon">📊</span>
                    <span class="badge-text">Statistics Report</span>
                </div>
                <h1 class="hero-title">
                    <?php echo $report_title; ?>
                    <span class="title-highlight">Statistics</span>
                </h1>
                <p class="hero-description">
                    <?php echo $report_subtitle; ?><br>
                    Statistical analysis and comprehensive data insights.
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
                                    <strong>Generated by:</strong> <?php echo $_SESSION['username']; ?> (<?php echo getRoleDisplayName($user_role); ?>)
                                </p>
                            </div>
                        </div>

                        <!-- Statistics Overview Cards -->
                        <div class="stats-overview fade-in">
                            <div class="stats-grid">
                                <div class="stat-card primary">
                                    <div class="stat-icon">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo $stats_data['total_residences'] ?? 0; ?></h3>
                                        <p>Total Residences</p>
                                    </div>
                                </div>
                                
                                <div class="stat-card success">
                                    <div class="stat-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo $stats_data['total_family_members'] ?? 0; ?></h3>
                                        <p>Family Members</p>
                                    </div>
                                </div>
                                
                                <?php if (isset($stats_data['total_wards'])): ?>
                                <div class="stat-card info">
                                    <div class="stat-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo $stats_data['total_wards']; ?></h3>
                                        <p>Total Wards</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($stats_data['total_villages'])): ?>
                                <div class="stat-card warning">
                                    <div class="stat-icon">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo $stats_data['total_villages']; ?></h3>
                                        <p>Total Villages</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Charts Section -->
                        <div class="charts-section fade-in">
                            <div class="charts-grid">
                                <!-- Gender Distribution Chart -->
                                <?php if (!empty($stats_data['gender_distribution'])): ?>
                                <div class="chart-card">
                                    <div class="chart-header">
                                        <h3>
                                            <i class="fas fa-venus-mars"></i>
                                            Gender Distribution
                                        </h3>
                                    </div>
                                    <div class="chart-body">
                                        <canvas id="genderChart" width="400" height="300"></canvas>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Ownership Distribution Chart -->
                                <?php if (!empty($stats_data['ownership_distribution'])): ?>
                                <div class="chart-card">
                                    <div class="chart-header">
                                        <h3>
                                            <i class="fas fa-key"></i>
                                            Ownership Distribution
                                        </h3>
                                    </div>
                                    <div class="chart-body">
                                        <canvas id="ownershipChart" width="400" height="300"></canvas>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Education Distribution Chart -->
                                <?php if (!empty($stats_data['education_distribution'])): ?>
                                <div class="chart-card">
                                    <div class="chart-header">
                                        <h3>
                                            <i class="fas fa-graduation-cap"></i>
                                            Education Distribution
                                        </h3>
                                    </div>
                                    <div class="chart-body">
                                        <canvas id="educationChart" width="400" height="300"></canvas>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Employment Distribution Chart -->
                                <?php if (!empty($stats_data['employment_distribution'])): ?>
                                <div class="chart-card">
                                    <div class="chart-header">
                                        <h3>
                                            <i class="fas fa-briefcase"></i>
                                            Employment Distribution
                                        </h3>
                                    </div>
                                    <div class="chart-body">
                                        <canvas id="employmentChart" width="400" height="300"></canvas>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Ward Distribution Chart -->
                                <?php if (!empty($stats_data['ward_distribution'])): ?>
                                <div class="chart-card full-width">
                                    <div class="chart-header">
                                        <h3>
                                            <i class="fas fa-map-marker-alt"></i>
                                            Ward Distribution
                                        </h3>
                                    </div>
                                    <div class="chart-body">
                                        <canvas id="wardChart" width="800" height="400"></canvas>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Village Distribution Chart -->
                                <?php if (!empty($stats_data['village_distribution'])): ?>
                                <div class="chart-card full-width">
                                    <div class="chart-header">
                                        <h3>
                                            <i class="fas fa-building"></i>
                                            Village Distribution
                                        </h3>
                                    </div>
                                    <div class="chart-body">
                                        <canvas id="villageChart" width="800" height="400"></canvas>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>







                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart.js configuration
    Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6c757d';

    // Color palette for charts
    const colors = {
        primary: '#007bff',
        success: '#28a745',
        info: '#17a2b8',
        warning: '#ffc107',
        danger: '#dc3545',
        secondary: '#6c757d',
        light: '#f8f9fa',
        dark: '#343a40'
    };

    const chartColors = [
        '#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8', 
        '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6c757d'
    ];

    // Gender Distribution Chart
    <?php if (!empty($stats_data['gender_distribution'])): ?>
    const genderCtx = document.getElementById('genderChart');
    if (genderCtx) {
        const genderData = <?php echo json_encode($stats_data['gender_distribution']); ?>;
        new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: genderData.map(item => item.gender),
                datasets: [{
                    data: genderData.map(item => item.count),
                    backgroundColor: ['#007bff', '#ffc107'],
                    borderColor: ['#0056b3', '#e0a800'],
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 2000
                }
            }
        });
    }
    <?php endif; ?>

    // Ownership Distribution Chart
    <?php if (!empty($stats_data['ownership_distribution'])): ?>
    const ownershipCtx = document.getElementById('ownershipChart');
    if (ownershipCtx) {
        const ownershipData = <?php echo json_encode($stats_data['ownership_distribution']); ?>;
        new Chart(ownershipCtx, {
            type: 'pie',
            data: {
                labels: ownershipData.map(item => item.ownership),
                datasets: [{
                    data: ownershipData.map(item => item.count),
                    backgroundColor: ['#28a745', '#6c757d'],
                    borderColor: ['#1e7e34', '#495057'],
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 2000
                }
            }
        });
    }
    <?php endif; ?>

    // Education Distribution Chart
    <?php if (!empty($stats_data['education_distribution'])): ?>
    const educationCtx = document.getElementById('educationChart');
    if (educationCtx) {
        const educationData = <?php echo json_encode($stats_data['education_distribution']); ?>;
        new Chart(educationCtx, {
            type: 'doughnut',
            data: {
                labels: educationData.map(item => item.education_level),
                datasets: [{
                    data: educationData.map(item => item.count),
                    backgroundColor: chartColors.slice(0, educationData.length),
                    borderColor: chartColors.slice(0, educationData.length).map(color => color + '80'),
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 2000
                }
            }
        });
    }
    <?php endif; ?>

    // Employment Distribution Chart
    <?php if (!empty($stats_data['employment_distribution'])): ?>
    const employmentCtx = document.getElementById('employmentChart');
    if (employmentCtx) {
        const employmentData = <?php echo json_encode($stats_data['employment_distribution']); ?>;
        new Chart(employmentCtx, {
            type: 'pie',
            data: {
                labels: employmentData.map(item => item.employment_status),
                datasets: [{
                    data: employmentData.map(item => item.count),
                    backgroundColor: ['#28a745', '#dc3545'],
                    borderColor: ['#1e7e34', '#c82333'],
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 2000
                }
            }
        });
    }
    <?php endif; ?>

    // Ward Distribution Chart
    <?php if (!empty($stats_data['ward_distribution'])): ?>
    const wardCtx = document.getElementById('wardChart');
    if (wardCtx) {
        const wardData = <?php echo json_encode($stats_data['ward_distribution']); ?>;
        new Chart(wardCtx, {
            type: 'bar',
            data: {
                labels: wardData.map(item => item.ward_name),
                datasets: [{
                    label: 'Residences',
                    data: wardData.map(item => item.residence_count),
                    backgroundColor: '#007bff',
                    borderColor: '#0056b3',
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                }, {
                    label: 'Family Members',
                    data: wardData.map(item => item.family_count),
                    backgroundColor: '#28a745',
                    borderColor: '#1e7e34',
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f8f9fa'
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }
    <?php endif; ?>

    // Village Distribution Chart
    <?php if (!empty($stats_data['village_distribution'])): ?>
    const villageCtx = document.getElementById('villageChart');
    if (villageCtx) {
        const villageData = <?php echo json_encode($stats_data['village_distribution']); ?>;
        new Chart(villageCtx, {
            type: 'bar',
            data: {
                labels: villageData.map(item => item.village_name),
                datasets: [{
                    label: 'Residences',
                    data: villageData.map(item => item.residence_count),
                    backgroundColor: '#17a2b8',
                    borderColor: '#138496',
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                }, {
                    label: 'Family Members',
                    data: villageData.map(item => item.family_count),
                    backgroundColor: '#ffc107',
                    borderColor: '#e0a800',
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f8f9fa'
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }
    <?php endif; ?>
});
</script>
