<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $deped_id = trim($_POST['deped_id']);
    $contact_number = trim($_POST['contact_number']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $surname = trim($_POST['surname']);
    $birth_date = trim($_POST['birth_date']);
    $gender = trim($_POST['gender']);
    $civil_status = trim($_POST['civil_status']);
    $home_address = trim($_POST['home_address']);
    
    // Combine full name
    $full_name = trim($first_name . ' ' . $middle_name . ' ' . $surname);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || 
        empty($deped_id) || empty($contact_number) || empty($first_name) || empty($surname) || 
        empty($birth_date) || empty($gender) || empty($civil_status) || empty($home_address)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}$/', $password)) {
        $error = "Password must contain at least one uppercase letter, one lowercase letter, and one number";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (!preg_match('/^2024-\d{4,5}$/', $deped_id)) {
        $error = "DepEd ID must be in format: 2024-12345 or 2024-1234";
    } elseif (!preg_match('/^09\d{9}$/', $contact_number)) {
        $error = "Contact number must be in format: 09XXXXXXXXX";
    } else {
        // Check if email already exists
        $check_sql = "SELECT id FROM users WHERE email = ? OR username = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email or username already exists";
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (username, email, password, full_name, deped_id, contact_number, first_name, middle_name, surname, birth_date, gender, civil_status, home_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sssssssssssss", $username, $email, $hashed_password, $full_name, $deped_id, $contact_number, $first_name, $middle_name, $surname, $birth_date, $gender, $civil_status, $home_address);
            
            if ($stmt->execute()) {
                $success = "Registration successful! You can now login.";
                // Clear form fields
                $username = $email = $password = $confirm_password = $deped_id = $contact_number = $first_name = $middle_name = $surname = $birth_date = $gender = $civil_status = $home_address = '';
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - DepEd Loan System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        
        .split-container {
            display: flex;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }
        
        .left-side {
            width: 60%;
            background: url('loginbg.jpg') center/cover;
            /* background-attachment: fixed; */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            padding: 3rem;
            position: relative;
            overflow: hidden;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
        }
        
        .left-side::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
        }
        
        .left-content {
            text-align: center;
            z-index: 1;
            max-width: 600px;
        }
        
        .logo-container {
            margin-bottom: 2rem;
        }
        
        .logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
            border-radius: 50%;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            background: white;
            padding: 10px;
        }
        
        .left-side h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .left-side p {
            font-size: 1.2rem;
            line-height: 1.6;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .benefits-list {
            text-align: left;
            margin-top: 2rem;
        }
        
        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .benefit-icon {
            margin-right: 1rem;
            font-size: 1.5rem;
        }
        
        .right-side {
            width: 40%;
            background: white;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem;
            margin-left: 60%;
            height: 100vh;
            overflow-y: auto;
        }
        
        .form-container {
            width: 100%;
            max-width: 450px;
        }
        
        .form-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }
        
        .section-title {
            font-size: 1.1rem;
            color: #8b0000;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #8b0000;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-row {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        label .required {
            color: #e74c3c;
            margin-left: 2px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="date"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #8b0000;
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-hint {
            font-size: 0.8rem;
            color: #666;
            margin-top: 3px;
            font-style: italic;
        }
        
        input.password-weak {
            border-color: #e74c3c;
        }
        
        input.password-medium {
            border-color: #f39c12;
        }
        
        input.password-strong {
            border-color: #27ae60;
        }
        
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 18px;
            user-select: none;
            transition: all 0.3s;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .password-toggle:hover {
            color: #8b0000;
        }
        
        .password-toggle::before {
            content: '👁';
            font-size: 16px;
            position: relative;
            z-index: 2;
        }
        
        .password-toggle.showing::before {
            opacity: 0.3;
        }
        
        .password-toggle.showing::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 2px;
            background: #666;
            transform: rotate(45deg);
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -1px;
            z-index: 1;
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .strength-weak {
            color: #e74c3c;
        }
        
        .strength-medium {
            color: #f39c12;
        }
        
        .strength-strong {
            color: #27ae60;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .error {
            color: #e74c3c;
            background: #fdf2f2;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .success {
            color: #27ae60;
            background: #f2fdf5;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .login-link a {
            color: #8b0000;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .split-container {
                flex-direction: column;
                height: auto;
            }
            
            .left-side, .right-side {
                width: 100%;
                position: relative;
                height: auto;
                margin-left: 0;
            }
            
            .left-side {
                min-height: 40vh;
                padding: 2rem;
            }
            
            .left-side h1 {
                font-size: 2rem;
            }
            
            .left-side p {
                font-size: 1rem;
            }
            
            .right-side {
                padding: 1rem;
                overflow-y: visible;
            }
            
            .form-container {
                max-width: 100%;
            }
            
            .form-container h2 {
                font-size: 20px;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="split-container">
        <div class="left-side">
            <div class="left-content">
                <div class="logo-container">
                    <img src="SDO.jpg" alt="DepEd Loan System Logo" class="logo">
                </div>
                <h1>Join DepEd Loan System</h1>
                <p>Create your account today and gain access to exclusive financial benefits designed for DepEd employees. Start your journey towards financial freedom.</p>
            </div>
        </div>
        
        <div class="right-side">
            <div class="form-container">
                <h2>Create Account</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <!-- Basic Information Section -->
            <div class="section-title">Basic Information</div>
            
            <div class="form-group">
                <label for="username">Username <span class="required">*</span></label>
                <input type="text" id="username" name="username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" title="Must contain at least one number, one uppercase and lowercase letter, and at least 8 characters">
                    <span class="password-toggle" onclick="togglePassword('password')"></span>
                </div>
                <div class="form-hint">Minimum 8 characters</div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" required pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" title="Must contain at least one number, one uppercase and lowercase letter, and at least 8 characters">
                    <span class="password-toggle" onclick="togglePassword('confirm_password')"></span>
                </div>
            </div>
            
            <!-- Personal Information Section -->
            <div class="section-title" style="margin-top: 2rem;">Personal Information</div>
            
            <div class="form-group">
                <label for="deped_id">DepEd ID <span class="required">*</span></label>
                <input type="text" id="deped_id" name="deped_id" value="<?php echo isset($deped_id) ? htmlspecialchars($deped_id) : ''; ?>" required placeholder="2024-12345">
                <div class="form-hint">Format: 2024-12345 or 2024-1234</div>
            </div>
            
            <div class="form-group">
                <label for="contact_number">Contact Number <span class="required">*</span></label>
                <input type="text" id="contact_number" name="contact_number" value="<?php echo isset($contact_number) ? htmlspecialchars($contact_number) : ''; ?>" required placeholder="09XXXXXXXXX">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo isset($first_name) ? htmlspecialchars($first_name) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" value="<?php echo isset($middle_name) ? htmlspecialchars($middle_name) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="surname">Last Name <span class="required">*</span></label>
                    <input type="text" id="surname" name="surname" value="<?php echo isset($surname) ? htmlspecialchars($surname) : ''; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="birth_date">Birth Date <span class="required">*</span></label>
                <input type="date" id="birth_date" name="birth_date" value="<?php echo isset($birth_date) ? htmlspecialchars($birth_date) : ''; ?>" required>
                <div class="form-hint">dd/mm/yyyy</div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="gender">Gender <span class="required">*</span></label>
                    <select id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo (isset($gender) && $gender == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (isset($gender) && $gender == 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo (isset($gender) && $gender == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="civil_status">Civil Status <span class="required">*</span></label>
                    <select id="civil_status" name="civil_status" required>
                        <option value="">Select Status</option>
                        <option value="Single" <?php echo (isset($civil_status) && $civil_status == 'Single') ? 'selected' : ''; ?>>Single</option>
                        <option value="Married" <?php echo (isset($civil_status) && $civil_status == 'Married') ? 'selected' : ''; ?>>Married</option>
                        <option value="Widowed" <?php echo (isset($civil_status) && $civil_status == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                        <option value="Separated" <?php echo (isset($civil_status) && $civil_status == 'Separated') ? 'selected' : ''; ?>>Separated</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="home_address">Home Address <span class="required">*</span></label>
                <textarea id="home_address" name="home_address" required><?php echo isset($home_address) ? htmlspecialchars($home_address) : ''; ?></textarea>
            </div>
            
            <button type="submit" class="btn">Register</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = passwordField.nextElementSibling;
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.add('showing');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('showing');
            }
        }
        
        function checkPasswordStrength(password) {
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Character type checks
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            return strength;
        }
        
        function updatePasswordStrength(password, fieldId) {
            const passwordField = document.getElementById(fieldId);
            const strength = checkPasswordStrength(password);
            
            // Remove all strength classes
            passwordField.classList.remove('password-weak', 'password-medium', 'password-strong');
            
            if (password.length === 0) {
                return;
            }
            
            if (strength <= 2) {
                passwordField.classList.add('password-weak');
            } else if (strength <= 4) {
                passwordField.classList.add('password-medium');
            } else {
                passwordField.classList.add('password-strong');
            }
        }
        
        // Event listeners
        document.getElementById('password').addEventListener('input', function() {
            updatePasswordStrength(this.value, 'password');
        });
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            updatePasswordStrength(this.value, 'confirm_password');
        });
    </script>
</body>
</html>
