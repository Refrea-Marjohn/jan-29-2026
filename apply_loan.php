<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT username, email, full_name, created_at, profile_photo FROM users WHERE id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$stmt->close();
$profile_photo = $user_data['profile_photo'] ?? '';
$profile_photo_exists = $profile_photo && file_exists(__DIR__ . '/' . $profile_photo);

// Handle form submission
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $loan_amount = trim($_POST['loan_amount']);
    $loan_purpose = trim($_POST['loan_purpose']);
    $loan_term = trim($_POST['loan_term']);
    $net_pay = trim($_POST['net_pay']);
    $school_assignment = trim($_POST['school_assignment']);
    $position = trim($_POST['position']);
    $salary_grade = trim($_POST['salary_grade']);
    $employment_status = trim($_POST['employment_status']);
    $co_maker_full_name = trim($_POST['co_maker_full_name']);
    $co_maker_position = trim($_POST['co_maker_position']);
    $co_maker_school_assignment = trim($_POST['co_maker_school_assignment']);
    $co_maker_net_pay = trim($_POST['co_maker_net_pay']);
    $co_maker_employment_status = trim($_POST['co_maker_employment_status']);
    $payslip_filename = '';
    $co_maker_payslip_filename = '';
    
    // Validation
    if (empty($loan_amount) || empty($loan_purpose) || empty($loan_term) || empty($net_pay) ||
        empty($school_assignment) || empty($position) || empty($salary_grade) || empty($employment_status) ||
        empty($co_maker_full_name) || empty($co_maker_position) || empty($co_maker_school_assignment) ||
        empty($co_maker_net_pay) || empty($co_maker_employment_status)) {
        $error = "All fields are required";
    } elseif (!is_numeric($loan_amount) || $loan_amount < 1000 || $loan_amount > 100000) {
        $error = "Loan amount must be between ₱1,000 and ₱100,000";
    } elseif (!is_numeric($net_pay) || $net_pay <= 0) {
        $error = "Net pay must be greater than ₱0";
    } elseif (!is_numeric($co_maker_net_pay) || $co_maker_net_pay <= 0) {
        $error = "Co-maker net pay must be greater than ₱0";
    } elseif (!in_array($loan_term, [6, 12, 18, 24, 30, 36, 42, 48, 54, 60])) {
        $error = "Invalid loan term selected";
    } else {
        // Payslip upload validation
        if (empty($_FILES['payslip_file']['name']) || $_FILES['payslip_file']['error'] !== UPLOAD_ERR_OK) {
            $error = "Borrower payslip file is required";
        } elseif (empty($_FILES['co_maker_payslip_file']['name']) || $_FILES['co_maker_payslip_file']['error'] !== UPLOAD_ERR_OK) {
            $error = "Co-maker payslip file is required";
        } else {
            $allowed_mimes = [
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png'
            ];
            $upload_dir = __DIR__ . '/../../private/payslips';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $payslip_file = $_FILES['payslip_file'];
            $co_maker_payslip_file = $_FILES['co_maker_payslip_file'];

            $validate_upload = function ($file, $label) use ($allowed_mimes, $upload_dir, $user_id) {
                if ($file['size'] > 5 * 1024 * 1024) {
                    return [$label . " payslip must be 5MB or less", null];
                }
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                if (!isset($allowed_mimes[$mime])) {
                    return [$label . " payslip must be PDF, JPG, or PNG", null];
                }
                $filename = $label . '_payslip_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_mimes[$mime];
                $target_path = $upload_dir . '/' . $filename;
                if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                    return ["Failed to upload " . strtolower($label) . " payslip", null];
                }
                return [null, $filename];
            };

            [$upload_error, $payslip_filename] = $validate_upload($payslip_file, 'borrower');
            if ($upload_error) {
                $error = $upload_error;
            } else {
                [$upload_error, $co_maker_payslip_filename] = $validate_upload($co_maker_payslip_file, 'co_maker');
                if ($upload_error) {
                    $error = $upload_error;
                }
            }
        }

        if (!empty($error)) {
            // Skip insert if upload failed
        } else {
        // Database connection
        require_once 'config.php';
        
        // Calculate loan details
        $annualInterestRate = 0.06; // 6% per annum
        $monthlyInterestRate = $annualInterestRate / 12; // 0.5% per month
        $monthlyPayment = $loan_amount * ($monthlyInterestRate * pow(1 + $monthlyInterestRate, $loan_term)) / (pow(1 + $monthlyInterestRate, $loan_term) - 1);
        $totalAmount = $monthlyPayment * $loan_term;
        $totalInterest = $totalAmount - $loan_amount;
        
        // Insert loan application with encryption
        $sql = "INSERT INTO loans (
            user_id,
            loan_amount,
            loan_purpose,
            loan_term,
            net_pay,
            school_assignment,
            position,
            salary_grade,
            employment_status,
            co_maker_full_name,
            co_maker_position,
            co_maker_school_assignment,
            co_maker_net_pay,
            co_maker_employment_status,
            payslip_filename,
            co_maker_payslip_filename,
            monthly_payment,
            total_amount,
            total_interest
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        // Create variables for bind_param (required for reference passing)
        $uid = $_SESSION['user_id'];
        $lamt = (float)$loan_amount;
        $lp = $loan_purpose;
        $lt = (int)$loan_term;
        $np = (float)$net_pay;
        $sa = $school_assignment;
        $pos = $position;
        $sg = $salary_grade;
        $es = $employment_status;
        $cmn = $co_maker_full_name;
        $cmp = $co_maker_position;
        $cmsa = $co_maker_school_assignment;
        $cmnp = (float)$co_maker_net_pay;
        $cmes = $co_maker_employment_status;
        $pf = $payslip_filename;
        $cmpf = $co_maker_payslip_filename;
        $mp = (float)$monthlyPayment;
        $ta = (float)$totalAmount;
        $ti = (float)$totalInterest;
        
        $stmt->bind_param(
            "idsidsssssssdsssssddd",
            $uid,
            $lamt,
            $lp,
            $lt,
            $np,
            $sa,
            $pos,
            $sg,
            $es,
            $cmn,
            $cmp,
            $cmsa,
            $cmnp,
            $cmes,
            $pf,
            $cmpf,
            $mp,
            $ta,
            $ti
        );
        
        if ($stmt->execute()) {
            $success = "Loan application submitted successfully! We will review your application within 3-5 business days.";
            
            // Clear form fields
            $loan_amount = $loan_purpose = $loan_term = $net_pay = $school_assignment = $position = $salary_grade = $employment_status = '';
            $co_maker_full_name = $co_maker_position = $co_maker_school_assignment = $co_maker_net_pay = $co_maker_employment_status = '';
            $payslip_filename = '';
            $co_maker_payslip_filename = '';
        } else {
            $error = "Error submitting application. Please try again.";
        }
        
        $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Loan - DepEd Loan System</title>
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
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            padding-bottom: 1rem;
            font-weight: 700;
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            border-radius: 2px;
            box-shadow: 0 2px 10px rgba(139, 0, 0, 0.3);
        }
        
        .loan-form {
            width: 100%;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            text-align: left;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
            text-align: left;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #8b0000;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
            width: 100%;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            border: 1px solid #f5c6cb;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            border: 1px solid #c3e6cb;
        }
        
        .loan-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .loan-info h3 {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        
        .info-item:hover {
            transform: translateY(-2px);
        }
        
        .info-item i {
            font-size: 1.5rem;
            color: #8b0000;
            width: 30px;
            text-align: center;
        }
        
        .info-item strong {
            color: #333;
            display: block;
            margin-bottom: 0.3rem;
        }
        
        .info-item p {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .requirements-section {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 2rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }
        
        .requirements-header {
            background: #6c757d;
            color: white;
            padding: 1.25rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .requirements-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .requirements-header h3 i {
            color: #ffffff;
            font-size: 1.1rem;
        }
        
        .requirements-content {
            padding: 1.5rem;
        }
        
        .requirements-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }
        
        .requirement-item {
            display: flex;
            align-items: flex-start;
            gap: 0.875rem;
            padding: 1.25rem;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .requirement-item:hover {
            background: #ffffff;
            border-color: #6c757d;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }
        
        .requirement-item i {
            color: #495057;
            font-size: 1.25rem;
            min-width: 20px;
            text-align: center;
            margin-top: 0.1rem;
        }
        
        .requirement-content {
            flex: 1;
        }
        
        .requirement-item .requirement-title {
            color: #212529;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.375rem;
            display: block;
        }
        
        .requirement-item .requirement-desc {
            color: #6c757d;
            font-size: 0.875rem;
            line-height: 1.4;
            margin: 0;
        }
        
        .form-container {
            background: white;
            padding: 0.7rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 0.25rem;
            text-align: left;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-bottom: 0.25rem;
            text-align: left;
        }
        
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-container h3 {
            color: #333;
            margin-bottom: 0.35rem;
            font-size: 1.05rem;
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #666;
            font-size: 0.72rem;
        }

        .file-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            align-items: center;
            margin-top: 0.5rem;
        }

        .file-preview-btn {
            background: #ffffff;
            color: #8b0000;
            border: 1px solid #eadfe2;
            padding: 0.25rem 0.6rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.2s ease;
        }

        .file-preview-btn:hover {
            background: #fff5f5;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
        }

        .form-group input[type="file"] {
            width: 100%;
            padding: 0.28rem;
            border: 1px dashed #d6dbe2;
            border-radius: 8px;
            background: #fafbfc;
        }
        
        .loan-computation h4 {
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .loan-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            background: white;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        
        .summary-item label {
            font-weight: 600;
            color: #495057;
            margin: 0;
        }
        
        .summary-item span {
            font-weight: 600;
            color: #212529;
        }
        
        .loan-computation {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0.75rem;
            margin: 0.25rem 0 0.75rem 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            padding: 0.25rem;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        
        .schedule-item {
            background: white;
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            text-align: center;
            transition: all 0.2s ease;
        }
        
        .schedule-item:hover {
            border-color: #8b0000;
            box-shadow: 0 2px 6px rgba(139, 0, 0, 0.1);
        }
        
        .schedule-month {
            font-weight: 600;
            color: #495057;
            font-size: 0.8rem;
            margin-bottom: 0.2rem;
        }
        
        .schedule-amount {
            color: #8b0000;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            flex: 1;
        }
        
        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(139, 0, 0, 0.4);
            background: linear-gradient(135deg, #a52a2a 0%, #e74c3c 100%);
        }
        
        .form-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 2px solid #e9ecef;
        }
        
        .reset-btn {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            flex: 1;
        }
        
        .reset-btn:hover {
            background: linear-gradient(135deg, #5a6268 0%, #343a40 100%);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .welcome-message {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="welcome-message">
            <div class="welcome-block">
                <div class="welcome-title">Welcome back, <strong><?php echo htmlspecialchars($user_data['full_name']); ?></strong>! 👋</div>
                <div class="welcome-meta">
                    <span class="meta-pill"><i class="fas fa-id-badge"></i> Borrower</span>
                    <span><i class="fas fa-calendar-check"></i> <?php echo date('M d, Y'); ?></span>
                    <span><i class="fas fa-file-signature"></i> Apply for Loan</span>
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
                    <a href="borrower_dashboard.php" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-home"></i></span>
                        Dashboard
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="my_loans.php" class="sidebar-link">
                        <span class="sidebar-icon"><i class="fas fa-credit-card"></i></span>
                        My Loans
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="apply_loan.php" class="sidebar-link active">
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
            <div class="content-section">
                <h2 class="section-title">Apply for Loan</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="form-container">
                    <h3><i class="fas fa-edit"></i> Application Form</h3>
                    
                    <!-- Loan Computation Section -->
                    <div id="loan-computation" class="loan-computation" style="display: none;">
                        <h4><i class="fas fa-calculator"></i> Loan Computation</h4>
                        <div class="loan-summary">
                            <div class="summary-item">
                                <label>Principal Amount:</label>
                                <span id="principal-display">₱0.00</span>
                            </div>
                            <div class="summary-item">
                                <label>Monthly Payment:</label>
                                <span id="monthly-payment-display">₱0.00</span>
                            </div>
                            <div class="summary-item">
                                <label>Total Amount:</label>
                                <span id="total-amount-display">₱0.00</span>
                            </div>
                            <div class="summary-item">
                                <label>Total Interest:</label>
                                <span id="total-interest-display">₱0.00</span>
                            </div>
                        </div>
                        
                        <h5><i class="fas fa-calendar-alt"></i> Payment Schedule</h5>
                        <div id="schedule-grid" class="schedule-grid">
                            <!-- Payment schedule will be generated here -->
                        </div>
                    </div>
                    
                    <form class="loan-form" method="POST" action="" enctype="multipart/form-data">
                        <h4 style="margin-bottom: 1rem; color: #8b0000;"><i class="fas fa-user"></i> Borrower Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="loan_amount"><i class="fas fa-peso-sign"></i> Loan Amount (₱)</label>
                                <input type="number" id="loan_amount" name="loan_amount" min="1000" max="100000" step="100" required value="<?php echo isset($loan_amount) ? htmlspecialchars($loan_amount) : ''; ?>">
                                <small>Minimum ₱1,000 / Maximum ₱100,000</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="loan_term"><i class="fas fa-calendar-alt"></i> Loan Term (months)</label>
                                <select id="loan_term" name="loan_term" required>
                                    <option value="">Select Payment Term</option>
                                    <option value="6" <?php echo (isset($loan_term) && $loan_term == '6') ? 'selected' : ''; ?>>6 months</option>
                                    <option value="12" <?php echo (isset($loan_term) && $loan_term == '12') ? 'selected' : ''; ?>>12 months</option>
                                    <option value="18" <?php echo (isset($loan_term) && $loan_term == '18') ? 'selected' : ''; ?>>18 months</option>
                                    <option value="24" <?php echo (isset($loan_term) && $loan_term == '24') ? 'selected' : ''; ?>>24 months</option>
                                    <option value="30" <?php echo (isset($loan_term) && $loan_term == '30') ? 'selected' : ''; ?>>30 months</option>
                                    <option value="36" <?php echo (isset($loan_term) && $loan_term == '36') ? 'selected' : ''; ?>>36 months</option>
                                    <option value="42" <?php echo (isset($loan_term) && $loan_term == '42') ? 'selected' : ''; ?>>42 months</option>
                                    <option value="48" <?php echo (isset($loan_term) && $loan_term == '48') ? 'selected' : ''; ?>>48 months</option>
                                    <option value="54" <?php echo (isset($loan_term) && $loan_term == '54') ? 'selected' : ''; ?>>54 months</option>
                                    <option value="60" <?php echo (isset($loan_term) && $loan_term == '60') ? 'selected' : ''; ?>>60 months (5 years)</option>
                                </select>
                                <small>6–60 months</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="net_pay"><i class="fas fa-money-bill-wave"></i> Net Pay (₱)</label>
                                <input type="number" id="net_pay" name="net_pay" min="1" step="100" required value="<?php echo isset($net_pay) ? htmlspecialchars($net_pay) : ''; ?>">
                                <small>Enter your net pay</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="school_assignment"><i class="fas fa-school"></i> School Assignment *</label>
                                <input type="text" id="school_assignment" name="school_assignment" required value="<?php echo isset($school_assignment) ? htmlspecialchars($school_assignment) : ''; ?>">
                                <small>Enter your assigned school</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="position"><i class="fas fa-user-tie"></i> Position *</label>
                                <input type="text" id="position" name="position" required value="<?php echo isset($position) ? htmlspecialchars($position) : ''; ?>">
                                <small>Enter your current position</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="salary_grade"><i class="fas fa-chart-line"></i> Salary Grade *</label>
                                <select id="salary_grade" name="salary_grade" required>
                                    <option value="">Select Salary Grade</option>
                                    <?php
                                    $salary_grades = range(1, 33);
                                    foreach ($salary_grades as $grade) {
                                        $selected = (isset($salary_grade) && $salary_grade == $grade) ? 'selected' : '';
                                        echo "<option value='$grade' $selected>Grade $grade</option>";
                                    }
                                    ?>
                                </select>
                                <small>Select your salary grade</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employment_status"><i class="fas fa-user-check"></i> Employment Status *</label>
                                <select id="employment_status" name="employment_status" required>
                                    <option value="">Select Employment Status</option>
                                    <option value="Permanent" <?php echo (isset($employment_status) && $employment_status == 'Permanent') ? 'selected' : ''; ?>>Permanent</option>
                                    <option value="Contractual" <?php echo (isset($employment_status) && $employment_status == 'Contractual') ? 'selected' : ''; ?>>Contractual</option>
                                    <option value="Substitute" <?php echo (isset($employment_status) && $employment_status == 'Substitute') ? 'selected' : ''; ?>>Substitute</option>
                                    <option value="Provisional" <?php echo (isset($employment_status) && $employment_status == 'Provisional') ? 'selected' : ''; ?>>Provisional</option>
                                    <option value="Probationary" <?php echo (isset($employment_status) && $employment_status == 'Probationary') ? 'selected' : ''; ?>>Probationary</option>
                                </select>
                                <small>Select your current employment status</small>
                            </div>

                            <div class="form-group">
                                <label for="payslip_file"><i class="fas fa-file-upload"></i> Latest Payslip (PDF/JPG/PNG)</label>
                                <input type="file" id="payslip_file" name="payslip_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                <small>Upload latest payslip (PDF, JPG, PNG) up to 5MB.</small>
                                <div class="file-actions">
                                    <button type="button" id="payslip_preview_btn" class="file-preview-btn" style="display: none;" onclick="previewPayslip('payslip_file')">
                                        <i class="fas fa-eye"></i> View Selected Payslip
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 1 1 100%;">
                                <label for="loan_purpose"><i class="fas fa-comment-alt"></i> Loan Purpose</label>
                                <textarea id="loan_purpose" name="loan_purpose" required placeholder="Please describe the purpose of your loan in detail..."><?php echo isset($loan_purpose) ? htmlspecialchars($loan_purpose) : ''; ?></textarea>
                                <small>Provide a clear description of how you plan to use the loan</small>
                            </div>
                        </div>

                        <h4 style="margin: 2rem 0 1rem; color: #8b0000;"><i class="fas fa-user-friends"></i> Co-Maker Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="co_maker_full_name"><i class="fas fa-user"></i> Co-Maker Full Name *</label>
                                <input type="text" id="co_maker_full_name" name="co_maker_full_name" required value="<?php echo isset($co_maker_full_name) ? htmlspecialchars($co_maker_full_name) : ''; ?>">
                                <small>Enter co-maker full name</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="co_maker_position"><i class="fas fa-user-tie"></i> Position *</label>
                                <input type="text" id="co_maker_position" name="co_maker_position" required value="<?php echo isset($co_maker_position) ? htmlspecialchars($co_maker_position) : ''; ?>">
                                <small>Enter co-maker position</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="co_maker_school_assignment"><i class="fas fa-school"></i> School / Office Assignment *</label>
                                <input type="text" id="co_maker_school_assignment" name="co_maker_school_assignment" required value="<?php echo isset($co_maker_school_assignment) ? htmlspecialchars($co_maker_school_assignment) : ''; ?>">
                                <small>Enter co-maker assignment</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="co_maker_net_pay"><i class="fas fa-money-bill-wave"></i> Net Pay (₱) *</label>
                                <input type="number" id="co_maker_net_pay" name="co_maker_net_pay" min="1" step="100" required value="<?php echo isset($co_maker_net_pay) ? htmlspecialchars($co_maker_net_pay) : ''; ?>">
                                <small>Enter co-maker net pay</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="co_maker_employment_status"><i class="fas fa-user-check"></i> Employment Status *</label>
                                <select id="co_maker_employment_status" name="co_maker_employment_status" required>
                                    <option value="">Select Employment Status</option>
                                    <option value="Permanent" <?php echo (isset($co_maker_employment_status) && $co_maker_employment_status == 'Permanent') ? 'selected' : ''; ?>>Permanent</option>
                                    <option value="Contractual" <?php echo (isset($co_maker_employment_status) && $co_maker_employment_status == 'Contractual') ? 'selected' : ''; ?>>Contractual</option>
                                    <option value="Substitute" <?php echo (isset($co_maker_employment_status) && $co_maker_employment_status == 'Substitute') ? 'selected' : ''; ?>>Substitute</option>
                                    <option value="Provisional" <?php echo (isset($co_maker_employment_status) && $co_maker_employment_status == 'Provisional') ? 'selected' : ''; ?>>Provisional</option>
                                    <option value="Probationary" <?php echo (isset($co_maker_employment_status) && $co_maker_employment_status == 'Probationary') ? 'selected' : ''; ?>>Probationary</option>
                                </select>
                                <small>Select co-maker employment status</small>
                            </div>
                            <div class="form-group">
                                <label for="co_maker_payslip_file"><i class="fas fa-file-upload"></i> Co-Maker Payslip (PDF/JPG/PNG)</label>
                                <input type="file" id="co_maker_payslip_file" name="co_maker_payslip_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                <small>Upload co-maker payslip (PDF, JPG, PNG) up to 5MB.</small>
                                <div class="file-actions">
                                    <button type="button" id="co_maker_payslip_preview_btn" class="file-preview-btn" style="display: none;" onclick="previewPayslip('co_maker_payslip_file')">
                                        <i class="fas fa-eye"></i> View Selected Payslip
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-paper-plane"></i> Submit Application
                            </button>
                            <button type="button" class="reset-btn" onclick="resetForm()">
                                <i class="fas fa-redo"></i> Reset Form
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Loan Information Section -->
                <div class="loan-info">
                    <h3><i class="fas fa-info-circle"></i> Loan Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <i class="fas fa-piggy-bank"></i>
                            <div>
                                <strong>Interest Rate</strong>
                                <p>6% per annum</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-calendar-check"></i>
                            <div>
                                <strong>Payment Terms</strong>
                                <p>6 to 60 months (up to 5 years)</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <div>
                                <strong>Maximum Loan</strong>
                                <p>₱100,000</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-shield-alt"></i>
                            <div>
                                <strong>Processing Time</strong>
                                <p>3-5 working days</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requirements Section -->
                <div class="requirements-section">
                    <div class="requirements-header">
                        <h3><i class="fas fa-file-contract"></i> Requirements (2 copies)</h3>
                    </div>
                    <div class="requirements-content">
                        <div class="requirements-list">
                            <div class="requirement-item">
                                <i class="fas fa-file-download"></i>
                                <div class="requirement-content">
                                    <strong class="requirement-title">Provident Fund Application Form</strong>
                                    <p class="requirement-desc">Can be downloaded at https://depedcabuyao.ph/accounting-forms/</p>
                                </div>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-envelope"></i>
                                <div class="requirement-content">
                                    <strong class="requirement-title">Letter Request</strong>
                                    <p class="requirement-desc">With Pictures/Registration Form/Bills, etc.</p>
                                </div>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-money-bill-wave"></i>
                                <div class="requirement-content">
                                    <strong class="requirement-title">Original Payslip</strong>
                                    <p class="requirement-desc">Latest month available at Cash Unit</p>
                                </div>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-copy"></i>
                                <div class="requirement-content">
                                    <strong class="requirement-title">Photocopy of Latest Payslip</strong>
                                    <p class="requirement-desc">Borrower & Co-borrower; should have net pay of Php 5,000.00 after initial computation of loan amortization</p>
                                </div>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-id-card"></i>
                                <div class="requirement-content">
                                    <strong class="requirement-title">Photocopy of DepED ID or Government ID</strong>
                                    <p class="requirement-desc">Any valid government ID with Certificate of Employment from HR (if no DepED ID)</p>
                                </div>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-users"></i>
                                <div class="requirement-content">
                                    <strong class="requirement-title">Photocopy of Co-borrowers' ID</strong>
                                    <p class="requirement-desc">DepED ID or any valid government ID</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
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
        
        // Reset form function
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                document.querySelector('.loan-form').reset();
                document.getElementById('loan-computation').style.display = 'none';
            }
        }
        
        // Loan computation function
        function computeLoan() {
            const loanAmount = parseFloat(document.getElementById('loan_amount').value) || 0;
            const loanTerm = parseInt(document.getElementById('loan_term').value) || 0;
            
            if (loanAmount > 0 && loanTerm > 0) {
                const annualInterestRate = 0.06; // 6% per annum
                const monthlyInterestRate = annualInterestRate / 12; // 0.5% per month
                
                // Compute using amortization formula (proper loan calculation)
                const monthlyPayment = loanAmount * (monthlyInterestRate * Math.pow(1 + monthlyInterestRate, loanTerm)) / (Math.pow(1 + monthlyInterestRate, loanTerm) - 1);
                const totalAmount = monthlyPayment * loanTerm;
                const totalInterest = totalAmount - loanAmount;
                
                // Update display
                document.getElementById('principal-display').textContent = '₱' + loanAmount.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('monthly-payment-display').textContent = '₱' + monthlyPayment.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('total-amount-display').textContent = '₱' + totalAmount.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('total-interest-display').textContent = '₱' + totalInterest.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                // Generate payment schedule
                generatePaymentSchedule(loanTerm, monthlyPayment);
                
                // Show computation section
                document.getElementById('loan-computation').style.display = 'block';
            } else {
                // Hide computation section if inputs are invalid
                document.getElementById('loan-computation').style.display = 'none';
            }
        }
        
        // Generate payment schedule
        function generatePaymentSchedule(loanTerm, monthlyPayment) {
            const scheduleGrid = document.getElementById('schedule-grid');
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                          'July', 'August', 'September', 'October', 'November', 'December'];
            const currentDate = new Date();
            let currentMonth = currentDate.getMonth();
            let currentYear = currentDate.getFullYear();
            
            let scheduleHTML = '';
            
            for (let i = 1; i <= loanTerm; i++) {
                const monthName = months[currentMonth];
                const displayText = i === 1 ? `${monthName} ${currentYear}` : monthName;
                
                scheduleHTML += `
                    <div class="schedule-item">
                        <div class="schedule-month">${displayText}</div>
                        <div class="schedule-amount">₱${monthlyPayment.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    </div>
                `;
                
                // Move to next month
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
            }
            
            scheduleGrid.innerHTML = scheduleHTML;
        }
        
        // Add event listeners for real-time computation
        document.addEventListener('DOMContentLoaded', function() {
            const loanAmountInput = document.getElementById('loan_amount');
            const loanTermSelect = document.getElementById('loan_term');
            
            loanAmountInput.addEventListener('input', computeLoan);
            loanTermSelect.addEventListener('change', computeLoan);
            
            // Compute on page load if values are pre-filled
            computeLoan();
        });

        function updatePayslipPreviewButton(inputId) {
            const input = document.getElementById(inputId);
            const buttonId = inputId === 'co_maker_payslip_file' ? 'co_maker_payslip_preview_btn' : 'payslip_preview_btn';
            const button = document.getElementById(buttonId);
            if (input && button) {
                button.style.display = input.files && input.files.length ? 'inline-flex' : 'none';
            }
        }

        function previewPayslip(inputId) {
            const input = document.getElementById(inputId);
            if (!input || !input.files || !input.files.length) {
                return;
            }
            const file = input.files[0];
            const fileUrl = URL.createObjectURL(file);
            window.open(fileUrl, '_blank', 'noopener');
            setTimeout(() => URL.revokeObjectURL(fileUrl), 10000);
        }

        document.getElementById('payslip_file').addEventListener('change', function() {
            updatePayslipPreviewButton('payslip_file');
        });
        document.getElementById('co_maker_payslip_file').addEventListener('change', function() {
            updatePayslipPreviewButton('co_maker_payslip_file');
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
