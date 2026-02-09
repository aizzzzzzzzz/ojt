<?php
session_start();
require_once __DIR__ . '/../private/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OJT Management System - Services</title>
  <meta name="description" content="Explore the services offered by the OJT Management System.">
  <meta name="keywords" content="OJT, Services, Management, System, Students, Employers, Admins">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      min-height: 100vh;
      margin: 0;
      padding: 0;
    }

    .hero-section {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 100px 0;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .hero-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
      animation: float 20s infinite linear;
      z-index: 1;
    }

    .hero-section .container {
      position: relative;
      z-index: 2;
      animation: fadeInUp 1s ease-out;
    }

    .hero-section h1 {
      font-size: 3.5rem;
      font-weight: bold;
      margin-bottom: 20px;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }

    .hero-section p {
      font-size: 1.25rem;
      margin-bottom: 0;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    }

    .services-section {
      padding: 80px 0;
      background: white;
    }

    .service-card {
      background: #fff;
      border-radius: 15px;
      padding: 40px 30px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
      text-align: center;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      border: 1px solid #e9ecef;
      animation: fadeInUp 1s ease-out;
      animation-fill-mode: both;
      margin-bottom: 30px;
    }

    .service-card:nth-child(1) { animation-delay: 0.2s; }
    .service-card:nth-child(2) { animation-delay: 0.4s; }
    .service-card:nth-child(3) { animation-delay: 0.6s; }
    .service-card:nth-child(4) { animation-delay: 0.8s; }
    .service-card:nth-child(5) { animation-delay: 1s; }
    .service-card:nth-child(6) { animation-delay: 1.2s; }

    .service-card:hover {
      transform: translateY(-10px) scale(1.05);
      box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    }

    .service-icon {
      font-size: 4rem;
      color: #667eea;
      margin-bottom: 20px;
      transition: transform 0.3s;
    }

    .service-card:hover .service-icon {
      transform: scale(1.2);
    }

    .service-card h3 {
      font-size: 1.5rem;
      font-weight: bold;
      margin-bottom: 15px;
      color: #333;
    }

    .service-card p {
      color: #666;
      line-height: 1.6;
    }

    .cta-section {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 80px 0;
      text-align: center;
    }

    .cta-section h2 {
      font-size: 2.5rem;
      font-weight: bold;
      margin-bottom: 20px;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }

    .cta-section p {
      font-size: 1.25rem;
      margin-bottom: 30px;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    }

    .btn-cta {
      background: white;
      color: #667eea;
      padding: 15px 30px;
      border-radius: 25px;
      text-decoration: none;
      font-weight: bold;
      font-size: 1.1rem;
      transition: all 0.3s;
      display: inline-block;
    }

    .btn-cta:hover {
      background: #f8f9fa;
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes float {
      0% { transform: translateY(0px); }
      100% { transform: translateY(-100px); }
    }

    @media (max-width: 768px) {
      .hero-section h1 {
        font-size: 2.5rem;
      }

      .hero-section p {
        font-size: 1rem;
      }

      .service-card {
        margin-bottom: 20px;
      }
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
      <a class="navbar-brand" href="index.php">OJT Management System</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="services.php">Services</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="about.php">About</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="contact.php">Contact</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <section class="hero-section">
    <div class="container">
      <h1>Our Services</h1>
      <p>Comprehensive solutions for managing On-the-Job Training programs</p>
    </div>
  </section>

  <section class="services-section">
    <div class="container">
      <div class="row">
        <div class="col-lg-4 col-md-6">
          <div class="service-card">
            <div class="service-icon">
              <i class="fas fa-user-graduate"></i>
            </div>
            <h3>Student Management</h3>
            <p>Complete student profile management, attendance tracking, and performance evaluation for OJT students.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="service-card">
            <div class="service-icon">
              <i class="fas fa-building"></i>
            </div>
            <h3>Employer Oversight</h3>
            <p>Tools for employers to monitor student progress, submit evaluations, and manage training programs.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="service-card">
            <div class="service-icon">
              <i class="fas fa-chart-line"></i>
            </div>
            <h3>Analytics & Reporting</h3>
            <p>Generate detailed reports on student performance, attendance patterns, and training outcomes.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="service-card">
            <div class="service-icon">
              <i class="fas fa-project-diagram"></i>
            </div>
            <h3>Project Management</h3>
            <p>Assign, track, and grade student projects with integrated submission and feedback systems.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="service-card">
            <div class="service-icon">
              <i class="fas fa-shield-alt"></i>
            </div>
            <h3>Security & Compliance</h3>
            <p>Secure data handling, audit trails, and compliance with educational standards and regulations.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="service-card">
            <div class="service-icon">
              <i class="fas fa-headset"></i>
            </div>
            <h3>24/7 Support</h3>
            <p>Dedicated support team available to assist administrators, employers, and students with any issues.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="cta-section">
    <div class="container">
      <h2>Ready to Get Started?</h2>
      <p>Join thousands of institutions using our OJT Management System</p>
      <a href="index.php" class="btn-cta">Login Now</a>
    </div>
  </section>

  <footer class="bg-dark text-white py-4">
    <div class="container text-center">
      <p>&copy; 2024 OJT Management System. All rights reserved.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
