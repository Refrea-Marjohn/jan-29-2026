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

// Fetch all loans
$all_loans = [];
$stmt = $conn->prepare(
    "SELECT l.*, u.full_name, u.email,
            COALESCE(SUM(d.amount), 0) AS total_deducted,
            MAX(d.deduction_date) AS last_deduction_date
     FROM loans l
     JOIN users u ON l.user_id = u.id
     LEFT JOIN deductions d ON d.loan_id = l.id
     WHERE l.status = 'approved'
     GROUP BY l.id
     ORDER BY l.application_date DESC"
);
$stmt->execute();
$result = $stmt->get_result();
$total_collected = 0;
$total_outstanding = 0;
while ($row = $result->fetch_assoc()) {
    $principal_total = $row['total_amount'] ?? $row['loan_amount'];
    $deducted = (float)($row['total_deducted'] ?? 0);
    $balance = max(0, (float)$principal_total - $deducted);
    $row['balance_remaining'] = $balance;
    $row['principal_total'] = (float)$principal_total;
    $row['payment_progress'] = $principal_total > 0 ? min(100, ($deducted / $principal_total) * 100) : 0;
    $total_collected += $deducted;
    $total_outstanding += $balance;
    $all_loans[] = $row;
}
$stmt->close();
$total_loans_count = count($all_loans);
$pending_loans_count = 0;
$collection_rate = ($total_collected + $total_outstanding) > 0
    ? ($total_collected / ($total_collected + $total_outstanding)) * 100
    : 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Loans - DepEd Loan System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .content-section {
            background: white;
            padding: 2.25rem;
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.7rem;
            color: #1f2937;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .section-subtitle {
            color: #6b7280;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .table-wrapper {
            border-radius: 14px;
            border: 1px solid #edf2f7;
            overflow: auto;
            max-height: 560px;
        }

        .loan-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
            font-size: 0.92rem;
        }

        .loan-table th,
        .loan-table td {
            padding: 0.9rem 1rem;
            text-align: left;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
        }

        .loan-table th {
            background: #f8fafc;
            font-weight: 700;
            color: #374151;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .loan-table tbody tr:nth-child(even) {
            background: #fcfcfd;
        }

        .loan-table tr:hover {
            background: #f3f4f6;
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

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .filter-section {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            padding: 1.15rem 1.25rem;
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #eef1f4;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .filter-group label {
            font-weight: 600;
            color: #475569;
        }

        .filter-group select {
            padding: 0.6rem 2.1rem 0.6rem 0.85rem;
            border: 1px solid #d7dce3;
            border-radius: 10px;
            font-size: 14px;
            background: #fff;
            min-width: 130px;
            appearance: none;
            background-image:
                linear-gradient(45deg, transparent 50%, #8b0000 50%),
                linear-gradient(135deg, #8b0000 50%, transparent 50%),
                linear-gradient(to right, #d7dce3, #d7dce3);
            background-position:
                calc(100% - 18px) calc(50% - 2px),
                calc(100% - 13px) calc(50% - 2px),
                calc(100% - 32px) 50%;
            background-size: 5px 5px, 5px 5px, 1px 20px;
            background-repeat: no-repeat;
        }

        .search-box {
            flex: 1;
            min-width: 200px;
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .search-box input {
            width: 100%;
            padding: 0.7rem 0.9rem 0.7rem 2.2rem;
            border: 1px solid #d7dce3;
            border-radius: 12px;
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .search-box input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #8b0000;
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.12);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.4rem 1.5rem;
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: rgba(139, 0, 0, 0.12);
            color: #8b0000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .stat-meta {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1f2937;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .amount-muted {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .balance-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.85rem;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #8b0000;
        }

        .progress-track {
            width: 100%;
            height: 6px;
            border-radius: 999px;
            background: #f0f0f0;
            overflow: hidden;
            margin-top: 0.35rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            border-radius: inherit;
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
                    <span><i class="fas fa-list"></i> All Loans</span>
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
                    <a href="loan_applications.php" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span>
                        Loan Applications
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="all_loans.php" class="sidebar-link active">
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
            <div class="content-section">
                <h2 class="section-title">
                    <i class="fas fa-list"></i>
                    All Loans
                </h2>
                <div class="section-subtitle">Track applications, deductions, and balances across all borrowers.</div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php 
                            $total_loans = count($all_loans);
                            echo $total_loans; 
                            ?>
                        </div>
                        <div class="stat-label">Total Loans</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-meta">
                            <div class="stat-number"><?php echo (int)$total_loans_count; ?></div>
                            <div class="stat-label">Approved Loans</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                        <div class="stat-meta">
                            <div class="stat-number">₱<?php echo number_format($total_collected, 2); ?></div>
                            <div class="stat-label">Total Collected</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-scale-balanced"></i></div>
                        <div class="stat-meta">
                            <div class="stat-number">₱<?php echo number_format($total_outstanding, 2); ?></div>
                            <div class="stat-label">Outstanding</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-meta">
                            <div class="stat-number"><?php echo number_format($collection_rate, 1); ?>%</div>
                            <div class="stat-label">Collection Rate</div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-section">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="search_input" placeholder="Search borrower name or email..." onkeyup="filterLoans()">
                    </div>
                </div>

                <?php if (empty($all_loans)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Loans Found</h3>
                        <p>There are no loan applications in the system yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="loan-table" id="loans_table">
                        <thead>
                            <tr>
                                <th>Application Date</th>
                                <th>Applicant</th>
                                <th>Email</th>
                                <th>Loan Amount</th>
                                <th>Term</th>
                                <th>Monthly Payment</th>
                                <th>Total Paid</th>
                                <th>Balance</th>
                                <th>Last Deduction</th>
                                <th>Status</th>
                                <th>Employment Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_loans as $loan): ?>
                                <tr data-status="<?php echo $loan['status']; ?>" data-name="<?php echo strtolower($loan['full_name'] . ' ' . $loan['email']); ?>">
                                    <td><?php echo date('M d, Y', strtotime($loan['application_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($loan['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($loan['email']); ?></td>
                                    <td>₱<?php echo number_format($loan['loan_amount'], 2); ?></td>
                                    <td><?php echo $loan['loan_term']; ?> months</td>
                                    <td>₱<?php echo number_format($loan['monthly_payment'] ?? 0, 2); ?></td>
                                    <td>
                                        ₱<?php echo number_format($loan['total_deducted'] ?? 0, 2); ?>
                                        <div class="progress-track" aria-hidden="true">
                                            <div class="progress-fill" style="width: <?php echo number_format($loan['payment_progress'], 0); ?>%;"></div>
                                        </div>
                                        <div class="amount-muted"><?php echo number_format($loan['payment_progress'], 0); ?>% paid</div>
                                    </td>
                                    <td>
                                        <span class="balance-pill">₱<?php echo number_format($loan['balance_remaining'] ?? 0, 2); ?></span>
                                        <div class="amount-muted">of ₱<?php echo number_format($loan['principal_total'] ?? 0, 2); ?></div>
                                    </td>
                                    <td>
                                        <?php echo $loan['last_deduction_date'] ? date('M d, Y', strtotime($loan['last_deduction_date'])) : '—'; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch($loan['status']) {
                                            case 'pending': $status_class = 'status-pending'; break;
                                            case 'approved': $status_class = 'status-approved'; break;
                                            case 'rejected': $status_class = 'status-rejected'; break;
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($loan['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($loan['employment_status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('active');
        }

        function filterLoans() {
            const searchInput = document.getElementById('search_input').value.toLowerCase();
            const rows = document.querySelectorAll('#loans_table tbody tr');

            rows.forEach(row => {
                const name = row.dataset.name;
                const nameMatch = !searchInput || name.includes(searchInput);
                row.style.display = nameMatch ? '' : 'none';
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const profileIcon = document.querySelector('.profile-icon');
            
            if (!profileIcon.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });
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
