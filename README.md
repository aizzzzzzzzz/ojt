# OJT Management System

A comprehensive web application for managing On-the-Job Training (OJT) programs, including student attendance, employer verification, quizzes, evaluations, and certificate generation.

## Features

- Student attendance tracking with time-in/out and lunch breaks
- Employer attendance verification
- Hours calculation and progress tracking
- Basic dashboards for students, employers, and admins
- Absence handling with notifications
- Daily tasks logging
- Employer evaluations
- Admin dashboard for management
- Quiz system with automatic grading
- Auto certificate generation with blockchain verification
- Email notifications for key events

## Security Enhancements

- CSRF protection on forms
- Input sanitization
- Password hashing
- Session management

## Notifications

- Email notifications for absences, evaluations, and quiz results
- Configurable SMTP settings in config.php

## Blockchain Verification

- Simple hash-based blockchain verification for certificates
- Verification endpoint at verify_blockchain.php

## Installation

1. Clone the repository
2. Set up XAMPP or similar web server
3. Import the database schema (student_db.sql)
4. Update config.php with your database credentials and SMTP settings
5. Access the application at http://localhost/ojt

## SSL Setup Guide

To enable HTTPS for secure communication:

1. **Obtain SSL Certificate:**
   - Use Let's Encrypt for free certificates: https://letsencrypt.org/
   - Or purchase from a CA like DigiCert

2. **Configure Apache (XAMPP):**
   - Edit httpd.conf and enable mod_ssl
   - Create virtual host with SSL
   - Example configuration:
     ```
     <VirtualHost *:443>
         DocumentRoot "C:/xampp/htdocs/ojt"
         ServerName yourdomain.com
         SSLEngine on
         SSLCertificateFile "path/to/certificate.crt"
         SSLCertificateKeyFile "path/to/private.key"
     </VirtualHost>
     ```

3. **Redirect HTTP to HTTPS:**
   - Add rewrite rules in .htaccess:
     ```
     RewriteEngine On
     RewriteCond %{HTTPS} off
     RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
     ```

4. **Test SSL:**
   - Use https://www.ssllabs.com/ssltest/ to check configuration

## Database Tables

Ensure the following tables exist:
- students
- employers
- attendance
- quizzes
- quiz_questions
- quiz_results
- evaluations
- certificate_hashes (for blockchain)

## Usage

- Admin: Manage employers, students, and view reports
- Employer: Verify attendance, create quizzes, evaluate students
- Student: Log attendance, take quizzes, view progress

## Contributing

Feel free to contribute improvements and bug fixes.
