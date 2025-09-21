<?php
// Suppress warnings and notices for clean PDF/CSV output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

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

// Get comprehensive family members data based on user role
$report_data = [];
$statistics = [];
$report_title = '';
$report_subtitle = '';

try {
    if ($user_role === 'super_admin') {
        // Super Admin sees all data
        $report_title = 'Family Members Report - System Wide';
        $report_subtitle = 'All Wards and Villages';
        
        // Get  statistics
        $stats_query = "
            SELECT 
                COUNT(DISTINCT fm.id) as total_family_members,
                COUNT(DISTINCT r.id) as total_residences,
                COUNT(DISTINCT v.id) as total_villages,
                COUNT(DISTINCT w.id) as total_wards,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) < 18 THEN 1 ELSE 0 END) as children_under_18,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 18 AND 24 THEN 1 ELSE 0 END) as adults_18_24,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 25 AND 39 THEN 1 ELSE 0 END) as adults_25_39,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 40 AND 54 THEN 1 ELSE 0 END) as adults_40_54,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 55 AND 65 THEN 1 ELSE 0 END) as adults_55_65,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 66 AND 110 THEN 1 ELSE 0 END) as seniors_66_110,
                SUM(CASE WHEN fm.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
                SUM(CASE WHEN fm.gender = 'Female' THEN 1 ELSE 0 END) as female_count
            FROM family_members fm
            JOIN residences r ON fm.residence_id = r.id
            JOIN villages v ON r.village_id = v.id
            JOIN wards w ON v.ward_id = w.id
            WHERE r.status = 'active'
        ";
        $stmt = $pdo->query($stats_query);
        $statistics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("
            SELECT fm.*, r.resident_name as residence_head, r.house_no, 
                   v.village_name, w.ward_name,
                   TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) as age
            FROM family_members fm
            JOIN residences r ON fm.residence_id = r.id
            JOIN villages v ON r.village_id = v.id
            JOIN wards w ON v.ward_id = w.id
            WHERE r.status = 'active'
            ORDER BY w.ward_name, v.village_name, r.resident_name, fm.relationship
        ");
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'admin') {
        // Admin sees their ward data
        $report_title = 'Family Members Report - Ward Administration';
        $report_subtitle = 'Ward: ' . $user_location['ward_name'];
        
        // Get comprehensive statistics for the ward
        $stats_query = "
            SELECT 
                COUNT(DISTINCT fm.id) as total_family_members,
                COUNT(DISTINCT r.id) as total_residences,
                COUNT(DISTINCT v.id) as total_villages,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) < 18 THEN 1 ELSE 0 END) as children_under_18,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 18 AND 24 THEN 1 ELSE 0 END) as adults_18_24,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 25 AND 39 THEN 1 ELSE 0 END) as adults_25_39,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 40 AND 54 THEN 1 ELSE 0 END) as adults_40_54,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 55 AND 65 THEN 1 ELSE 0 END) as adults_55_65,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 66 AND 110 THEN 1 ELSE 0 END) as seniors_66_110,
                SUM(CASE WHEN fm.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
                SUM(CASE WHEN fm.gender = 'Female' THEN 1 ELSE 0 END) as female_count
            FROM family_members fm
            JOIN residences r ON fm.residence_id = r.id
            JOIN villages v ON r.village_id = v.id
            WHERE v.ward_id = ? AND r.status = 'active'
        ";
        $stmt = $pdo->prepare($stats_query);
        $stmt->execute([$user_location['ward_id']]);
        $statistics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT fm.*, r.resident_name as residence_head, r.house_no, 
                   v.village_name, w.ward_name,
                   TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) as age
            FROM family_members fm
            JOIN residences r ON fm.residence_id = r.id
            JOIN villages v ON r.village_id = v.id
            JOIN wards w ON v.ward_id = w.id
            WHERE v.ward_id = ? AND r.status = 'active'
            ORDER BY v.village_name, r.resident_name, fm.relationship
        ");
        $stmt->execute([$user_location['ward_id']]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'weo') {
        // WEO sees their ward data
        $report_title = 'Family Members Report - Ward Executive Officer';
        $report_subtitle = 'Ward: ' . $user_location['ward_name'];
        
        // Get comprehensive statistics for the ward
        $stats_query = "
            SELECT 
                COUNT(DISTINCT fm.id) as total_family_members,
                COUNT(DISTINCT r.id) as total_residences,
                COUNT(DISTINCT v.id) as total_villages,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) < 18 THEN 1 ELSE 0 END) as children_under_18,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 18 AND 24 THEN 1 ELSE 0 END) as adults_18_24,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 25 AND 39 THEN 1 ELSE 0 END) as adults_25_39,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 40 AND 54 THEN 1 ELSE 0 END) as adults_40_54,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 55 AND 65 THEN 1 ELSE 0 END) as adults_55_65,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 66 AND 110 THEN 1 ELSE 0 END) as seniors_66_110,
                SUM(CASE WHEN fm.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
                SUM(CASE WHEN fm.gender = 'Female' THEN 1 ELSE 0 END) as female_count
            FROM family_members fm
            JOIN residences r ON fm.residence_id = r.id
            JOIN villages v ON r.village_id = v.id
            WHERE v.ward_id = ? AND r.status = 'active'
        ";
        $stmt = $pdo->prepare($stats_query);
        $stmt->execute([$user_location['ward_id']]);
        $statistics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT fm.*, r.resident_name as residence_head, r.house_no, 
                   v.village_name, w.ward_name,
                   TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) as age
            FROM family_members fm
            JOIN residences r ON fm.residence_id = r.id
            JOIN villages v ON r.village_id = v.id
            JOIN wards w ON v.ward_id = w.id
            WHERE v.ward_id = ? AND r.status = 'active'
            ORDER BY v.village_name, r.resident_name, fm.relationship
        ");
        $stmt->execute([$user_location['ward_id']]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'veo') {
        // VEO sees their village data
        $report_title = 'Family Members Report - Village Executive Officer';
        $report_subtitle = 'Village: ' . $user_location['village_name'];
        
        // Get comprehensive statistics for the village
        $stats_query = "
            SELECT 
                COUNT(DISTINCT fm.id) as total_family_members,
                COUNT(DISTINCT r.id) as total_residences,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) < 18 THEN 1 ELSE 0 END) as children_under_18,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 18 AND 24 THEN 1 ELSE 0 END) as adults_18_24,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 25 AND 39 THEN 1 ELSE 0 END) as adults_25_39,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 40 AND 54 THEN 1 ELSE 0 END) as adults_40_54,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 55 AND 65 THEN 1 ELSE 0 END) as adults_55_65,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) BETWEEN 66 AND 110 THEN 1 ELSE 0 END) as seniors_66_110,
                SUM(CASE WHEN fm.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
                SUM(CASE WHEN fm.gender = 'Female' THEN 1 ELSE 0 END) as female_count
            FROM family_members fm
            JOIN residences r ON fm.residence_id = r.id
            WHERE r.village_id = ? AND r.status = 'active'
        ";
        $stmt = $pdo->prepare($stats_query);
        $stmt->execute([$user_location['village_id']]);
        $statistics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT fm.*, r.resident_name as residence_head, r.house_no, 
                   v.village_name, w.ward_name,
                   TIMESTAMPDIFF(YEAR, fm.date_of_birth, CURDATE()) as age
            FROM family_members fm
            JOIN residences r ON fm.residence_id = r.id
            JOIN villages v ON r.village_id = v.id
            JOIN wards w ON v.ward_id = w.id
            WHERE r.village_id = ? AND r.status = 'active'
            ORDER BY r.resident_name, fm.relationship
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
        // Start output buffering to prevent any output before PDF
        ob_start();
        
        // Include TCPDF library
        require_once('vendor/autoload.php');
        
        // Generate dynamic heading based on user role
        $report_heading = 'Family Members Report';
        $report_description = 'Comprehensive family members data and demographics';
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Ward Registration System');
        $pdf->SetAuthor($_SESSION['username']);
        $pdf->SetTitle($report_heading);
        $pdf->SetSubject('Family Members Report');
        $pdf->SetKeywords('Family, Members, Demographics, Report');
        
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
        
        // Statistics Overview
        if (!empty($statistics)) {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Statistics Overview', 0, 1, 'L');
            $pdf->Ln(5);
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetTextColor(0);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.3);
            $pdf->SetFont('helvetica', 'B');
            
            // Statistics table
            $pdf->Cell(60, 8, 'Metric', 1, 0, 'C', 1);
            $pdf->Cell(30, 8, 'Count', 1, 1, 'C', 1);
            
            $pdf->SetFont('helvetica', '');
            $stats_data = array(
                array('Total Family Members', $statistics['total_family_members'] ?? 0),
                array('Total Residences', $statistics['total_residences'] ?? 0),
                array('Male Members', $statistics['male_count'] ?? 0),
                array('Female Members', $statistics['female_count'] ?? 0),
                array('Children (Under 18)', $statistics['children_under_18'] ?? 0),
                array('Young Adults (18-24)', $statistics['adults_18_24'] ?? 0),
                array('Adults (25-39)', $statistics['adults_25_39'] ?? 0),
                array('Middle Age (40-54)', $statistics['adults_40_54'] ?? 0),
                array('Pre-Senior (55-65)', $statistics['adults_55_65'] ?? 0),
                array('Seniors (66-110)', $statistics['seniors_66_110'] ?? 0)
            );
            
            if (isset($statistics['total_wards'])) {
                $stats_data[] = array('Total Wards', $statistics['total_wards']);
            }
            if (isset($statistics['total_villages'])) {
                $stats_data[] = array('Total Villages', $statistics['total_villages']);
            }
            
            foreach ($stats_data as $stat) {
                $pdf->Cell(60, 8, $stat[0], 1, 0, 'L', 0);
                $pdf->Cell(30, 8, $stat[1], 1, 1, 'C', 0);
            }
            $pdf->Ln(10);
        }
        
        // Family Members Data Table
        if (!empty($report_data)) {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Family Members Data', 0, 1, 'L');
            $pdf->Ln(5);
            
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetTextColor(0);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.3);
            $pdf->SetFont('helvetica', 'B');
            
            // Table headers
            $pdf->Cell(25, 8, 'Ward', 1, 0, 'C', 1);
            $pdf->Cell(25, 8, 'Village', 1, 0, 'C', 1);
            $pdf->Cell(30, 8, 'Residence Head', 1, 0, 'C', 1);
            $pdf->Cell(15, 8, 'House No', 1, 0, 'C', 1);
            $pdf->Cell(30, 8, 'Family Member', 1, 0, 'C', 1);
            $pdf->Cell(20, 8, 'Relationship', 1, 0, 'C', 1);
            $pdf->Cell(15, 8, 'Gender', 1, 0, 'C', 1);
            $pdf->Cell(20, 8, 'Age', 1, 0, 'C', 1);
            $pdf->Cell(25, 8, 'NIDA Number', 1, 1, 'C', 1);
            
            $pdf->SetFont('helvetica', '', 7);
            foreach ($report_data as $row) {
                $pdf->Cell(25, 8, $row['ward_name'], 1, 0, 'L', 0);
                $pdf->Cell(25, 8, $row['village_name'], 1, 0, 'L', 0);
                $pdf->Cell(30, 8, $row['residence_head'], 1, 0, 'L', 0);
                $pdf->Cell(15, 8, $row['house_no'], 1, 0, 'C', 0);
                $pdf->Cell(30, 8, $row['name'], 1, 0, 'L', 0);
                $pdf->Cell(20, 8, $row['relationship'], 1, 0, 'C', 0);
                $pdf->Cell(15, 8, $row['gender'], 1, 0, 'C', 0);
                $pdf->Cell(20, 8, $row['age'], 1, 0, 'C', 0);
                $pdf->Cell(25, 8, $row['nida_number'], 1, 1, 'C', 0);
            }
        } else {
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 10, 'No family members data available.', 0, 1, 'C');
        }
        
        // Footer
        $pdf->SetY(-15);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'Generated by Ward Registration System on ' . date('Y-m-d H:i:s'), 0, 0, 'C');
        
        // Clean output buffer and output PDF
        ob_end_clean();
        $filename = 'Family_Members_Report_' . date('Y-m-d_H-i-s') . '.pdf';
        $pdf->Output($filename, 'D');
        exit();
        
    } elseif ($export_format === 'csv') {
        // Start output buffering to prevent any output before CSV
        ob_start();
        
        // Generate CSV report
        $filename = 'Family_Members_Report_' . date('Y-m-d_H-i-s') . '.csv';
        
        // Clean output buffer and set headers
        ob_end_clean();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Add BOM for UTF-8 support in Excel
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Write report header
        fputcsv($output, array('Ward Registration System - Family Members Report'));
        fputcsv($output, array('Generated on: ' . date('Y-m-d H:i:s')));
        fputcsv($output, array('Generated by: ' . $_SESSION['username'] . ' (' . getRoleDisplayName($user_role) . ')'));
        fputcsv($output, array('Scope: ' . $report_subtitle));
        fputcsv($output, array(''));
        
        // Statistics Overview
        if (!empty($statistics)) {
            fputcsv($output, array('STATISTICS OVERVIEW'));
            fputcsv($output, array('Metric', 'Count'));
            fputcsv($output, array('Total Family Members', $statistics['total_family_members'] ?? 0));
            fputcsv($output, array('Total Residences', $statistics['total_residences'] ?? 0));
            fputcsv($output, array('Male Members', $statistics['male_count'] ?? 0));
            fputcsv($output, array('Female Members', $statistics['female_count'] ?? 0));
            fputcsv($output, array('Children (Under 18)', $statistics['children_under_18'] ?? 0));
            fputcsv($output, array('Young Adults (18-24)', $statistics['adults_18_24'] ?? 0));
            fputcsv($output, array('Adults (25-39)', $statistics['adults_25_39'] ?? 0));
            fputcsv($output, array('Middle Age (40-54)', $statistics['adults_40_54'] ?? 0));
            fputcsv($output, array('Pre-Senior (55-65)', $statistics['adults_55_65'] ?? 0));
            fputcsv($output, array('Seniors (66-110)', $statistics['seniors_66_110'] ?? 0));
            
            if (isset($statistics['total_wards'])) {
                fputcsv($output, array('Total Wards', $statistics['total_wards']));
            }
            if (isset($statistics['total_villages'])) {
                fputcsv($output, array('Total Villages', $statistics['total_villages']));
            }
            fputcsv($output, array(''));
        }
        
        // Family Members Data
        fputcsv($output, array('FAMILY MEMBERS DATA'));
        fputcsv($output, array('Ward', 'Village', 'Residence Head', 'House No', 'Family Member Name', 
                              'Relationship', 'Gender', 'Date of Birth', 'Age', 'NIDA Number', 
                              'Phone', 'Education Level', 'Employment Status'));
        
        foreach ($report_data as $row) {
            fputcsv($output, array($row['ward_name'], $row['village_name'], $row['residence_head'], 
                                 $row['house_no'], $row['name'], $row['relationship'], 
                                 $row['gender'], $row['date_of_birth'], $row['age'], $row['nida_number'], 
                                 $row['phone'], $row['education_level'], $row['employment_status']));
        }
        
        fclose($output);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $report_title; ?> - Ward Registration System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .max-w-6xl {
            max-width: 72rem;
        }

        .mx-auto {
            margin-left: auto;
            margin-right: auto;
        }

        .space-y-6 > * + * {
            margin-top: 1.5rem;
        }

        .bg-white {
            background-color: #ffffff;
        }

        .rounded-lg {
            border-radius: 0.5rem;
        }

        .shadow-md {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .p-6 {
            padding: 1.5rem;
        }

        .flex {
            display: flex;
        }

        .justify-between {
            justify-content: space-between;
        }

        .items-start {
            align-items: flex-start;
        }

        .items-center {
            align-items: center;
        }

        .mb-4 {
            margin-bottom: 1rem;
        }

        .mb-6 {
            margin-bottom: 1.5rem;
        }

        .text-2xl {
            font-size: 1.5rem;
            line-height: 2rem;
        }

        .text-xl {
            font-size: 1.25rem;
            line-height: 1.75rem;
        }

        .text-lg {
            font-size: 1.125rem;
            line-height: 1.75rem;
        }

        .text-sm {
            font-size: 0.875rem;
            line-height: 1.25rem;
        }

        .text-xs {
            font-size: 0.75rem;
            line-height: 1rem;
        }

        .font-bold {
            font-weight: 700;
        }

        .font-semibold {
            font-weight: 600;
        }

        .font-medium {
            font-weight: 500;
        }

        .text-gray-800 {
            color: #1f2937;
        }

        .text-gray-600 {
            color: #4b5563;
        }

        .text-gray-500 {
            color: #6b7280;
        }

        .text-gray-900 {
            color: #111827;
        }

        .space-x-2 > * + * {
            margin-left: 0.5rem;
        }

        .space-x-4 > * + * {
            margin-left: 1rem;
        }

        .space-y-2 > * + * {
            margin-top: 0.5rem;
        }

        .space-y-4 > * + * {
            margin-top: 1rem;
        }

        .bg-gray-500 {
            background-color: #6b7280;
        }

        .bg-blue-600 {
            background-color: #2563eb;
        }

        .bg-blue-100 {
            background-color: #dbeafe;
        }

        .bg-blue-800 {
            color: #1e40af;
        }

        .bg-green-100 {
            background-color: #dcfce7;
        }

        .bg-green-800 {
            color: #166534;
        }

        .bg-yellow-100 {
            background-color: #fef3c7;
        }

        .bg-yellow-800 {
            color: #92400e;
        }

        .bg-purple-100 {
            background-color: #e9d5ff;
        }

        .bg-purple-800 {
            color: #6b21a8;
        }

        .bg-red-100 {
            background-color: #fee2e2;
        }

        .bg-red-800 {
            color: #991b1b;
        }

        .bg-gray-100 {
            background-color: #f3f4f6;
        }

        .bg-gray-800 {
            color: #1f2937;
        }

        .text-white {
            color: #ffffff;
        }

        .text-blue-600 {
            color: #2563eb;
        }

        .text-red-600 {
            color: #dc2626;
        }

        .px-4 {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .py-2 {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        .px-3 {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }

        .py-1 {
            padding-top: 0.25rem;
            padding-bottom: 0.25rem;
        }

        .rounded-md {
            border-radius: 0.375rem;
        }

        .rounded-full {
            border-radius: 9999px;
        }

        .hover\:bg-gray-600:hover {
            background-color: #4b5563;
        }

        .hover\:bg-blue-700:hover {
            background-color: #1d4ed8;
        }

        .transition {
            transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }

        .duration-200 {
            transition-duration: 200ms;
        }

        .mr-2 {
            margin-right: 0.5rem;
        }

        .mr-4 {
            margin-right: 1rem;
        }

        .mt-1 {
            margin-top: 0.25rem;
        }

        .mt-6 {
            margin-top: 1.5rem;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .mb-3 {
            margin-bottom: 0.75rem;
        }

        .w-full {
            width: 100%;
        }

        .w-8 {
            width: 2rem;
        }

        .h-2 {
            height: 0.5rem;
        }

        .h-8 {
            height: 2rem;
        }

        .bg-gray-200 {
            background-color: #e5e7eb;
        }

        .bg-blue-500 {
            background-color: #3b82f6;
        }

        .bg-green-500 {
            background-color: #10b981;
        }

        .bg-red-500 {
            background-color: #ef4444;
        }

        .bg-red-50 {
            background-color: #fef2f2;
        }

        .bg-green-50 {
            background-color: #f0fdf4;
        }

        .bg-gray-50 {
            background-color: #f9fafb;
        }

        .text-red-800 {
            color: #991b1b;
        }

        .text-green-800 {
            color: #166534;
        }

        .text-blue-800 {
            color: #1e40af;
        }

        .text-yellow-800 {
            color: #92400e;
        }

        .text-purple-800 {
            color: #6b21a8;
        }

        .text-red-800 {
            color: #991b1b;
        }

        .text-gray-800 {
            color: #1f2937;
        }

        .text-gray-600 {
            color: #4b5563;
        }

        .text-gray-500 {
            color: #6b7280;
        }

        .text-gray-900 {
            color: #111827;
        }

        .text-gray-700 {
            color: #374151;
        }

        .block {
            display: block;
        }

        .flex-1 {
            flex: 1 1 0%;
        }

        .grid {
            display: grid;
        }

        .grid-cols-1 {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }

        .gap-6 {
            gap: 1.5rem;
        }

        .gap-8 {
            gap: 2rem;
        }

        .gap-2 {
            gap: 0.5rem;
        }

        .p-4 {
            padding: 1rem;
        }

        .font-mono {
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Consolas, "Liberation Mono", Menlo, monospace;
        }

        .justify-center {
            justify-content: center;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .overflow-x-auto {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background-color: #f8f9fa;
            color: #374151;
            font-weight: 600;
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: #f9fafb;
        }

        .table tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 9999px;
        }

        .badge-primary {
            background-color: #3b82f6;
            color: white;
        }

        .badge-success {
            background-color: #10b981;
            color: white;
        }

        .badge-info {
            background-color: #06b6d4;
            color: white;
        }

        .badge-warning {
            background-color: #f59e0b;
            color: white;
        }

        .badge-danger {
            background-color: #ef4444;
            color: white;
        }

        .badge-secondary {
            background-color: #6b7280;
            color: white;
        }

        .avatar-circle {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background-color: #3b82f6;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .d-flex {
            display: flex;
        }

        .align-items-center {
            align-items: center;
        }

        .me-2 {
            margin-right: 0.5rem;
        }

        .text-primary {
            color: #3b82f6;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
        }

        .empty-icon {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1.5rem;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.75rem;
        }

        .empty-desc {
            font-size: 1rem;
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn:hover {
            background-color: #2563eb;
            transform: translateY(-1px);
        }

        @media (min-width: 768px) {
            .md\:grid-cols-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .lg\:grid-cols-3 {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .md\:col-span-2 {
            grid-column: span 2 / span 2;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800"><?php echo $report_title; ?></h1>
                    <p class="text-gray-600"><?php echo $report_subtitle; ?></p>
                </div>
                <div class="flex space-x-2">
                    <a href="reports_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Reports
                    </a>
                    <a href="?export=pdf" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-file-pdf mr-2"></i>Export PDF
                    </a>
                    <a href="?export=csv" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200">
                        <i class="fas fa-file-csv mr-2"></i>Export CSV
                    </a>
                </div>
            </div>
            
            <!-- Report Info -->
            <div class="flex space-x-4 text-sm text-gray-600">
                <span><i class="fas fa-user-circle mr-1"></i> <?php echo $_SESSION['username']; ?></span>
                <span><i class="fas fa-clock mr-1"></i> <?php echo date('F j, Y, g:i a'); ?></span>
                <span><i class="fas fa-list mr-1"></i> <?php echo count($report_data); ?> records</span>
            </div>
        </div>

        <!-- Report Content -->
        <div class="bg-white rounded-lg shadow-md p-6">

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
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-users mr-2"></i>Family Members Data
                </h2>
                
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ward</th>
                                <th>Village</th>
                                <th>Residence Head</th>
                                <th>House No</th>
                                <th>Family Member</th>
                                <th>Relationship</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>NIDA Number</th>
                                <th>Phone</th>
                                <th>Education</th>
                                <th>Employment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $index => $row): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-map-marker-alt text-red-600 mr-2"></i>
                                        <?php echo htmlspecialchars($row['ward_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['village_name']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle mr-2">
                                                <?php echo strtoupper(substr($row['residence_head'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($row['residence_head']); ?>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($row['house_no']); ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle mr-2">
                                                <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($row['name']); ?>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars($row['relationship']); ?></span></td>
                                    <td>
                                        <span class="badge <?php echo $row['gender'] === 'Male' ? 'badge-info' : 'badge-warning'; ?>">
                                            <?php echo htmlspecialchars($row['gender']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            <?php echo $row['age']; ?> years
                                        </span>
                                    </td>
                                    <td class="font-mono"><?php echo htmlspecialchars($row['nida_number']); ?></td>
                                    <td>
                                        <a href="tel:<?php echo htmlspecialchars($row['phone']); ?>" class="text-blue-600">
                                            <i class="fas fa-phone-alt mr-1"></i> <?php echo htmlspecialchars($row['phone']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['education_level']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $row['employment_status'] === 'Employed' ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo htmlspecialchars($row['employment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="empty-title">No Family Members Found</h3>
                    <p class="empty-desc">There are no family members registered in your assigned area.</p>
                    <div class="empty-actions">
                        <a href="reports_dashboard.php" class="btn">
                            <i class="fas fa-arrow-left"></i> Back to Reports
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
