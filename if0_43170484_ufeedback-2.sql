-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql110.infinityfree.com
-- Generation Time: Oct 20, 2026 at 11:36 PM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_43170484_ufeedback`
--

-- --------------------------------------------------------

--
-- Table structure for table `action_plans`
--

CREATE TABLE `action_plans` (
  `id` int(11) NOT NULL,
  `feedback_id` int(11) NOT NULL COMMENT 'The specific low-rating feedback this addresses',
  `office_id` int(11) NOT NULL,
  `assigned_staff_id` varchar(50) DEFAULT NULL,
  `root_cause_analysis` text NOT NULL,
  `action_taken` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'Medium',
  `due_date` date DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` varchar(50) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `action_plans`
--

INSERT INTO `action_plans` (`id`, `feedback_id`, `office_id`, `assigned_staff_id`, `root_cause_analysis`, `action_taken`, `status`, `created_at`, `resolved_at`, `priority`, `due_date`, `acknowledged_at`, `verified_at`, `verified_by`, `resolution_notes`, `updated_at`) VALUES
(1, 4, 22, 'HES-683', 'Unclear signage', 'added signage', 'Resolved', '2026-10-17 00:00:36', '2026-10-20 16:25:23', 'Medium', NULL, NULL, NULL, NULL, NULL, '2026-10-20 16:25:23'),
(2, 9, 1, 'HES-683', 'Understaffed Frontlines', 'retrained staffs', 'In Progress', '2026-10-17 01:04:18', NULL, 'Medium', NULL, NULL, NULL, NULL, NULL, '2026-10-21 02:29:30');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_pic` varchar(255) DEFAULT 'default.jpg',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `arta_feedback`
--

CREATE TABLE `arta_feedback` (
  `feedback_id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `office_id` int(11) NOT NULL,
  `service_accessed` varchar(255) NOT NULL COMMENT 'Specific service requested (e.g., Enrollment, COR)',
  `transaction_date` date NOT NULL,
  `service_start_date` date DEFAULT NULL,
  `client_type` enum('Citizen','Business','Government') NOT NULL DEFAULT 'Citizen',
  `age_group` varchar(20) NOT NULL,
  `sex` enum('Male','Female','Prefer not to say') NOT NULL,
  `region` varchar(100) NOT NULL DEFAULT 'Region IX',
  `cc1` tinyint(1) NOT NULL COMMENT '1: Knows CC, 2: Knows CC but didn''t see, 3: Learned CC only now, 4: Doesn''t know CC',
  `cc2` tinyint(1) NOT NULL COMMENT '1: Easy to see, 2: Somewhat easy, 3: Difficult to see, 4: Not visible, 5: N/A',
  `cc3` tinyint(1) NOT NULL COMMENT '1: Helped very much, 2: Somewhat helped, 3: Did not help, 4: N/A',
  `sqd1_responsiveness` tinyint(1) NOT NULL DEFAULT 0,
  `sqd2_reliability` tinyint(1) NOT NULL DEFAULT 0,
  `sqd3_access_facilities` tinyint(1) NOT NULL DEFAULT 0,
  `sqd4_communication` tinyint(1) NOT NULL DEFAULT 0,
  `sqd5_costs` tinyint(1) NOT NULL DEFAULT 0,
  `sqd6_integrity` tinyint(1) NOT NULL DEFAULT 0,
  `sqd7_assurance` tinyint(1) NOT NULL DEFAULT 0,
  `sqd8_outcome` tinyint(1) NOT NULL DEFAULT 0,
  `comments_recommendations` text DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0 COMMENT 'Replaces the history table',
  `submission_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `arta_feedback`
--

INSERT INTO `arta_feedback` (`feedback_id`, `user_id`, `office_id`, `service_accessed`, `transaction_date`, `service_start_date`, `client_type`, `age_group`, `sex`, `region`, `cc1`, `cc2`, `cc3`, `sqd1_responsiveness`, `sqd2_reliability`, `sqd3_access_facilities`, `sqd4_communication`, `sqd5_costs`, `sqd6_integrity`, `sqd7_assurance`, `sqd8_outcome`, `comments_recommendations`, `is_archived`, `submission_timestamp`) VALUES
(1, NULL, 8, 'Enrollment', '2026-10-14', '2026-10-05', 'Business', '19 or lower', 'Male', 'Region IX - Zamboanga Peninsula', 2, 2, 1, 5, 5, 1, 0, 0, 5, 5, 5, 'Hakdog', 0, '2026-10-14 07:29:29'),
(2, NULL, 8, 'Chupapi', '2026-10-14', '2026-10-05', 'Citizen', '19 or lower', 'Male', 'Region IX - Zamboanga Peninsula', 1, 1, 1, 5, 5, 5, 5, 0, 5, 5, 5, 'Good', 0, '2026-10-14 09:10:59'),
(3, NULL, 23, 'Request', '2026-10-14', '2026-10-05', 'Citizen', '19 or lower', 'Male', 'Region IX - Zamboanga Peninsula', 1, 2, 1, 5, 5, 5, 5, 5, 5, 5, 5, 'Good', 0, '2026-10-14 09:12:10'),
(4, NULL, 22, 'Chupapi', '2026-10-14', '2026-10-08', 'Citizen', '19 or lower', 'Male', 'Region IX - Zamboanga Peninsula', 1, 2, 1, 5, 5, 5, 5, 4, 2, 2, 3, 'Mabuti', 0, '2026-10-14 09:13:35'),
(5, NULL, 1, 'Request', '2026-10-14', '2026-10-09', 'Citizen', '19 or lower', 'Male', 'Region IX - Zamboanga Peninsula', 1, 1, 1, 5, 5, 4, 3, 0, 3, 5, 5, 'Okay', 0, '2026-10-14 09:14:52'),
(6, 'admin', 8, 'Academic Advising & Subject Enlistment', '2026-10-14', '2026-10-09', 'Government', '35-49', 'Prefer not to say', 'Region IX - Zamboanga Peninsula', 1, 1, 1, 5, 4, 5, 4, 0, 5, 5, 4, '', 0, '2026-10-14 12:45:41'),
(7, NULL, 8, 'Academic Advising & Subject Enlistment', '2026-10-14', '2026-10-06', 'Citizen', '19 or lower', 'Male', 'Region IX - Zamboanga Peninsula', 1, 2, 1, 5, 5, 5, 5, 0, 5, 5, 5, 'bold plss', 0, '2026-10-14 13:27:34'),
(8, NULL, 43, 'Application for Graduation & University Clearance', '2026-10-15', '2026-10-09', 'Citizen', '20-34', 'Male', 'Region IX - Zamboanga Peninsula', 1, 1, 1, 4, 4, 0, 4, 0, 3, 4, 4, 'mabait sila.', 0, '2026-10-15 08:27:24'),
(9, 'admin', 1, 'Adding, Dropping, & Changing of Subjects', '2026-10-15', '2026-10-09', 'Government', '35-49', 'Prefer not to say', 'Region IX - Zamboanga Peninsula', 1, 1, 1, 2, 5, 4, 5, 0, 5, 5, 5, '', 0, '2026-10-15 08:49:53'),
(10, 'admin', 8, 'Adding, Dropping, & Changing of Subjects', '2026-10-15', '2026-10-05', 'Government', '35-49', 'Prefer not to say', 'Region IX - Zamboanga Peninsula', 1, 1, 1, 4, 4, 5, 5, 0, 5, 4, 5, '', 0, '2026-10-15 08:54:34'),
(11, NULL, 8, 'Academic Advising & Subject Enlistment', '2026-10-16', '2026-10-16', 'Citizen', '20-34', 'Male', 'Region IX - Zamboanga Peninsula', 1, 2, 1, 4, 4, 4, 4, 0, 4, 1, 4, 'Too many paper works and documents needee', 0, '2026-10-17 02:27:05'),
(12, NULL, 22, 'Inquiries on Admission Requirements', '2026-10-17', '2026-10-17', 'Citizen', '35-49', 'Male', 'Region IX - Zamboanga Peninsula', 1, 1, 1, 5, 4, 4, 4, 3, 5, 5, 5, 'approachable sila', 0, '2026-10-17 09:20:34'),
(13, NULL, 22, 'Inquiries on Admission Requirements', '2026-10-18', '2026-10-18', 'Citizen', '20-34', 'Male', 'Region IX - Zamboanga Peninsula', 1, 2, 1, 5, 4, 0, 5, 4, 5, 4, 2, '', 0, '2026-10-19 01:59:39'),
(14, 'admin', 8, 'Signing of College Clearance & Completion Forms (INC)', '2026-10-19', '2026-10-19', 'Government', '35-49', 'Prefer not to say', 'Region IX - Zamboanga Peninsula', 1, 1, 1, 5, 5, 4, 5, 0, 5, 4, 5, '', 0, '2026-10-19 08:10:55');

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `colleges`
--

INSERT INTO `colleges` (`id`, `name`) VALUES
(1, 'College of Information and Computing Sciences'),
(2, 'College of Arts, Humanities & Social Sciences'),
(3, 'College of Engineering Technology'),
(4, 'College of Teacher Education'),
(5, 'Institute of Technical Education'),
(6, 'College of Physical Education and Sports'),
(7, 'College of Maritime Education'),
(8, 'College of Law');

-- --------------------------------------------------------

--
-- Table structure for table `faculty_staff`
--

CREATE TABLE `faculty_staff` (
  `id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `office_id` int(11) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT 'default.jpg'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `recipient_type` enum('Admin','Office_Staff','User') NOT NULL,
  `recipient_id` varchar(50) NOT NULL COMMENT 'Can be Admin ID, Staff ID, or User ID',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `id` int(11) NOT NULL,
  `college_id` int(11) DEFAULT NULL,
  `office_name` varchar(255) NOT NULL,
  `office_description` text DEFAULT NULL,
  `office_type` enum('Academic','Administrative') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offices`
--

INSERT INTO `offices` (`id`, `college_id`, `office_name`, `office_description`, `office_type`, `created_at`) VALUES
(1, 1, 'CAHSS', 'College of Arts, Humanities & Social Sciences Room 103', 'Academic', '2026-10-14 07:17:31'),
(2, 2, 'CET', 'College of Engineering Technology', 'Academic', '2026-10-14 07:17:31'),
(3, 3, 'Department Of Physical Education', NULL, 'Academic', '2026-10-14 07:17:31'),
(4, 3, 'Professional Education Certificate', NULL, 'Academic', '2026-10-14 07:17:31'),
(5, 3, 'Senior High School Laboratory', NULL, 'Academic', '2026-10-14 07:17:31'),
(6, 4, 'Competency Assessment Training Center', NULL, 'Academic', '2026-10-14 07:17:31'),
(7, 4, 'ITE', 'Institute of Technical Education', 'Academic', '2026-10-14 07:17:31'),
(8, 6, 'CICS', 'College of Information and Computing Sciences', 'Academic', '2026-10-14 07:17:31'),
(9, 7, 'CME', 'College of Maritime Education', 'Academic', '2026-10-14 07:17:31'),
(10, 8, 'College of Law', NULL, 'Academic', '2026-10-14 07:17:31'),
(11, NULL, 'Curriculum and Program Development Office', NULL, 'Academic', '2026-10-14 07:17:31'),
(12, NULL, 'Extended Tertiary Education Equivalent and Accreditation Program', NULL, 'Academic', '2026-10-14 07:17:31'),
(13, NULL, 'External Program Delivery Unit', NULL, 'Academic', '2026-10-14 07:17:31'),
(14, NULL, 'Flexible Learning Office', NULL, 'Academic', '2026-10-14 07:17:31'),
(15, NULL, 'Instructional Resource Validation Office', NULL, 'Academic', '2026-10-14 07:17:31'),
(16, NULL, 'Licensure Examination Management Unit', NULL, 'Academic', '2026-10-14 07:17:31'),
(17, NULL, 'NSTP', NULL, 'Academic', '2026-10-14 07:17:31'),
(18, NULL, 'Office Of The Vice President Academic Affairs', NULL, 'Academic', '2026-10-14 07:17:31'),
(19, NULL, 'Public Employment Service Office', NULL, 'Academic', '2026-10-14 07:17:31'),
(20, NULL, 'School of Business Administration', NULL, 'Academic', '2026-10-14 07:17:31'),
(21, NULL, 'School of Governance', NULL, 'Academic', '2026-10-14 07:17:31'),
(22, NULL, 'Admission Office', 'ZPPSU Admissions Office', 'Administrative', '2026-10-14 07:17:31'),
(23, NULL, 'Bids and Awards Committee', NULL, 'Administrative', '2026-10-14 07:17:31'),
(25, NULL, 'COA Audit Team Office', NULL, 'Administrative', '2026-10-14 07:17:31'),
(26, NULL, 'Data Privacy Office', NULL, 'Administrative', '2026-10-14 07:17:31'),
(27, NULL, 'DRRMO', 'Disaster Risk Reduction Management Office of ZPPSU', 'Administrative', '2026-10-14 07:17:31'),
(28, NULL, 'General Service Unit', NULL, 'Administrative', '2026-10-14 07:17:31'),
(29, NULL, 'Guidance Office', NULL, 'Administrative', '2026-10-14 07:17:31'),
(30, NULL, 'Human Resources Management Unit', NULL, 'Administrative', '2026-10-14 07:17:31'),
(31, NULL, 'Information and Communication Technology Unit (ICTU)', NULL, 'Administrative', '2026-10-14 07:17:31'),
(32, NULL, 'Office of Alumni Affairs', NULL, 'Administrative', '2026-10-14 07:17:31'),
(33, NULL, 'Office of the Administrative Division', NULL, 'Administrative', '2026-10-14 07:17:31'),
(34, NULL, 'Office of The Assistant to the President', NULL, 'Administrative', '2026-10-14 07:17:31'),
(35, NULL, 'Office of the Finance Division', NULL, 'Administrative', '2026-10-14 07:17:31'),
(36, NULL, 'Office Of The University President', NULL, 'Administrative', '2026-10-14 07:17:31'),
(37, NULL, 'Office of the Vice President Administration and Finance', NULL, 'Administrative', '2026-10-14 07:17:31'),
(38, NULL, 'Planning and Programming Office', NULL, 'Administrative', '2026-10-14 07:17:31'),
(39, NULL, 'Project Development Management and Evaluation Office', NULL, 'Administrative', '2026-10-14 07:17:31'),
(40, NULL, 'Public Information Office', NULL, 'Administrative', '2026-10-14 07:17:31'),
(41, NULL, 'Quality Assurance Office', NULL, 'Administrative', '2026-10-14 07:17:31'),
(42, NULL, 'Records Unit', NULL, 'Administrative', '2026-10-14 07:17:31'),
(43, NULL, 'Registrar', 'University Registrar', 'Administrative', '2026-10-14 07:17:31'),
(44, NULL, 'Statistics Data Science', NULL, 'Administrative', '2026-10-14 07:17:31'),
(45, NULL, 'Supervising Administrative Officer', NULL, 'Administrative', '2026-10-14 07:17:31'),
(46, NULL, 'Supply Property Management Unit', NULL, 'Administrative', '2026-10-14 07:17:31'),
(47, NULL, 'Cash Unit', NULL, 'Administrative', '2026-10-14 11:55:13'),
(48, NULL, 'Office of Internationalization and External Linkages', NULL, 'Administrative', '2026-10-17 09:26:37');

-- --------------------------------------------------------

--
-- Table structure for table `office_classifications`
--

CREATE TABLE `office_classifications` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `office_classifications`
--

INSERT INTO `office_classifications` (`id`, `name`, `created_at`) VALUES
(2, 'Academic College / Institute', '2026-10-14 11:32:27'),
(3, 'Frontline Service', '2026-10-14 11:32:27'),
(4, 'Support Service', '2026-10-14 11:32:27'),
(5, 'Academic', '2026-10-14 11:43:01'),
(6, 'Administrative', '2026-10-14 11:43:01');

-- --------------------------------------------------------

--
-- Table structure for table `resolution_updates`
--

CREATE TABLE `resolution_updates` (
  `id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `author_id` varchar(50) DEFAULT NULL,
  `author_name` varchar(255) DEFAULT NULL,
  `update_type` varchar(40) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `security_logs`
--

INSERT INTO `security_logs` (`log_id`, `user_id`, `event_type`, `ip_address`, `device_info`, `created_at`) VALUES
(1, 'HES-683', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-17 07:50:05'),
(2, 'HES-683', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-17 07:50:36'),
(3, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-17 07:50:40'),
(4, 'admin', 'LOGIN_FAILED', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 07:52:07'),
(5, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 07:52:17'),
(6, 'admin', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 08:32:20'),
(7, 'HES-683', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 08:32:27'),
(8, 'HES-683', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 08:32:39'),
(9, 'HES-683', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 08:32:42'),
(10, 'HES-683', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 08:32:48'),
(11, 'HES-683', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 08:32:52'),
(12, 'HES-683', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 08:32:56'),
(13, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 08:33:15'),
(14, 'admin', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 08:33:58'),
(15, 'admin', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-17 08:36:12'),
(16, 'HES-683', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-17 08:36:17'),
(17, 'HES-683', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-17 08:36:26'),
(18, 'HES-683', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 08:40:19'),
(19, 'HES-683', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 08:40:25'),
(20, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (Linux; Android 9; Omega X) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.0.0 Mobile Safari/537.36', '2026-10-17 08:43:15'),
(21, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-17 09:19:15'),
(22, 'admin', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2026-10-17 09:20:48'),
(23, 'admin', 'LOGIN_SUCCESS', '49.149.106.57', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-17 09:22:05'),
(24, 'joeanmark', 'LOGIN_FAILED', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 09:31:33'),
(25, 'HES-683', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 09:31:36'),
(26, 'HES-683', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 09:33:26'),
(27, 'admin', 'LOGIN_FAILED', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 09:33:32'),
(28, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 09:33:43'),
(29, 'admin', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (Linux; Android 9; Omega X) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.0.0 Mobile Safari/537.36', '2026-10-17 09:40:54'),
(30, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-17 22:10:12'),
(31, 'admin', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 23:55:54'),
(32, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-17 23:56:46'),
(33, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0.1 Mobile/15E148 Safari/604.1', '2026-10-18 22:27:03'),
(34, 'admin', 'LOGIN_SUCCESS', '110.54.158.137', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-19 00:59:28'),
(35, 'admin', 'LOGIN_FAILED', '180.193.199.147', 'Mozilla/5.0 (Linux; Android 14; ALT-LX2 Build/HONORALT-L32; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/583.0.0.29.95;]', '2026-10-19 02:00:01'),
(36, 'admin', 'LOGIN_SUCCESS', '180.193.199.147', 'Mozilla/5.0 (Linux; Android 14; ALT-LX2 Build/HONORALT-L32; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/583.0.0.29.95;]', '2026-10-19 02:00:16'),
(37, 'admin', 'LOGOUT', '112.198.99.36', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2026-10-19 12:35:03'),
(38, 'admin', 'LOGIN_SUCCESS', '112.198.99.36', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2026-10-19 12:40:02'),
(39, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2026-10-19 15:09:59'),
(40, 'admin', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-19 15:29:26'),
(41, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2026-10-19 15:38:54'),
(42, 'admin', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-20 11:01:07'),
(43, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-20 11:54:30'),
(44, 'faithandres35@gmail.com', 'LOGIN_FAILED', '180.190.45.244', 'Mozilla/5.0 (Linux; Android 11; RMX2195 Build/RKQ1.201217.002; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/573.0.0.44.88;]', '2026-10-20 12:09:45'),
(45, 'pungutanusman@gmail.com', 'LOGIN_FAILED', '49.145.195.159', 'Mozilla/5.0 (Linux; Android 13; Infinix X6528 Build/TP1A.220624.014; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/583.0.0.29.95;]', '2026-10-20 12:10:00'),
(46, '2367784', 'LOGIN_FAILED', '180.190.45.244', 'Mozilla/5.0 (Linux; Android 11; RMX2195 Build/RKQ1.201217.002; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/573.0.0.44.88;]', '2026-10-20 12:10:01'),
(47, 'admin', 'LOGIN_SUCCESS', '49.149.96.76', 'Mozilla/5.0 (Linux; Android 11; RMX3195 Build/RP1A.200720.011; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/583.0.0.29.95;]', '2026-10-20 12:10:07'),
(48, '23-67784', 'LOGIN_FAILED', '180.190.45.244', 'Mozilla/5.0 (Linux; Android 11; RMX2195 Build/RKQ1.201217.002; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/573.0.0.44.88;]', '2026-10-20 12:10:58'),
(49, '24-00630', 'LOGIN_FAILED', '49.149.104.163', 'Mozilla/5.0 (Linux; Android 16; BRP-NX1 Build/HONORBRP-N21; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/583.0.0.29.95;]', '2026-10-20 12:12:32'),
(50, 'Mobienmudjah05@gmail.com', 'LOGIN_FAILED', '49.149.104.163', 'Mozilla/5.0 (Linux; Android 16; BRP-NX1 Build/HONORBRP-N21; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/583.0.0.29.95;]', '2026-10-20 12:12:56'),
(51, '24-00630', 'LOGIN_FAILED', '49.149.104.163', 'Mozilla/5.0 (Linux; Android 16; BRP-NX1 Build/HONORBRP-N21; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/583.0.0.29.95;]', '2026-10-20 12:13:15'),
(52, 'admin', 'LOGIN_SUCCESS', '180.194.206.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 27_0_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/24A446 [FBAN/FBIOS;FBAV/583.0.0.32.105;FBBV/1090090915;FBDV/iPhone18,2;FBMD/iPhone;FBSN/iOS;FBSV/27.0.1;FBSS/3;FBCR/;FBID/phone;FBLC/en_PH;FBOP/80]', '2026-10-20 12:14:58'),
(53, '23467784', 'LOGIN_FAILED', '180.190.45.244', 'Mozilla/5.0 (Linux; Android 11; RMX2195 Build/RKQ1.201217.002; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/573.0.0.44.88;]', '2026-10-20 12:15:38'),
(54, 'admin', 'LOGIN_SUCCESS', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 12:19:58'),
(55, 'admin', 'LOGOUT', '131.226.115.29', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', '2026-10-20 12:39:42'),
(56, 'admin', 'LOGIN_SUCCESS', '131.226.115.29', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', '2026-10-20 12:40:00'),
(57, 'admin', 'LOGOUT', '180.194.206.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-20 12:44:38'),
(58, 'Admin', 'LOGIN_FAILED', '112.198.162.157', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/22H374 [FBAN/FBIOS;FBAV/583.0.0.32.105;FBBV/1090090915;FBDV/iPhone11,8;FBMD/iPhone;FBSN/iOS;FBSV/18.7.10;FBSS/2;FBCR/;FBID/phone;FBLC/en_AU@rg=phzzzz;FBOP/80]', '2026-10-20 13:01:03'),
(59, 'admin', 'LOGIN_SUCCESS', '112.198.162.157', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/22H374 [FBAN/FBIOS;FBAV/583.0.0.32.105;FBBV/1090090915;FBDV/iPhone11,8;FBMD/iPhone;FBSN/iOS;FBSV/18.7.10;FBSS/2;FBCR/;FBID/phone;FBLC/en_AU@rg=phzzzz;FBOP/80]', '2026-10-20 13:02:22'),
(60, 'admin', 'LOGIN_SUCCESS', '49.145.192.177', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:158.0) Gecko/20100101 Firefox/158.0', '2026-10-20 13:53:43'),
(61, 'admin', 'LOGIN_SUCCESS', '138.84.108.194', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-20 14:06:50'),
(62, 'admin', 'LOGIN_SUCCESS', '143.44.192.86', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Mobile Safari/537.36', '2026-10-20 14:33:44'),
(63, '24-00475', 'LOGIN_FAILED', '131.226.114.23', 'Mozilla/5.0 (Linux; Android 15; Infinix X6880 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36[FBAN/EMA;FBLC/en_US;FBAV/533.0.0.11.105;FBCX/modulariab;]', '2026-10-20 15:04:58'),
(64, '24-00475', 'LOGIN_FAILED', '131.226.114.23', 'Mozilla/5.0 (Linux; Android 15; Infinix X6880 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36[FBAN/EMA;FBLC/en_US;FBAV/533.0.0.11.105;FBCX/modulariab;]', '2026-10-20 15:05:16'),
(65, 'tacbianangraceann75@gmail.com', 'LOGIN_FAILED', '131.226.114.23', 'Mozilla/5.0 (Linux; Android 15; Infinix X6880 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36[FBAN/EMA;FBLC/en_US;FBAV/533.0.0.11.105;FBCX/modulariab;]', '2026-10-20 15:05:36'),
(66, '24-00475', 'LOGOUT', '131.226.114.23', 'Mozilla/5.0 (Linux; Android 15; Infinix X6880 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36[FBAN/EMA;FBLC/en_US;FBAV/533.0.0.11.105;FBCX/modulariab;]', '2026-10-20 15:06:57'),
(67, '24-00475', 'LOGIN_SUCCESS', '131.226.114.23', 'Mozilla/5.0 (Linux; Android 15; Infinix X6880 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36[FBAN/EMA;FBLC/en_US;FBAV/533.0.0.11.105;FBCX/modulariab;]', '2026-10-20 15:11:36'),
(68, 'admin', 'LOGOUT', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 15:20:46'),
(69, 'vanna31606@gmail.com', 'LOGIN_FAILED', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 15:41:02'),
(70, 'vanna31606@gmail.com', 'LOGIN_FAILED', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 15:41:27'),
(71, '879', 'LOGOUT', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 15:57:06'),
(72, 'admin', 'LOGIN_SUCCESS', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 15:57:24'),
(73, 'admin', 'LOGOUT', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:00:23'),
(74, '879', 'LOGIN_SUCCESS', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:04:21'),
(75, '879', 'LOGOUT', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:11:37'),
(76, 'admin', 'LOGIN_FAILED', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:11:46'),
(77, 'admin', 'LOGIN_FAILED', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:11:56'),
(78, 'admin', 'LOGIN_FAILED', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:12:04'),
(79, 'admin', 'LOGIN_SUCCESS', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:12:15'),
(80, 'admin', 'LOGOUT', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:17:00'),
(81, 'admin', 'LOGIN_SUCCESS', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:18:16'),
(82, 'admin', 'LOGOUT', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:28:03'),
(83, 'vanna31606@gmail.com', 'LOGIN_FAILED', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:28:18'),
(84, 'w', 'LOGIN_FAILED', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:28:26'),
(85, 'rh', 'LOGIN_FAILED', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:28:31'),
(86, 'ev', 'LOGIN_FAILED', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:28:36'),
(87, 'ehje', 'LOGIN_FAILED', '131.226.115.29', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-20 16:28:40'),
(88, '24-00504', 'LOGIN_FAILED', '203.9.195.163', 'Mozilla/5.0 (Linux; Android 15; LGN-LX2 Build/HONORLGN-L42; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/155.0.8059.43 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/583.0.0.29.95;]', '2026-10-20 16:35:34'),
(89, 'admin', 'LOGIN_SUCCESS', '49.149.102.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-21 00:17:54'),
(90, 'admin', 'LOGOUT', '49.149.102.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-21 00:18:00'),
(91, 'admin', 'LOGIN_SUCCESS', '49.149.102.215', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-21 00:18:42'),
(92, 'admin', 'LOGOUT', '49.149.102.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2026-10-21 00:20:18'),
(93, 'goritjudith@gmail.com', 'LOGIN_FAILED', '49.149.102.24', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Mobile Safari/537.36', '2026-10-21 00:42:38');
INSERT INTO `security_logs` (`log_id`, `user_id`, `event_type`, `ip_address`, `device_info`, `created_at`) VALUES
(94, 'admin', 'LOGIN_SUCCESS', '180.194.210.25', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Mobile Safari/537.36', '2026-10-21 02:07:36'),
(95, '879', 'LOGIN_SUCCESS', '131.226.114.92', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-21 02:10:04'),
(96, '879', 'LOGOUT', '131.226.114.92', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-21 02:16:21'),
(97, 'admin', 'LOGIN_SUCCESS', '131.226.114.92', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-21 02:16:29'),
(98, 'admin', 'LOGOUT', '131.226.114.92', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-21 02:21:05'),
(99, '879', 'LOGIN_SUCCESS', '131.226.114.92', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-21 02:21:21'),
(100, '879', 'LOGOUT', '131.226.114.92', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-21 02:21:25'),
(101, 'admin', 'LOGIN_SUCCESS', '131.226.114.92', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-21 02:24:14'),
(102, 'admin', 'LOGOUT', '131.226.114.92', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-21 02:27:10'),
(103, '879', 'LOGIN_SUCCESS', '131.226.114.92', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-21 02:27:28'),
(104, '879', 'LOGOUT', '131.226.114.92', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-21 02:27:55'),
(105, 'admin', 'LOGIN_SUCCESS', '131.226.114.92', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-21 02:28:04');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `classification` enum('Simple','Complex','Highly Technical') NOT NULL DEFAULT 'Simple',
  `processing_time_days` int(11) DEFAULT 3 COMMENT 'Standard processing time in days based on ARTA',
  `fee_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fee_notes` varchar(255) DEFAULT NULL,
  `legal_basis` varchar(255) DEFAULT NULL,
  `charter_notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `office_id`, `service_name`, `classification`, `processing_time_days`, `fee_amount`, `fee_notes`, `legal_basis`, `charter_notes`, `is_active`, `updated_at`) VALUES
(8, 47, 'Application for College Admission / Entrance Exam', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(9, 22, 'Issuance of Test Permit / Notice of Admission', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(10, 22, 'Submission & Verification of Admission Credentials', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(11, 22, 'Inquiries on Admission Requirements', 'Simple', 3, '0.00', NULL, NULL, NULL, 0, '2026-10-20 14:47:50'),
(12, 47, 'Payment of Tuition & Institutional Fees', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(13, 47, 'Payment for Transcript of Records (TOR) & Certifications', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(14, 47, 'Claiming of Checks / Student Allowances / Honoraria', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(15, 47, 'Issuance of Official Receipts (OR)', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(16, 43, 'Issuance of Official Transcript of Records (TOR)', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(17, 43, 'Issuance of Certificate of Registration (COR) / Enrollment', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(18, 43, 'Issuance of Honorable Dismissal / Transfer Credentials', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(19, 43, 'Authentication & Verification of Academic Records (CAV)', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(20, 43, 'Application for Graduation & University Clearance', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(21, 43, 'Issuance of Diploma & Duplicate Credentials', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(22, 23, 'Sale & Issuance of Bidding Documents', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(23, 23, 'Submission and Opening of Bids', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(24, 23, 'Inquiries on Procurement & Bid Opportunities', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(25, 8, 'Academic Advising & Subject Enlistment', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(26, 8, 'Adding, Dropping, & Changing of Subjects', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(27, 8, 'Signing of College Clearance & Completion Forms (INC)', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(28, 8, 'Endorsement for Practicum / On-the-Job Training (OJT)', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(29, 2, 'Academic Advising & Subject Enlistment', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(30, 2, 'Adding, Dropping, & Changing of Subjects', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(31, 2, 'Signing of College Clearance & Completion Forms (INC)', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(32, 2, 'Laboratory Reservation & Equipment Borrowing', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(33, 1, 'Academic Advising & Subject Enlistment', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(34, 1, 'Adding, Dropping, & Changing of Subjects', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(35, 1, 'Signing of College Clearance & Completion Forms (INC)', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(36, 9, 'Academic Advising & Subject Enlistment', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(37, 9, 'Adding, Dropping, & Changing of Subjects', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(38, 9, 'Signing of College Clearance & Completion Forms (INC)', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(39, 25, 'Submission of Financial Documents & Vouchers', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(40, 25, 'Compliance Verification & Audit Inquiries', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(42, 29, 'Psychological Testing & Counseling Services', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(43, 29, 'Student Exit Interview & Clearance Signing', 'Simple', 3, '0.00', NULL, NULL, NULL, 1, '2026-10-17 00:03:04'),
(44, 43, 'Issuance of Certificate of Good Moral Character', 'Simple', 1, '50.00', 'â‚±50.00 per copy', 'University Student Handbook Section 4', 'Expedited processing is available for urgent scholarship applications.', 1, '2026-10-17 00:37:12');

-- --------------------------------------------------------

--
-- Table structure for table `service_requirements`
--

CREATE TABLE `service_requirements` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_requirements`
--

INSERT INTO `service_requirements` (`id`, `service_id`, `document_name`, `is_mandatory`, `sort_order`) VALUES
(1, 44, 'Duly filled-out Request Form |', 1, 0),
(2, 44, 'Valid University ID', 1, 1),
(3, 44, 'Official Receipt of Payment', 1, 2),
(4, 33, 'Duly accomplished request or application form', 1, 1),
(5, 34, 'Duly accomplished request or application form', 1, 1),
(6, 35, 'Duly accomplished request or application form', 1, 1),
(7, 29, 'Duly accomplished request or application form', 1, 1),
(8, 30, 'Duly accomplished request or application form', 1, 1),
(9, 31, 'Duly accomplished request or application form', 1, 1),
(10, 32, 'Duly accomplished request or application form', 1, 1),
(11, 25, 'Duly accomplished request or application form', 1, 1),
(12, 26, 'Duly accomplished request or application form', 1, 1),
(13, 27, 'Duly accomplished request or application form', 1, 1),
(14, 28, 'Duly accomplished request or application form', 1, 1),
(15, 36, 'Duly accomplished request or application form', 1, 1),
(16, 37, 'Duly accomplished request or application form', 1, 1),
(17, 38, 'Duly accomplished request or application form', 1, 1),
(18, 9, 'Duly accomplished request or application form', 1, 1),
(19, 10, 'Duly accomplished request or application form', 1, 1),
(20, 11, 'Duly accomplished request or application form', 1, 1),
(21, 22, 'Duly accomplished request or application form', 1, 1),
(22, 23, 'Duly accomplished request or application form', 1, 1),
(23, 24, 'Duly accomplished request or application form', 1, 1),
(24, 39, 'Duly accomplished request or application form', 1, 1),
(25, 40, 'Duly accomplished request or application form', 1, 1),
(26, 42, 'Duly accomplished request or application form', 1, 1),
(27, 43, 'Duly accomplished request or application form', 1, 1),
(28, 16, 'Duly accomplished request or application form', 1, 1),
(29, 17, 'Duly accomplished request or application form', 1, 1),
(30, 18, 'Duly accomplished request or application form', 1, 1),
(31, 19, 'Duly accomplished request or application form', 1, 1),
(32, 20, 'Duly accomplished request or application form', 1, 1),
(33, 21, 'Duly accomplished request or application form', 1, 1),
(34, 8, 'Duly accomplished request or application form', 1, 1),
(35, 12, 'Duly accomplished request or application form', 1, 1),
(36, 13, 'Duly accomplished request or application form', 1, 1),
(37, 14, 'Duly accomplished request or application form', 1, 1),
(38, 15, 'Duly accomplished request or application form', 1, 1),
(67, 33, 'Valid University ID or Government-issued ID', 1, 2),
(68, 34, 'Valid University ID or Government-issued ID', 1, 2),
(69, 35, 'Valid University ID or Government-issued ID', 1, 2),
(70, 29, 'Valid University ID or Government-issued ID', 1, 2),
(71, 30, 'Valid University ID or Government-issued ID', 1, 2),
(72, 31, 'Valid University ID or Government-issued ID', 1, 2),
(73, 32, 'Valid University ID or Government-issued ID', 1, 2),
(74, 25, 'Valid University ID or Government-issued ID', 1, 2),
(75, 26, 'Valid University ID or Government-issued ID', 1, 2),
(76, 27, 'Valid University ID or Government-issued ID', 1, 2),
(77, 28, 'Valid University ID or Government-issued ID', 1, 2),
(78, 36, 'Valid University ID or Government-issued ID', 1, 2),
(79, 37, 'Valid University ID or Government-issued ID', 1, 2),
(80, 38, 'Valid University ID or Government-issued ID', 1, 2),
(81, 9, 'Valid University ID or Government-issued ID', 1, 2),
(82, 10, 'Valid University ID or Government-issued ID', 1, 2),
(83, 11, 'Valid University ID or Government-issued ID', 1, 2),
(84, 22, 'Valid University ID or Government-issued ID', 1, 2),
(85, 23, 'Valid University ID or Government-issued ID', 1, 2),
(86, 24, 'Valid University ID or Government-issued ID', 1, 2),
(87, 39, 'Valid University ID or Government-issued ID', 1, 2),
(88, 40, 'Valid University ID or Government-issued ID', 1, 2),
(89, 42, 'Valid University ID or Government-issued ID', 1, 2),
(90, 43, 'Valid University ID or Government-issued ID', 1, 2),
(91, 16, 'Valid University ID or Government-issued ID', 1, 2),
(92, 17, 'Valid University ID or Government-issued ID', 1, 2),
(93, 18, 'Valid University ID or Government-issued ID', 1, 2),
(94, 19, 'Valid University ID or Government-issued ID', 1, 2),
(95, 20, 'Valid University ID or Government-issued ID', 1, 2),
(96, 21, 'Valid University ID or Government-issued ID', 1, 2),
(97, 8, 'Valid University ID or Government-issued ID', 1, 2),
(98, 12, 'Valid University ID or Government-issued ID', 1, 2),
(99, 13, 'Valid University ID or Government-issued ID', 1, 2),
(100, 14, 'Valid University ID or Government-issued ID', 1, 2),
(101, 15, 'Valid University ID or Government-issued ID', 1, 2),
(130, 33, 'Official Receipt of Payment (if applicable)', 0, 3),
(131, 34, 'Official Receipt of Payment (if applicable)', 0, 3),
(132, 35, 'Official Receipt of Payment (if applicable)', 0, 3),
(133, 29, 'Official Receipt of Payment (if applicable)', 0, 3),
(134, 30, 'Official Receipt of Payment (if applicable)', 0, 3),
(135, 31, 'Official Receipt of Payment (if applicable)', 0, 3),
(136, 32, 'Official Receipt of Payment (if applicable)', 0, 3),
(137, 25, 'Official Receipt of Payment (if applicable)', 0, 3),
(138, 26, 'Official Receipt of Payment (if applicable)', 0, 3),
(139, 27, 'Official Receipt of Payment (if applicable)', 0, 3),
(140, 28, 'Official Receipt of Payment (if applicable)', 0, 3),
(141, 36, 'Official Receipt of Payment (if applicable)', 0, 3),
(142, 37, 'Official Receipt of Payment (if applicable)', 0, 3),
(143, 38, 'Official Receipt of Payment (if applicable)', 0, 3),
(144, 9, 'Official Receipt of Payment (if applicable)', 0, 3),
(145, 10, 'Official Receipt of Payment (if applicable)', 0, 3),
(146, 11, 'Official Receipt of Payment (if applicable)', 0, 3),
(147, 22, 'Official Receipt of Payment (if applicable)', 0, 3),
(148, 23, 'Official Receipt of Payment (if applicable)', 0, 3),
(149, 24, 'Official Receipt of Payment (if applicable)', 0, 3),
(150, 39, 'Official Receipt of Payment (if applicable)', 0, 3),
(151, 40, 'Official Receipt of Payment (if applicable)', 0, 3),
(152, 42, 'Official Receipt of Payment (if applicable)', 0, 3),
(153, 43, 'Official Receipt of Payment (if applicable)', 0, 3),
(154, 16, 'Official Receipt of Payment (if applicable)', 0, 3),
(155, 17, 'Official Receipt of Payment (if applicable)', 0, 3),
(156, 18, 'Official Receipt of Payment (if applicable)', 0, 3),
(157, 19, 'Official Receipt of Payment (if applicable)', 0, 3),
(158, 20, 'Official Receipt of Payment (if applicable)', 0, 3),
(159, 21, 'Official Receipt of Payment (if applicable)', 0, 3),
(160, 44, 'Official Receipt of Payment (if applicable)', 0, 3),
(161, 8, 'Official Receipt of Payment (if applicable)', 0, 3),
(162, 12, 'Official Receipt of Payment (if applicable)', 0, 3),
(163, 13, 'Official Receipt of Payment (if applicable)', 0, 3),
(164, 14, 'Official Receipt of Payment (if applicable)', 0, 3),
(165, 15, 'Official Receipt of Payment (if applicable)', 0, 3);

-- --------------------------------------------------------

--
-- Table structure for table `service_steps`
--

CREATE TABLE `service_steps` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `step_number` int(11) NOT NULL,
  `step_description` text NOT NULL,
  `responsible_person` varchar(255) DEFAULT NULL,
  `estimated_minutes` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_steps`
--

INSERT INTO `service_steps` (`id`, `service_id`, `step_number`, `step_description`, `responsible_person`, `estimated_minutes`) VALUES
(1, 44, 1, 'Submit the accomplished request form and present University ID at Window 2.', 'OSA Clerk', 3),
(2, 44, 2, 'Pay the certification fee at the University Cashier', 'Cashier', 5),
(3, 44, 3, 'Present the Official Receipt to Window 2 to claim the printed certificate', 'OSA Clerk', 5),
(4, 33, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(5, 34, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(6, 35, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(7, 29, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(8, 30, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(9, 31, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(10, 32, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(11, 25, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(12, 26, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(13, 27, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(14, 28, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(15, 36, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(16, 37, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(17, 38, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(18, 9, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(19, 10, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(20, 11, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(21, 22, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(22, 23, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(23, 24, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(24, 39, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(25, 40, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(26, 42, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(27, 43, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(28, 16, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(29, 17, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(30, 18, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(31, 19, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(32, 20, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(33, 21, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(34, 8, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(35, 12, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(36, 13, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(37, 14, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(38, 15, 1, 'Client submits the required documents at the receiving window.', 'Receiving Clerk', 5),
(67, 33, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(68, 34, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(69, 35, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(70, 29, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(71, 30, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(72, 31, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(73, 32, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(74, 25, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(75, 26, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(76, 27, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(77, 28, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(78, 36, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(79, 37, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(80, 38, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(81, 9, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(82, 10, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(83, 11, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(84, 22, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(85, 23, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(86, 24, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(87, 39, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(88, 40, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(89, 42, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(90, 43, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(91, 16, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(92, 17, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(93, 18, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(94, 19, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(95, 20, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(96, 21, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(97, 8, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(98, 12, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(99, 13, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(100, 14, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(101, 15, 2, 'Staff processes the request and verifies the submitted records.', 'Processing Officer', 15),
(130, 33, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(131, 34, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(132, 35, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(133, 29, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(134, 30, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(135, 31, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(136, 32, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(137, 25, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(138, 26, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(139, 27, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(140, 28, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(141, 36, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(142, 37, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(143, 38, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(144, 9, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(145, 10, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(146, 11, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(147, 22, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(148, 23, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(149, 24, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(150, 39, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(151, 40, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(152, 42, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(153, 43, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(154, 16, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(155, 17, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(156, 18, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(157, 19, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(158, 20, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(159, 21, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(160, 8, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(161, 12, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(162, 13, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(163, 14, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5),
(164, 15, 3, 'Client receives the requested document or service completion.', 'Releasing Clerk', 5);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','email','textarea','boolean','select') NOT NULL DEFAULT 'text',
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `setting_options` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `setting_options`, `updated_at`, `updated_by`) VALUES
('active_semester', 'First Semester', 'select', 'general', '[\"First Semester\",\"Second Semester\",\"Summer\"]', '2026-10-17 07:44:17', NULL),
('arta_low_rating_threshold', '2.0', 'number', 'arta', NULL, '2026-10-17 07:44:17', NULL),
('arta_min_responses_for_ranking', '3', 'number', 'arta', NULL, '2026-10-17 07:44:17', NULL),
('arta_satisfaction_target', '80', 'number', 'arta', NULL, '2026-10-17 07:44:17', NULL),
('campus_location', 'R.T. Lim Boulevard, Zamboanga City', 'text', 'general', NULL, '2026-10-17 07:44:17', NULL),
('contact_email', 'csm@zppsu.edu.ph', 'email', 'general', NULL, '2026-10-17 07:44:17', NULL),
('default_sla_days', '3', 'number', 'arta', NULL, '2026-10-17 07:44:17', NULL),
('force_password_reset_days', '90', 'number', 'security', NULL, '2026-10-17 07:44:17', NULL),
('maintenance_message', 'The CSM Portal is currently undergoing scheduled maintenance. Please check back shortly.', 'textarea', 'maintenance', NULL, '2026-10-17 07:44:17', NULL),
('maintenance_mode', '0', 'boolean', 'maintenance', NULL, '2026-10-17 07:44:17', NULL),
('max_daily_feedback', '10', 'number', 'arta', NULL, '2026-10-17 07:44:17', NULL),
('max_login_attempts', '5', 'number', 'security', NULL, '2026-10-17 07:44:17', NULL),
('notify_email_enabled', '0', 'boolean', 'notifications', NULL, '2026-10-17 07:44:17', NULL),
('notify_low_rating', '1', 'boolean', 'notifications', NULL, '2026-10-17 07:44:17', NULL),
('notify_new_feedback', '1', 'boolean', 'notifications', NULL, '2026-10-17 07:44:17', NULL),
('notify_resolution_overdue', '1', 'boolean', 'notifications', NULL, '2026-10-17 07:44:17', NULL),
('password_min_length', '6', 'number', 'security', NULL, '2026-10-17 07:44:17', NULL),
('portal_name', 'ZPPSU Client Satisfaction Measurement', 'text', 'general', NULL, '2026-10-17 07:44:17', NULL),
('resolution_reminder_days', '7', 'number', 'notifications', NULL, '2026-10-17 07:44:17', NULL),
('school_year', '2026-2027', 'text', 'general', NULL, '2026-10-17 07:44:17', NULL),
('session_timeout_minutes', '120', 'number', 'security', NULL, '2026-10-17 07:44:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sla_reviews`
--

CREATE TABLE `sla_reviews` (
  `id` int(11) NOT NULL,
  `feedback_id` int(11) NOT NULL,
  `reviewed_by` varchar(50) DEFAULT NULL,
  `outcome` enum('Escalated','Acknowledged','False Positive') NOT NULL,
  `notes` text DEFAULT NULL,
  `action_plan_id` int(11) DEFAULT NULL COMMENT 'Set when outcome = Escalated',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suspicious_activity`
--

CREATE TABLE `suspicious_activity` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suspicious_activity`
--

INSERT INTO `suspicious_activity` (`id`, `username`, `ip_address`, `reason`, `created_at`) VALUES
(1, 'admin', '112.198.99.102', 'Incorrect password attempt.', '2026-10-14 08:22:02'),
(2, 'admin', '112.198.99.102', 'Incorrect password attempt.', '2026-10-14 08:22:14'),
(3, 'admin', '112.198.99.102', 'Incorrect password attempt.', '2026-10-14 11:20:50'),
(4, 'admin', '112.198.99.102', 'Incorrect password attempt.', '2026-10-14 12:21:54'),
(5, 'admin', '49.149.102.215', 'Incorrect password attempt.', '2026-10-15 08:55:50'),
(6, 'joeanmark', '180.194.206.247', 'Non-existent user login attempt.', '2026-10-17 03:42:08'),
(7, 'jmhiolin@zppsu.edu.ph', '180.194.206.247', 'Incorrect password attempt.', '2026-10-17 03:51:27'),
(8, 'jmhiolin@zppsu.edu.ph', '180.194.206.247', 'Incorrect password attempt.', '2026-10-17 03:51:36'),
(9, 'admin', '180.194.206.247', 'Incorrect password attempt.', '2026-10-17 04:04:10'),
(10, 'admin', '180.194.206.247', 'Incorrect password attempt.', '2026-10-17 04:04:19'),
(11, 'admin', '180.194.206.247', 'Incorrect password attempt.', '2026-10-17 04:04:26'),
(12, 'admin', '180.194.206.247', 'Incorrect password attempt.', '2026-10-17 04:04:34'),
(13, 'admin', '180.194.206.247', 'Incorrect password attempt.', '2026-10-17 04:10:08'),
(14, 'admin', '180.194.206.215', 'Incorrect password attempt.', '2026-10-17 07:52:07'),
(15, 'joeanmark', '180.194.206.215', 'Non-existent user login attempt.', '2026-10-17 09:31:33'),
(16, 'admin', '180.194.206.215', 'Incorrect password attempt.', '2026-10-17 09:33:32'),
(17, 'admin', '180.193.199.147', 'Incorrect password attempt.', '2026-10-19 02:00:01'),
(18, 'faithandres35@gmail.com', '180.190.45.244', 'Non-existent user login attempt.', '2026-10-20 12:09:45'),
(19, 'pungutanusman@gmail.com', '49.145.195.159', 'Non-existent user login attempt.', '2026-10-20 12:10:00'),
(20, '2367784', '180.190.45.244', 'Non-existent user login attempt.', '2026-10-20 12:10:01'),
(21, '23-67784', '180.190.45.244', 'Non-existent user login attempt.', '2026-10-20 12:10:58'),
(22, '24-00630', '49.149.104.163', 'Non-existent user login attempt.', '2026-10-20 12:12:32'),
(23, 'Mobienmudjah05@gmail.com', '49.149.104.163', 'Non-existent user login attempt.', '2026-10-20 12:12:56'),
(24, '24-00630', '49.149.104.163', 'Non-existent user login attempt.', '2026-10-20 12:13:15'),
(25, '23467784', '180.190.45.244', 'Non-existent user login attempt.', '2026-10-20 12:15:38'),
(26, 'Admin', '112.198.162.157', 'Incorrect password attempt.', '2026-10-20 13:01:03'),
(27, '24-00475', '131.226.114.23', 'Non-existent user login attempt.', '2026-10-20 15:04:58'),
(28, '24-00475', '131.226.114.23', 'Non-existent user login attempt.', '2026-10-20 15:05:16'),
(29, 'tacbianangraceann75@gmail.com', '131.226.114.23', 'Non-existent user login attempt.', '2026-10-20 15:05:36'),
(30, 'vanna31606@gmail.com', '131.226.115.29', 'Non-existent user login attempt.', '2026-10-20 15:41:02'),
(31, 'vanna31606@gmail.com', '131.226.115.29', 'Non-existent user login attempt.', '2026-10-20 15:41:27'),
(32, 'admin', '131.226.115.29', 'Incorrect password attempt.', '2026-10-20 16:11:46'),
(33, 'admin', '131.226.115.29', 'Incorrect password attempt.', '2026-10-20 16:11:56'),
(34, 'admin', '131.226.115.29', 'Incorrect password attempt.', '2026-10-20 16:12:04'),
(35, 'vanna31606@gmail.com', '131.226.115.29', 'Incorrect password attempt.', '2026-10-20 16:28:18'),
(36, 'w', '131.226.115.29', 'Non-existent user login attempt.', '2026-10-20 16:28:26'),
(37, 'rh', '131.226.115.29', 'Non-existent user login attempt.', '2026-10-20 16:28:31'),
(38, 'ev', '131.226.115.29', 'Non-existent user login attempt.', '2026-10-20 16:28:36'),
(39, 'ehje', '131.226.115.29', 'Non-existent user login attempt.', '2026-10-20 16:28:40'),
(40, 'RATE_LIMIT', '131.226.115.29', 'Brute-force protection triggered: 5 failures in 10 min.', '2026-10-20 16:28:44'),
(41, 'RATE_LIMIT', '131.226.115.29', 'Brute-force protection triggered: 6 failures in 10 min.', '2026-10-20 16:29:50'),
(42, 'RATE_LIMIT', '131.226.115.29', 'Brute-force protection triggered: 7 failures in 10 min.', '2026-10-20 16:30:10'),
(43, '24-00504', '203.9.195.163', 'Non-existent user login attempt.', '2026-10-20 16:35:34'),
(44, 'RATE_LIMIT', '131.226.115.29', 'Brute-force protection triggered: 8 failures in 10 min.', '2026-10-20 16:35:56'),
(45, 'RATE_LIMIT', '131.226.115.29', 'Brute-force protection triggered: 9 failures in 10 min.', '2026-10-20 16:36:34'),
(46, 'goritjudith@gmail.com', '49.149.102.24', 'Non-existent user login attempt.', '2026-10-21 00:42:38');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_type` enum('Admin','Staff') NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL COMMENT 'e.g., EXPORT_REPORT, UPDATE_OFFICE, DELETE_USER',
  `description` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(50) NOT NULL COMMENT 'Can be Student Number, Employee ID, or Guest Token',
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `internal_user_type` enum('student','zppsu_employee','external_client','admin') NOT NULL DEFAULT 'student',
  `office_id` int(11) DEFAULT NULL,
  `client_type` enum('Citizen','Business','Government') NOT NULL DEFAULT 'Citizen',
  `age_group` varchar(20) DEFAULT NULL,
  `sex` enum('Male','Female','Prefer not to say') DEFAULT NULL,
  `region` varchar(100) DEFAULT 'Region IX',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `internal_user_type`, `office_id`, `client_type`, `age_group`, `sex`, `region`, `created_at`) VALUES
('23467784', 'Andres, Faith Ciello C.', 'faithandres35@gmail.com', '$2y$12$UsPu864TqA.1YMDv8Vh.3OyopFiKRG6jmav3SwEiD1IQK4dC0UeLi', 'student', NULL, 'Citizen', '20-34', 'Female', 'Region IX - Zamboanga Peninsula', '2026-10-20 12:17:14'),
('24-00430', 'Gorit, Judith D.', 'goritjudith@gmail.com', '$2y$12$tgSbqshz8wxxzlGNIwV9ZuvkOMncm7WHxzAzFtZ8Rj/kmMSG2sR3y', 'student', NULL, 'Citizen', '20-34', 'Female', 'Region IX - Zamboanga Peninsula', '2026-10-21 00:44:01'),
('24-00475', 'Tacbianan Grace Ann A', 'tacbianangraceann75@gmail.com', '$2y$12$ghL.LCt2utAMRYeZvMxDe.2/Pch8msaJcgbe3uc5iRkXOGPNh1Dey', 'student', NULL, 'Citizen', '20-34', 'Female', 'Region IX - Zamboanga Peninsula', '2026-10-20 15:06:36'),
('879', 'Vanna Tabañera', 'vanna31606@gmail.com', '$2y$12$4rk7E09eFwDzzko4RCk8GutaBmZimf/7vtl771VTF6oUstZ4rKDzW', 'student', NULL, 'Citizen', '20-34', 'Female', 'Region IX - Zamboanga Peninsula', '2026-10-20 15:45:43'),
('admin', 'System Administrator', 'admin@zppsu.edu.ph', '$2y$10$123456789012345678901u./zCLeARnBuyfsw17qkjYMfd7L4o2om', 'admin', NULL, 'Government', '35-49', 'Prefer not to say', 'Region IX - Zamboanga Peninsula', '2026-10-14 07:45:56'),
('HES-683', 'Hiolin, Joean Mark B.', 'jmhiolin@zppsu.edu.ph', '$2y$12$fba63l5Q1JI0WBl.N/v9/e.EpWiFCRGS.TbDz0b6ipGACcOij.OuC', 'zppsu_employee', NULL, 'Citizen', '20-34', 'Male', 'Region IX - Zamboanga Peninsula', '2026-10-14 07:39:23'),
('HES-684', 'Arnold Cruz', 'arnold@gmail.com', '$2y$12$PR7l9RgzHxtmn0PqNL4tsOXlSTqdQtkFStxvWR.fVPs8ZpoEZmq0q', 'student', NULL, 'Citizen', '19 or lower', 'Male', 'Region IX - Zamboanga Peninsula', '2026-10-16 13:30:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `action_plans`
--
ALTER TABLE `action_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `feedback_id` (`feedback_id`),
  ADD KEY `office_id` (`office_id`),
  ADD KEY `action_plans_ibfk_3` (`assigned_staff_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username_unique` (`username`);

--
-- Indexes for table `arta_feedback`
--
ALTER TABLE `arta_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `arta_feedback_ibfk_1` (`user_id`),
  ADD KEY `idx_submission_ts` (`submission_timestamp`),
  ADD KEY `idx_office_service` (`office_id`,`service_accessed`),
  ADD KEY `idx_start_date` (`service_start_date`),
  ADD KEY `idx_service_start` (`service_start_date`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `faculty_staff`
--
ALTER TABLE `faculty_staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number_unique` (`id_number`),
  ADD KEY `office_id` (`office_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `office_name_unique` (`office_name`),
  ADD KEY `college_id` (`college_id`);

--
-- Indexes for table `office_classifications`
--
ALTER TABLE `office_classifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `resolution_updates`
--
ALTER TABLE `resolution_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plan` (`plan_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_event` (`event_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `office_id` (`office_id`);

--
-- Indexes for table `service_requirements`
--
ALTER TABLE `service_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `service_steps`
--
ALTER TABLE `service_steps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`),
  ADD KEY `idx_group` (`setting_group`);

--
-- Indexes for table `sla_reviews`
--
ALTER TABLE `sla_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `feedback_unique` (`feedback_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `action_plan_id` (`action_plan_id`);

--
-- Indexes for table `suspicious_activity`
--
ALTER TABLE `suspicious_activity`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email_unique` (`email`),
  ADD KEY `users_office_fk` (`office_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `action_plans`
--
ALTER TABLE `action_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `arta_feedback`
--
ALTER TABLE `arta_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `faculty_staff`
--
ALTER TABLE `faculty_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `office_classifications`
--
ALTER TABLE `office_classifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `resolution_updates`
--
ALTER TABLE `resolution_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `service_requirements`
--
ALTER TABLE `service_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=166;

--
-- AUTO_INCREMENT for table `service_steps`
--
ALTER TABLE `service_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=165;

--
-- AUTO_INCREMENT for table `sla_reviews`
--
ALTER TABLE `sla_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suspicious_activity`
--
ALTER TABLE `suspicious_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `action_plans`
--
ALTER TABLE `action_plans`
  ADD CONSTRAINT `action_plans_ibfk_1` FOREIGN KEY (`feedback_id`) REFERENCES `arta_feedback` (`feedback_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `action_plans_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `action_plans_ibfk_3` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `arta_feedback`
--
ALTER TABLE `arta_feedback`
  ADD CONSTRAINT `arta_feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `arta_feedback_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `faculty_staff`
--
ALTER TABLE `faculty_staff`
  ADD CONSTRAINT `faculty_staff_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `offices`
--
ALTER TABLE `offices`
  ADD CONSTRAINT `offices_ibfk_1` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `service_requirements`
--
ALTER TABLE `service_requirements`
  ADD CONSTRAINT `service_requirements_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `service_steps`
--
ALTER TABLE `service_steps`
  ADD CONSTRAINT `service_steps_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sla_reviews`
--
ALTER TABLE `sla_reviews`
  ADD CONSTRAINT `sla_reviews_ibfk_1` FOREIGN KEY (`feedback_id`) REFERENCES `arta_feedback` (`feedback_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `sla_reviews_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `sla_reviews_ibfk_3` FOREIGN KEY (`action_plan_id`) REFERENCES `action_plans` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_office_fk` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
