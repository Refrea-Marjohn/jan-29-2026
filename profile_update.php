<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get current user data
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
// Determine dashboard target based on role/username
$is_admin = ($user_data['role'] ?? '') === 'admin' || $user_data['username'] === 'admin';
$dashboard_url = $is_admin ? 'admin_dashboard.php' : 'borrower_dashboard.php';
$is_embed = isset($_GET['embed']) && $_GET['embed'] === '1';
$profile_photo = $user_data['profile_photo'] ?? '';
$profile_photo_exists = $profile_photo && file_exists(__DIR__ . '/' . $profile_photo);
unset($is_admin);

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $home_address = trim($_POST['home_address']);
    $profile_photo = $user_data['profile_photo'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($contact_number) || empty($home_address)) {
        $error = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (!preg_match('/^09\d{9}$/', $contact_number)) {
        $error = "Contact number must be in format: 09XXXXXXXXX";
    } else {
        // Ensure profile_photo column exists
        $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
        if ($column_check && $column_check->num_rows === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL");
        }

        // Handle profile photo upload
        if (!empty($_FILES['profile_photo']['name'])) {
            $file = $_FILES['profile_photo'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                if ($file['size'] > 2 * 1024 * 1024) {
                    $error = "Profile photo must be 2MB or less";
                } else {
                    $allowed_mimes = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp'
                    ];
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);
                    if (!isset($allowed_mimes[$mime])) {
                        $error = "Profile photo must be JPG, PNG, or WebP";
                    } else {
                        $upload_dir = __DIR__ . '/uploads/profile_pictures';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $filename = 'user_' . $user_id . '_' . time() . '.' . $allowed_mimes[$mime];
                        $target_path = $upload_dir . '/' . $filename;
                        if (move_uploaded_file($file['tmp_name'], $target_path)) {
                            $profile_photo = 'uploads/profile_pictures/' . $filename;
                        } else {
                            $error = "Failed to upload profile photo";
                        }
                    }
                }
            } else {
                $error = "Failed to upload profile photo";
            }
        }

        if (empty($error)) {
            // Check if email or username already exists (excluding current user)
            $check_sql = "SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ssi", $email, $username, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Email or username already exists";
            } else {
                // Update profile
                $update_sql = "UPDATE users SET username = ?, email = ?, contact_number = ?, home_address = ?, profile_photo = ? WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("sssssi", $username, $email, $contact_number, $home_address, $profile_photo, $user_id);
                
                if ($stmt->execute()) {
                    $success = "Profile updated successfully!";
                    // Refresh user data
                    $sql = "SELECT * FROM users WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user_data = $result->fetch_assoc();
                    $profile_photo = $user_data['profile_photo'] ?? '';
                    $profile_photo_exists = $profile_photo && file_exists(__DIR__ . '/' . $profile_photo);
                } else {
                    $error = "Profile update failed. Please try again.";
                }
            }
            $stmt->close();
        }
    }
}

// Handle password update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long";
    } else {
        // Verify current password
        if (password_verify($current_password, $user_data['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success = "Password updated successfully!";
            } else {
                $error = "Password update failed. Please try again.";
            }
            $stmt->close();
        } else {
            $error = "Current password is incorrect";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - Provident Loan System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/shared.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        body.embed {
            background: #f5f6f8;
            align-items: flex-start;
            justify-content: flex-start;
            padding: 12px;
            overflow: hidden;
        }

        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }

        body.embed .container {
            max-width: 100%;
            border-radius: 12px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
        }


        .content {
            padding: 2rem;
        }

        body.embed .content {
            padding: 0.8rem 1rem 1rem;
        }

        body.embed .header {
            padding: 0.7rem 0.95rem;
        }

        body.embed .profile-title {
            font-size: 1.05rem;
        }

        body.embed .profile-subtitle {
            font-size: 0.78rem;
        }

        body.embed .profile-meta {
            margin-top: 0.3rem;
            font-size: 0.72rem;
        }

        body.embed .profile-actions {
            gap: 0.25rem;
        }

        body.embed .role-pill {
            padding: 0.12rem 0.5rem;
            font-size: 0.66rem;
        }

        body.embed .back-btn {
            display: none;
        }

        .tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 2px solid #eee;
        }

        body.embed .tabs {
            margin-bottom: 0.5rem;
        }

        .tab {
            padding: 1rem 2rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        body.embed .tab {
            padding: 0.38rem 0.7rem;
            font-size: 0.8rem;
        }

        .tab.active {
            color: #8b0000;
            border-bottom-color: #8b0000;
        }

        .tab:hover {
            color: #8b0000;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        body.embed .form-group {
            margin-bottom: 0.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        body.embed .form-group label {
            font-size: 0.82rem;
            margin-bottom: 0.3rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group small {
            display: block;
            margin-top: 0.2rem;
            color: #6b7280;
        }

        body.embed .form-group small {
            font-size: 0.68rem;
        }

        body.embed .form-group input,
        body.embed .form-group textarea {
            padding: 0.4rem 0.55rem;
            font-size: 0.84rem;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #8b0000;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        body.embed .form-group textarea {
            min-height: 46px;
        }

        .btn {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        body.embed .btn {
            padding: 0.4rem 0.9rem;
            font-size: 0.82rem;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        body.embed .form-actions {
            gap: 0.45rem;
        }

        .btn-outline {
            background: #ffffff;
            color: #8b0000;
            border: 1px solid #eadfe2;
        }

        .btn-outline:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
        }

        body.embed .back-btn {
            display: none;
        }

        .profile-photo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            border: 1px solid #eef1f4;
            border-radius: 10px;
            background: #fbfbfd;
            margin-bottom: 1.2rem;
        }

        body.embed .profile-photo-section {
            margin-bottom: 0.7rem;
            padding: 0.45rem 0.6rem;
        }

        .profile-photo {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #f0f2f5;
            color: #8b0000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.4rem;
            overflow: hidden;
            border: 2px solid #f1d4d7;
        }

        body.embed .profile-photo {
            width: 44px;
            height: 44px;
            font-size: 0.9rem;
        }

        body.embed .profile-photo-actions label {
            font-size: 0.82rem;
        }

        body.embed .profile-photo-actions small {
            font-size: 0.72rem;
        }

        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-photo-actions label {
            font-weight: 600;
            color: #333;
            display: block;
            margin-bottom: 0.35rem;
        }

        .profile-photo-actions small {
            color: #6b7280;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(139, 0, 0, 0.3);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem 1rem;
        }

        body.embed .form-grid {
            gap: 0.6rem 0.7rem;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }


        @media (max-width: 768px) {
            .container {
                margin: 1rem;
            }
            
            .header {
                padding: 1.5rem;
            }
            
            .content {
                padding: 1.5rem;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                border-bottom: 1px solid #eee;
                border-right: none;
            }
            
            .tab.active {
                border-bottom-color: #8b0000;
            }
        }
    </style>
</head>
<body class="<?php echo $is_embed ? 'embed' : ''; ?>">
    <a href="<?php echo $dashboard_url; ?>" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="container">
        <div class="header">
            <div class="profile-header">
                <div>
                    <div class="profile-title">Update Profile</div>
                    <div class="profile-subtitle">Keep your account details accurate and up to date</div>
                    <div class="profile-meta">
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($user_data['username']); ?></span>
                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user_data['email']); ?></span>
                        <span><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($user_data['deped_id'] ?? 'DepEd ID not set'); ?></span>
                        <span><i class="fas fa-calendar-check"></i> Last updated: <?php echo date('M d, Y'); ?></span>
                    </div>
                </div>
                <div class="profile-actions">
                    <span class="role-pill">
                        <i class="fas fa-id-badge"></i>
                        <?php echo htmlspecialchars(ucfirst($user_data['role'] ?? 'User')); ?>
                    </span>
                    <div class="profile-actions-note">DepEd Provident Loan System</div>
                </div>
            </div>
        </div>

        <div class="content">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <button class="tab active" onclick="showTab('profile', this)">
                    <i class="fas fa-user"></i> Update Profile
                </button>
                <button class="tab" onclick="showTab('password', this)">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>

            <!-- Update Profile Tab -->
            <div id="profile-tab" class="tab-content active">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="profile-photo-section">
                        <div class="profile-photo">
                            <?php if ($profile_photo_exists): ?>
                                <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile Photo">
                            <?php else: ?>
                                <?php echo strtoupper(substr($user_data['full_name'] ?: $user_data['username'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="profile-photo-actions">
                            <label for="profile_photo">Profile Photo</label>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/png,image/jpeg,image/webp">
                            <small>JPG, PNG, or WebP up to 2MB.</small>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">Username <span style="color: red;">*</span></label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address <span style="color: red;">*</span></label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="contact_number">Contact Number <span style="color: red;">*</span></label>
                            <input type="tel" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($user_data['contact_number'] ?? ''); ?>" required placeholder="09XXXXXXXXX">
                            <small style="color: #666;">Format: 09XXXXXXXXX</small>
                        </div>

                        <div class="form-group">
                            <label for="home_address">Home Address <span style="color: red;">*</span></label>
                            <textarea id="home_address" name="home_address" rows="2" required><?php echo htmlspecialchars($user_data['home_address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeParentModal()">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password Tab -->
            <div id="password-tab" class="tab-content">
                <form method="POST">
                    <input type="hidden" name="update_password" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password">Current Password <span style="color: red;">*</span></label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password <span style="color: red;">*</span></label>
                            <input type="password" id="new_password" name="new_password" required minlength="8">
                            <small style="color: #666;">Minimum 8 characters</small>
                        </div>

                        <div class="form-group full-width">
                            <label for="confirm_password">Confirm New Password <span style="color: red;">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeParentModal()">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName, button) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button (or matching button)
            const activeButton = button || document.querySelector('.tab[onclick*="' + tabName + '"]');
            if (activeButton) {
                activeButton.classList.add('active');
            }
        }

        // Auto-select tab when linked with ?tab=password
        const urlParams = new URLSearchParams(window.location.search);
        const initialTab = urlParams.get('tab');
        if (initialTab === 'password') {
            const passwordButton = document.querySelector('.tab[onclick*="password"]');
            showTab('password', passwordButton);
        }

        function closeParentModal() {
            if (window.parent && typeof window.parent.closeProfileModal === 'function') {
                window.parent.closeProfileModal();
                return;
            }
            window.location.href = '<?php echo $dashboard_url; ?>';
        }

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
