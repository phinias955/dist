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

// Get statistics for dashboard
$stats = [];
try {
    if ($user_role === 'super_admin') {
        // Super Admin sees all data
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM residences WHERE status = 'active'");
        $stats['total_residences'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM family_members");
        $stats['total_family'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM wards");
        $stats['total_wards'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM villages");
        $stats['total_villages'] = $stmt->fetch()['total'];
        
    } elseif ($user_role === 'admin' || $user_role === 'weo') {
        // Admin/WEO sees ward data
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM residences r JOIN villages v ON r.village_id = v.id WHERE v.ward_id = ? AND r.status = 'active'");
        $stmt->execute([$user_location['ward_id']]);
        $stats['total_residences'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM family_members fm JOIN residences r ON fm.residence_id = r.id JOIN villages v ON r.village_id = v.id WHERE v.ward_id = ?");
        $stmt->execute([$user_location['ward_id']]);
        $stats['total_family'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM villages WHERE ward_id = ?");
        $stmt->execute([$user_location['ward_id']]);
        $stats['total_villages'] = $stmt->fetch()['total'];
        
    } elseif ($user_role === 'veo') {
        // VEO sees village data
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM residences WHERE village_id = ? AND status = 'active'");
        $stmt->execute([$user_location['village_id']]);
        $stats['total_residences'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM family_members fm JOIN residences r ON fm.residence_id = r.id WHERE r.village_id = ?");
        $stmt->execute([$user_location['village_id']]);
        $stats['total_family'] = $stmt->fetch()['total'];
    }
} catch (PDOException $e) {
    $stats_error = "Error loading statistics: " . $e->getMessage();
}

include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Dashboard - Residence Management System</title>
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

        .hero-stats {
            display: flex;
            gap: 30px;
        }

        .hero-stat {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.8;
        }

        .hero-visual {
            position: relative;
            height: 200px;
        }

        .floating-cards {
            position: relative;
            height: 100%;
        }

        .floating-card {
            position: absolute;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            animation: float 3s ease-in-out infinite;
        }

        .card-1 {
            top: 20px;
            right: 120px;
            animation-delay: 0s;
        }

        .card-2 {
            top: 80px;
            right: 60px;
            animation-delay: 1s;
        }

        .card-3 {
            top: 40px;
            right: 0;
            animation-delay: 2s;
        }

        .card-4 {
            top: 100px;
            right: 180px;
            animation-delay: 1.5s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Stats Overview */
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
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
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

        .stat-content {
            flex: 1;
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

        /* Reports Grid */
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .report-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .summary-card .card-icon {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .residence-card .card-icon {
            background: rgba(25, 135, 84, 0.1);
            color: var(--success);
        }

        .family-card .card-icon {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .transfer-card .card-icon {
            background: rgba(13, 202, 240, 0.1);
            color: var(--info);
        }

        .statistics-card .card-icon {
            background: rgba(108, 117, 125, 0.1);
            color: var(--secondary);
        }

        .custom-card .card-icon {
            background: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
        }

        .card-info {
            flex: 1;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 4px;
        }

        .card-subtitle {
            font-size: 14px;
            color: var(--gray-600);
        }

        .card-status {
            display: flex;
            align-items: center;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-dot.active {
            background: var(--success);
        }

        .status-dot.new {
            background: var(--info);
        }

        .card-body {
            padding: 20px;
            flex: 1;
        }

        .card-description {
            margin-bottom: 16px;
        }

        .card-description p {
            color: var(--gray-600);
            font-size: 14px;
            line-height: 1.5;
        }

        .card-features {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .feature-tag {
            padding: 4px 10px;
            background: var(--gray-100);
            border-radius: 50px;
            font-size: 12px;
            color: var(--gray-600);
        }

        .card-footer {
            padding: 20px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .primary-btn {
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
            flex: 1;
            justify-content: center;
        }

        .primary-btn:hover {
            background: var(--primary-dark);
            color: white;
            text-decoration: none;
        }

        .export-options {
            display: flex;
            gap: 8px;
        }

        .export-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--gray-100);
            color: var(--gray-600);
            text-decoration: none;
            transition: var(--transition);
        }

        .export-btn:hover {
            background: var(--gray-200);
            transform: translateY(-2px);
        }

        .export-btn.pdf:hover {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .export-btn.excel:hover {
            background: rgba(25, 135, 84, 0.1);
            color: var(--success);
        }

        .export-btn.csv:hover {
            background: rgba(13, 202, 240, 0.1);
            color: var(--info);
        }

        .card-note {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--gray-500);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .reports-grid {
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            }
        }

        @media (max-width: 992px) {
            .hero-stats {
                flex-wrap: wrap;
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .dashboard-hero {
                padding: 20px;
            }
            
            .hero-title {
                font-size: 28px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .card-footer {
                flex-direction: column;
                align-items: stretch;
            }
            
            .export-options {
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .hero-title {
                font-size: 24px;
            }
            
            .hero-description {
                font-size: 14px;
            }
            
            .stat-card {
                flex-direction: column;
                text-align: center;
            }
            
            .card-header {
                flex-direction: column;
                text-align: center;
                gap: 12px;
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

        .delay-1 {
            animation-delay: 0.1s;
        }

        .delay-2 {
            animation-delay: 0.2s;
        }

        .delay-3 {
            animation-delay: 0.3s;
        }

        .delay-4 {
            animation-delay: 0.4s;
        }

        .delay-5 {
            animation-delay: 0.5s;
        }

        .delay-6 {
            animation-delay: 0.6s;
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
                    <span class="badge-text">Reports Center</span>
                </div>
                <h1 class="hero-title">
                    Analytics & Reports
                    <span class="title-highlight">Dashboard</span>
                </h1>
                <p class="hero-description">
                    Generate comprehensive reports and analytics for your data. 
                    Export in multiple formats and get insights at a glance.
                </p>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="stat-number">6</span>
                        <span class="stat-label " style="color: white;">Report Types</span>
                    </div>
                    <div class="hero-stat">
                        <span class="stat-number">3</span>
                        <span class="stat-label" style="color: white;">Export Formats</span>
                    </div>
                    <div class="hero-stat">
                        <span class="stat-number">24/7</span>
                        <span class="stat-label" style="color: white;">Available</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <?php if (isset($stats_error)): ?>
                <div class="stat-card fade-in">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">Error</div>
                        <div class="stat-label"><?php echo $stats_error; ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="stat-card fade-in delay-1">
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['total_residences'] ?? 0; ?></div>
                        <div class="stat-label">
                            <?php 
                            if ($user_role === 'super_admin') echo 'Total Residences';
                            elseif ($user_role === 'admin' || $user_role === 'weo') echo 'Ward Residences';
                            else echo 'Village Residences';
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card fade-in delay-2">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['total_family'] ?? 0; ?></div>
                        <div class="stat-label">
                            <?php 
                            if ($user_role === 'super_admin') echo 'Total Family Members';
                            elseif ($user_role === 'admin' || $user_role === 'weo') echo 'Ward Family Members';
                            else echo 'Village Family Members';
                            ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($user_role === 'super_admin'): ?>
                <div class="stat-card fade-in delay-3">
                    <div class="stat-icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['total_wards'] ?? 0; ?></div>
                        <div class="stat-label">Total Wards</div>
                    </div>
                </div>
                
                <div class="stat-card fade-in delay-4">
                    <div class="stat-icon">
                        <i class="fas fa-house-user"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['total_villages'] ?? 0; ?></div>
                        <div class="stat-label">Total Villages</div>
                    </div>
                </div>
                <?php elseif ($user_role === 'admin' || $user_role === 'weo'): ?>
                <div class="stat-card fade-in delay-3">
                    <div class="stat-icon">
                        <i class="fas fa-house-user"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['total_villages'] ?? 0; ?></div>
                        <div class="stat-label">Ward Villages</div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Reports Grid -->
        <div class="reports-grid">
            <!-- Summary Report Card -->
            <div class="report-card summary-card fade-in delay-1">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="card-info">
                        <h3 class="card-title">Summary Report</h3>
                        <p class="card-subtitle">Overview of all data</p>
                    </div>
                    <div class="card-status">
                        <span class="status-dot active"></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-description">
                        <p>Get a comprehensive overview of all your data with key metrics and statistics.</p>
                    </div>
                    <div class="card-features">
                        <span class="feature-tag">Analytics</span>
                        <span class="feature-tag">Summary</span>
                        <span class="feature-tag">Overview</span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="reports.php" class="primary-btn">
                        <i class="fas fa-eye"></i>
                        <span>View Report</span>
                    </a>
                    <div class="export-options">
                        <a href="reports.php?export=pdf" class="export-btn pdf" title="Export PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <a href="reports.php?export=excel" class="export-btn excel" title="Export Excel">
                            <i class="fas fa-file-excel"></i>
                        </a>
                        <a href="reports.php?export=csv" class="export-btn csv" title="Export CSV">
                            <i class="fas fa-file-csv"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Detailed Residence Report Card -->
            <div class="report-card residence-card fade-in delay-2">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="card-info">
                        <h3 class="card-title">Detailed Residence Report</h3>
                        <p class="card-subtitle">Complete residence information</p>
                    </div>
                    <div class="card-status">
                        <span class="status-dot active"></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-description">
                        <p>Comprehensive residence data with detailed information about each property and its occupants.</p>
                    </div>
                    <div class="card-features">
                        <span class="feature-tag">Residences</span>
                        <span class="feature-tag">Details</span>
                        <span class="feature-tag">Occupants</span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="detailed_residence_report.php" class="primary-btn">
                        <i class="fas fa-eye"></i>
                        <span>View Report</span>
                    </a>
                    <div class="export-options">
                        <a href="detailed_residence_report.php?export=pdf" class="export-btn pdf" title="Export PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <a href="detailed_residence_report.php?export=excel" class="export-btn excel" title="Export Excel">
                            <i class="fas fa-file-excel"></i>
                        </a>
                        <a href="detailed_residence_report.php?export=csv" class="export-btn csv" title="Export CSV">
                            <i class="fas fa-file-csv"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Family Members Report Card -->
            <div class="report-card family-card fade-in delay-3">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-info">
                        <h3 class="card-title">Family Members Report</h3>
                        <p class="card-subtitle">Family member details</p>
                    </div>
                    <div class="card-status">
                        <span class="status-dot active"></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-description">
                        <p>Detailed information about all family members including demographics and relationships.</p>
                    </div>
                    <div class="card-features">
                        <span class="feature-tag">Family</span>
                        <span class="feature-tag">Demographics</span>
                        <span class="feature-tag">Relationships</span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="family_members_report.php" class="primary-btn">
                        <i class="fas fa-eye"></i>
                        <span>View Report</span>
                    </a>
                    <div class="export-options">
                        <a href="family_members_report.php?export=pdf" class="export-btn pdf" title="Export PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <a href="family_members_report.php?export=excel" class="export-btn excel" title="Export Excel">
                            <i class="fas fa-file-excel"></i>
                        </a>
                        <a href="family_members_report.php?export=csv" class="export-btn csv" title="Export CSV">
                            <i class="fas fa-file-csv"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Transfer Report Card -->
            <div class="report-card transfer-card fade-in delay-4">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="card-info">
                        <h3 class="card-title">Transfer Report</h3>
                        <p class="card-subtitle">Residence transfers and movements</p>
                    </div>
                    <div class="card-status">
                        <span class="status-dot active"></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-description">
                        <p>Track all residence transfers, movements, and status changes across different locations.</p>
                    </div>
                    <div class="card-features">
                        <span class="feature-tag">Transfers</span>
                        <span class="feature-tag">Movements</span>
                        <span class="feature-tag">Tracking</span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="transfer_report.php" class="primary-btn">
                        <i class="fas fa-eye"></i>
                        <span>View Report</span>
                    </a>
                    <div class="export-options">
                        <a href="transfer_report.php?export=pdf" class="export-btn pdf" title="Export PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <a href="transfer_report.php?export=excel" class="export-btn excel" title="Export Excel">
                            <i class="fas fa-file-excel"></i>
                        </a>
                        <a href="transfer_report.php?export=csv" class="export-btn csv" title="Export CSV">
                            <i class="fas fa-file-csv"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Report Card -->
            <div class="report-card statistics-card fade-in delay-5">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="card-info">
                        <h3 class="card-title">Statistics Report</h3>
                        <p class="card-subtitle">Statistical analysis and trends</p>
                    </div>
                    <div class="card-status">
                        <span class="status-dot active"></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-description">
                        <p>Advanced statistical analysis with charts, trends, and data insights for better decision making.</p>
                    </div>
                    <div class="card-features">
                        <span class="feature-tag">Analytics</span>
                        <span class="feature-tag">Trends</span>
                        <span class="feature-tag">Insights</span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="statistics_report.php" class="primary-btn">
                        <i class="fas fa-eye"></i>
                        <span>View Report</span>
                    </a>
                    <div class="export-options">
                        <a href="statistics_report.php?export=pdf" class="export-btn pdf" title="Export PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <a href="statistics_report.php?export=excel" class="export-btn excel" title="Export Excel">
                            <i class="fas fa-file-excel"></i>
                        </a>
                        <a href="statistics_report.php?export=csv" class="export-btn csv" title="Export CSV">
                            <i class="fas fa-file-csv"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Custom Report Card -->
            <div class="report-card custom-card fade-in delay-6">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="card-info">
                        <h3 class="card-title">Custom Report</h3>
                        <p class="card-subtitle">Create custom reports</p>
                    </div>
                    <div class="card-status">
                        <span class="status-dot new"></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-description">
                        <p>Build your own custom reports with advanced filtering, date ranges, and specific criteria.</p>
                    </div>
                    <div class="card-features">
                        <span class="feature-tag">Custom</span>
                        <span class="feature-tag">Filters</span>
                        <span class="feature-tag">Advanced</span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="custom_report.php" class="primary-btn">
                        <i class="fas fa-plus"></i>
                        <span>Create Report</span>
                    </a>
                    <div class="card-note">
                        <i class="fas fa-lightbulb"></i>
                        <span>Advanced filtering available</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Add click animation to cards
            const cards = document.querySelectorAll('.report-card');
            cards.forEach(card => {
                card.addEventListener('click', function(e) {
                    if (e.target.tagName !== 'A' && !e.target.closest('a')) {
                        const link = this.querySelector('a.primary-btn');
                        if (link) link.click();
                    }
                });
            });
            
            // Add hover effects to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.1)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.08)';
                });
            });
        });
    </script>
</body>
</html>

<?php include 'includes/footer.php'; ?>