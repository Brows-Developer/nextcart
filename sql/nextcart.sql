-- phpMyAdmin SQL Dump
-- version 4.9.5deb2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 23, 2021 at 09:32 PM
-- Server version: 8.0.25-0ubuntu0.20.04.1
-- PHP Version: 7.4.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nextcart`
--

-- --------------------------------------------------------

--
-- Table structure for table `global_variables`
--

CREATE TABLE `global_variables` (
  `id` int NOT NULL,
  `key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `global_variables`
--

INSERT INTO `global_variables` (`id`, `key`, `value`) VALUES
(1, 'time_difference', '0'),
(2, 'websocket_url', 'ws://localhost:19001'),
(3, 'login_header_text', 'NextCart System Login'),
(4, 'login_image', 'images/user/nextcart.png'),
(5, 'site_title', 'NextCart'),
(6, 'favicon', 'images/logo3.ico'),
(7, 'currency', 'Peso,PHP,php'),
(8, 'thousands_separator', ','),
(9, 'decimal_mark', '.'),
(10, 'currency_symbol', '₱');

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `modules_id` int NOT NULL,
  `modules_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modules_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modules_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modules_urls` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_children` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`modules_id`, `modules_label`, `modules_description`, `modules_url`, `modules_urls`, `has_children`, `icon`) VALUES
(1, 'Dashboard', NULL, '#/dashboard', '/dashboard', 'menu-item', 'fa-th-large'),
(2, 'Notifications', NULL, '', '/notification,/activity-log', 'menu-item-has-children', 'fa-bell'),
(3, 'Sales', NULL, '', '/sales/orders,/sales/returns', 'menu-item-has-children', 'fa-shopping-cart'),
(4, 'Catalog', NULL, '', '/catalog/categories,/catalog/products,/catalog/attributes,/catalog/manufacturers,/catalog/informations', 'menu-item-has-children', 'fa-tags'),
(5, 'System', NULL, '', '/settings,/roles,/users', 'menu-item-has-children', 'fa-cog');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int NOT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `host` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inserted_by` int DEFAULT NULL,
  `reservation_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inserted_datetime` datetime DEFAULT NULL,
  `notification_type` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `action`, `message`, `host`, `inserted_by`, `reservation_link`, `inserted_datetime`, `notification_type`) VALUES
(1, 'notify', 'Demo Demo has logged out', 'nextcart.local', 119, '', '2021-05-20 19:42:23', 9),
(2, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-20 19:42:28', 9),
(3, '', 'NextCart Role Role has been modified. Start url: \"null\" to \"/dashboard\";', 'nextcart.local', 119, '#/roles', '2021-05-20 20:30:45', 9),
(4, 'notify', 'Demo Demo has logged out', 'nextcart.local', 119, '', '2021-05-20 20:30:49', 9),
(5, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-20 20:30:52', 9),
(6, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-21 13:00:42', 9),
(7, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-21 15:18:09', 9),
(8, 'notify', 'Demo Demo has logged out', 'nextcart.local', 119, '', '2021-05-21 16:26:33', 9),
(9, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-21 16:26:41', 9),
(10, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-22 19:17:22', 9),
(11, '', 'NextCart Role Role has been modified. ', 'nextcart.local', 119, '#/roles', '2021-05-22 19:34:54', 9),
(12, '', 'User detail has been modified: ', 'nextcart.local', 119, '#/users', '2021-05-22 19:35:00', 9),
(13, 'notify', 'Demo Demo has logged out', 'nextcart.local', 119, '', '2021-05-22 19:35:14', 9),
(14, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-22 19:35:19', 9),
(15, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-22 19:35:24', 9),
(16, 'notify', 'Demo Demo has logged out', 'nextcart.local', 119, '', '2021-05-22 19:37:45', 9),
(17, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-22 19:38:04', 9),
(18, 'notify', 'Demo Demo has logged out', 'nextcart.local', 119, '', '2021-05-22 20:11:55', 9),
(19, 'notify', 'Demo Demo has logged out', 'nextcart.local', 119, '', '2021-05-22 20:12:07', 9),
(20, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-22 20:12:35', 9),
(21, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-22 20:13:07', 9),
(22, 'notify', 'Demo Demo has logged out', 'nextcart.local', 119, '', '2021-05-22 21:15:28', 9),
(23, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-22 21:15:35', 9),
(24, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-22 21:29:06', 9),
(25, 'notify', 'Demo Demo has logged out', 'nextcart.local', 119, '', '2021-05-22 22:21:55', 9),
(26, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-23 20:09:58', 9),
(27, '', 'NextCart Role Role has been modified. Sales has been enabled; ', 'nextcart.local', 119, '#/roles', '2021-05-23 20:35:02', 9),
(28, '', 'User detail has been modified: ', 'nextcart.local', 119, '#/users', '2021-05-23 20:35:08', 9),
(29, 'notify', 'Demo Demo has logged out', 'nextcart.local', 119, '', '2021-05-23 20:35:11', 9),
(30, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-23 20:35:16', 9),
(31, '', 'NextCart Role Role has been modified. Catalog has been enabled; ', 'nextcart.local', 119, '#/roles', '2021-05-23 21:01:09', 9),
(32, '', 'User detail has been modified: ', 'nextcart.local', 119, '#/users', '2021-05-23 21:01:14', 9),
(33, 'notify', 'Demo Demo has logged out', 'nextcart.local', 119, '', '2021-05-23 21:01:21', 9),
(34, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-23 21:01:27', 9),
(35, 'notify', 'Demo Demo has logged out', 'nextcart.local', 119, '', '2021-05-23 21:16:00', 9),
(36, 'notify', 'Demo Demo has logged in', 'nextcart.local', 119, '', '2021-05-23 21:16:02', 9);

-- --------------------------------------------------------

--
-- Table structure for table `notification_types`
--

CREATE TABLE `notification_types` (
  `notification_type_id` int NOT NULL,
  `notification_type_name` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `notification_types`
--

INSERT INTO `notification_types` (`notification_type_id`, `notification_type_name`) VALUES
(1, 'order');

-- --------------------------------------------------------

--
-- Table structure for table `privileges`
--

CREATE TABLE `privileges` (
  `user_id` int DEFAULT NULL,
  `year` int DEFAULT NULL,
  `rules1_allowed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `allowed_rates` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `permissible_costs` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `allowed_contracts` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `allowed_cases` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cash_payments` varchar(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priv_ins_reservation` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priv_mod_reservation` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priv_mod_pers` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priv_ins_clients` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prefix_customers` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `priv_ins_costs` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priv_see_tab` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priv_ins_rates` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priv_ins_rules` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priv_messages` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priv_inventory` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `privileges`
--

INSERT INTO `privileges` (`user_id`, `year`, `rules1_allowed`, `allowed_rates`, `permissible_costs`, `allowed_contracts`, `allowed_cases`, `cash_payments`, `priv_ins_reservation`, `priv_mod_reservation`, `priv_mod_pers`, `priv_ins_clients`, `prefix_customers`, `priv_ins_costs`, `priv_see_tab`, `priv_ins_rates`, `priv_ins_rules`, `priv_messages`, `priv_inventory`) VALUES
(2, 1, NULL, NULL, NULL, NULL, 'n,', NULL, NULL, NULL, 'snnn', 'sssss', 'n,', NULL, NULL, NULL, NULL, 'nn', 'snsnnssns'),
(2, 2017, 'nm,', 'n,', 'n,', 'ns,', NULL, NULL, 'sssssssssssss', 'sssssssssnss000000snsnsssss', NULL, NULL, NULL, 'nnnn', 'ssnnnsnno', 'ssnn', NULL, NULL, NULL),
(3, 1, NULL, NULL, NULL, NULL, 'n,', NULL, NULL, NULL, 'snsn', 'sssss', 'n,', NULL, NULL, NULL, NULL, 'ss', 'sssssssss'),
(3, 2017, 'nm,', 'n,', 'n,', 'ns,', NULL, NULL, 'sssssssssssns', 'sssssssssnss000000ssssssnss', NULL, NULL, NULL, 'ssnn', 'sssssssso', 'ssss', NULL, NULL, NULL),
(4, 1, '', '', '', '', 'n,', '', '', '', 'snsn', 'sssss', 'n,', '', '', '', '', 'ss', 'sssssssss'),
(4, 2017, 'nm,', 'n,', 'n,', 'ns,', '', '', 'sssssssssssns', 'sssssssssnss000000ssssssnss', '', '', '', 'ssnn', 'sssssssso', 'ssss', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int NOT NULL,
  `role_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_url` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allowed_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_page_modules_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `start_url`, `allowed_url`, `start_page_modules_id`) VALUES
(8, 'NextCart Role', '/dashboard', '/,/login,/dashboard,/notification,/activity-log,/sales/orders,/sales/returns,/catalog/categories,/catalog/products,/catalog/attributes,/catalog/manufacturers,/catalog/informations,/settings,/roles,/users,', 1);

-- --------------------------------------------------------

--
-- Table structure for table `roles_modules_relationship`
--

CREATE TABLE `roles_modules_relationship` (
  `roles_modules_relationship_id` int NOT NULL,
  `role_id` int DEFAULT NULL,
  `module_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `roles_modules_relationship`
--

INSERT INTO `roles_modules_relationship` (`roles_modules_relationship_id`, `role_id`, `module_id`) VALUES
(97, 3, 6),
(98, 3, 9),
(144, 1, 1),
(145, 1, 2),
(146, 1, 3),
(147, 1, 5),
(148, 1, 6),
(149, 1, 8),
(150, 1, 9),
(165, 7, 1),
(166, 7, 2),
(167, 7, 3),
(168, 7, 5),
(169, 7, 6),
(170, 7, 8),
(171, 7, 9),
(182, 6, 1),
(183, 6, 3),
(184, 6, 5),
(185, 6, 6),
(186, 6, 8),
(210, 8, 1),
(211, 8, 2),
(212, 8, 3),
(213, 8, 4),
(214, 8, 5);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `sessions_id` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type_conn` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_access` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`sessions_id`, `user_id`, `ip_address`, `type_conn`, `user_agent`, `last_access`) VALUES
('201708092207098798462515', 1, '::1', 'HTTP', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36', '2017-08-09 22:08:02');

-- --------------------------------------------------------

--
-- Table structure for table `sub_modules`
--

CREATE TABLE `sub_modules` (
  `sub_modules_id` int NOT NULL,
  `sub_modules_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_modules_description` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_modules_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_modules_urls` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modules_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `sub_modules`
--

INSERT INTO `sub_modules` (`sub_modules_id`, `sub_modules_label`, `sub_modules_description`, `sub_modules_url`, `sub_modules_urls`, `modules_id`) VALUES
(1, 'Notification List', NULL, '#/notification', '/notification', 2),
(2, 'Activity Log', NULL, '#/activity-log', '/activity-log', 2),
(3, 'Settings', NULL, '#/settings', '/settings', 5),
(4, 'Roles', NULL, '#/roles', '/roles', 5),
(5, 'Users', NULL, '#/users', '/users', 5),
(6, 'Orders', NULL, '#/sales/orders', '/sales/orders', 3),
(7, 'Returns', NULL, '#/sales/returns', '/sales/returns', 3),
(8, 'Categories', NULL, '#/catalog/categories', '/catalog/categories', 4),
(9, 'Products', NULL, '#/catalog/products', '/catalog/products', 4),
(10, 'Attributes', NULL, '#/catalog/attributes', '/catalog/attributes', 4),
(11, 'Manufacturers', NULL, '#/catalog/manufacturers', '/catalog/manufacturers', 4),
(12, 'Informations', NULL, '#/catalog/informations', '/catalog/informations', 4);

-- --------------------------------------------------------

--
-- Table structure for table `userprivilegecat`
--

CREATE TABLE `userprivilegecat` (
  `user_privilegecat_id` int NOT NULL,
  `users_id` int NOT NULL,
  `main_category_id` int NOT NULL,
  `status` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `userprivilegecat`
--

INSERT INTO `userprivilegecat` (`user_privilegecat_id`, `users_id`, `main_category_id`, `status`) VALUES
(1, 3, 1, 'active'),
(2, 3, 2, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `users_id` int NOT NULL,
  `username` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `salt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pass_type` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inserted_date` datetime DEFAULT NULL,
  `inserted_host` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firstname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(5) DEFAULT 'en',
  `phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pin` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_key` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_key_epos` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiration` int DEFAULT NULL,
  `prof_pic` varchar(225) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` int NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`users_id`, `username`, `password`, `salt`, `pass_type`, `inserted_date`, `inserted_host`, `firstname`, `lastname`, `title`, `language`, `phone_number`, `pin`, `session_key`, `session_key_epos`, `expiration`, `prof_pic`, `active`) VALUES
(119, 'demo', 'ef8745b7ca41609bf41e3df8ff5fa5ca', 'bQQWxr2NpvWU7re5Uj7', NULL, '2021-05-19 19:05:19', 'nextcart.local', 'Demo', 'Demo', 'Demo User', 'en', NULL, NULL, 'btatda1ghJnCWtR65tk', NULL, 24, 'images/user/nextcart.png', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users_role`
--

CREATE TABLE `users_role` (
  `users_role_id` int NOT NULL,
  `users_id` int DEFAULT NULL,
  `role_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users_role`
--

INSERT INTO `users_role` (`users_role_id`, `users_id`, `role_id`) VALUES
(1, 1, 7),
(2, 2, 2),
(3, 3, 2),
(4, 4, 3),
(5, 5, 2),
(6, 14, 2),
(7, 15, 2),
(8, 16, 2),
(9, 17, 2),
(10, 19, 2),
(11, 20, 2),
(12, 21, 2),
(13, 22, 2),
(14, 23, 5),
(15, 24, 4),
(16, 102, 2),
(17, 103, 5),
(18, 104, 2),
(19, 105, 2),
(20, 106, 6),
(21, 107, 6),
(22, 108, 6),
(23, 109, 6),
(24, 110, 6),
(25, 111, 6),
(26, 112, 6),
(27, 113, 6),
(28, 114, 3),
(29, 115, 3),
(30, 116, 3),
(31, 117, 3),
(32, 118, 6),
(33, 119, 8);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `global_variables`
--
ALTER TABLE `global_variables`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`modules_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`);

--
-- Indexes for table `notification_types`
--
ALTER TABLE `notification_types`
  ADD PRIMARY KEY (`notification_type_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `roles_modules_relationship`
--
ALTER TABLE `roles_modules_relationship`
  ADD PRIMARY KEY (`roles_modules_relationship_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`sessions_id`);

--
-- Indexes for table `sub_modules`
--
ALTER TABLE `sub_modules`
  ADD PRIMARY KEY (`sub_modules_id`);

--
-- Indexes for table `userprivilegecat`
--
ALTER TABLE `userprivilegecat`
  ADD PRIMARY KEY (`user_privilegecat_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`users_id`);

--
-- Indexes for table `users_role`
--
ALTER TABLE `users_role`
  ADD PRIMARY KEY (`users_role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `global_variables`
--
ALTER TABLE `global_variables`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `notification_types`
--
ALTER TABLE `notification_types`
  MODIFY `notification_type_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `roles_modules_relationship`
--
ALTER TABLE `roles_modules_relationship`
  MODIFY `roles_modules_relationship_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=215;

--
-- AUTO_INCREMENT for table `userprivilegecat`
--
ALTER TABLE `userprivilegecat`
  MODIFY `user_privilegecat_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `users_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT for table `users_role`
--
ALTER TABLE `users_role`
  MODIFY `users_role_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;