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
            $where_conditions[] = "r.registered_at >= ? AND r.registered_at <= ?";
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
                    ORDER BY r.registered_at DESC
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
                    ORDER BY r.registered_at DESC
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Report Generator - Residence Management System</title>
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

        /* Form Section */
        .form-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 24px;
        }

        .form-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control {
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #157347;
            transform: translateY(-2px);
        }

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-info:hover {
            background: #0aa2c0;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #b02a37;
            transform: translateY(-2px);
        }

        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
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

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .alert-success {
            background: rgba(25, 135, 84, 0.1);
            color: var(--success);
            border: 1px solid rgba(25, 135, 84, 0.2);
        }

        .alert-info {
            background: rgba(13, 202, 240, 0.1);
            color: var(--info);
            border: 1px solid rgba(13, 202, 240, 0.2);
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
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
                    <span class="badge-icon">⚙️</span>
                    <span class="badge-text">Custom Report Generator</span>
                </div>
                <h1 class="hero-title">
                    Generate Custom Reports
                    <span class="title-highlight">Analytics</span>
                </h1>
                <p class="hero-description">
                    Create personalized reports with advanced filtering options.<br>
                    Select your criteria and generate detailed analytics for your data.
                </p>
            </div>
        </div>

        <!-- Form Section -->
        <div class="form-container fade-in">
            <h2 class="form-title">
                <i class="fas fa-cog"></i>
                Generate Custom Report
            </h2>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="report_type" class="form-label">
                            <i class="fas fa-chart-bar"></i>
                            Report Type
                        </label>
                        <select class="form-control form-select" id="report_type" name="report_type" required>
                            <option value="">Select Report Type</option>
                            <option value="residences" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] === 'residences') ? 'selected' : ''; ?>>Residences Report</option>
                            <option value="family_members" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] === 'family_members') ? 'selected' : ''; ?>>Family Members Report</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from" class="form-label">
                            <i class="fas fa-calendar-alt"></i>
                            From Date
                        </label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo $_POST['date_from'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to" class="form-label">
                            <i class="fas fa-calendar-alt"></i>
                            To Date
                        </label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo $_POST['date_to'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="gender_filter" class="form-label">
                            <i class="fas fa-venus-mars"></i>
                            Gender Filter
                        </label>
                        <select class="form-control form-select" id="gender_filter" name="gender_filter">
                            <option value="">All Genders</option>
                            <option value="Male" <?php echo (isset($_POST['gender_filter']) && $_POST['gender_filter'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($_POST['gender_filter']) && $_POST['gender_filter'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ownership_filter" class="form-label">
                            <i class="fas fa-key"></i>
                            Ownership Filter
                        </label>
                        <select class="form-control form-select" id="ownership_filter" name="ownership_filter">
                            <option value="">All Ownership Types</option>
                            <option value="Owner" <?php echo (isset($_POST['ownership_filter']) && $_POST['ownership_filter'] === 'Owner') ? 'selected' : ''; ?>>Owner</option>
                            <option value="Tenant" <?php echo (isset($_POST['ownership_filter']) && $_POST['ownership_filter'] === 'Tenant') ? 'selected' : ''; ?>>Tenant</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="education_filter" class="form-label">
                            <i class="fas fa-graduation-cap"></i>
                            Education Level Filter
                        </label>
                        <select class="form-control form-select" id="education_filter" name="education_filter">
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
                    
                    <div class="form-group">
                        <label for="employment_filter" class="form-label">
                            <i class="fas fa-briefcase"></i>
                            Employment Status Filter
                        </label>
                        <select class="form-control form-select" id="employment_filter" name="employment_filter">
                            <option value="">All Employment Status</option>
                            <option value="Employed" <?php echo (isset($_POST['employment_filter']) && $_POST['employment_filter'] === 'Employed') ? 'selected' : ''; ?>>Employed</option>
                            <option value="Unemployed" <?php echo (isset($_POST['employment_filter']) && $_POST['employment_filter'] === 'Unemployed') ? 'selected' : ''; ?>>Unemployed</option>
                            <option value="Self-employed" <?php echo (isset($_POST['employment_filter']) && $_POST['employment_filter'] === 'Self-employed') ? 'selected' : ''; ?>>Self-employed</option>
                            <option value="Student" <?php echo (isset($_POST['employment_filter']) && $_POST['employment_filter'] === 'Student') ? 'selected' : ''; ?>>Student</option>
                            <option value="Retired" <?php echo (isset($_POST['employment_filter']) && $_POST['employment_filter'] === 'Retired') ? 'selected' : ''; ?>>Retired</option>
                        </select>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-chart-line"></i>
                        Generate Report
                    </button>
                    <a href="reports_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Reports
                    </a>
                </div>
            </form>
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
                                        <td><?php echo date('Y-m-d', strtotime($row['registered_at'] ?? 'now')); ?></td>
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

<style>
@media print {
    .no-print { display: none !important; }
    .card-header .card-tools { display: none !important; }
    .btn-group { display: none !important; }
    body { margin: 0; }
    .container-fluid { padding: 0; }
    .card { border: none; box-shadow: none; }
    .table { font-size: 12px; }
    .table th, .table td { padding: 4px; }
}
</style>

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
