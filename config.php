<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'depedloan');

// Start session
session_start();

// Create database connection with port 3307
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    // Database created or already exists
} else {
    // Handle error silently for now
}

// Select the database
$conn->select_db(DB_NAME);

// Create users table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'borrower', 'accountant') DEFAULT 'borrower',
    deped_id VARCHAR(50),
    contact_number VARCHAR(20),
    birth_date DATE,
    gender VARCHAR(10),
    civil_status VARCHAR(20),
    home_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

// Check if role column exists, if not, add it
$check_column_sql = "SHOW COLUMNS FROM users LIKE 'role'";
$result = $conn->query($check_column_sql);
if ($result->num_rows == 0) {
    $alter_sql = "ALTER TABLE users ADD COLUMN role ENUM('admin', 'borrower', 'accountant') DEFAULT 'borrower'";
    $conn->query($alter_sql);
} else {
    $role_type = $result->fetch_assoc()['Type'] ?? '';
    if (strpos($role_type, "'accountant'") === false && strpos($role_type, "'accounting'") !== false) {
        $conn->query("ALTER TABLE users MODIFY role ENUM('admin', 'borrower', 'accounting', 'accountant') DEFAULT 'borrower'");
        $conn->query("UPDATE users SET role = 'accountant' WHERE role = 'accounting'");
        $conn->query("ALTER TABLE users MODIFY role ENUM('admin', 'borrower', 'accountant') DEFAULT 'borrower'");
    } else {
        $conn->query("ALTER TABLE users MODIFY role ENUM('admin', 'borrower', 'accountant') DEFAULT 'borrower'");
        $conn->query("UPDATE users SET role = 'accountant' WHERE role = 'accounting'");
    }
}

// Create loans table if it doesn't exist
$create_loans_sql = "CREATE TABLE IF NOT EXISTS loans (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    loan_amount DECIMAL(10,2) NOT NULL,
    loan_purpose TEXT NOT NULL,
    loan_term INT NOT NULL,
    net_pay DECIMAL(10,2) NOT NULL,
    school_assignment VARCHAR(255) NOT NULL,
    position VARCHAR(255) NOT NULL,
    salary_grade VARCHAR(10) NOT NULL,
    employment_status VARCHAR(50) NOT NULL,
    co_maker_full_name VARCHAR(150) NOT NULL,
    co_maker_position VARCHAR(150) NOT NULL,
    co_maker_school_assignment VARCHAR(255) NOT NULL,
    co_maker_net_pay DECIMAL(10,2) NOT NULL,
    co_maker_employment_status VARCHAR(50) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by_id INT(11) NULL,
    reviewed_by_role VARCHAR(50) NULL,
    reviewed_by_name VARCHAR(150) NULL,
    reviewed_at TIMESTAMP NULL,
    monthly_payment DECIMAL(10,2),
    total_amount DECIMAL(10,2),
    total_interest DECIMAL(10,2),
    released_at TIMESTAMP NULL,
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($create_loans_sql);

// Create deductions table for payroll collections
$create_deductions_sql = "CREATE TABLE IF NOT EXISTS deductions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    loan_id INT(11) NOT NULL,
    borrower_id INT(11) NOT NULL,
    deduction_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    posted_by INT(11) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    FOREIGN KEY (borrower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
)";
$conn->query($create_deductions_sql);

// Create fund ledger table for fund health reporting
$create_fund_ledger_sql = "CREATE TABLE IF NOT EXISTS fund_ledger (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    entry_type ENUM('collection', 'release', 'adjustment') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id INT(11) NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_fund_ledger_sql);

// Add new loan columns if missing (migration for existing DBs)
$loan_columns = [
    'net_pay' => "ALTER TABLE loans ADD COLUMN net_pay DECIMAL(10,2) NOT NULL",
    'co_maker_full_name' => "ALTER TABLE loans ADD COLUMN co_maker_full_name VARCHAR(150) NOT NULL",
    'co_maker_position' => "ALTER TABLE loans ADD COLUMN co_maker_position VARCHAR(150) NOT NULL",
    'co_maker_school_assignment' => "ALTER TABLE loans ADD COLUMN co_maker_school_assignment VARCHAR(255) NOT NULL",
    'co_maker_net_pay' => "ALTER TABLE loans ADD COLUMN co_maker_net_pay DECIMAL(10,2) NOT NULL",
    'co_maker_employment_status' => "ALTER TABLE loans ADD COLUMN co_maker_employment_status VARCHAR(50) NOT NULL",
    'payslip_filename' => "ALTER TABLE loans ADD COLUMN payslip_filename VARCHAR(255) NOT NULL",
    'co_maker_payslip_filename' => "ALTER TABLE loans ADD COLUMN co_maker_payslip_filename VARCHAR(255) NOT NULL",
    'reviewed_by_id' => "ALTER TABLE loans ADD COLUMN reviewed_by_id INT(11) NULL",
    'reviewed_by_role' => "ALTER TABLE loans ADD COLUMN reviewed_by_role VARCHAR(50) NULL",
    'reviewed_by_name' => "ALTER TABLE loans ADD COLUMN reviewed_by_name VARCHAR(150) NULL",
    'reviewed_at' => "ALTER TABLE loans ADD COLUMN reviewed_at TIMESTAMP NULL",
    'monthly_payment' => "ALTER TABLE loans ADD COLUMN monthly_payment DECIMAL(10,2) NULL",
    'total_amount' => "ALTER TABLE loans ADD COLUMN total_amount DECIMAL(10,2) NULL",
    'total_interest' => "ALTER TABLE loans ADD COLUMN total_interest DECIMAL(10,2) NULL",
    'released_at' => "ALTER TABLE loans ADD COLUMN released_at TIMESTAMP NULL"
];

foreach ($loan_columns as $column => $alter_sql) {
    $check_column_sql = "SHOW COLUMNS FROM loans LIKE '$column'";
    $result = $conn->query($check_column_sql);
    if ($result && $result->num_rows == 0) {
        $conn->query($alter_sql);
    }
}

// Relax old columns if they exist from previous schema
$legacy_columns = ['monthly_income', 'basic_salary'];
foreach ($legacy_columns as $column) {
    $check_column_sql = "SHOW COLUMNS FROM loans LIKE '$column'";
    $result = $conn->query($check_column_sql);
    if ($result && $result->num_rows == 1) {
        $conn->query("ALTER TABLE loans MODIFY $column DECIMAL(10,2) NULL");
    }
}
?>
