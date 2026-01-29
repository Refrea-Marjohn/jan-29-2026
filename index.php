<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DepEd Loan System - Home</title>
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
        
        .navbar {
            background: rgba(139, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        .nav-links a:hover {
            opacity: 0.8;
        }
        
        .btn-nav {
            background: rgba(139, 0, 0, 0.8);
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-nav:hover {
            background: rgba(139, 0, 0, 1);
            transform: translateY(-2px);
        }
        
        .hero {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 0 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="50" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            opacity: 0.3;
        }
        
        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .hero-text h1 {
            font-size: 3.5rem;
            color: white;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .hero-text p {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            background: white;
            color: #8b0000;
            padding: 15px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: transform 0.2s;
            display: inline-block;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: transparent;
            color: white;
            padding: 15px 30px;
            border: 2px solid white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            background: white;
            color: #8b0000;
        }
        
        .hero-image {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .hero-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .hero-card h3 {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .loan-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }
        
        .features {
            padding: 5rem 2rem;
            background: #f8f9fa;
        }
        
        .features-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .features h2 {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .features p {
            text-align: center;
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 24px;
            color: white;
        }
        
        .feature-card h3 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .feature-card p {
            color: #666;
            line-height: 1.6;
        }
        
        .cta {
            background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
            padding: 5rem 2rem;
            text-align: center;
            color: white;
            display: none;
        }
        
        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .cta p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .footer {
            background: black;
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        .footer-links a:hover {
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .hero-content {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }
            
            .hero-text h1 {
                font-size: 2.5rem;
            }
            
            .hero-buttons {
                justify-content: center;
            }
            
            .nav-links {
                gap: 1rem;
            }
            
            .loan-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">DepEd Loan System</div>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#about">About</a>
                <a href="login.php" class="btn-nav">Login</a>
                <a href="register.php" class="btn-nav">Register</a>
            </div>
        </div>
    </nav>
    
    <section class="hero">
        <div class="hero-content">
            <div class="hero-text">
                <h1>Financial Support for DepEd Employees</h1>
                <p>Your trusted partner for educational financial assistance. We provide quick, secure, and transparent loan services designed specifically for Department of Education employees.</p>
                <div class="hero-buttons">
                    <a href="register.php" class="btn-primary">Get Started</a>
                    <a href="#features" class="btn-secondary">Learn More</a>
                </div>
            </div>
            
            <div class="hero-image">
                <div class="hero-card">
                    <img src="SDO.jpg" alt="DepEd Loan System" style="width: 100%; height: 400px; object-fit: cover; border-radius: 10px; margin-bottom: 1rem;">
                
                </div>
            </div>
        </div>
    </section>
    
    <section class="features" id="features">
        <div class="features-container">
            <h2>Why Choose DepEd Loan System?</h2>
            <p>We offer comprehensive loan solutions tailored to meet the unique needs of DepEd employees</p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>Fast Processing</h3>
                    <p>Quick loan approval process with minimal paperwork. Get your funds within 24 hours of approval.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">💰</div>
                    <h3>Competitive Rates</h3>
                    <p>Enjoy low interest rates and flexible payment terms designed for government employees.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Secure & Confidential</h3>
                    <p>Your information is protected with bank-level security. All transactions are encrypted and confidential.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>Online Access</h3>
                    <p>Manage your loans anytime, anywhere through our secure online portal and mobile-friendly interface.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h3>Exclusive Benefits</h3>
                    <p>Special loan programs and benefits exclusively available to DepEd employees and their families.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🤝</div>
                    <h3>Dedicated Support</h3>
                    <p>Our team of loan specialists is ready to assist you throughout your loan journey.</p>
                </div>
            </div>
        </div>
    </section>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="#features">Features</a>
                <a href="#about">About Us</a>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Contact Us</a>
                <a href="#">FAQ</a>
            </div>
            <div style="margin-bottom: 2rem;">
                <h3 style="margin-bottom: 1rem; color: white;">DepEd Loan System</h3>
                <p style="max-width: 600px; margin: 0 auto; line-height: 1.6;">
                    Your trusted financial partner for Department of Education employees. 
                    Providing fast, secure, and affordable loan services to support the educational community.
                </p>
            </div>
            <div style="display: flex; justify-content: center; gap: 2rem; margin-bottom: 2rem; flex-wrap: wrap;">
                <div>
                    <h4 style="color: white; margin-bottom: 0.5rem;">Contact Info</h4>
                    <p style="font-size: 0.9rem;">Email: support@depedloan.gov.ph</p>
                    <p style="font-size: 0.9rem;">Hotline: (02) 1234-5678</p>
                </div>
                <div>
                    <h4 style="color: white; margin-bottom: 0.5rem;">Office Hours</h4>
                    <p style="font-size: 0.9rem;">Monday - Friday: 8:00 AM - 5:00 PM</p>
                    <p style="font-size: 0.9rem;">Saturday: 9:00 AM - 12:00 PM</p>
                </div>
                <div>
                    <h4 style="color: white; margin-bottom: 0.5rem;">Quick Links</h4>
                    <p style="font-size: 0.9rem;">Loan Calculator</p>
                    <p style="font-size: 0.9rem;">Requirements</p>
                </div>
            </div>
            <div style="border-top: 1px solid rgba(255, 255, 255, 0.2); padding-top: 1rem; margin-top: 1rem;">
                <p>&copy; 2024 DepEd Loan System. All rights reserved. | Department of Education Philippines</p>
                <p style="font-size: 0.85rem; opacity: 0.8; margin-top: 0.5rem;">
                    SEC Registered | BSP Accredited | Data Privacy Compliant
                </p>
            </div>
        </div>
    </footer>
</body>
</html>
