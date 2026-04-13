-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 13, 2026 at 08:48 PM
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
(1, 1, 'student', 'Student Login', 'Student aiz logged in', '103.27.108.10', '2026-03-15 23:55:00'),
(2, 2, 'student', 'Student Login', 'Student raffy logged in', '103.27.108.11', '2026-03-15 23:57:00'),
(3, 3, 'student', 'Student Login', 'Student jade logged in', '103.27.108.12', '2026-03-15 23:58:00'),
(4, 1, 'student', 'Shift Change Request', 'Requested shift change for 2026-03-18: 09:00:00 - 18:00:00', '103.27.108.10', '2026-03-17 08:20:00'),
(5, 2, 'student', 'Shift Change Request', 'Requested shift change for 2026-03-19: 07:00:00 - 16:00:00', '103.27.108.11', '2026-03-18 07:45:00'),
(6, 3, 'student', 'Shift Change Request', 'Requested shift change for 2026-03-20: 10:00:00 - 19:00:00', '103.27.108.12', '2026-03-19 06:30:00'),
(7, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-04-06 14:20:06'),
(8, 1, 'student', 'Download Certificate', 'Downloaded OJT certificate', '::1', '2026-04-06 14:20:34'),
(9, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-04-06 14:22:28'),
(10, 2, 'student', 'Student Login', 'Student raffy logged in', '::1', '2026-04-06 14:22:33'),
(11, 2, 'student', 'Download Certificate', 'Downloaded OJT certificate', '::1', '2026-04-06 14:22:46'),
(12, 2, 'student', 'Download Evaluation', 'Downloaded evaluation report', '::1', '2026-04-06 14:22:46'),
(13, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-04-06 14:24:00'),
(14, 1, 'student', 'Download Certificate', 'Downloaded OJT certificate', '::1', '2026-04-06 14:24:01'),
(15, 1, 'student', 'Download Certificate', 'Downloaded OJT certificate', '::1', '2026-04-06 14:25:15'),
(16, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-04-06 14:31:10'),
(17, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-04-06 14:31:21'),
(18, 1, 'student', 'Download Certificate', 'Downloaded OJT certificate', '::1', '2026-04-06 14:34:00'),
(19, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-04-13 16:35:31'),
(20, 1, 'student', 'Download Certificate', 'Downloaded OJT certificate', '::1', '2026-04-13 16:35:41'),
(21, 1, 'student', 'Download Evaluation', 'Downloaded evaluation report', '::1', '2026-04-13 16:35:43'),
(22, 1, 'student', 'Student Login', 'Student aiz logged in', '::1', '2026-04-13 16:35:54');

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
(1, 'Sir Raf', '$2y$10$jQHglhYv9XzjZCrmDDIzn.Opbiue0Tlpz3luNSIPAL2aijjieDzZe', 'Rafael Javier', '2026-04-06 14:19:25');

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

INSERT INTO `attendance` (`id`, `student_id`, `employer_id`, `log_date`, `time_in`, `effective_start_time`, `time_out`, `status`, `shift_status`, `late_minutes`, `verified`, `reason`, `daily_task`) VALUES
(1, 1, 1, '2026-03-16', '2026-03-16 08:02:00', NULL, '2026-03-16 17:01:00', 'Present', 'on_time', 0, 1, NULL, 'Accessed the live InfinityFree deployment and completed attendance logging test.'),
(2, 1, 1, '2026-03-17', '2026-03-17 08:00:00', NULL, '2026-03-17 17:03:00', 'Present', 'on_time', 0, 1, NULL, 'Verified online login flow and completed internship task entry.'),
(3, 1, 1, '2026-03-18', '2026-03-18 09:01:00', '2026-03-18 09:00:00', '2026-03-18 18:00:00', 'Present', 'adjusted_shift', 0, 1, NULL, 'Used approved adjusted shift and completed dashboard workflow testing.'),
(4, 1, 1, '2026-03-19', '2026-03-19 08:04:00', NULL, '2026-03-19 17:02:00', 'Present', 'on_time', 0, 1, NULL, 'Validated live attendance persistence and record updates.'),
(5, 1, 1, '2026-03-20', '2026-03-20 08:00:00', NULL, '2026-03-20 17:05:00', 'Present', 'on_time', 0, 1, NULL, 'Completed final online attendance test and supervisor confirmation run.'),
(6, 2, 1, '2026-03-16', '2026-03-16 08:09:00', NULL, '2026-03-16 17:02:00', 'Present', 'late_grace', 9, 1, NULL, 'Logged in through the internet and tested regular attendance submission.'),
(7, 2, 1, '2026-03-17', '2026-03-17 08:03:00', NULL, '2026-03-17 17:00:00', 'Present', 'on_time', 0, 1, NULL, 'Checked student dashboard behavior on the live deployment.'),
(8, 2, 1, '2026-03-18', '2026-03-18 08:01:00', NULL, '2026-03-18 17:04:00', 'Present', 'on_time', 0, 1, NULL, 'Performed attendance logging and routine deployment checks.'),
(9, 2, 1, '2026-03-19', '2026-03-19 08:03:00', NULL, '2026-03-19 17:01:00', 'Present', 'on_time', 0, 1, NULL, 'Completed attendance after shift request rejection and maintained normal schedule.'),
(10, 2, 1, '2026-03-20', '2026-03-20 08:00:00', NULL, '2026-03-20 17:03:00', 'Present', 'on_time', 0, 0, NULL, 'Finished the final live attendance validation cycle.'),
(11, 3, 1, '2026-03-16', '2026-03-16 08:00:00', NULL, '2026-03-16 17:00:00', 'Present', 'on_time', 0, 1, NULL, 'Logged in successfully and tested attendance submission on production.'),
(12, 3, 1, '2026-03-17', '2026-03-17 08:05:00', NULL, '2026-03-17 17:02:00', 'Present', 'on_time', 0, 1, NULL, 'Performed regular attendance logging and online functionality checks.'),
(13, 3, 1, '2026-03-18', '2026-03-18 08:02:00', NULL, '2026-03-18 17:01:00', 'Present', 'on_time', 0, 1, NULL, 'Validated attendance history and time capture accuracy.'),
(14, 3, 1, '2026-03-19', '2026-03-19 08:01:00', NULL, '2026-03-19 17:04:00', 'Present', 'on_time', 0, 1, NULL, 'Completed pre-evaluation live testing tasks and attendance logging.'),
(15, 3, 1, '2026-03-20', '2026-03-20 10:02:00', '2026-03-20 10:00:00', '2026-03-20 19:01:00', 'Present', 'adjusted_shift', 0, 0, NULL, 'Worked under the approved adjusted shift and completed final production checks.');

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
(1, 'employer', 1, 'Approve Shift Request', 'Shift request approved for student ID: 1', '185.27.134.210', '2026-03-17 09:05:00'),
(2, 'employer', 1, 'Reject Shift Request', 'Shift request rejected for student ID: 2', '185.27.134.210', '2026-03-18 08:10:00'),
(3, 'employer', 1, 'Approve Shift Request', 'Shift request approved for student ID: 3', '185.27.134.210', '2026-03-19 07:00:00'),
(4, 'employer', 1, 'Submit Evaluation', 'Evaluation submitted for student ID: 1', '185.27.134.210', '2026-03-20 07:30:00'),
(5, 'employer', 1, 'Submit Evaluation', 'Evaluation submitted for student ID: 2', '185.27.134.210', '2026-03-20 07:40:00'),
(6, 'employer', 1, 'Submit Evaluation', 'Evaluation submitted for student ID: 3', '185.27.134.210', '2026-03-20 07:50:00'),
(7, 'employer', 1, 'Generate Certificate', 'Certificate generated for student ID: 1, Certificate No: CERT-2026-1-001', '185.27.134.210', '2026-03-20 08:30:00'),
(8, 'employer', 1, 'Generate Certificate', 'Certificate generated for student ID: 2, Certificate No: CERT-2026-2-001', '185.27.134.210', '2026-03-20 08:40:00'),
(9, 'employer', 1, 'Generate Certificate', 'Certificate generated for student ID: 3, Certificate No: CERT-2026-3-001', '185.27.134.210', '2026-03-20 08:50:00'),
(10, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-04-06 14:19:27'),
(11, 'admin', 1, 'Update Employer', 'ian', '::1', '2026-04-06 14:19:40'),
(12, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-04-06 14:21:32'),
(13, 'employer', 1, 'Generate Certificate', 'Certificate generated for student ID: 1, Certificate No: CERT-2026-1-001', '::1', '2026-04-06 14:23:40'),
(14, 'employer', 1, 'Generate Certificate', 'Certificate generated for student ID: 3, Certificate No: CERT-2026-3-001', '::1', '2026-04-06 14:23:46'),
(15, 'employer', 1, 'Generate Certificate', 'Certificate generated for student ID: 2, Certificate No: CERT-2026-2-001', '::1', '2026-04-06 14:23:52'),
(16, 'employer', 1, 'Verify Attendance', 'Verified attendance for student ID: 1, Date: 2026-03-20', '::1', '2026-04-06 14:31:17'),
(17, 'admin', 1, 'Admin Login', 'Admin Sir Raf logged in.', '::1', '2026-04-13 16:59:09');

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
(4, 1, 1, 'CERT-2026-1-001', 'certificates/certificate_1_1775485414.pdf', 40.07, '2026-03-20 17:23:34'),
(5, 3, 1, 'CERT-2026-3-001', 'certificates/certificate_3_1775485422.pdf', 39.97, '2026-03-20 17:25:42'),
(6, 2, 1, 'CERT-2026-2-001', 'certificates/certificate_2_1775485428.pdf', 39.90, '2026-03-20 17:27:48');

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
(4, 1, 'CERT-2026-1-001', '2026-04-06 14:23:34'),
(5, 3, 'CERT-2026-3-001', '2026-04-06 14:23:42'),
(6, 2, 'CERT-2026-2-001', '2026-04-06 14:23:48');

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

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`company_id`, `company_name`, `created_at`, `updated_at`) VALUES
(1, 'InfinityFree Live Deployment', '2026-04-06 14:18:49', '2026-04-06 14:18:49');

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
(1, 'Ian Luriz', 'Jedlian Holdings', 'ian', 'aizjedlian@gmail.com', '$2y$10$8s34DWWcNFR5J4Vmqk7Aqezfv7wO2Zg08DR94HwqR.U/wt4km2UMu', '2026-04-06 14:18:49', 1, 1, '08:00:00', '17:00:00', 10, 3);

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
(1, 1, 1, '2026-03-20', 5, 5, 4, 4, 5, 4, 5, 4, 4, 'Completed the live InfinityFree deployment test successfully and demonstrated reliable attendance logging.', NULL, '2026-04-06 14:18:49'),
(2, 2, 1, '2026-03-20', 4, 4, 4, 4, 4, 4, 4, 4, 4, 'Completed attendance and workflow validation successfully under the standard schedule.', NULL, '2026-04-06 14:18:49'),
(3, 3, 1, '2026-03-20', 5, 5, 5, 4, 5, 5, 5, 4, 5, 'Completed the full online testing cycle successfully, including adjusted shift workflow validation.', NULL, '2026-04-06 14:18:49');

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
(2, 'Sir Ge', '::1', 2, NULL, NULL, NULL, 0, '2026-04-13 16:36:03');

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

--
-- Dumping data for table `shift_change_requests`
--

INSERT INTO `shift_change_requests` (`id`, `student_id`, `request_date`, `requested_shift_start`, `requested_shift_end`, `reason`, `status`, `used`, `requested_at`, `reviewed_by`, `reviewed_at`, `approved_at`, `review_notes`) VALUES
(2, 2, '2026-03-19', '07:00:00', '16:00:00', 'Requested an earlier shift to accommodate an afternoon academic obligation.', 'rejected', 0, '2026-03-18 07:45:00', 1, '2026-03-18 08:10:00', NULL, 'Rejected because the regular testing schedule needed consistent morning deployment checks.'),
(3, 3, '2026-03-20', '10:00:00', '19:00:00', 'Requested a later shift because of a scheduled academic requirement in the morning.', 'approved', 1, '2026-03-19 06:30:00', 1, '2026-03-19 07:00:00', '2026-03-19 07:00:00', 'Approved with adjusted shift applied for final day of testing.');

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
(1, 'aiz', '$2y$10$8s34DWWcNFR5J4Vmqk7Aqezfv7wO2Zg08DR94HwqR.U/wt4km2UMu', 'Jerome', 'Manubay', 'Domingo', 300, 'BSIT', 'CRT', '2026-04-06 14:18:49', 1, 'domingojerome34@gmail.com', 1, 1),
(2, 'raffy', '$2y$10$8s34DWWcNFR5J4Vmqk7Aqezfv7wO2Zg08DR94HwqR.U/wt4km2UMu', 'Rafael', 'Viola', 'Tabora', 300, 'BSIT', 'CRT', '2026-04-06 14:18:49', 1, 'rafaetabora2004@gmail.com', 1, 1),
(3, 'jade', '$2y$10$8s34DWWcNFR5J4Vmqk7Aqezfv7wO2Zg08DR94HwqR.U/wt4km2UMu', 'Jade Laurence', 'Pasco', 'Pablo', 500, 'BSIT', 'CRT', '2026-04-06 14:18:49', 1, 'laurenzkiepablo12@gmail.com', 1, 1);

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
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

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
  MODIFY `company_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employers`
--
ALTER TABLE `employers`
  MODIFY `employer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `evaluation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `evaluation_verification_codes`
--
ALTER TABLE `evaluation_verification_codes`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `shift_change_requests`
--
ALTER TABLE `shift_change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

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
