-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 03, 2026 at 06:06 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `student_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('student','employer','admin') NOT NULL,
  `action` varchar(255) NOT NULL,
  `target` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `role`, `action`, `target`, `ip_address`, `created_at`) VALUES
(1, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-03-30 15:42:41'),
(2, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-03-30 15:44:15'),
(3, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-03-30 16:18:32'),
(4, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-03-30 16:21:43'),
(5, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-03-31 02:17:35'),
(6, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-03-31 02:18:54'),
(7, 1, 'student', 'Download Certificate', 'Downloaded OJT certificate', '::1', '2026-03-31 02:18:56'),
(8, 1, 'student', 'Download Evaluation', 'Downloaded evaluation report', '::1', '2026-03-31 02:19:05'),
(9, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-03-31 02:22:39'),
(10, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-03-31 02:24:31'),
(11, 3, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-04-01 01:13:17'),
(12, 3, 'student', 'Change Password', 'Student changed password after first login', '::1', '2026-04-01 01:13:29'),
(13, 3, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-04-01 05:44:28'),
(14, 3, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-04-01 06:11:42'),
(15, 3, 'student', 'Shift Change Request', 'Requested shift change for 2026-04-02: 13:00 - 17:00:00', '::1', '2026-04-01 06:24:41'),
(16, 3, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-04-01 06:25:12'),
(17, 3, 'student', 'Shift Change Request', 'Requested shift change for 2026-04-01: 14:30 - 17:00:00', '::1', '2026-04-01 06:25:46'),
(18, 3, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-04-01 06:26:05'),
(19, 5, 'student', 'Student Login', 'Student raffy logged in', '::1', '2026-04-03 14:32:58'),
(20, 5, 'student', 'Change Password', 'Student changed password after first login', '::1', '2026-04-03 14:33:25'),
(21, 5, 'student', 'Student Login', 'Student raffy logged in', '::1', '2026-04-03 14:36:05'),
(22, 5, 'student', 'Student Login', 'Student raffy logged in', '::1', '2026-04-03 14:42:54'),
(23, 5, 'student', 'Shift Change Request', 'Requested shift change for 2026-04-03: 08:00:00 - 17:00:00', '::1', '2026-04-03 14:43:47'),
(24, 5, 'student', 'Student Login', 'Student raffy logged in', '::1', '2026-04-03 14:44:11'),
(25, 5, 'student', 'Student Login', 'Student raffy logged in', '::1', '2026-04-03 15:02:37'),
(26, 5, 'student', 'Student Login', 'Student raffy logged in', '::1', '2026-04-03 15:03:05'),
(27, 5, 'student', 'Student Login', 'Student raffy logged in', '::1', '2026-04-03 15:03:24'),
(28, 5, 'student', 'Download Evaluation', 'Downloaded evaluation report', '::1', '2026-04-03 15:03:25');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password`, `full_name`, `created_at`) VALUES
(1, 'Sir Raf', '$2y$10$m5X40dY9wfiDpwrLnBbhYe6OqPpeJuyO8GzHoY39Fk.teWiTL1Dc6', 'Rafael Javier', '2026-03-15 08:58:51');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `employer_id` int(11) DEFAULT NULL,
  `log_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `effective_start_time` datetime DEFAULT NULL,
  `lunch_out` datetime DEFAULT NULL,
  `lunch_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `status` enum('Present','Absent') DEFAULT 'Absent',
  `shift_status` enum('on_time','late_grace','adjusted_shift') DEFAULT 'on_time',
  `late_minutes` int(11) DEFAULT 0,
  `verified` tinyint(1) DEFAULT 0,
  `reason` text DEFAULT NULL,
  `daily_task` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `employer_id`, `log_date`, `time_in`, `effective_start_time`, `lunch_out`, `lunch_in`, `time_out`, `status`, `shift_status`, `late_minutes`, `verified`, `reason`, `daily_task`) VALUES
(8, 3, NULL, '2026-04-03', NULL, NULL, NULL, NULL, NULL, 'Absent', 'on_time', 0, 0, 'Auto-marked: No attendance recorded by 7:00 PM', NULL),
(9, 4, NULL, '2026-04-03', NULL, NULL, NULL, NULL, NULL, 'Absent', 'on_time', 0, 0, 'Auto-marked: No attendance recorded by 7:00 PM', NULL),
(10, 5, NULL, '2026-04-03', NULL, NULL, NULL, NULL, NULL, 'Absent', 'on_time', 0, 0, 'Auto-marked: No attendance recorded by 7:00 PM', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_type` enum('admin','employer') NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `target` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_type`, `user_id`, `action`, `target`, `ip_address`, `created_at`) VALUES
(1, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-03-15 08:58:53'),
(2, 'admin', 1, 'Add Employer', 'Sir Ge', '::1', '2026-03-15 08:59:00'),
(3, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-03-17 23:14:17'),
(4, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-03-18 00:40:46'),
(5, 'employer', 1, 'Generate Certificate', 'Certificate generated for student ID: 1, Certificate No: CERT-2026-1-001', '::1', '2026-03-30 15:42:38'),
(6, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-03-30 15:43:10'),
(7, 'employer', 1, 'Verify Attendance', 'Verified attendance for student ID: 1, Date: 2026-03-30', '::1', '2026-03-30 16:18:07'),
(8, 'employer', 1, 'Verify Attendance', 'Verified attendance for student ID: 1, Date: 2026-03-31', '::1', '2026-03-30 16:21:37'),
(9, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-03-31 02:17:48'),
(10, 'employer', 1, 'Create Project', 'Created project: porfolio', '::1', '2026-03-31 02:24:20'),
(11, 'employer', 1, 'Grade Submission', 'Submission ID: 2, Status: Approved', '::1', '2026-03-31 02:26:03'),
(12, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-03-31 03:58:43'),
(13, 'admin', 1, 'Add Employer', 'aiz', '::1', '2026-03-31 03:59:37'),
(14, 'employer', 2, 'Change Password', 'Supervisor changed password after first login', '::1', '2026-03-31 04:00:31'),
(15, 'employer', 2, 'Add Student', 'Employer added student: cedi', '::1', '2026-03-31 04:01:40'),
(16, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-04-01 00:29:48'),
(17, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-04-01 00:31:35'),
(18, 'admin', 1, 'Delete Employer', 'aiz', '::1', '2026-04-01 00:46:28'),
(19, 'employer', 1, 'Mark Absent', 'Marked student ID: 2 absent, Reason: 131', '::1', '2026-04-01 01:06:46'),
(20, 'employer', 1, 'Add Student', 'Employer added student: aiz', '::1', '2026-04-01 01:12:27'),
(21, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-04-01 03:47:34'),
(22, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-04-01 05:43:36'),
(23, 'employer', 1, 'Approve Shift Request', 'Shift request #1 approvedd for student ID: 3', '::1', '2026-04-01 06:24:59'),
(24, 'employer', 1, 'Approve Shift Request', 'Shift request #2 approvedd for student ID: 3', '::1', '2026-04-01 06:25:55'),
(25, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-04-03 10:45:12'),
(26, 'employer', 1, 'Verify Attendance', 'Verified attendance for student ID: 3, Date: 2026-04-01', '::1', '2026-04-03 13:01:27'),
(27, 'employer', 1, 'Auto Mark Absent', 'Student ID 3 (jedlian aiz) marked as absent - no attendance by 7PM', '::1', '2026-04-03 13:02:59'),
(28, 'employer', 1, 'Add Student', 'Employer added student: aizzz', '::1', '2026-04-03 14:32:10'),
(29, 'employer', 1, 'Add Student', 'Employer added student: raffy', '::1', '2026-04-03 14:32:52'),
(30, 'employer', 1, 'Auto Mark Absent', 'Student ID 3 (jedlian aiz) marked as absent - no attendance by 7PM', '::1', '2026-04-03 14:44:24'),
(31, 'employer', 1, 'Auto Mark Absent', 'Student ID 4 (jerome domingo) marked as absent - no attendance by 7PM', '::1', '2026-04-03 14:44:24'),
(32, 'employer', 1, 'Auto Mark Absent', 'Student ID 5 (jerome domingo) marked as absent - no attendance by 7PM', '::1', '2026-04-03 14:44:24'),
(33, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-04-03 14:45:36'),
(34, 'employer', 1, 'Reject Shift Request', 'Shift request #3 rejectedd for student ID: 5', '::1', '2026-04-03 14:51:26'),
(35, 'employer', 1, 'Submit Evaluation', 'Evaluation submitted for student: aiz', '::1', '2026-04-03 15:02:18'),
(36, 'employer', 1, 'Generate Certificate', 'Certificate generated for student ID: 3, Certificate No: CERT-2026-3-001', '::1', '2026-04-03 15:02:33'),
(37, 'employer', 1, 'Submit Evaluation', 'Evaluation submitted for student: raffy', '::1', '2026-04-03 15:02:59'),
(38, 'employer', 1, 'Generate Certificate', 'Certificate generated for student ID: 5, Certificate No: CERT-2026-5-001', '::1', '2026-04-03 15:03:19'),
(39, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-04-03 15:12:47');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `certificate_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `certificate_no` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `hours_completed` decimal(5,2) NOT NULL,
  `generated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`certificate_id`, `student_id`, `employer_id`, `certificate_no`, `file_path`, `hours_completed`, `generated_at`) VALUES
(5, 3, 1, 'CERT-2026-3-001', 'certificates/certificate_3_1775228547.pdf', 0.00, '2026-04-03 23:02:27'),
(6, 5, 1, 'CERT-2026-5-001', 'certificates/certificate_5_1775228594.pdf', 0.00, '2026-04-03 23:03:14');

-- --------------------------------------------------------

--
-- Table structure for table `certificate_hashes`
--

CREATE TABLE `certificate_hashes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `certificate_hash` varchar(255) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificate_hashes`
--

INSERT INTO `certificate_hashes` (`id`, `student_id`, `certificate_hash`, `generated_at`) VALUES
(5, 3, 'CERT-2026-3-001', '2026-04-03 15:02:27'),
(6, 5, 'CERT-2026-5-001', '2026-04-03 15:03:14');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employers`
--

CREATE TABLE `employers` (
  `employer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `company` varchar(255) NOT NULL DEFAULT '',
  `username` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `password_changed` tinyint(1) DEFAULT 0,
  `company_id` int(11) DEFAULT NULL,
  `work_start` time NOT NULL DEFAULT '08:00:00',
  `work_end` time NOT NULL DEFAULT '17:00:00',
  `late_grace_minutes` tinyint(4) NOT NULL DEFAULT 10,
  `eod_grace_hours` tinyint(4) NOT NULL DEFAULT 3
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employers`
--

INSERT INTO `employers` (`employer_id`, `name`, `company`, `username`, `email`, `password`, `created_at`, `password_changed`, `company_id`, `work_start`, `work_end`, `late_grace_minutes`, `eod_grace_hours`) VALUES
(1, 'Gerard Busuego', 'CRT', 'Sir Ge', 'domingojerome34@gmail.com', '$2y$10$XQBwthckgzATcmYg6TDv8.87GImxIorH1VzQ/FatNj3DEUYGJ./qq', '2026-03-15 08:59:00', 1, NULL, '08:00:00', '17:00:00', 20, 3);

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `evaluation_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `evaluation_date` date NOT NULL,
  `attendance_rating` tinyint(4) NOT NULL CHECK (`attendance_rating` between 1 and 5),
  `work_quality_rating` tinyint(4) NOT NULL CHECK (`work_quality_rating` between 1 and 5),
  `initiative_rating` tinyint(4) NOT NULL CHECK (`initiative_rating` between 1 and 5),
  `communication_rating` tinyint(4) NOT NULL CHECK (`communication_rating` between 1 and 5),
  `teamwork_rating` tinyint(4) NOT NULL CHECK (`teamwork_rating` between 1 and 5),
  `adaptability_rating` tinyint(4) NOT NULL CHECK (`adaptability_rating` between 1 and 5),
  `professionalism_rating` tinyint(4) NOT NULL CHECK (`professionalism_rating` between 1 and 5),
  `problem_solving_rating` tinyint(4) NOT NULL CHECK (`problem_solving_rating` between 1 and 5),
  `technical_skills_rating` tinyint(4) NOT NULL CHECK (`technical_skills_rating` between 1 and 5),
  `comments` text DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`evaluation_id`, `student_id`, `employer_id`, `evaluation_date`, `attendance_rating`, `work_quality_rating`, `initiative_rating`, `communication_rating`, `teamwork_rating`, `adaptability_rating`, `professionalism_rating`, `problem_solving_rating`, `technical_skills_rating`, `comments`, `signature_path`, `created_at`) VALUES
(3, 3, 1, '2026-04-03', 3, 4, 4, 5, 5, 5, 4, 5, 5, '', 'assets/signature_1_3.png', '2026-04-03 15:02:12'),
(4, 5, 1, '2026-04-03', 4, 5, 5, 5, 4, 5, 3, 5, 5, '', 'assets/signature_1_5.png', '2026-04-03 15:02:55');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_verification_codes`
--

CREATE TABLE `evaluation_verification_codes` (
  `verification_id` int(11) NOT NULL,
  `verification_key` varchar(64) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `sent_to_email` varchar(255) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `attempts` tinyint(4) NOT NULL DEFAULT 0,
  `max_attempts` tinyint(4) NOT NULL DEFAULT 5,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `evaluation_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluation_verification_codes`
--

INSERT INTO `evaluation_verification_codes` (`verification_id`, `verification_key`, `employer_id`, `student_id`, `sent_to_email`, `code_hash`, `attempts`, `max_attempts`, `expires_at`, `verified_at`, `used_at`, `evaluation_id`, `created_at`) VALUES
(1, '7ca6da759d6b4abbb6378eacfcc144ff', 1, 4, 'domingojerome34@gmail.com', '$2y$10$sUDNpRMaiZhdYJbU0PFv7.g.1WNiOp5Og7NTa4jEfHVJd5YmMOBkK', 0, 5, '2026-04-03 23:23:38', '2026-04-03 23:13:59', NULL, NULL, '2026-04-03 15:13:38');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `locked_at` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `reset_used` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `attempt_count`, `locked_at`, `reset_token`, `reset_expires`, `reset_used`, `updated_at`) VALUES
(7, 'aiz', '::1', 3, '2026-04-03 22:31:50', NULL, NULL, 0, '2026-04-03 14:31:50'),
(8, 'aizzz', '::1', 3, '2026-04-03 22:32:25', NULL, NULL, 0, '2026-04-03 14:32:25');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('Pending','Ongoing','Completed') DEFAULT 'Pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`project_id`, `project_name`, `description`, `start_date`, `due_date`, `status`, `created_by`, `created_at`) VALUES
(3, 'porfolio', '', '2026-03-31', '2026-03-31', 'Completed', 1, '2026-03-31 02:24:20');

-- --------------------------------------------------------

--
-- Table structure for table `project_submissions`
--

CREATE TABLE `project_submissions` (
  `submission_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `submission_status` enum('On Time','Late') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shift_change_requests`
--

CREATE TABLE `shift_change_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `request_date` date NOT NULL,
  `requested_shift_start` time NOT NULL,
  `requested_shift_end` time NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `used` tinyint(1) DEFAULT 0,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `required_hours` int(11) NOT NULL,
  `course` varchar(100) NOT NULL,
  `school` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `password_changed` tinyint(1) DEFAULT 0,
  `email` varchar(255) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `username`, `password`, `first_name`, `middle_name`, `last_name`, `required_hours`, `course`, `school`, `created_at`, `password_changed`, `email`, `created_by`, `company_id`) VALUES
(3, 'aiz', '$2y$10$mqSzAy9CX2B/cxhdP/8sq.4Jmddw0XwrJvfaCIOaKEKFHwPeqhnrS', 'jedlian', 'manubay', 'aiz', 200, 'bsit', 'CRT', '2026-04-01 01:12:27', 1, 'aizjedlian@gmail.com', 1, NULL),
(4, 'aizzz', '$2y$10$7C8haRQhIVzTfdkztYaSLe.2hBu.gQ2kEBZQseg.ZA8Hw4df/ZwiK', 'jerome', 'Manubay', 'domingo', 200, 'BSIT', 'CRT', '2026-04-03 14:32:10', 0, 'domingojerome34@gmail.com', 1, NULL),
(5, 'raffy', '$2y$10$64UX8HDbyrb5TgTKiaOwVec4z9mngqRac9x96/xNtaBaEkdRhnqYq', 'jerome', 'Manubay', 'domingo', 200, 'bacomm', 'CRT', '2026-04-03 14:32:52', 1, 'domingojerome34@gmail.com', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `uploaded_files`
--

CREATE TABLE `uploaded_files` (
  `id` int(11) NOT NULL,
  `uploader_type` enum('admin','employer') DEFAULT 'employer',
  `uploader_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(500) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id_role` (`user_id`,`role`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`student_id`,`log_date`,`employer_id`),
  ADD KEY `employer_id` (`employer_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`certificate_id`),
  ADD UNIQUE KEY `certificate_no` (`certificate_no`),
  ADD UNIQUE KEY `unique_certificate` (`student_id`,`employer_id`),
  ADD KEY `employer_id` (`employer_id`);

--
-- Indexes for table `certificate_hashes`
--
ALTER TABLE `certificate_hashes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_hash` (`student_id`,`certificate_hash`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`company_id`),
  ADD UNIQUE KEY `company_name` (`company_name`);

--
-- Indexes for table `employers`
--
ALTER TABLE `employers`
  ADD PRIMARY KEY (`employer_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_employers_company_id` (`company_id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`evaluation_id`),
  ADD UNIQUE KEY `unique_evaluation` (`student_id`,`employer_id`),
  ADD KEY `employer_id` (`employer_id`);

--
-- Indexes for table `evaluation_verification_codes`
--
ALTER TABLE `evaluation_verification_codes`
  ADD PRIMARY KEY (`verification_id`),
  ADD UNIQUE KEY `verification_key` (`verification_key`),
  ADD KEY `idx_eval_verify_lookup` (`verification_key`),
  ADD KEY `idx_eval_verify_owner` (`employer_id`,`student_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_username_ip` (`username`,`ip_address`),
  ADD KEY `idx_reset_token` (`reset_token`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`);

--
-- Indexes for table `project_submissions`
--
ALTER TABLE `project_submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD UNIQUE KEY `unique_submission` (`project_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `shift_change_requests`
--
ALTER TABLE `shift_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_approved_at` (`approved_at`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_students_company_id` (`company_id`);

--
-- Indexes for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_uploader_filename` (`uploader_type`,`uploader_id`,`filename`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `certificate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `certificate_hashes`
--
ALTER TABLE `certificate_hashes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `company_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employers`
--
ALTER TABLE `employers`
  MODIFY `employer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `evaluation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `evaluation_verification_codes`
--
ALTER TABLE `evaluation_verification_codes`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `project_submissions`
--
ALTER TABLE `project_submissions`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `shift_change_requests`
--
ALTER TABLE `shift_change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`employer_id`) ON DELETE CASCADE;

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`employer_id`) ON DELETE CASCADE;

--
-- Constraints for table `certificate_hashes`
--
ALTER TABLE `certificate_hashes`
  ADD CONSTRAINT `certificate_hashes_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `employers`
--
ALTER TABLE `employers`
  ADD CONSTRAINT `fk_employers_company_id` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE SET NULL;

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`employer_id`) ON DELETE CASCADE;

--
-- Constraints for table `project_submissions`
--
ALTER TABLE `project_submissions`
  ADD CONSTRAINT `project_submissions_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `shift_change_requests`
--
ALTER TABLE `shift_change_requests`
  ADD CONSTRAINT `shift_change_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shift_change_requests_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `employers` (`employer_id`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_company_id` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
