<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$profile_photo = $user['profile_photo'] ?? '';
$profile_photo_exists = $profile_photo && file_exists(__DIR__ . '/' . $profile_photo);

// Check if user is admin or accountant
$is_admin = ($user['role'] ?? '') === 'admin' || $user['username'] === 'admin';
$is_accounting = ($user['role'] ?? '') === 'accountant';
if (!$is_admin && !$is_accounting) {
    header("Location: borrower_dashboard.php");
    exit();
}

$dashboard_url = $is_accounting ? 'accountant_dashboard.php' : 'admin_dashboard.php';
$dashboard_label = $is_accounting ? 'Accountant Dashboard' : 'Admin Dashboard';
$role_label = $is_accounting ? 'Accountant' : 'Administrator';
$access_label = $is_accounting ? 'Accountant Access' : 'Admin Access';

// Handle loan approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $loan_id = $_POST['loan_id'];
    $action = $_POST['action'];
    $reviewed_by_id = $user_id;
    $reviewed_by_name = $user['full_name'] ?? 'Unknown';
    $reviewed_by_role = $user['role'] ?? ($user['username'] === 'admin' ? 'admin' : 'accountant');
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE loans SET status = 'approved', reviewed_by_id = ?, reviewed_by_role = ?, reviewed_by_name = ?, reviewed_at = NOW(), released_at = NOW() WHERE id = ?");
        $stmt->bind_param("issi", $reviewed_by_id, $reviewed_by_role, $reviewed_by_name, $loan_id);
        $message = "Loan application approved successfully!";
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE loans SET status = 'rejected', reviewed_by_id = ?, reviewed_by_role = ?, reviewed_by_name = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->bind_param("issi", $reviewed_by_id, $reviewed_by_role, $reviewed_by_name, $loan_id);
        $message = "Loan application rejected!";
    }
    
    if ($stmt->execute()) {
        $success = $message;
    } else {
        $error = "Error updating loan status.";
    }
    $stmt->close();
}

// Fetch all loan applications with user details
$all_loans = [];
$stmt = $conn->prepare("SELECT l.*, u.full_name, u.email FROM loans l JOIN users u ON l.user_id = u.id ORDER BY l.application_date DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $all_loans[] = $row;
}
$stmt->close();

// Separate loans by status
$pending_loans = array_filter($all_loans, fn($loan) => $loan['status'] == 'pending');
$completed_loans = array_filter($all_loans, fn($loan) => $loan['status'] != 'pending');

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Applications - DepEd Loan System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/shared.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .navbar {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 250px;
            right: 0;
            z-index: 1000;
        }
        
        .welcome-message {
            font-size: 1.2rem;
            color: #333;
        }
        
        .welcome-message strong {
            color: #8b0000;
        }
        
        .nav-icons {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            position: relative;
        }
        
        .icon-button {
            position: relative;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            transition: color 0.3s;
        }
        
        .icon-button:hover {
            color: #8b0000;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        

        .container {
            display: flex;
            margin-top: 70px;
            min-height: calc(100vh - 70px);
        }

        .sidebar {
            width: 250px;
            background: rgba(179, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            padding: 0;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow: hidden;
            z-index: 999;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .sidebar-title {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        .sidebar-menu {
            list-style: none;
            flex: 1;
            padding: 0.5rem 0;
            overflow: hidden;
        }

        .sidebar-item {
            margin-bottom: 0.2rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.8rem 2rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: white;
        }

        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: white;
            font-weight: 600;
        }

        .sidebar-icon {
            margin-right: 1rem;
            font-size: 1.2rem;
        }
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: white;
        }

        .sidebar-icon {
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        .sidebar-item.logout {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            position: absolute;
            bottom: 0;
            left: -30px;
            right: 0;
            padding: 1rem 0;
            text-align: center;
        }
        
        .sidebar-item.logout .sidebar-link {
            justify-content: center;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: 250px;
        }

        .section-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: #8b0000;
        }

        .section-badge {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: auto;
        }

        .content-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            border-left: 5px solid;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .summary-card.pending {
            border-left-color: #ffc107;
        }

        .summary-card.approved {
            border-left-color: #28a745;
        }

        .summary-card.rejected {
            border-left-color: #dc3545;
        }

        .summary-card.total {
            border-left-color: #8b0000;
        }

        .summary-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .summary-card.pending .summary-icon {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        }

        .summary-card.approved .summary-icon {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .summary-card.rejected .summary-icon {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .summary-card.total .summary-icon {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
        }

        .summary-content {
            flex: 1;
        }

        .summary-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.3rem;
        }

        .summary-label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }

        .summary-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .pending-status {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .approved-status {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
        }

        .rejected-status {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
        }

        .total-status {
            background: rgba(139, 0, 0, 0.1);
            color: #8b0000;
        }

        .loan-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .loan-table th,
        .loan-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .loan-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .loan-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .loan-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .loan-stats .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-left: 4px solid #8b0000;
        }

        .loan-stats .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .loan-stats .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 1200px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            color: white;
            padding: 1.1rem 1.4rem;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }

        .close {
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            opacity: 0.7;
        }

        .modal-body {
            padding: 1.2rem 1.4rem 1.5rem;
            max-height: none;
            overflow-y: visible;
        }

        .loan-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .detail-section {
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #eceff3;
            padding: 0.8rem 1rem;
        }

        .detail-section h4 {
            margin: 0 0 0.75rem;
            font-size: 0.85rem;
            color: #8b0000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-list {
            display: grid;
            gap: 0.45rem;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 0.45rem;
            align-items: start;
            font-size: 0.85rem;
        }

        .detail-label {
            color: #555;
            font-weight: 600;
        }

        .detail-value {
            color: #333;
            font-weight: 500;
        }

        .detail-value strong {
            color: #8b0000;
        }

        @media (max-width: 900px) {
            .loan-detail-grid {
                grid-template-columns: 1fr;
            }

            .detail-row {
                grid-template-columns: 1fr;
            }
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="welcome-message">
            <div class="welcome-block">
                <div class="welcome-title">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>! 👋</div>
                <div class="welcome-meta">
                    <span class="meta-pill"><i class="fas fa-id-badge"></i> <?php echo $role_label; ?></span>
                    <span><i class="fas fa-calendar-check"></i> <?php echo date('M d, Y'); ?></span>
                    <span><i class="fas fa-clipboard-check"></i> Loan Applications</span>
                    <span><i class="fas fa-shield-alt"></i> <?php echo $access_label; ?></span>
                </div>
            </div>
        </div>
        <div class="nav-icons">
            <button class="icon-button" title="Notifications">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">3</span>
            </button>
            <div class="profile-icon" title="Profile" onclick="toggleProfileDropdown()">
                <?php if ($profile_photo_exists): ?>
                    <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile Photo">
                <?php else: ?>
                    <span class="profile-initial"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></span>
                <?php endif; ?>
                <div class="status-indicator"></div>
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        <div class="dropdown-user-info">
                            <div class="dropdown-user-avatar">
                                <?php if ($profile_photo_exists): ?>
                                    <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile Photo">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-user-details">
                                <div class="dropdown-user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                                <div class="dropdown-user-email"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                    <a href="#" class="dropdown-item" onclick="openProfileModal('profile'); return false;">
                        <i class="fas fa-user-edit"></i>
                        Update Profile
                    </a>
                    <a href="#" class="dropdown-item" onclick="openProfileModal('password'); return false;">
                        <i class="fas fa-key"></i>
                        Change Password
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item logout-item">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="SDO.jpg" alt="DepEd Loan System Logo">
                </div>
                <div class="sidebar-title">DepEd Loan System</div>
            </div>
            
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="<?php echo $dashboard_url; ?>" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span>
                        <?php echo $dashboard_label; ?>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="loan_applications.php" class="sidebar-link active">
                        <span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span>
                        Loan Applications
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="all_loans.php" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                        All Loans
                    </a>
                </li>
                <?php if ($is_admin): ?>
                    <li class="sidebar-item">
                        <a href="manage_users.php" class="sidebar-link">
                            <span class="sidebar-icon"><i class="fas fa-users"></i></span>
                            Manage Users
                        </a>
                    </li>
                <?php elseif ($is_accounting): ?>
                    <li class="sidebar-item">
                        <a href="accountant_manage_users.php" class="sidebar-link">
                            <span class="sidebar-icon"><i class="fas fa-users"></i></span>
                            Manage Users
                        </a>
                    </li>
                <?php endif; ?>
                <li class="sidebar-item">
                    <a href="admin_reports.php" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-chart-bar"></i></span>
                        Reports
                    </a>
                </li>
            </ul>
            
        </aside>

        <main class="main-content">
            <?php if (!empty($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Summary Cards Section -->
            <div class="summary-cards">
                <div class="summary-card pending">
                    <div class="summary-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-number"><?php echo count($pending_loans); ?></div>
                        <div class="summary-label">Pending Applications</div>
                    </div>
                    <div class="summary-status pending-status">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>For Review</span>
                    </div>
                </div>

                <div class="summary-card approved">
                    <div class="summary-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-number"><?php echo count(array_filter($all_loans, fn($loan) => $loan['status'] == 'approved')); ?></div>
                        <div class="summary-label">Approved Applications</div>
                    </div>
                    <div class="summary-status approved-status">
                        <i class="fas fa-check"></i>
                        <span>Completed</span>
                    </div>
                </div>

                <div class="summary-card rejected">
                    <div class="summary-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-number"><?php echo count(array_filter($all_loans, fn($loan) => $loan['status'] == 'rejected')); ?></div>
                        <div class="summary-label">Rejected Applications</div>
                    </div>
                    <div class="summary-status rejected-status">
                        <i class="fas fa-times"></i>
                        <span>Declined</span>
                    </div>
                </div>

                <div class="summary-card total">
                    <div class="summary-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-number"><?php echo count($all_loans); ?></div>
                        <div class="summary-label">Total Applications</div>
                    </div>
                    <div class="summary-status total-status">
                        <i class="fas fa-chart-bar"></i>
                        <span>All Time</span>
                    </div>
                </div>
            </div>

            <!-- Pending Applications Section -->
            <div class="content-section">
                <h2 class="section-title">
                    <i class="fas fa-clock"></i>
                    Pending Loan Applications
                    <span class="section-badge"><?php echo count($pending_loans); ?></span>
                </h2>

                <?php if (empty($pending_loans)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Pending Applications</h3>
                        <p>There are currently no pending loan applications to review.</p>
                    </div>
                <?php else: ?>
                    <table class="loan-table">
                        <thead>
                            <tr>
                                <th>Application Date</th>
                                <th>Applicant</th>
                                <th>Email</th>
                                <th>Loan Amount</th>
                                <th>Term</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_loans as $loan): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($loan['application_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($loan['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($loan['email']); ?></td>
                                    <td>₱<?php echo number_format($loan['loan_amount'], 2); ?></td>
                                    <td><?php echo $loan['loan_term']; ?> months</td>
                                    <td><span class="status-badge status-pending">Pending</span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info" onclick="viewLoanInfo(<?php echo $loan['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-approve" onclick="return confirm('Approve this loan application?')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-reject" onclick="return confirm('Reject this loan application?')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Completed Applications Section -->
            <div class="content-section">
                <h2 class="section-title">
                    <i class="fas fa-check-circle"></i>
                    Completed Applications
                    <span class="section-badge"><?php echo count($completed_loans); ?></span>
                </h2>

                <?php if (empty($completed_loans)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No Completed Applications</h3>
                        <p>There are no approved or rejected loan applications yet.</p>
                    </div>
                <?php else: ?>
                    <table class="loan-table">
                        <thead>
                            <tr>
                                <th>Application Date</th>
                                <th>Applicant</th>
                                <th>Email</th>
                                <th>Loan Amount</th>
                                <th>Term</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completed_loans as $loan): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($loan['application_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($loan['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($loan['email']); ?></td>
                                    <td>₱<?php echo number_format($loan['loan_amount'], 2); ?></td>
                                    <td><?php echo $loan['loan_term']; ?> months</td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch($loan['status']) {
                                            case 'approved': $statusClass = 'status-approved'; break;
                                            case 'rejected': $statusClass = 'status-rejected'; break;
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($loan['status']); ?>
                                        </span>
                                        <?php if (!empty($loan['reviewed_by_name'])): ?>
                                            <div style="margin-top: 4px; font-size: 0.78rem; color: #666;">
                                                By <?php echo htmlspecialchars($loan['reviewed_by_name']); ?>
                                                (<?php echo htmlspecialchars(ucfirst($loan['reviewed_by_role'] ?? '')); ?>)
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info" onclick="viewLoanInfo(<?php echo $loan['id']; ?>)">
                                                <i class="fas fa-eye"></i> View Info
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Loan Information Modal -->
            <div id="loanModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Loan Application Details</h3>
                        <span class="close" onclick="closeModal()">&times;</span>
                    </div>
                    <div class="modal-body" id="loanDetails">
                        <!-- Loan details will be loaded here -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function viewLoanInfo(loanId) {
            // Fetch loan details via AJAX
            fetch(`get_loan_details.php?id=${loanId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const details = data.loan;
                        const detailsHtml = `
                            <div class="loan-detail-grid">
                                <div class="detail-section">
                                    <h4><i class="fas fa-user"></i> Applicant Info</h4>
                                    <div class="detail-list">
                                        <div class="detail-row">
                                            <div class="detail-label">Name</div>
                                            <div class="detail-value"><strong>${details.full_name}</strong></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Email</div>
                                            <div class="detail-value">${details.email}</div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Application Date</div>
                                            <div class="detail-value">${new Date(details.application_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="detail-section">
                                    <h4><i class="fas fa-file-contract"></i> Loan Details</h4>
                                    <div class="detail-list">
                                        <div class="detail-row">
                                            <div class="detail-label">Loan Amount</div>
                                            <div class="detail-value"><strong>₱${parseFloat(details.loan_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Loan Purpose</div>
                                            <div class="detail-value">${details.loan_purpose}</div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Loan Term</div>
                                            <div class="detail-value">${details.loan_term} months</div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Net Pay</div>
                                            <div class="detail-value">₱${parseFloat(details.net_pay).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Monthly Payment</div>
                                            <div class="detail-value"><strong>₱${parseFloat(details.monthly_payment).toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="detail-section">
                                    <h4><i class="fas fa-user-tie"></i> Co-Maker Info</h4>
                                    <div class="detail-list">
                                        <div class="detail-row">
                                            <div class="detail-label">Name</div>
                                            <div class="detail-value"><strong>${details.co_maker_full_name}</strong></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Position</div>
                                            <div class="detail-value">${details.co_maker_position}</div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Assignment</div>
                                            <div class="detail-value">${details.co_maker_school_assignment}</div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Employment Status</div>
                                            <div class="detail-value">${details.co_maker_employment_status}</div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Net Pay</div>
                                            <div class="detail-value">₱${parseFloat(details.co_maker_net_pay).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="detail-section">
                                    <h4><i class="fas fa-file-upload"></i> Payslips</h4>
                                    <div class="detail-list">
                                        <div class="detail-row">
                                            <div class="detail-label">Borrower Payslip</div>
                                            <div class="detail-value">
                                                ${details.payslip_filename ? `<a href="download_payslip.php?id=${details.id}" class="view-btn" target="_blank"><i class="fas fa-file-download"></i> View Payslip</a>` : 'Not available'}
                                            </div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Co-Maker Payslip</div>
                                            <div class="detail-value">
                                                ${details.co_maker_payslip_filename ? `<a href="download_payslip.php?id=${details.id}&type=co_maker" class="view-btn" target="_blank"><i class="fas fa-file-download"></i> View Payslip</a>` : 'Not available'}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="detail-section">
                                    <h4><i class="fas fa-calculator"></i> Totals</h4>
                                    <div class="detail-list">
                                        <div class="detail-row">
                                            <div class="detail-label">Total Amount</div>
                                            <div class="detail-value"><strong>₱${parseFloat(details.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Total Interest</div>
                                            <div class="detail-value">₱${parseFloat(details.total_interest).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="detail-section">
                                    <h4><i class="fas fa-check-circle"></i> Review Status</h4>
                                    <div class="detail-list">
                                        <div class="detail-row">
                                            <div class="detail-label">Reviewed By</div>
                                            <div class="detail-value">${details.reviewed_by_name ? `${details.reviewed_by_name} (${details.reviewed_by_role || 'N/A'})` : 'Not reviewed yet'}</div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Reviewed At</div>
                                            <div class="detail-value">${details.reviewed_at ? new Date(details.reviewed_at).toLocaleString() : 'N/A'}</div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Status</div>
                                            <div class="detail-value">
                                                <span class="status-badge status-${details.status}">
                                                    <i class="fas fa-${details.status === 'pending' ? 'clock' : (details.status === 'approved' ? 'check-circle' : 'times-circle')}"></i>
                                                    ${details.status.charAt(0).toUpperCase() + details.status.slice(1)}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        document.getElementById('loanDetails').innerHTML = detailsHtml;
                        document.getElementById('loanModal').style.display = 'block';
                    } else {
                        alert('Error loading loan details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading loan details');
                });
        }

        function closeModal() {
            document.getElementById('loanModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('loanModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>

    <div id="profileModalOverlay" class="profile-modal-overlay">
        <div class="profile-modal-content">
            <iframe id="profileModalFrame" src="" title="Profile Settings"></iframe>
        </div>
    </div>

    <script>
        function openProfileModal(tab) {
            const overlay = document.getElementById('profileModalOverlay');
            const frame = document.getElementById('profileModalFrame');
            const safeTab = tab === 'password' ? 'password' : 'profile';
            frame.src = 'profile_update.php?tab=' + safeTab + '&embed=1';
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeProfileModal() {
            const overlay = document.getElementById('profileModalOverlay');
            const frame = document.getElementById('profileModalFrame');
            overlay.classList.remove('active');
            document.body.style.overflow = 'auto';
            frame.src = '';
        }

        document.addEventListener('click', function(event) {
            if (event.target && event.target.id === 'profileModalOverlay') {
                closeProfileModal();
            }
        });
    </script>
</body>
</html>
