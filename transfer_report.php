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
                        <th>Transfer ID</th>
                        <th>Resident Name</th>
                        <th>House No</th>
                        <th>From Location</th>
                        <th>To Location</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th>Request Date</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($report_data as $row) {
                $html .= '<tr>
                    <td>' . $row['id'] . '</td>
                    <td>' . htmlspecialchars($row['resident_name']) . '</td>
                    <td>' . htmlspecialchars($row['house_no']) . '</td>
                    <td>' . htmlspecialchars($row['from_ward_name'] . ', ' . $row['from_village_name']) . '</td>
                    <td>' . htmlspecialchars($row['to_ward_name'] . ', ' . $row['to_village_name']) . '</td>
                    <td>' . getStatusDisplay($row['status']) . '</td>
                    <td>' . htmlspecialchars($row['requested_by_username']) . '</td>
                    <td>' . date('Y-m-d H:i', strtotime($row['created_at'])) . '</td>
                    <td>' . htmlspecialchars($row['reason']) . '</td>
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
        header('Content-Disposition: attachment; filename="transfer_report_' . date('Y-m-d_H-i-s') . '.pdf"');
        
        // For now, output HTML that can be printed as PDF by browser
        echo $html;
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
        $filename = 'transfer_report_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, ['Transfer ID', 'Resident Name', 'House No', 'From Location', 'To Location', 
                         'Status', 'Requested By', 'Request Date', 'Reason']);
        
        // Data rows
        foreach ($report_data as $data_row) {
            fputcsv($output, [
                $data_row['id'], $data_row['resident_name'], $data_row['house_no'],
                $data_row['from_ward_name'] . ', ' . $data_row['from_village_name'],
                $data_row['to_ward_name'] . ', ' . $data_row['to_village_name'],
                getStatusDisplay($data_row['status']), $data_row['requested_by_username'],
                date('Y-m-d H:i', strtotime($data_row['created_at'])), $data_row['reason']
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
                <h4 class="page-title">Transfer Report</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="reports_dashboard.php">Reports</a></li>
                        <li class="breadcrumb-item active">Transfer Report</li>
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
                                            <th>Transfer ID</th>
                                            <th>Resident Name</th>
                                            <th>House No</th>
                                            <th>From Location</th>
                                            <th>To Location</th>
                                            <th>Status</th>
                                            <th>Requested By</th>
                                            <th>Request Date</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo htmlspecialchars($row['resident_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['house_no']); ?></td>
                                                <td><?php echo htmlspecialchars($row['from_ward_name'] . ', ' . $row['from_village_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['to_ward_name'] . ', ' . $row['to_village_name']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo getStatusColor($row['status']); ?>">
                                                        <?php echo getStatusDisplay($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['requested_by_username']); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['reason']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
