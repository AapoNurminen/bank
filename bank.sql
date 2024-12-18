-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 18, 2024 at 01:09 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.1.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bank`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `iban` varchar(34) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'Unnamed Account',
  `is_approved` tinyint(1) DEFAULT 0,
  `is_delete_requested` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `user_id`, `balance`, `iban`, `account_name`, `name`, `is_approved`, `is_delete_requested`) VALUES
(1, 1, '275.00', 'FI781493867098939', '', 'Unnamed Account', 1, 0),
(12, 1, '325.00', 'FI325539120593278', '', 'savings', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `iban` varchar(34) NOT NULL,
  `user_id` int(11) NOT NULL,
  `to_iban` varchar(34) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `info` text DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `iban`, `user_id`, `to_iban`, `to_user_id`, `amount`, `info`, `transaction_date`) VALUES
(13, 'FI781493867098939', 1, 'FI325539120593278', 1, '200.00', 'Transfer from FI781493867098939', '2024-12-18 11:46:38'),
(15, 'FI781493867098939', 1, 'FI325539120593278', 1, '123.00', 'Transfer from FI781493867098939', '2024-12-18 11:51:23'),
(16, 'FI781493867098939', 1, 'FI325539120593278', 1, '2.00', 'Transfer from FI781493867098939', '2024-12-18 12:07:38');

--
-- Triggers `transactions`
--
DELIMITER $$
CREATE TRIGGER `transactions_trigger` AFTER INSERT ON `transactions` FOR EACH ROW UPDATE accounts SET balance = balance - NEW.amount WHERE iban = NEW.iban
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `transactions_trigger1` AFTER INSERT ON `transactions` FOR EACH ROW UPDATE accounts SET balance = balance + NEW.amount WHERE iban = NEW.to_iban
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `is_admin`) VALUES
(1, 'aapo', '$2y$10$fd0aqoOT4S3cCxxSZCLgre5kzYWt/kzfV2jqm3kh2vIb70f.DwrpG', 1),
(7, 'q', '$2y$10$djZY0RkUYmODW4IFjIlnt.N8V8my/cETTK7bljiEeQZsfwm0cW7TC', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `iban` (`iban`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `iban` (`iban`),
  ADD KEY `to_iban` (`to_iban`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `to_user_id` (`to_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`iban`) REFERENCES `accounts` (`iban`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`to_iban`) REFERENCES `accounts` (`iban`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_4` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
