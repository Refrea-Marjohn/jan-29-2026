<?php
require_once 'config.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($username) || empty($password)) {
        $error = "Username and password are required";
    } else {
        // Check user credentials
        $sql = "SELECT id, username, email, password, full_name, role FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Password is correct, start session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                
                // Redirect based on user role
                if (($user['role'] ?? '') === 'admin' || $user['username'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif (($user['role'] ?? '') === 'accountant') {
                    header("Location: accountant_dashboard.php");
                } else {
                    header("Location: borrower_dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
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
    <title>Login - DepEd Loan System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
        }
        
        .split-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        .left-side {
            width: 60%;
            background: url('loginbg.jpg') center/cover;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            padding: 3rem;
            position: relative;
            overflow: hidden;
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
        
        .feature-list {
            text-align: left;
            margin-top: 2rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .feature-icon {
            margin-right: 1rem;
            font-size: 1.5rem;
        }
        
        .right-side {
            width: 40%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .form-container {
            width: 100%;
            max-width: 420px;
            position: relative;
        }
        
        .back-button {
            position: absolute;
            top: -50px;
            left: 0;
            background: rgba(139, 0, 0, 0.08);
            border: 1px solid rgba(139, 0, 0, 0.25);
            color: #8b0000;
            padding: 0.45rem 0.85rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            gap: 0.45rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .back-button:hover {
            background: rgba(139, 0, 0, 0.2);
            border-color: #8b0000;
            transform: translateX(-2px);
        }
        
        .form-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 2.75rem 2.75rem 2.5rem;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            border: 1px solid #f0f0f0;
        }
        
        .form-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 0.6rem;
            font-size: 32px;
        }

        .form-subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 2.25rem;
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 26px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .remember-me input[type="checkbox"] {
            width: auto;
            margin: 0;
            padding: 0;
        }
        
        .remember-me label {
            margin: 0;
            font-size: 14px;
            cursor: pointer;
        }
        
        .forgot-password {
            color: #8b0000;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .forgot-password:hover {
            color: #dc143c;
            text-decoration: underline;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        
        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa0a6;
            font-size: 0.95rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 14px 14px 14px 42px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
            background: #fff;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #8b0000;
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.12);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            box-shadow: 0 10px 25px rgba(139, 0, 0, 0.2);
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
        
        .register-link {
            text-align: center;
            margin-top: 26px;
            color: #666;
        }
        
        .register-link a {
            color: #8b0000;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .split-container {
                flex-direction: column;
            }
            
            .left-side, .right-side {
                width: 100%;
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

            .back-button {
                position: static;
                margin-bottom: 1rem;
                width: fit-content;
            }

            .form-card {
                padding: 2rem 1.75rem 1.9rem;
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
                <h1>DepEd Loan System</h1>
                <p>Your gateway to exclusive financial benefits and support services designed for DepEd employees. Access your account to manage loans and track applications.</p>
            </div>
        </div>
        
        <div class="right-side">
            <div class="form-container">
                <a href="index.php" class="back-button">
                    <span class="back-icon">←</span>
                    Back to Landing Page
                </a>
                <div class="form-card">
                    <h2>Login</h2>
                    <div class="form-subtitle">Sign in to access your loan dashboard.</div>
        
                <?php if (!empty($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <input type="text" id="username" name="username" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember me</label>
                        </div>
                        <a href="#" class="forgot-password">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn">Login</button>
                </form>
                
                <div class="register-link">
                    Don't have an account? <a href="register.php">Register here</a>
                </div>
            </div>
            </div>
        </div>
    </div>
</body>
</html>
