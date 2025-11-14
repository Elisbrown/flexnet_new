-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 11, 2025 at 01:06 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u123583059_flexnet`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` char(60) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `full_name`, `email`, `password_hash`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'System Superadmin', 'admin@flexnet.cm', '$2y$10$Vcft62z12kfrQ50w1.hmDeLoJR/mrWtN4fTtn9HEOIPFG16ZzVgr.', 1, NULL, '2025-11-11 01:04:40', '2025-11-11 01:04:40');

-- --------------------------------------------------------

--
-- Table structure for table `admin_roles`
--

CREATE TABLE `admin_roles` (
  `admin_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_roles`
--

INSERT INTO `admin_roles` (`admin_id`, `role_id`) VALUES
(1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `admin_sessions`
--

CREATE TABLE `admin_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` bigint(20) UNSIGNED NOT NULL,
  `session_token` char(64) NOT NULL,
  `ip_address` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(191) NOT NULL,
  `question_en` varchar(255) NOT NULL,
  `answer_en` text NOT NULL,
  `question_fr` varchar(255) DEFAULT NULL,
  `answer_fr` text DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `slug`, `question_en`, `answer_en`, `question_fr`, `answer_fr`, `is_published`, `sort_order`, `updated_at`, `created_at`) VALUES
(1, 'what-is-flexnet', 'What is Flexnet?', 'Flexnet is a home internet service that provides high-speed, reliable connectivity to your building or residence.', 'Qu\'est-ce que Flexnet ?', 'Flexnet est un service d’internet à domicile qui fournit une connexion rapide et stable à votre immeuble ou résidence.', 1, 1, '2025-11-11 01:04:40', '2025-11-11 01:04:40'),
(2, 'how-to-pay-my-internet-bill', 'How do I pay my internet bill?', 'Open the Flexnet app, go to the Billing tab, enter your mobile money number and choose MTN MoMo or Orange Money to complete payment.', 'Comment payer ma facture internet ?', 'Ouvrez l’application Flexnet, allez dans l’onglet Facturation, saisissez votre numéro Mobile Money et choisissez MTN MoMo ou Orange Money pour finaliser le paiement.', 1, 2, '2025-11-11 01:04:40', '2025-11-11 01:04:40'),
(3, 'what-happens-when-my-subscription-expires', 'What happens when my subscription expires?', 'Your connection may be restricted until you renew your subscription. You can always see the remaining days and renew directly from the app.', 'Que se passe-t-il lorsque mon abonnement expire ?', 'Votre connexion peut être limitée jusqu’à ce que vous renouveliez votre abonnement. Vous pouvez toujours voir les jours restants et renouveler directement depuis l’application.', 1, 3, '2025-11-11 01:04:40', '2025-11-11 01:04:40');

-- --------------------------------------------------------

--
-- Table structure for table `households`
--

CREATE TABLE `households` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `apartment_label` varchar(64) NOT NULL,
  `primary_full_name` varchar(191) NOT NULL,
  `phone_msisdn` varchar(32) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `login_identifier` varchar(64) NOT NULL,
  `pin_hash` char(60) NOT NULL,
  `password_hash` char(60) DEFAULT NULL,
  `has_changed_default_pin` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `current_subscription_id` bigint(20) UNSIGNED DEFAULT NULL,
  `subscription_status` enum('NONE','PENDING','ACTIVE','PAUSED','EXPIRED') NOT NULL DEFAULT 'NONE',
  `subscription_end_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `households`
--

INSERT INTO `households` (`id`, `location_id`, `apartment_label`, `primary_full_name`, `phone_msisdn`, `email`, `login_identifier`, `pin_hash`, `password_hash`, `has_changed_default_pin`, `is_active`, `current_subscription_id`, `subscription_status`, `subscription_end_date`, `created_at`, `updated_at`, `last_login_at`) VALUES
(1, 1, 'A-12', 'Test Household', '679000000', 'household@example.com', 'CITADEL-A12', '$2y$10$1vXYHN7j4D6hDGYrfrwabOaGri2H7MGmYcj3P/uJQ./iX2icg5ZoK', NULL, 0, 1, NULL, 'NONE', NULL, '2025-11-11 01:04:40', '2025-11-11 01:04:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `code` varchar(64) NOT NULL,
  `address_line1` varchar(191) DEFAULT NULL,
  `address_line2` varchar(191) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`, `code`, `address_line1`, `address_line2`, `city`, `region`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Citadel Complex', 'CITADEL-001', 'Route Messassi', 'Opposite Total Station', 'Yaoundé', 'Centre', 1, '2025-11-11 01:04:40', '2025-11-11 01:04:40');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `household_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `body` text NOT NULL,
  `type` varchar(64) DEFAULT NULL,
  `status` enum('UNREAD','READ') NOT NULL DEFAULT 'UNREAD',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `household_id` bigint(20) UNSIGNED NOT NULL,
  `subscription_id` bigint(20) UNSIGNED DEFAULT NULL,
  `plan_id` bigint(20) UNSIGNED DEFAULT NULL,
  `provider` enum('FAPSHI') NOT NULL DEFAULT 'FAPSHI',
  `channel` enum('MTN_MOMO','ORANGE_MONEY','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  `currency_code` char(3) NOT NULL DEFAULT 'XAF',
  `amount_xaf` int(10) UNSIGNED NOT NULL,
  `external_id` varchar(64) NOT NULL,
  `provider_user_id` varchar(64) DEFAULT NULL,
  `provider_txn_id` varchar(128) DEFAULT NULL,
  `provider_status` varchar(64) DEFAULT NULL,
  `status` enum('PENDING','SUCCESS','FAILED','EXPIRED') NOT NULL DEFAULT 'PENDING',
  `redirect_url` varchar(512) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `last_webhook_at` datetime DEFAULT NULL,
  `raw_request_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_request_json`)),
  `raw_response_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_response_json`)),
  `last_webhook_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`last_webhook_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_webhooks`
--

CREATE TABLE `payment_webhooks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `provider` enum('FAPSHI') NOT NULL,
  `external_id` varchar(64) DEFAULT NULL,
  `provider_txn_id` varchar(128) DEFAULT NULL,
  `event_status` varchar(64) DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `http_status` int(11) DEFAULT NULL,
  `processed_ok` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `speed_mbps` int(11) DEFAULT NULL,
  `data_cap_gb` int(11) DEFAULT NULL,
  `price_xaf` int(10) UNSIGNED NOT NULL,
  `duration_days` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `description`, `speed_mbps`, `data_cap_gb`, `price_xaf`, `duration_days`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Home Fiber – Standard', 'Unlimited Home Internet – up to 25 Mbps, billed monthly.', 25, NULL, 25000, 30, 1, '2025-11-11 01:04:40', '2025-11-11 01:04:40');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(64) NOT NULL,
  `description` varchar(191) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `is_system`) VALUES
(1, 'SUPER_ADMIN', 'Full access to all modules and system settings', 1),
(2, 'BILLING_ADMIN', 'Manage payments, subscriptions and invoices', 1),
(3, 'SUPPORT_AGENT', 'Handle support tickets and FAQs', 1);

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `household_id` bigint(20) UNSIGNED NOT NULL,
  `plan_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('PENDING','ACTIVE','PAUSED','EXPIRED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `pause_reason` varchar(255) DEFAULT NULL,
  `last_action` enum('ACTIVATE','RENEW','PAUSE','EXTEND','CANCEL') DEFAULT NULL,
  `created_by_admin` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_events`
--

CREATE TABLE `subscription_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `subscription_id` bigint(20) UNSIGNED NOT NULL,
  `household_id` bigint(20) UNSIGNED NOT NULL,
  `event_type` enum('CREATE','ACTIVATE','RENEW','PAUSE','EXTEND','EXPIRE','CANCEL') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `actor_type` enum('ADMIN','SYSTEM','HOUSEHOLD') NOT NULL,
  `actor_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_household_id` bigint(20) UNSIGNED DEFAULT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `sender_type` enum('HOUSEHOLD','ADMIN','SYSTEM') NOT NULL,
  `sender_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sender_household_id` bigint(20) UNSIGNED DEFAULT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `household_id` bigint(20) UNSIGNED NOT NULL,
  `subject` varchar(191) NOT NULL,
  `category` varchar(64) DEFAULT NULL,
  `status` enum('OPEN','IN_PROGRESS','RESOLVED','CLOSED') NOT NULL DEFAULT 'OPEN',
  `priority` enum('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
  `created_by_type` enum('HOUSEHOLD','ADMIN','SYSTEM') NOT NULL,
  `created_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `assigned_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor_type` enum('ADMIN','SYSTEM','HOUSEHOLD') NOT NULL,
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_label` varchar(191) DEFAULT NULL,
  `action` varchar(128) NOT NULL,
  `entity_type` varchar(64) NOT NULL,
  `entity_id` varchar(64) NOT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `ip_address` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_admins_email` (`email`),
  ADD KEY `idx_admins_active` (`is_active`);

--
-- Indexes for table `admin_roles`
--
ALTER TABLE `admin_roles`
  ADD PRIMARY KEY (`admin_id`,`role_id`),
  ADD KEY `fk_admin_roles_role` (`role_id`);

--
-- Indexes for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sessions_token` (`session_token`),
  ADD KEY `idx_sessions_admin` (`admin_id`,`is_active`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_faqs_slug` (`slug`),
  ADD KEY `idx_faqs_published` (`is_published`,`sort_order`);

--
-- Indexes for table `households`
--
ALTER TABLE `households`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_households_login_id` (`login_identifier`),
  ADD UNIQUE KEY `uq_households_phone` (`phone_msisdn`),
  ADD KEY `idx_households_location` (`location_id`,`apartment_label`),
  ADD KEY `idx_households_status` (`subscription_status`,`subscription_end_date`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_locations_code` (`code`),
  ADD KEY `idx_locations_active` (`is_active`,`name`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_household` (`household_id`,`status`,`created_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payments_external` (`external_id`),
  ADD KEY `fk_payments_subscription` (`subscription_id`),
  ADD KEY `fk_payments_plan` (`plan_id`),
  ADD KEY `idx_payments_status` (`status`,`requested_at`),
  ADD KEY `idx_payments_provider` (`provider`,`provider_txn_id`),
  ADD KEY `idx_payments_household` (`household_id`,`requested_at`),
  ADD KEY `idx_payments_channel` (`channel`,`requested_at`);

--
-- Indexes for table `payment_webhooks`
--
ALTER TABLE `payment_webhooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_webhooks_external` (`external_id`),
  ADD KEY `idx_webhooks_txn` (`provider_txn_id`),
  ADD KEY `idx_webhooks_status` (`event_status`,`created_at`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plans_active` (`is_active`,`price_xaf`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_name` (`name`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subs_plan` (`plan_id`),
  ADD KEY `idx_subs_household` (`household_id`,`status`),
  ADD KEY `idx_subs_dates` (`status`,`end_date`),
  ADD KEY `idx_subs_admin` (`created_by_admin`);

--
-- Indexes for table `subscription_events`
--
ALTER TABLE `subscription_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sub_events_sub` (`subscription_id`),
  ADD KEY `idx_sub_events_household` (`household_id`),
  ADD KEY `idx_sub_events_created` (`created_at`);

--
-- Indexes for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_msgs_ticket` (`ticket_id`,`created_at`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tickets_status` (`status`,`priority`,`updated_at`),
  ADD KEY `idx_tickets_assignee` (`assigned_admin_id`),
  ADD KEY `idx_tickets_household` (`household_id`,`created_at`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_created` (`created_at`),
  ADD KEY `idx_logs_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_logs_actor` (`actor_type`,`actor_id`),
  ADD KEY `idx_logs_action` (`action`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `households`
--
ALTER TABLE `households`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_webhooks`
--
ALTER TABLE `payment_webhooks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_events`
--
ALTER TABLE `subscription_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_roles`
--
ALTER TABLE `admin_roles`
  ADD CONSTRAINT `fk_admin_roles_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `fk_admin_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD CONSTRAINT `fk_sessions_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`);

--
-- Constraints for table `households`
--
ALTER TABLE `households`
  ADD CONSTRAINT `fk_households_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`),
  ADD CONSTRAINT `fk_payments_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`),
  ADD CONSTRAINT `fk_payments_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`);

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `fk_subs_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`),
  ADD CONSTRAINT `fk_subs_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`);

--
-- Constraints for table `subscription_events`
--
ALTER TABLE `subscription_events`
  ADD CONSTRAINT `fk_sub_events_sub` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`);

--
-- Constraints for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `fk_msgs_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`);

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `fk_tickets_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
