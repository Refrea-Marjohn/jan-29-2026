-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307:3307
-- Generation Time: Jan 24, 2026 at 05:31 PM
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
-- Database: `depedloan`
--

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `loan_amount` decimal(10,2) NOT NULL,
  `loan_purpose` text NOT NULL,
  `loan_term` int(11) NOT NULL,
  `net_pay` decimal(10,2) NOT NULL,
  `school_assignment` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `salary_grade` varchar(10) NOT NULL,
  `employment_status` varchar(50) NOT NULL,
  `co_maker_full_name` varchar(150) NOT NULL,
  `co_maker_position` varchar(150) NOT NULL,
  `co_maker_school_assignment` varchar(255) NOT NULL,
  `co_maker_net_pay` decimal(10,2) NOT NULL,
  `co_maker_employment_status` varchar(50) NOT NULL,
  `payslip_filename` varchar(255) NOT NULL,
  `co_maker_payslip_filename` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by_id` int(11) DEFAULT NULL,
  `reviewed_by_role` varchar(50) DEFAULT NULL,
  `reviewed_by_name` varchar(150) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `monthly_payment` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `total_interest` decimal(10,2) DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `application_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loans`
--

-- No sample loan records; keep database clean on import.

-- --------------------------------------------------------

--
-- Table structure for table `deductions`
--

CREATE TABLE `deductions` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `borrower_id` int(11) NOT NULL,
  `deduction_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `posted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fund_ledger`
--

CREATE TABLE `fund_ledger` (
  `id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `entry_type` enum('collection','release','adjustment') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','borrower','accountant') DEFAULT 'borrower',
  `profile_photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `created_at`) VALUES
(1, 'admin', 'admin@depedloan.gov.ph', '$2y$10$SjJiWKXo86z9j6Cll.KHyePjwdPsvVGJ5XiAFoZ.gLBZXTLGQNpXq', 'System Administrator', 'admin', '2026-01-23 03:20:45');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `deductions`
--
ALTER TABLE `deductions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `borrower_id` (`borrower_id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `fund_ledger`
--
ALTER TABLE `fund_ledger`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deductions`
--
ALTER TABLE `deductions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fund_ledger`
--
ALTER TABLE `fund_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deductions`
--
ALTER TABLE `deductions`
  ADD CONSTRAINT `deductions_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deductions_ibfk_2` FOREIGN KEY (`borrower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deductions_ibfk_3` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
