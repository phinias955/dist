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

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? '';
    $date_from = $_POST['date_from'] ?? '';
    $date_to = $_POST['date_to'] ?? '';
    $gender_filter = $_POST['gender_filter'] ?? '';
    $ownership_filter = $_POST['ownership_filter'] ?? '';
    $education_filter = $_POST['education_filter'] ?? '';
    $employment_filter = $_POST['employment_filter'] ?? '';
    
    // Validate required fields
    if (empty($report_type)) {
        $error_message = "Please select a report type.";
    } elseif (empty($date_from) || empty($date_to)) {
        $error_message = "Please select both start and end dates.";
    } else {
        // Generate custom report
        try {
            $where_conditions = ["r.status = 'active'"];
            $params = [];
            
            // Add date filter
            $where_conditions[] = "r.created_at >= ? AND r.created_at <= ?";
            $params[] = $date_from . ' 00:00:00';
            $params[] = $date_to . ' 23:59:59';
            
            // Add role-based location filter
            if ($user_role === 'admin' || $user_role === 'weo') {
                $where_conditions[] = "v.ward_id = ?";
                $params[] = $user_location['ward_id'];
            } elseif ($user_role === 'veo') {
                $where_conditions[] = "r.village_id = ?";
                $params[] = $user_location['village_id'];
            }
            
            // Add gender filter
            if (!empty($gender_filter)) {
                $where_conditions[] = "r.gender = ?";
                $params[] = $gender_filter;
            }
            
            // Add ownership filter
            if (!empty($ownership_filter)) {
                $where_conditions[] = "r.ownership = ?";
                $params[] = $ownership_filter;
            }
            
            // Add education filter
            if (!empty($education_filter)) {
                $where_conditions[] = "r.education_level = ?";
                $params[] = $education_filter;
            }
            
            // Add employment filter
            if (!empty($employment_filter)) {
                $where_conditions[] = "r.employment_status = ?";
                $params[] = $employment_filter;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            if ($report_type === 'residences') {
                $sql = "
                    SELECT r.*, v.village_name, w.ward_name,
                           COUNT(fm.id) as family_member_count
                    FROM residences r
                    LEFT JOIN villages v ON r.village_id = v.id
                    LEFT JOIN wards w ON v.ward_id = w.id
                    LEFT JOIN family_members fm ON r.id = fm.residence_id
                    WHERE $where_clause
                    GROUP BY r.id
                    ORDER BY r.created_at DESC
                ";
            } else {
                $sql = "
                    SELECT fm.*, r.resident_name as residence_head, r.house_no,
                           v.village_name, w.ward_name
                    FROM family_members fm
                    JOIN residences r ON fm.residence_id = r.id
                    JOIN villages v ON r.village_id = v.id
                    JOIN wards w ON v.ward_id = w.id
                    WHERE $where_clause
                    ORDER BY r.created_at DESC
                ";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $success_message = "Report generated successfully with " . count($report_data) . " records.";
            
        } catch (PDOException $e) {
            $error_message = "Error generating report: " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Custom Report Generator</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="reports_dashboard.php">Reports</a></li>
                        <li class="breadcrumb-item active">Custom Report</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Generate Custom Report</h4>
                </div>
                <div class="card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="report_type">Report Type</label>
                                    <select class="form-control" id="report_type" name="report_type" required>
                                        <option value="">Select Report Type</option>
                                        <option value="residences" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] === 'residences') ? 'selected' : ''; ?>>Residences Report</option>
                                        <option value="family_members" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] === 'family_members') ? 'selected' : ''; ?>>Family Members Report</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_from">From Date</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" 
                                           value="<?php echo $_POST['date_from'] ?? ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_to">To Date</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" 
                                           value="<?php echo $_POST['date_to'] ?? ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="gender_filter">Gender Filter</label>
                                    <select class="form-control" id="gender_filter" name="gender_filter">
                                        <option value="">All Genders</option>
                                        <option value="Male" <?php echo (isset($_POST['gender_filter']) && $_POST['gender_filter'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (isset($_POST['gender_filter']) && $_POST['gender_filter'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="ownership_filter">Ownership Filter</label>
                                    <select class="form-control" id="ownership_filter" name="ownership_filter">
                                        <option value="">All Ownership Types</option>
                                        <option value="Owner" <?php echo (isset($_POST['ownership_filter']) && $_POST['ownership_filter'] === 'Owner') ? 'selected' : ''; ?>>Owner</option>
                                        <option value="Tenant" <?php echo (isset($_POST['ownership_filter']) && $_POST['ownership_filter'] === 'Tenant') ? 'selected' : ''; ?>>Tenant</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="education_filter">Education Level Filter</label>
                                    <select class="form-control" id="education_filter" name="education_filter">
                                        <option value="">All Education Levels</option>
                                        <option value="Primary" <?php echo (isset($_POST['education_filter']) && $_POST['education_filter'] === 'Primary') ? 'selected' : ''; ?>>Primary</option>
                                        <option value="Secondary" <?php echo (isset($_POST['education_filter']) && $_POST['education_filter'] === 'Secondary') ? 'selected' : ''; ?>>Secondary</option>
                                        <option value="Diploma" <?php echo (isset($_POST['education_filter']) && $_POST['education_filter'] === 'Diploma') ? 'selected' : ''; ?>>Diploma</option>
                                        <option value="Degree" <?php echo (isset($_POST['education_filter']) && $_POST['education_filter'] === 'Degree') ? 'selected' : ''; ?>>Degree</option>
                                        <option value="Masters" <?php echo (isset($_POST['education_filter']) && $_POST['education_filter'] === 'Masters') ? 'selected' : ''; ?>>Masters</option>
                                        <option value="PhD" <?php echo (isset($_POST['education_filter']) && $_POST['education_filter'] === 'PhD') ? 'selected' : ''; ?>>PhD</option>
                                        <option value="None" <?php echo (isset($_POST['education_filter']) && $_POST['education_filter'] === 'None') ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="employment_filter">Employment Status Filter</label>
                                    <select class="form-control" id="employment_filter" name="employment_filter">
                                        <option value="">All Employment Status</option>
                                        <option value="Employed" <?php echo (isset($_POST['employment_filter']) && $_POST['employment_filter'] === 'Employed') ? 'selected' : ''; ?>>Employed</option>
                                        <option value="Unemployed" <?php echo (isset($_POST['employment_filter']) && $_POST['employment_filter'] === 'Unemployed') ? 'selected' : ''; ?>>Unemployed</option>
                                        <option value="Self-employed" <?php echo (isset($_POST['employment_filter']) && $_POST['employment_filter'] === 'Self-employed') ? 'selected' : ''; ?>>Self-employed</option>
                                        <option value="Student" <?php echo (isset($_POST['employment_filter']) && $_POST['employment_filter'] === 'Student') ? 'selected' : ''; ?>>Student</option>
                                        <option value="Retired" <?php echo (isset($_POST['employment_filter']) && $_POST['employment_filter'] === 'Retired') ? 'selected' : ''; ?>>Retired</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-chart-bar mr-2"></i>Generate Report
                            </button>
                            <a href="reports_dashboard.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Reports
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Report Information</h5>
                </div>
                <div class="card-body">
                    <h6>Available Filters:</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success mr-2"></i>Date Range</li>
                        <li><i class="fas fa-check text-success mr-2"></i>Gender</li>
                        <li><i class="fas fa-check text-success mr-2"></i>Ownership Type</li>
                        <li><i class="fas fa-check text-success mr-2"></i>Education Level</li>
                        <li><i class="fas fa-check text-success mr-2"></i>Employment Status</li>
                    </ul>
                    
                    <h6 class="mt-3">Report Types:</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-building text-primary mr-2"></i>Residences Report</li>
                        <li><i class="fas fa-users text-info mr-2"></i>Family Members Report</li>
                    </ul>
                    
                    <div class="alert alert-info mt-3">
                        <small>
                            <i class="fas fa-info-circle mr-1"></i>
                            Reports are automatically filtered based on your role and assigned location.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (isset($report_data) && !empty($report_data)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Report Results</h4>
                    <div class="card-tools">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-danger btn-sm" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </button>
                            <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                            <button type="button" class="btn btn-info btn-sm" onclick="exportToCSV()">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered" id="reportTable">
                            <thead class="thead-dark">
                                <?php if ($report_type === 'residences'): ?>
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
                                <?php else: ?>
                                <tr>
                                    <th>Ward</th>
                                    <th>Village</th>
                                    <th>Residence Head</th>
                                    <th>House No</th>
                                    <th>Family Member Name</th>
                                    <th>Relationship</th>
                                    <th>Gender</th>
                                    <th>Date of Birth</th>
                                    <th>NIDA Number</th>
                                    <th>Phone</th>
                                    <th>Occupation</th>
                                    <th>Education Level</th>
                                    <th>Employment Status</th>
                                    <th>Email</th>
                                </tr>
                                <?php endif; ?>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <?php if ($report_type === 'residences'): ?>
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
                                    <?php else: ?>
                                        <td><?php echo htmlspecialchars($row['ward_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['village_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['residence_head']); ?></td>
                                        <td><?php echo htmlspecialchars($row['house_no']); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['relationship']); ?></td>
                                        <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                        <td><?php echo htmlspecialchars($row['date_of_birth']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nida_number']); ?></td>
                                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($row['occupation']); ?></td>
                                        <td><?php echo htmlspecialchars($row['education_level']); ?></td>
                                        <td><?php echo htmlspecialchars($row['employment_status']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <?php endif; ?>
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
</div>

<script>
function exportToPDF() {
    // Simple PDF export using browser print
    window.print();
}

function exportToExcel() {
    // Simple Excel export using table data
    const table = document.getElementById('reportTable');
    const wb = XLSX.utils.table_to_book(table);
    XLSX.writeFile(wb, 'custom_report_<?php echo date('Y-m-d_H-i-s'); ?>.xlsx');
}

function exportToCSV() {
    // Simple CSV export
    const table = document.getElementById('reportTable');
    const csv = tableToCSV(table);
    downloadCSV(csv, 'custom_report_<?php echo date('Y-m-d_H-i-s'); ?>.csv');
}

function tableToCSV(table) {
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            row.push(cols[j].innerText);
        }
        
        csv.push(row.join(','));
    }
    
    return csv.join('\n');
}

function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<!-- Include SheetJS for Excel export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<?php include 'includes/footer.php'; ?>
