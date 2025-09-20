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

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Reports Dashboard</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Reports</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Summary Report Card -->
        <div class="col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title">Summary Report</h4>
                            <p class="text-muted">Overview of all data</p>
                        </div>
                        <div class="align-self-center">
                            <i class="mdi mdi-chart-line text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="reports.php" class="btn btn-primary btn-sm">View Report</a>
                        <div class="btn-group ml-2" role="group">
                            <a href="reports.php?export=pdf" class="btn btn-outline-danger btn-sm">
                                <i class="mdi mdi-file-pdf"></i>
                            </a>
                            <a href="reports.php?export=excel" class="btn btn-outline-success btn-sm">
                                <i class="mdi mdi-file-excel"></i>
                            </a>
                            <a href="reports.php?export=csv" class="btn btn-outline-info btn-sm">
                                <i class="mdi mdi-file-csv"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Residence Report Card -->
        <div class="col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title">Detailed Residence Report</h4>
                            <p class="text-muted">Complete residence information</p>
                        </div>
                        <div class="align-self-center">
                            <i class="mdi mdi-home text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="detailed_residence_report.php" class="btn btn-success btn-sm">View Report</a>
                        <div class="btn-group ml-2" role="group">
                            <a href="detailed_residence_report.php?export=pdf" class="btn btn-outline-danger btn-sm">
                                <i class="mdi mdi-file-pdf"></i>
                            </a>
                            <a href="detailed_residence_report.php?export=excel" class="btn btn-outline-success btn-sm">
                                <i class="mdi mdi-file-excel"></i>
                            </a>
                            <a href="detailed_residence_report.php?export=csv" class="btn btn-outline-info btn-sm">
                                <i class="mdi mdi-file-csv"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Family Members Report Card -->
        <div class="col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title">Family Members Report</h4>
                            <p class="text-muted">Family member details</p>
                        </div>
                        <div class="align-self-center">
                            <i class="mdi mdi-account-group text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="family_members_report.php" class="btn btn-info btn-sm">View Report</a>
                        <div class="btn-group ml-2" role="group">
                            <a href="family_members_report.php?export=pdf" class="btn btn-outline-danger btn-sm">
                                <i class="mdi mdi-file-pdf"></i>
                            </a>
                            <a href="family_members_report.php?export=excel" class="btn btn-outline-success btn-sm">
                                <i class="mdi mdi-file-excel"></i>
                            </a>
                            <a href="family_members_report.php?export=csv" class="btn btn-outline-info btn-sm">
                                <i class="mdi mdi-file-csv"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfer Report Card -->
        <div class="col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title">Transfer Report</h4>
                            <p class="text-muted">Residence transfers and movements</p>
                        </div>
                        <div class="align-self-center">
                            <i class="mdi mdi-swap-horizontal text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="transfer_report.php" class="btn btn-warning btn-sm">View Report</a>
                        <div class="btn-group ml-2" role="group">
                            <a href="transfer_report.php?export=pdf" class="btn btn-outline-danger btn-sm">
                                <i class="mdi mdi-file-pdf"></i>
                            </a>
                            <a href="transfer_report.php?export=excel" class="btn btn-outline-success btn-sm">
                                <i class="mdi mdi-file-excel"></i>
                            </a>
                            <a href="transfer_report.php?export=csv" class="btn btn-outline-info btn-sm">
                                <i class="mdi mdi-file-csv"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Report Card -->
        <div class="col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title">Statistics Report</h4>
                            <p class="text-muted">Statistical analysis and trends</p>
                        </div>
                        <div class="align-self-center">
                            <i class="mdi mdi-chart-bar text-purple" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="statistics_report.php" class="btn btn-purple btn-sm">View Report</a>
                        <div class="btn-group ml-2" role="group">
                            <a href="statistics_report.php?export=pdf" class="btn btn-outline-danger btn-sm">
                                <i class="mdi mdi-file-pdf"></i>
                            </a>
                            <a href="statistics_report.php?export=excel" class="btn btn-outline-success btn-sm">
                                <i class="mdi mdi-file-excel"></i>
                            </a>
                            <a href="statistics_report.php?export=csv" class="btn btn-outline-info btn-sm">
                                <i class="mdi mdi-file-csv"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Report Card -->
        <div class="col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title">Custom Report</h4>
                            <p class="text-muted">Create custom reports</p>
                        </div>
                        <div class="align-self-center">
                            <i class="mdi mdi-cog text-secondary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="custom_report.php" class="btn btn-secondary btn-sm">Create Report</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Quick Statistics</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        try {
                            // Get quick stats based on user role
                            if ($user_role === 'super_admin') {
                                // Super Admin sees all data
                                $stmt = $pdo->query("SELECT COUNT(*) as total FROM residences WHERE status = 'active'");
                                $total_residences = $stmt->fetch()['total'];
                                
                                $stmt = $pdo->query("SELECT COUNT(*) as total FROM family_members");
                                $total_family = $stmt->fetch()['total'];
                                
                                $stmt = $pdo->query("SELECT COUNT(*) as total FROM wards");
                                $total_wards = $stmt->fetch()['total'];
                                
                                $stmt = $pdo->query("SELECT COUNT(*) as total FROM villages");
                                $total_villages = $stmt->fetch()['total'];
                                
                                echo '<div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="text-primary">' . $total_residences . '</h3>
                                        <p class="text-muted">Total Residences</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="text-success">' . $total_family . '</h3>
                                        <p class="text-muted">Total Family Members</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="text-info">' . $total_wards . '</h3>
                                        <p class="text-muted">Total Wards</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="text-warning">' . $total_villages . '</h3>
                                        <p class="text-muted">Total Villages</p>
                                    </div>
                                </div>';
                                
                            } elseif ($user_role === 'admin' || $user_role === 'weo') {
                                // Admin/WEO sees ward data
                                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM residences r JOIN villages v ON r.village_id = v.id WHERE v.ward_id = ? AND r.status = 'active'");
                                $stmt->execute([$user_location['ward_id']]);
                                $total_residences = $stmt->fetch()['total'];
                                
                                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM family_members fm JOIN residences r ON fm.residence_id = r.id JOIN villages v ON r.village_id = v.id WHERE v.ward_id = ?");
                                $stmt->execute([$user_location['ward_id']]);
                                $total_family = $stmt->fetch()['total'];
                                
                                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM villages WHERE ward_id = ?");
                                $stmt->execute([$user_location['ward_id']]);
                                $total_villages = $stmt->fetch()['total'];
                                
                                echo '<div class="col-md-4">
                                    <div class="text-center">
                                        <h3 class="text-primary">' . $total_residences . '</h3>
                                        <p class="text-muted">Ward Residences</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h3 class="text-success">' . $total_family . '</h3>
                                        <p class="text-muted">Ward Family Members</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h3 class="text-info">' . $total_villages . '</h3>
                                        <p class="text-muted">Ward Villages</p>
                                    </div>
                                </div>';
                                
                            } elseif ($user_role === 'veo') {
                                // VEO sees village data
                                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM residences WHERE village_id = ? AND status = 'active'");
                                $stmt->execute([$user_location['village_id']]);
                                $total_residences = $stmt->fetch()['total'];
                                
                                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM family_members fm JOIN residences r ON fm.residence_id = r.id WHERE r.village_id = ?");
                                $stmt->execute([$user_location['village_id']]);
                                $total_family = $stmt->fetch()['total'];
                                
                                echo '<div class="col-md-6">
                                    <div class="text-center">
                                        <h3 class="text-primary">' . $total_residences . '</h3>
                                        <p class="text-muted">Village Residences</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-center">
                                        <h3 class="text-success">' . $total_family . '</h3>
                                        <p class="text-muted">Village Family Members</p>
                                    </div>
                                </div>';
                            }
                        } catch (PDOException $e) {
                            echo '<div class="col-12"><div class="alert alert-danger">Error loading statistics: ' . $e->getMessage() . '</div></div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.btn-purple {
    background-color: #6f42c1;
    border-color: #6f42c1;
    color: white;
}

.btn-purple:hover {
    background-color: #5a32a3;
    border-color: #5a32a3;
    color: white;
}
</style>

<?php include 'includes/footer.php'; ?>
