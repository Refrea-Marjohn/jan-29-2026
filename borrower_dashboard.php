<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user statistics
$user_id = $_SESSION['user_id'];

// Get real loan statistics from database
$active_loans_sql = "SELECT COUNT(*) as count FROM loans WHERE user_id = ? AND status = 'approved'";
$stmt = $conn->prepare($active_loans_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_loans = $stmt->get_result()->fetch_assoc()['count'];

$pending_applications_sql = "SELECT COUNT(*) as count FROM loans WHERE user_id = ? AND status = 'pending'";
$stmt = $conn->prepare($pending_applications_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_applications = $stmt->get_result()->fetch_assoc()['count'];

$completed_loans_sql = "SELECT COUNT(*) as count FROM loans WHERE user_id = ? AND status = 'completed'";
$stmt = $conn->prepare($completed_loans_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$completed_loans = $stmt->get_result()->fetch_assoc()['count'];

$total_borrowed_sql = "SELECT COALESCE(SUM(loan_amount), 0) as total FROM loans WHERE user_id = ? AND status IN ('approved', 'completed')";
$stmt = $conn->prepare($total_borrowed_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_borrowed = $stmt->get_result()->fetch_assoc()['total'];

// Get recent loan activity
$recent_loans_sql = "SELECT loan_amount, loan_purpose, status, application_date FROM loans WHERE user_id = ? ORDER BY application_date DESC LIMIT 5";
$stmt = $conn->prepare($recent_loans_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_loans = $stmt->get_result();

// Get user profile information
$user_sql = "SELECT username, email, full_name, role, created_at, profile_photo FROM users WHERE id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$stmt->close();
$user_data = $user_data ?: null;
if (!$user_data) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$profile_photo = $user_data['profile_photo'] ?? '';
$profile_photo_exists = $profile_photo && file_exists(__DIR__ . '/' . $profile_photo);

// Format registration date
$registration_date = date('F j, Y', strtotime($user_data['created_at']));
$account_age = date_diff(date_create($user_data['created_at']), date_create())->format('%y years, %m months');

// Handle AJAX requests for modal forms
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['username']) || isset($_POST['current_password']))) {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => ''];
    
    // Handle profile update
    if (isset($_POST['username'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $contact_number = trim($_POST['contact_number']);
        $home_address = trim($_POST['home_address']);
        
        // Validation
        if (empty($username) || empty($email) || empty($contact_number) || empty($home_address)) {
            $response['message'] = "All fields are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = "Invalid email format";
        } elseif (!preg_match('/^09\d{9}$/', $contact_number)) {
            $response['message'] = "Contact number must be in format: 09XXXXXXXXX";
        } else {
            // Check if email or username already exists (excluding current user)
            $check_sql = "SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ssi", $email, $username, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $response['message'] = "Email or username already exists";
            } else {
                // Update profile
                $update_sql = "UPDATE users SET username = ?, email = ?, contact_number = ?, home_address = ? WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ssssi", $username, $email, $contact_number, $home_address, $user_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = "Profile updated successfully!";
                } else {
                    $response['message'] = "Profile update failed. Please try again.";
                }
            }
            $stmt->close();
        }
    }
    
    // Handle password update
    if (isset($_POST['current_password'])) {
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $response['message'] = "All password fields are required";
        } elseif ($new_password !== $confirm_password) {
            $response['message'] = "New passwords do not match";
        } elseif (strlen($new_password) < 8) {
            $response['message'] = "New password must be at least 8 characters long";
        } else {
            // Verify current password
            if (password_verify($current_password, $user_data['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = "Password updated successfully!";
                } else {
                    $response['message'] = "Password update failed. Please try again.";
                }
                $stmt->close();
            } else {
                $response['message'] = "Current password is incorrect";
            }
        }
    }
    
    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrower Dashboard - DepEd Loan System</title>
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
            font-size: 1.1rem;
            color: #333;
        }
        
        .welcome-message strong {
            color: #8b0000;
        }
        
        .user-info-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            min-width: 250px;
        }
        
        .user-info-badge {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 500;
            padding: 0.25rem 0.5rem;
            background: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
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
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            text-align: center;
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
            font-size: 2rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.3rem;
            position: relative;
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
        
        .content-section {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-title i {
            color: #8b0000;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .quick-action-btn {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 0.95rem;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(139, 0, 0, 0.3);
        }
        
        .quick-action-btn.secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
        }
        
        .activity-item {
            padding: 0.8rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background 0.3s ease;
        }
        
        .activity-item:hover {
            background: #f8f9fa;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .activity-icon.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .activity-icon.approved {
            background: #d4edda;
            color: #155724;
        }
        
        .activity-icon.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.2rem;
        }
        
        .activity-meta {
            font-size: 0.85rem;
            color: #666;
        }
        
        .activity-amount {
            font-weight: 600;
            color: #8b0000;
            font-size: 1.1rem;
        }
        
        .chart-container {
            position: relative;
            height: 250px;
            margin-bottom: 1.5rem;
        }
        
        .notification-panel {
            position: fixed;
            top: 70px;
            right: 20px;
            width: 350px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            z-index: 1002;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-panel.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            font-weight: 600;
            color: #333;
        }
        
        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s ease;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
        }
        
        .notification-item:last-child {
            border-bottom: none;
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 95%;
            height: auto;
            max-height: none;
            overflow: visible;
            animation: slideUp 0.3s ease;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: transform 0.3s;
            padding: 0.5rem;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .modal-form .form-group {
            margin-bottom: 1rem;
        }
        
        .modal-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .modal-form input,
        .modal-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        .modal-form input:focus,
        .modal-form textarea:focus {
            outline: none;
            border-color: #8b0000;
        }
        
        .modal-form textarea {
            resize: vertical;
            min-height: 60px;
            max-height: 120px;
        }
        
        .modal-form small {
            color: #666;
            font-size: 0.85rem;
            margin-top: 0.25rem;
            display: block;
        }
        
        .modal-footer {
            padding: 0 2rem 2rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .modal-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-btn-primary {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            color: white;
        }
        
        .modal-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 0, 0, 0.3);
        }
        
        .modal-btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }
        
        .modal-btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(108, 117, 125, 0.3);
        }
        
        .modal-alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .modal-alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(50px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
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
            
            .welcome-message {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="welcome-section">
            <div class="welcome-block">
                <div class="welcome-title">Welcome back, <strong><?php echo htmlspecialchars($user_data['full_name']); ?></strong>! 👋</div>
                <div class="welcome-meta">
                    <span class="meta-pill"><i class="fas fa-id-badge"></i> Borrower</span>
                    <span><i class="fas fa-calendar-check"></i> <?php echo date('M d, Y'); ?></span>
                    <span><i class="fas fa-wallet"></i> Loan Portal</span>
                </div>
            </div>
        </div>
        <div class="nav-icons">
            <button class="icon-button" title="Notifications" onclick="toggleNotifications()">
                <i class="fas fa-bell"></i>
                <span class="notification-badge"><?php echo $pending_applications; ?></span>
            </button>
            <div class="profile-icon" title="Profile" onclick="toggleProfileDropdown()">
                <?php if ($profile_photo_exists): ?>
                    <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile Photo">
                <?php else: ?>
                    <span class="profile-initial"><?php echo strtoupper(substr($user_data['full_name'], 0, 1)); ?></span>
                <?php endif; ?>
                <div class="status-indicator"></div>
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        <div class="dropdown-user-info">
                            <div class="dropdown-user-avatar">
                                <?php if ($profile_photo_exists): ?>
                                    <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile Photo">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user_data['full_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-user-details">
                                <div class="dropdown-user-name"><?php echo htmlspecialchars($user_data['full_name']); ?></div>
                                <div class="dropdown-user-email"><?php echo htmlspecialchars($user_data['email']); ?></div>
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
                    <a href="borrower_dashboard.php" class="sidebar-link active">
                        <span class="sidebar-icon"><i class="fas fa-home"></i></span>
                        Borrower Dashboard
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="my_loans.php" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-credit-card"></i></span>
                        My Loans
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="apply_loan.php" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-plus-circle"></i></span>
                        Apply for Loan
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="loan_history.php" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-history"></i></span>
                        Loan History
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="#" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-file-alt"></i></span>
                        Documents
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="#" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-headset"></i></span>
                        Support
                    </a>
                </li>
            </ul>
            
        </aside>
        
        <main class="main-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-trend trend-neutral">Active</div>
                    <div class="stat-icon tooltip" data-tooltip="Currently active loans"><i class="fas fa-peso-sign"></i></div>
                    <div class="stat-number"><?php echo $active_loans; ?></div>
                    <div class="stat-label">Active Loans</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-trend trend-up">Pending</div>
                    <div class="stat-icon tooltip" data-tooltip="Applications under review"><i class="fas fa-clipboard-list"></i></div>
                    <div class="stat-number"><?php echo $pending_applications; ?></div>
                    <div class="stat-label">Pending Applications</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-trend trend-up">Completed</div>
                    <div class="stat-icon tooltip" data-tooltip="Successfully completed loans"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $completed_loans; ?></div>
                    <div class="stat-label">Completed Loans</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-trend trend-neutral">Total</div>
                    <div class="stat-icon tooltip" data-tooltip="Total amount borrowed"><i class="fas fa-wallet"></i></div>
                    <div class="stat-number">₱<?php echo number_format($total_borrowed, 2); ?></div>
                    <div class="stat-label">Total Borrowed</div>
                </div>
            </div>
            
            <div class="content-section">
                <h2 class="section-title"><i class="fas fa-chart-line"></i> Loan Overview</h2>
                <div class="chart-container">
                    <canvas id="loanChart"></canvas>
                    <div id="noDataMessage" style="display: none; text-align: center; padding: 3rem; color: #666;">
                        <i class="fas fa-chart-pie" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No loan data available</p>
                        <p style="font-size: 0.9rem;">Start by applying for your first loan to see your loan overview here!</p>
                    </div>
                </div>
            </div>
            
            <div class="content-section">
                <h2 class="section-title"><i class="fas fa-clock"></i> Recent Activity</h2>
                <?php if ($recent_loans->num_rows > 0): ?>
                    <ul class="activity-list">
                        <?php while ($loan = $recent_loans->fetch_assoc()): ?>
                            <li class="activity-item">
                                <div class="activity-icon <?php echo $loan['status']; ?>">
                                    <?php if ($loan['status'] == 'pending'): ?>
                                        <i class="fas fa-clock"></i>
                                    <?php elseif ($loan['status'] == 'approved'): ?>
                                        <i class="fas fa-check"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title"><?php echo htmlspecialchars($loan['loan_purpose']); ?></div>
                                    <div class="activity-meta">
                                        Applied on <?php echo date('M d, Y', strtotime($loan['application_date'])); ?> • 
                                        Status: <span style="font-weight: 600; color: <?php echo $loan['status'] == 'approved' ? '#28a745' : ($loan['status'] == 'pending' ? '#ffc107' : '#dc3545'); ?>"><?php echo ucfirst($loan['status']); ?></span>
                                    </div>
                                </div>
                                <div class="activity-amount">₱<?php echo number_format($loan['loan_amount'], 2); ?></div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 2rem;">No recent activity to display. Start by applying for your first loan!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-header">
            <i class="fas fa-bell"></i> Notifications
        </div>
        <?php if ($pending_applications > 0): ?>
            <div class="notification-item">
                <div style="font-weight: 600; color: #856404; margin-bottom: 0.3rem;">Loan Application Pending</div>
                <div style="font-size: 0.85rem; color: #666;">You have <?php echo $pending_applications; ?> loan application(s) under review.</div>
            </div>
        <?php endif; ?>
        <?php if ($active_loans > 0): ?>
            <div class="notification-item">
                <div style="font-weight: 600; color: #155724; margin-bottom: 0.3rem;">Active Loans</div>
                <div style="font-size: 0.85rem; color: #666;">You have <?php echo $active_loans; ?> active loan(s). Keep track of your payments!</div>
            </div>
        <?php endif; ?>
        <div class="notification-item">
            <div style="font-weight: 600; color: #333; margin-bottom: 0.3rem;">Welcome to Your Borrower Dashboard</div>
            <div style="font-size: 0.85rem; color: #666;">Manage your loans and applications efficiently from here.</div>
        </div>
    </div>
    
    <!-- Update Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Update Profile</h3>
                <button class="modal-close" onclick="closeModal('profileModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="profileAlert"></div>
                <form id="profileForm" class="modal-form">
                    <div class="form-group">
                        <label for="modal_username">Username <span style="color: red;">*</span></label>
                        <input type="text" id="modal_username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_email">Email Address <span style="color: red;">*</span></label>
                        <input type="email" id="modal_email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_contact">Contact Number <span style="color: red;">*</span></label>
                        <input type="tel" id="modal_contact" name="contact_number" value="<?php echo htmlspecialchars($user_data['contact_number'] ?? ''); ?>" required placeholder="09XXXXXXXXX">
                        <small>Format: 09XXXXXXXXX</small>
                    </div>
                    <div class="form-group">
                        <label for="modal_address">Home Address <span style="color: red;">*</span></label>
                        <textarea id="modal_address" name="home_address" required><?php echo htmlspecialchars($user_data['home_address'] ?? ''); ?></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('profileModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" form="profileForm" class="modal-btn modal-btn-primary">
                    <i class="fas fa-save"></i> Update Profile
                </button>
            </div>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Change Password</h3>
                <button class="modal-close" onclick="closeModal('passwordModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="passwordAlert"></div>
                <form id="passwordForm" class="modal-form">
                    <div class="form-group">
                        <label for="modal_current_password">Current Password <span style="color: red;">*</span></label>
                        <input type="password" id="modal_current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_new_password">New Password <span style="color: red;">*</span></label>
                        <input type="password" id="modal_new_password" name="new_password" required minlength="8">
                        <small>Minimum 8 characters</small>
                    </div>
                    <div class="form-group">
                        <label for="modal_confirm_password">Confirm New Password <span style="color: red;">*</span></label>
                        <input type="password" id="modal_confirm_password" name="confirm_password" required minlength="8">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('passwordModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" form="passwordForm" class="modal-btn modal-btn-primary">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>
        </div>
    </div>
    
    <script>
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('active');
        }
        
        function toggleNotifications() {
            const panel = document.getElementById('notificationPanel');
            panel.classList.toggle('active');
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const profileIcon = document.querySelector('.profile-icon');
            const profileDropdown = document.getElementById('profileDropdown');
            const notificationButton = document.querySelector('.icon-button[title="Notifications"]');
            const notificationPanel = document.getElementById('notificationPanel');
            
            if (!profileIcon.contains(event.target)) {
                profileDropdown.classList.remove('active');
            }
            
            if (!notificationButton.contains(event.target) && !notificationPanel.contains(event.target)) {
                notificationPanel.classList.remove('active');
            }
        });
        
        // Initialize Chart
        const ctx = document.getElementById('loanChart').getContext('2d');
        const noDataMessage = document.getElementById('noDataMessage');
        
        // Chart data with fallback values
        const activeLoans = <?php echo $active_loans ?? 0; ?>;
        const pendingApplications = <?php echo $pending_applications ?? 0; ?>;
        const completedLoans = <?php echo $completed_loans ?? 0; ?>;
        
        // Check if there's any data to display
        const hasData = activeLoans > 0 || pendingApplications > 0 || completedLoans > 0;
        
        // Show/hide no data message
        if (hasData) {
            noDataMessage.style.display = 'none';
        } else {
            noDataMessage.style.display = 'none'; // Hide the message even with sample data
        }
        
        // Use sample data when no real data exists
        const chartData = hasData ? [activeLoans, pendingApplications, completedLoans] : [2, 1, 3];
        const chartLabels = hasData ? ['Active Loans', 'Pending Applications', 'Completed Loans'] : ['Active Loans (Sample)', 'Pending Applications (Sample)', 'Completed Loans (Sample)'];
        
        const loanChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartData,
                    backgroundColor: [
                        'rgba(139, 0, 0, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(40, 167, 69, 0.8)'
                    ],
                    borderColor: [
                        'rgba(139, 0, 0, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(40, 167, 69, 1)'
                    ],
                    borderWidth: 2
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
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = hasData ? (context.parsed || 0) : context.parsed || 0;
                                const total = chartData.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                const displayLabel = hasData ? label : label.replace(' (Sample)', '');
                                return displayLabel + ': ' + value + ' (' + percentage + '%)' + (hasData ? '' : ' [Sample]');
                            }
                        }
                    }
                }
            }
        });
        
        // Modal functions
        function openModal(modalId) {
            event.preventDefault();
            const modal = document.getElementById(modalId);
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Close dropdown when opening modal
            document.getElementById('profileDropdown').classList.remove('active');
        }
        
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            
            // Clear alerts when closing
            const alertId = modalId.replace('Modal', 'Alert');
            document.getElementById(alertId).innerHTML = '';
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });
        
        // Profile form submission
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const alertDiv = document.getElementById('profileAlert');
            
            // Client-side validation
            const username = formData.get('username');
            const email = formData.get('email');
            const contact = formData.get('contact_number');
            const address = formData.get('home_address');
            
            if (!username || !email || !contact || !address) {
                alertDiv.innerHTML = '<div class="modal-alert modal-alert-error"><i class="fas fa-exclamation-circle"></i> All fields are required</div>';
                return;
            }
            
            if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                alertDiv.innerHTML = '<div class="modal-alert modal-alert-error"><i class="fas fa-exclamation-circle"></i> Invalid email format</div>';
                return;
            }
            
            if (!contact.match(/^09\d{9}$/)) {
                alertDiv.innerHTML = '<div class="modal-alert modal-alert-error"><i class="fas fa-exclamation-circle"></i> Contact number must be in format: 09XXXXXXXXX</div>';
                return;
            }
            
            // Send AJAX request
            fetch('borrower_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertDiv.innerHTML = '<div class="modal-alert modal-alert-success"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
                    setTimeout(() => {
                        closeModal('profileModal');
                        location.reload(); // Reload to show updated data
                    }, 2000);
                } else {
                    alertDiv.innerHTML = '<div class="modal-alert modal-alert-error"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</div>';
                }
            })
            .catch(error => {
                alertDiv.innerHTML = '<div class="modal-alert modal-alert-error"><i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.</div>';
            });
        });
        
        // Password form submission
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const alertDiv = document.getElementById('passwordAlert');
            
            // Client-side validation
            const currentPass = formData.get('current_password');
            const newPass = formData.get('new_password');
            const confirmPass = formData.get('confirm_password');
            
            if (!currentPass || !newPass || !confirmPass) {
                alertDiv.innerHTML = '<div class="modal-alert modal-alert-error"><i class="fas fa-exclamation-circle"></i> All password fields are required</div>';
                return;
            }
            
            if (newPass !== confirmPass) {
                alertDiv.innerHTML = '<div class="modal-alert modal-alert-error"><i class="fas fa-exclamation-circle"></i> New passwords do not match</div>';
                return;
            }
            
            if (newPass.length < 8) {
                alertDiv.innerHTML = '<div class="modal-alert modal-alert-error"><i class="fas fa-exclamation-circle"></i> New password must be at least 8 characters long</div>';
                return;
            }
            
            // Send AJAX request
            fetch('borrower_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertDiv.innerHTML = '<div class="modal-alert modal-alert-success"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
                    setTimeout(() => {
                        closeModal('passwordModal');
                        this.reset(); // Clear form
                    }, 2000);
                } else {
                    alertDiv.innerHTML = '<div class="modal-alert modal-alert-error"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</div>';
                }
            })
            .catch(error => {
                alertDiv.innerHTML = '<div class="modal-alert modal-alert-error"><i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.</div>';
            });
        });
        
        // Password confirmation validation
        document.getElementById('modal_confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('modal_new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
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
