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

// Check if user is admin
$is_admin = ($user['role'] ?? '') === 'admin' || $user['username'] === 'admin';
if (!$is_admin) {
    header("Location: borrower_dashboard.php");
    exit();
}

// Get comprehensive statistics
$total_users_sql = "SELECT COUNT(*) as total FROM users";
$stmt = $conn->prepare($total_users_sql);
$stmt->execute();
$total_users = $stmt->get_result()->fetch_assoc()['total'];

$total_loans_sql = "SELECT COUNT(*) as total FROM loans";
$stmt = $conn->prepare($total_loans_sql);
$stmt->execute();
$total_loans = $stmt->get_result()->fetch_assoc()['total'];

$pending_loans_sql = "SELECT COUNT(*) as total FROM loans WHERE status = 'pending'";
$stmt = $conn->prepare($pending_loans_sql);
$stmt->execute();
$pending_loans = $stmt->get_result()->fetch_assoc()['total'];

$approved_loans_sql = "SELECT COUNT(*) as total FROM loans WHERE status = 'approved'";
$stmt = $conn->prepare($approved_loans_sql);
$stmt->execute();
$approved_loans = $stmt->get_result()->fetch_assoc()['total'];

$total_amount_sql = "SELECT COALESCE(SUM(loan_amount), 0) as total FROM loans WHERE status IN ('approved', 'completed')";
$stmt = $conn->prepare($total_amount_sql);
$stmt->execute();
$total_amount = $stmt->get_result()->fetch_assoc()['total'];

// Get monthly loan statistics for chart
$monthly_stats_sql = "SELECT DATE_FORMAT(application_date, '%Y-%m') as month, COUNT(*) as count, COALESCE(SUM(loan_amount), 0) as amount FROM loans WHERE application_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(application_date, '%Y-%m') ORDER BY month";
$stmt = $conn->prepare($monthly_stats_sql);
$stmt->execute();
$monthly_stats = $stmt->get_result();

// Get recent users
$recent_users_sql = "SELECT id, username, email, full_name, created_at FROM users ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($recent_users_sql);
$stmt->execute();
$recent_users = $stmt->get_result();

// Get recent loan applications
$recent_applications_sql = "SELECT l.loan_amount, l.loan_purpose, l.status, l.application_date, u.full_name FROM loans l JOIN users u ON l.user_id = u.id ORDER BY l.application_date DESC LIMIT 5";
$stmt = $conn->prepare($recent_applications_sql);
$stmt->execute();
$recent_applications = $stmt->get_result();

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DepEd Loan System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/shared.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
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
        
        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: 250px;
        }
        
        .content-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
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
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-pending {
            background: #ffc107;
        }
        
        .status-approved {
            background: #28a745;
        }
        
        .status-rejected {
            background: #dc3545;
        }
        
        .tooltip {
            position: relative;
            cursor: help;
        }
        
        .tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 0.5rem 0.8rem;
            border-radius: 6px;
            font-size: 0.85rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .tooltip:hover::after {
            opacity: 1;
            visibility: visible;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .stat-trend {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 0.8rem;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .trend-up {
            background: #d4edda;
            color: #155724;
        }
        
        .trend-down {
            background: #f8d7da;
            color: #721c24;
        }
        
        .trend-neutral {
            background: #fff3cd;
            color: #856404;
        }
        
        .admin-badge {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 1rem;
        }
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .user-table th,
        .user-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .user-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .user-table tr:hover {
            background: #f8f9fa;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
        
        .action-btn {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .action-btn.secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        
        @media (max-width: 768px) {
            .navbar {
                left: 0;
                padding: 1rem;
            }
            
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
                order: 2;
            }
            
            .main-content {
                margin-left: 0;
                order: 1;
            }
            
            .container {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .user-table {
                font-size: 0.9rem;
            }
            
            .user-table th,
            .user-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="welcome-message">
            <div class="welcome-block">
                <div class="welcome-title">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>! 👋</div>
                <div class="welcome-meta">
                    <span class="meta-pill"><i class="fas fa-id-badge"></i> Administrator</span>
                    <span><i class="fas fa-calendar-check"></i> <?php echo date('M d, Y'); ?></span>
                    <span><i class="fas fa-shield-alt"></i> Admin Access</span>
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
                    <a href="admin_dashboard.php" class="sidebar-link active">
                        <span class="sidebar-icon"><i class="fas fa-tachometer-alt"></i></span>
                        Admin Dashboard
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="loan_applications.php" class="sidebar-link">
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
                <li class="sidebar-item">
                    <a href="manage_users.php" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-users"></i></span>
                        Manage Users
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin_reports.php" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-chart-bar"></i></span>
                        Reports
                    </a>
                </li>
            </ul>
            
        </aside>
        
        <main class="main-content">
            <div class="content-section">
                <h2 class="section-title"><i class="fas fa-tachometer-alt"></i> Admin Dashboard Overview</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-trend trend-up">Active</div>
                        <div class="stat-icon tooltip" data-tooltip="Total registered users"><i class="fas fa-users"></i></div>
                        <div class="stat-number"><?php echo $total_users; ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-trend trend-up">Active</div>
                        <div class="stat-icon tooltip" data-tooltip="Total loan applications"><i class="fas fa-clipboard-list"></i></div>
                        <div class="stat-number"><?php echo $total_loans; ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-trend trend-neutral">Pending</div>
                        <div class="stat-icon tooltip" data-tooltip="Applications awaiting review"><i class="fas fa-clock"></i></div>
                        <div class="stat-number"><?php echo $pending_loans; ?></div>
                        <div class="stat-label">Pending Review</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-trend trend-up">Total</div>
                        <div class="stat-icon tooltip" data-tooltip="Total amount disbursed"><i class="fas fa-peso-sign"></i></div>
                        <div class="stat-number">₱<?php echo number_format($total_amount, 2); ?></div>
                        <div class="stat-label">Total Disbursed</div>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-grid">
                <div class="content-section">
                    <h2 class="section-title"><i class="fas fa-chart-line"></i> Loan Trends</h2>
                    <div class="chart-container">
                        <canvas id="loanTrendChart"></canvas>
                        <div id="noTrendData" style="display: none; text-align: center; padding: 3rem; color: #666;">
                            <i class="fas fa-chart-line" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No loan trends available</p>
                            <p style="font-size: 0.9rem;">Loan application data will appear here once users start applying for loans.</p>
                        </div>
                    </div>
                </div>
                
                <div class="content-section">
                    <h2 class="section-title"><i class="fas fa-chart-pie"></i> Loan Status Distribution</h2>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                        <div id="noStatusData" style="display: none; text-align: center; padding: 3rem; color: #666;">
                            <i class="fas fa-chart-pie" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No loan status data available</p>
                            <p style="font-size: 0.9rem;">Loan status distribution will appear here once applications are received.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-grid">
                <div class="content-section">
                    <h2 class="section-title"><i class="fas fa-users"></i> Recent User Registrations</h2>
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Registration Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recent_users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="content-section">
                    <h2 class="section-title"><i class="fas fa-clipboard-list"></i> Recent Loan Applications</h2>
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Applicant</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($app = $recent_applications->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                <td>₱<?php echo number_format($app['loan_amount'], 2); ?></td>
                                <td>
                                    <span class="status-indicator status-<?php echo $app['status']; ?>"></span>
                                    <?php echo ucfirst($app['status']); ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($app['application_date'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
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

    <script>
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('active');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const profileIcon = document.querySelector('.profile-icon');
            const dropdown = document.getElementById('profileDropdown');
            
            if (!profileIcon.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });
        
        // Initialize Charts
        // Loan Trend Chart
        const trendCtx = document.getElementById('loanTrendChart').getContext('2d');
        const noTrendData = document.getElementById('noTrendData');
        const months = [];
        const loanCounts = [];
        const loanAmounts = [];
        
        <?php 
        $monthly_stats->data_seek(0);
        while ($month = $monthly_stats->fetch_assoc()): ?>
            months.push('<?php echo date('M Y', strtotime($month['month'] . '-01')); ?>');
            loanCounts.push(<?php echo $month['count']; ?>);
            loanAmounts.push(<?php echo $month['amount']; ?>);
        <?php endwhile; ?>
        
        const hasTrendData = loanCounts.length > 0 && loanCounts.some(count => count > 0);
        
        if (hasTrendData) {
            noTrendData.style.display = 'none';
        } else {
            noTrendData.style.display = 'none'; // Hide message when showing sample data
        }
        
        // Use sample data when no real data exists
        const trendLabels = hasTrendData && months.length > 0 ? months : ['Jan 2024', 'Feb 2024', 'Mar 2024', 'Apr 2024', 'May 2024', 'Jun 2024'];
        const trendData = hasTrendData ? loanCounts : [3, 5, 4, 7, 6, 8];
        
        const loanTrendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: hasTrendData ? 'Number of Loans' : 'Number of Loans (Sample)',
                    data: trendData,
                    borderColor: 'rgba(139, 0, 0, 1)',
                    backgroundColor: 'rgba(139, 0, 0, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        display: true
                    },
                    x: {
                        display: true
                    }
                }
            }
        });
        
        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const noStatusData = document.getElementById('noStatusData');
        
        const pendingLoans = <?php echo $pending_loans ?? 0; ?>;
        const approvedLoans = <?php echo $approved_loans ?? 0; ?>;
        const hasStatusData = pendingLoans > 0 || approvedLoans > 0;
        
        if (hasStatusData) {
            noStatusData.style.display = 'none';
        } else {
            noStatusData.style.display = 'none'; // Hide message when showing sample data
        }
        
        // Use sample data when no real data exists
        const statusData = hasStatusData ? [pendingLoans, approvedLoans, 0, 0] : [4, 8, 3, 1];
        const statusLabels = hasStatusData ? ['Pending', 'Approved', 'Completed', 'Rejected'] : ['Pending (Sample)', 'Approved (Sample)', 'Completed (Sample)', 'Rejected (Sample)'];
        
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: [
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(23, 162, 184, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 193, 7, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(23, 162, 184, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = statusData.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                const displayLabel = hasStatusData ? label : label.replace(' (Sample)', '');
                                return displayLabel + ': ' + value + ' (' + percentage + '%)' + (hasStatusData ? '' : ' [Sample]');
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
