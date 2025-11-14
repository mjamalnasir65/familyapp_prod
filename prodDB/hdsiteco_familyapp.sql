-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 13, 2025 at 12:33 PM
-- Server version: 8.0.43-cll-lve
-- PHP Version: 8.4.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hdsiteco_familyapp`
--

-- --------------------------------------------------------

--
-- Table structure for table `action_logs`
--

CREATE TABLE `action_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `session_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `action_logs`
--

INSERT INTO `action_logs` (`id`, `user_id`, `session_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'df423fa8ccee6099be68d824e2826b3f', 'person_view', '{\"person_id\": 2}', '161.142.148.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-11-13 04:27:48'),
(2, 1, 'df423fa8ccee6099be68d824e2826b3f', 'person_view', '{\"person_id\": 3}', '161.142.148.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-11-13 04:29:07');

-- --------------------------------------------------------

--
-- Table structure for table `families`
--

CREATE TABLE `families` (
  `id` int NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `family_token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `family_token_expires_at` datetime DEFAULT NULL,
  `family_token_issued_by` bigint DEFAULT NULL,
  `family_token_union_id` bigint DEFAULT NULL,
  `expected_children` int UNSIGNED DEFAULT NULL,
  `children_added` int UNSIGNED NOT NULL DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `creator_id` int NOT NULL,
  `created_by_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  `step_1_completed` enum('N','Y') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `step_2_completed` enum('N','Y') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `step_3_completed` enum('N','Y') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `step_4_completed` enum('N','Y') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `step_5_completed` enum('N','Y') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `step_6_completed` enum('N','Y') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `wizard_completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `families`
--

INSERT INTO `families` (`id`, `name`, `family_token_hash`, `family_token_expires_at`, `family_token_issued_by`, `family_token_union_id`, `expected_children`, `children_added`, `description`, `creator_id`, `created_by_email`, `created_at`, `updated_at`, `is_active`, `step_1_completed`, `step_2_completed`, `step_3_completed`, `step_4_completed`, `step_5_completed`, `step_6_completed`, `wizard_completed_at`) VALUES
(1, 'Family Jamal', NULL, NULL, NULL, NULL, NULL, 0, '', 1, 'jamal@email.com', '2025-11-13 04:26:03', '2025-11-13 04:27:11', 1, 'Y', 'Y', 'Y', 'Y', 'Y', 'N', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `family_members`
--

CREATE TABLE `family_members` (
  `id` int NOT NULL,
  `family_id` int NOT NULL,
  `user_id` int NOT NULL,
  `role` enum('creator','member','viewer','editor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'member',
  `can_edit` tinyint(1) DEFAULT '0',
  `can_add` tinyint(1) DEFAULT '0',
  `can_delete` tinyint(1) DEFAULT '0',
  `can_manage_files` tinyint(1) DEFAULT '0',
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','pending','suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `family_members`
--

INSERT INTO `family_members` (`id`, `family_id`, `user_id`, `role`, `can_edit`, `can_add`, `can_delete`, `can_manage_files`, `joined_at`, `status`) VALUES
(1, 1, 1, 'creator', 1, 1, 1, 1, '2025-11-13 04:26:03', 'active');

-- --------------------------------------------------------

--
-- Stand-in structure for view `family_statistics`
-- (See below for the actual view)
--
CREATE TABLE `family_statistics` (
`family_id` int
,`family_name` varchar(191)
,`total_members` bigint
,`living_members` bigint
,`deceased_members` bigint
,`total_relationships` bigint
,`total_unions` bigint
,`current_unions` bigint
,`last_person_added` timestamp
,`wizard_completed_at` timestamp
,`family_created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` int NOT NULL,
  `family_id` int NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_image` tinyint(1) DEFAULT '0',
  `uploaded_by` int NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `file_attachments`
--

CREATE TABLE `file_attachments` (
  `id` int NOT NULL,
  `file_id` int NOT NULL,
  `person_id` int NOT NULL,
  `family_id` int NOT NULL,
  `attachment_type` enum('photo','document','audio','video','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'photo',
  `attached_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `global_people`
--

CREATE TABLE `global_people` (
  `id` bigint NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canonical_person_id` bigint DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invites`
--

CREATE TABLE `invites` (
  `id` int NOT NULL,
  `families_id` int NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `inviter_id` int NOT NULL,
  `invited_user_id` int DEFAULT NULL,
  `role` enum('owner','editor','viewer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'viewer',
  `scope_type` enum('family','couple','person') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'family',
  `parent1_id` int DEFAULT NULL,
  `parent2_id` int DEFAULT NULL,
  `person_id` int DEFAULT NULL,
  `can_edit` tinyint(1) DEFAULT '0',
  `can_add` tinyint(1) DEFAULT '0',
  `can_delete` tinyint(1) DEFAULT '0',
  `can_manage_files` tinyint(1) DEFAULT '0',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_expires_at` datetime NOT NULL,
  `status` enum('pending','accepted','revoked','expired','canceled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `accepted_at` datetime DEFAULT NULL,
  `accepted_by` int DEFAULT NULL,
  `last_sent_at` datetime DEFAULT NULL,
  `sent_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nested_families`
--

CREATE TABLE `nested_families` (
  `id` int NOT NULL,
  `family_id` int NOT NULL,
  `kind` enum('A','D') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'D',
  `level` int UNSIGNED NOT NULL DEFAULT '1',
  `label` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_nested_id` int DEFAULT NULL,
  `root_union_id` int NOT NULL,
  `expected_children` int UNSIGNED DEFAULT NULL,
  `children_added` int UNSIGNED NOT NULL DEFAULT '0',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nested_members`
--

CREATE TABLE `nested_members` (
  `id` int NOT NULL,
  `nested_id` int NOT NULL,
  `user_id` int NOT NULL,
  `role` enum('owner','editor','viewer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'editor',
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `persons`
--

CREATE TABLE `persons` (
  `id` int NOT NULL,
  `family_id` int NOT NULL,
  `full_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suffix` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_to_say') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `other_gender` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_alive` tinyint(1) DEFAULT '1',
  `birth_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `death_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `burial_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_place` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `death_place` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `burial_place` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profession` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `interests` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `activities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bio_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `death_cause` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blog` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_site` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_tel` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_tel` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fb_link` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `other_contact` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sort_order` decimal(12,4) DEFAULT '0.0000',
  `label_color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `persons`
--

INSERT INTO `persons` (`id`, `family_id`, `full_name`, `nickname`, `first_name`, `last_name`, `birth_name`, `title`, `suffix`, `gender`, `other_gender`, `is_alive`, `birth_date`, `death_date`, `burial_date`, `birth_place`, `death_place`, `burial_place`, `profession`, `company`, `interests`, `activities`, `bio_notes`, `death_cause`, `email`, `website`, `blog`, `photo_site`, `home_tel`, `work_tel`, `mobile`, `fb_link`, `address`, `other_contact`, `sort_order`, `label_color`, `photo_data`, `created_by`, `created_at`, `updated_by`, `updated_at`, `notes`) VALUES
(1, 1, 'Jamal', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jamal@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:26:06', NULL, '2025-11-13 04:26:06', NULL),
(2, 1, 'Nasir', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:26:20', NULL, '2025-11-13 04:26:20', NULL),
(3, 1, 'Jonah', NULL, NULL, NULL, NULL, NULL, NULL, 'female', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:26:20', NULL, '2025-11-13 04:26:20', NULL),
(4, 1, 'Zakaria', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:27:00', NULL, '2025-11-13 04:27:00', NULL),
(5, 1, 'Latib', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:27:00', NULL, '2025-11-13 04:27:00', NULL),
(6, 1, 'Amat', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:27:00', NULL, '2025-11-13 04:27:00', NULL),
(7, 1, 'Jalil', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:27:00', NULL, '2025-11-13 04:27:00', NULL),
(8, 1, 'JandaF', NULL, NULL, NULL, NULL, NULL, NULL, 'female', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:27:11', NULL, '2025-11-13 04:27:11', NULL),
(9, 1, 'Akmal', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:27:37', NULL, '2025-11-13 04:27:37', NULL),
(10, 1, 'Daus', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:27:37', NULL, '2025-11-13 04:27:37', NULL),
(11, 1, 'Ayu', NULL, NULL, NULL, NULL, NULL, NULL, 'female', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:27:37', NULL, '2025-11-13 04:27:37', NULL),
(12, 1, 'Yasid', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:28:05', NULL, '2025-11-13 04:28:05', NULL),
(13, 1, 'Aminah', NULL, NULL, NULL, NULL, NULL, NULL, 'female', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:28:05', NULL, '2025-11-13 04:28:05', NULL),
(14, 1, 'Kadir', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:28:32', NULL, '2025-11-13 04:28:32', NULL),
(15, 1, 'Jamil', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:28:32', NULL, '2025-11-13 04:28:32', NULL),
(16, 1, 'Hj Yusof', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:29:31', NULL, '2025-11-13 04:29:31', NULL),
(17, 1, 'Mardhiah', NULL, NULL, NULL, NULL, NULL, NULL, 'female', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:29:31', NULL, '2025-11-13 04:29:31', NULL),
(18, 1, 'Hj Husin', NULL, NULL, NULL, NULL, NULL, NULL, 'male', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:29:55', NULL, '2025-11-13 04:29:55', NULL),
(19, 1, 'Wak Tom', NULL, NULL, NULL, NULL, NULL, NULL, 'female', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 1, '2025-11-13 04:29:55', NULL, '2025-11-13 04:29:55', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `person_details`
-- (See below for the actual view)
--
CREATE TABLE `person_details` (
`id` int
,`family_id` int
,`full_name` varchar(191)
,`nickname` varchar(100)
,`first_name` varchar(100)
,`last_name` varchar(100)
,`birth_name` varchar(100)
,`title` varchar(50)
,`suffix` varchar(50)
,`gender` enum('male','female','other','prefer_not_to_say')
,`other_gender` varchar(50)
,`is_alive` tinyint(1)
,`birth_date` varchar(50)
,`death_date` varchar(50)
,`burial_date` varchar(50)
,`birth_place` varchar(255)
,`death_place` varchar(255)
,`burial_place` varchar(255)
,`profession` varchar(255)
,`company` varchar(255)
,`interests` text
,`activities` text
,`bio_notes` text
,`death_cause` text
,`email` varchar(255)
,`website` varchar(255)
,`blog` varchar(255)
,`photo_site` varchar(255)
,`home_tel` varchar(50)
,`work_tel` varchar(50)
,`mobile` varchar(50)
,`address` text
,`other_contact` text
,`sort_order` decimal(12,4)
,`label_color` varchar(20)
,`photo_data` text
,`created_by` int
,`created_at` timestamp
,`updated_at` timestamp
,`notes` text
,`family_name` varchar(191)
,`family_creator_id` int
,`created_by_name` varchar(100)
,`children_count` bigint
,`parent_count` bigint
,`current_unions_count` bigint
);

-- --------------------------------------------------------

--
-- Table structure for table `person_links`
--

CREATE TABLE `person_links` (
  `id` bigint NOT NULL,
  `person_id_a` bigint NOT NULL,
  `person_id_b` bigint NOT NULL,
  `verified_by_account_id` bigint DEFAULT NULL,
  `verified_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `verified_status` enum('pending','confirmed','rejected') DEFAULT 'pending',
  `note` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `possible_duplicates`
--

CREATE TABLE `possible_duplicates` (
  `id` int NOT NULL,
  `person_a_id` int NOT NULL,
  `person_b_id` int NOT NULL,
  `invite_id` int DEFAULT NULL,
  `reason` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `merge_type` enum('sibling_branch','parent_child','partner_alias','unknown') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` json DEFAULT NULL,
  `similar_email` tinyint(1) DEFAULT '0',
  `similar_full_name` tinyint(1) DEFAULT '0',
  `similar_father_name` tinyint(1) DEFAULT '0',
  `similar_mother_name` tinyint(1) DEFAULT '0',
  `similar_shared_parent` tinyint(1) DEFAULT '0',
  `status` enum('pending','reviewed','merged','dismissed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `merge_click_u1_id` bigint DEFAULT NULL,
  `merge_click_u2_id` bigint DEFAULT NULL,
  `u1_merged_click` enum('N','Y') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `u2_merged_click` enum('N','Y') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `date_click_u1` datetime DEFAULT NULL,
  `date_click_u2` datetime DEFAULT NULL,
  `pair_lo` int GENERATED ALWAYS AS (least(`person_a_id`,`person_b_id`)) STORED,
  `pair_hi` int GENERATED ALWAYS AS (greatest(`person_a_id`,`person_b_id`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `relationships`
--

CREATE TABLE `relationships` (
  `id` int NOT NULL,
  `family_id` int NOT NULL,
  `parent_id` int NOT NULL,
  `child_id` int NOT NULL,
  `relationship_type` enum('biological','adopted','step','foster','guardian','godparent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'biological',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `relationships`
--

INSERT INTO `relationships` (`id`, `family_id`, `parent_id`, `child_id`, `relationship_type`, `notes`, `created_at`) VALUES
(1, 1, 2, 1, 'biological', NULL, '2025-11-13 04:26:20'),
(2, 1, 3, 1, 'biological', NULL, '2025-11-13 04:26:20'),
(3, 1, 2, 4, 'biological', NULL, '2025-11-13 04:27:00'),
(4, 1, 3, 4, 'biological', NULL, '2025-11-13 04:27:00'),
(5, 1, 2, 5, 'biological', NULL, '2025-11-13 04:27:00'),
(6, 1, 3, 5, 'biological', NULL, '2025-11-13 04:27:00'),
(7, 1, 2, 6, 'biological', NULL, '2025-11-13 04:27:00'),
(8, 1, 3, 6, 'biological', NULL, '2025-11-13 04:27:00'),
(9, 1, 2, 7, 'biological', NULL, '2025-11-13 04:27:00'),
(10, 1, 3, 7, 'biological', NULL, '2025-11-13 04:27:00'),
(11, 1, 3, 9, 'biological', NULL, '2025-11-13 04:27:37'),
(12, 1, 8, 9, 'biological', NULL, '2025-11-13 04:27:37'),
(13, 1, 3, 10, 'biological', NULL, '2025-11-13 04:27:37'),
(14, 1, 8, 10, 'biological', NULL, '2025-11-13 04:27:37'),
(15, 1, 3, 11, 'biological', NULL, '2025-11-13 04:27:37'),
(16, 1, 8, 11, 'biological', NULL, '2025-11-13 04:27:37'),
(17, 1, 12, 2, 'biological', NULL, '2025-11-13 04:28:05'),
(18, 1, 13, 2, 'biological', NULL, '2025-11-13 04:28:05'),
(19, 1, 12, 14, 'biological', NULL, '2025-11-13 04:28:32'),
(20, 1, 13, 14, 'biological', NULL, '2025-11-13 04:28:32'),
(21, 1, 12, 15, 'biological', NULL, '2025-11-13 04:28:32'),
(22, 1, 13, 15, 'biological', NULL, '2025-11-13 04:28:32'),
(23, 1, 16, 3, 'biological', NULL, '2025-11-13 04:29:31'),
(24, 1, 17, 3, 'biological', NULL, '2025-11-13 04:29:31'),
(25, 1, 16, 18, 'biological', NULL, '2025-11-13 04:29:55'),
(26, 1, 17, 18, 'biological', NULL, '2025-11-13 04:29:55'),
(27, 1, 16, 19, 'biological', NULL, '2025-11-13 04:29:55'),
(28, 1, 17, 19, 'biological', NULL, '2025-11-13 04:29:55');

--
-- Triggers `relationships`
--
DELIMITER $$
CREATE TRIGGER `ensure_union_on_parent_pair` AFTER INSERT ON `relationships` FOR EACH ROW BEGIN
  DECLARE other_parent_id INT;
  DECLARE p1 INT; DECLARE p2 INT;

  -- find any other parent for this child in same family
  SELECT r.parent_id
    INTO other_parent_id
  FROM relationships r
  WHERE r.family_id = NEW.family_id
    AND r.child_id = NEW.child_id
    AND r.parent_id <> NEW.parent_id
  ORDER BY r.id DESC
  LIMIT 1;

  IF other_parent_id IS NOT NULL THEN
    SET p1 = LEAST(NEW.parent_id, other_parent_id);
    SET p2 = GREATEST(NEW.parent_id, other_parent_id);
    INSERT INTO unions (family_id, person1_id, person2_id, union_type, is_current)
    VALUES (NEW.family_id, p1, p2, 'marriage', NULL)
    ON DUPLICATE KEY UPDATE union_type = union_type; -- no-op to be idempotent
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `prevent_circular_relationships` BEFORE INSERT ON `relationships` FOR EACH ROW BEGIN
  IF NEW.parent_id = NEW.child_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot create circular relationship: person cannot be their own parent';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `family_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `unions`
--

CREATE TABLE `unions` (
  `id` int NOT NULL,
  `family_id` int NOT NULL,
  `person1_id` int NOT NULL,
  `person2_id` int NOT NULL,
  `union_type` enum('marriage','partnership','engagement','divorced','separated','widowed','annulled','common_law') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'marriage',
  `other_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT '1',
  `marriage_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marriage_place` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `divorce_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `separation_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `annulment_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `engagement_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marriage_year` year DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `unions`
--

INSERT INTO `unions` (`id`, `family_id`, `person1_id`, `person2_id`, `union_type`, `other_type`, `is_current`, `marriage_date`, `marriage_place`, `end_date`, `divorce_date`, `separation_date`, `annulment_date`, `engagement_date`, `start_date`, `marriage_year`, `created_at`, `updated_at`, `notes`) VALUES
(1, 1, 2, 3, 'marriage', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-13 04:26:20', '2025-11-13 04:26:20', NULL),
(7, 1, 1, 8, 'marriage', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-13 04:27:11', '2025-11-13 04:27:11', NULL),
(8, 1, 3, 8, 'marriage', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-13 04:27:37', '2025-11-13 04:27:37', NULL),
(11, 1, 12, 13, 'marriage', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-13 04:28:05', '2025-11-13 04:28:05', NULL),
(15, 1, 16, 17, 'marriage', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-13 04:29:31', '2025-11-13 04:29:31', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `families_id` int DEFAULT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  `email_verified` tinyint(1) DEFAULT '0',
  `verification_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_expires` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_count` int DEFAULT '0',
  `pwa_admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = admin, 0 = regular user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `families_id`, `full_name`, `email`, `password_hash`, `created_at`, `updated_at`, `is_active`, `email_verified`, `verification_token`, `reset_token`, `reset_expires`, `last_login`, `login_count`, `pwa_admin`) VALUES
(1, 1, 'Jamal', 'jamal@email.com', '$2y$10$VwYZO79FY6M.B4Mnu8omzeZfVsFNzD23Q9qfrq9kyhMjh0yGR7.vi', '2025-11-13 04:25:51', '2025-11-13 04:26:03', 1, 0, NULL, NULL, NULL, NULL, 0, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `action_logs`
--
ALTER TABLE `action_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `families`
--
ALTER TABLE `families`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_creator` (`creator_id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_created_by_email` (`created_by_email`),
  ADD KEY `wizard_completion_idx` (`step_1_completed`,`step_2_completed`,`step_3_completed`,`step_4_completed`,`step_5_completed`,`step_6_completed`),
  ADD KEY `idx_families_token_hash` (`family_token_hash`);

--
-- Indexes for table `family_members`
--
ALTER TABLE `family_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_family_user` (`family_id`,`user_id`),
  ADD KEY `idx_family` (`family_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_family` (`family_id`),
  ADD KEY `idx_uploader` (`uploaded_by`),
  ADD KEY `idx_type` (`file_type`),
  ADD KEY `idx_image` (`is_image`);

--
-- Indexes for table `file_attachments`
--
ALTER TABLE `file_attachments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_file_person` (`file_id`,`person_id`),
  ADD KEY `idx_file` (`file_id`),
  ADD KEY `idx_person` (`person_id`),
  ADD KEY `idx_family` (`family_id`);

--
-- Indexes for table `global_people`
--
ALTER TABLE `global_people`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_gp_canonical` (`canonical_person_id`);

--
-- Indexes for table `invites`
--
ALTER TABLE `invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_open_invite` (`families_id`,`email`,`token_hash`),
  ADD KEY `idx_token` (`token_hash`),
  ADD KEY `idx_family_status` (`families_id`,`status`),
  ADD KEY `idx_inviter` (`inviter_id`),
  ADD KEY `idx_scope_couple` (`parent1_id`,`parent2_id`),
  ADD KEY `idx_person_scope` (`person_id`),
  ADD KEY `fk_inv_user` (`invited_user_id`),
  ADD KEY `fk_inv_p2` (`parent2_id`);

--
-- Indexes for table `nested_families`
--
ALTER TABLE `nested_families`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_nf_family` (`family_id`),
  ADD KEY `fk_nf_parent` (`parent_nested_id`);

--
-- Indexes for table `nested_members`
--
ALTER TABLE `nested_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_nested_user` (`nested_id`,`user_id`),
  ADD KEY `fk_nm_user` (`user_id`);

--
-- Indexes for table `persons`
--
ALTER TABLE `persons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_family` (`family_id`),
  ADD KEY `idx_name` (`full_name`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_family_alive` (`family_id`,`is_alive`),
  ADD KEY `idx_birth_date` (`birth_date`),
  ADD KEY `idx_death_date` (`death_date`),
  ADD KEY `idx_living` (`is_alive`);
ALTER TABLE `persons` ADD FULLTEXT KEY `idx_search` (`full_name`,`nickname`,`last_name`,`birth_name`,`profession`,`bio_notes`,`interests`,`activities`);

--
-- Indexes for table `person_links`
--
ALTER TABLE `person_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `person_id_a` (`person_id_a`),
  ADD KEY `person_id_b` (`person_id_b`);

--
-- Indexes for table `possible_duplicates`
--
ALTER TABLE `possible_duplicates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pd_pair_reason` (`pair_lo`,`pair_hi`,`reason`),
  ADD KEY `person_a_id` (`person_a_id`),
  ADD KEY `person_b_id` (`person_b_id`),
  ADD KEY `idx_pd_status` (`status`),
  ADD KEY `idx_pd_a_status` (`person_a_id`,`status`),
  ADD KEY `idx_pd_b_status` (`person_b_id`,`status`),
  ADD KEY `idx_pd_invite` (`invite_id`);

--
-- Indexes for table `relationships`
--
ALTER TABLE `relationships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_parent_child` (`parent_id`,`child_id`),
  ADD KEY `idx_family` (`family_id`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_child` (`child_id`),
  ADD KEY `idx_family_type` (`family_id`,`relationship_type`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting` (`family_id`,`user_id`,`setting_key`),
  ADD KEY `idx_family` (`family_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Indexes for table `unions`
--
ALTER TABLE `unions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_union` (`person1_id`,`person2_id`,`family_id`),
  ADD UNIQUE KEY `uniq_family_couple` (`family_id`,`person1_id`,`person2_id`),
  ADD KEY `idx_family` (`family_id`),
  ADD KEY `idx_person1` (`person1_id`),
  ADD KEY `idx_person2` (`person2_id`),
  ADD KEY `idx_current` (`is_current`),
  ADD KEY `idx_type` (`union_type`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_families` (`families_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_email_verified` (`email_verified`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `action_logs`
--
ALTER TABLE `action_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `families`
--
ALTER TABLE `families`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `family_members`
--
ALTER TABLE `family_members`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `file_attachments`
--
ALTER TABLE `file_attachments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `global_people`
--
ALTER TABLE `global_people`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `invites`
--
ALTER TABLE `invites`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `nested_families`
--
ALTER TABLE `nested_families`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nested_members`
--
ALTER TABLE `nested_members`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `persons`
--
ALTER TABLE `persons`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `person_links`
--
ALTER TABLE `person_links`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `possible_duplicates`
--
ALTER TABLE `possible_duplicates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `relationships`
--
ALTER TABLE `relationships`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `unions`
--
ALTER TABLE `unions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

-- --------------------------------------------------------

--
-- Structure for view `family_statistics`
--
DROP TABLE IF EXISTS `family_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `family_statistics`  AS SELECT `f`.`id` AS `family_id`, `f`.`name` AS `family_name`, count(distinct `p`.`id`) AS `total_members`, count(distinct (case when (`p`.`is_alive` = 1) then `p`.`id` end)) AS `living_members`, count(distinct (case when (`p`.`is_alive` = 0) then `p`.`id` end)) AS `deceased_members`, count(distinct `r`.`id`) AS `total_relationships`, count(distinct `un`.`id`) AS `total_unions`, count(distinct (case when (`un`.`is_current` = 1) then `un`.`id` end)) AS `current_unions`, max(`p`.`created_at`) AS `last_person_added`, `f`.`wizard_completed_at` AS `wizard_completed_at`, `f`.`created_at` AS `family_created_at` FROM (((`families` `f` left join `persons` `p` on((`f`.`id` = `p`.`family_id`))) left join `relationships` `r` on((`f`.`id` = `r`.`family_id`))) left join `unions` `un` on((`f`.`id` = `un`.`family_id`))) GROUP BY `f`.`id`, `f`.`name`, `f`.`wizard_completed_at`, `f`.`created_at` ;

-- --------------------------------------------------------

--
-- Structure for view `person_details`
--
DROP TABLE IF EXISTS `person_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `person_details`  AS SELECT `p`.`id` AS `id`, `p`.`family_id` AS `family_id`, `p`.`full_name` AS `full_name`, `p`.`nickname` AS `nickname`, `p`.`first_name` AS `first_name`, `p`.`last_name` AS `last_name`, `p`.`birth_name` AS `birth_name`, `p`.`title` AS `title`, `p`.`suffix` AS `suffix`, `p`.`gender` AS `gender`, `p`.`other_gender` AS `other_gender`, `p`.`is_alive` AS `is_alive`, `p`.`birth_date` AS `birth_date`, `p`.`death_date` AS `death_date`, `p`.`burial_date` AS `burial_date`, `p`.`birth_place` AS `birth_place`, `p`.`death_place` AS `death_place`, `p`.`burial_place` AS `burial_place`, `p`.`profession` AS `profession`, `p`.`company` AS `company`, `p`.`interests` AS `interests`, `p`.`activities` AS `activities`, `p`.`bio_notes` AS `bio_notes`, `p`.`death_cause` AS `death_cause`, `p`.`email` AS `email`, `p`.`website` AS `website`, `p`.`blog` AS `blog`, `p`.`photo_site` AS `photo_site`, `p`.`home_tel` AS `home_tel`, `p`.`work_tel` AS `work_tel`, `p`.`mobile` AS `mobile`, `p`.`address` AS `address`, `p`.`other_contact` AS `other_contact`, `p`.`sort_order` AS `sort_order`, `p`.`label_color` AS `label_color`, `p`.`photo_data` AS `photo_data`, `p`.`created_by` AS `created_by`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at`, `p`.`notes` AS `notes`, `f`.`name` AS `family_name`, `f`.`creator_id` AS `family_creator_id`, `u`.`full_name` AS `created_by_name`, (select count(0) from `relationships` `r` where (`r`.`parent_id` = `p`.`id`)) AS `children_count`, (select count(0) from `relationships` `r` where (`r`.`child_id` = `p`.`id`)) AS `parent_count`, (select count(0) from `unions` `un` where (((`un`.`person1_id` = `p`.`id`) or (`un`.`person2_id` = `p`.`id`)) and (`un`.`is_current` = 1))) AS `current_unions_count` FROM ((`persons` `p` left join `families` `f` on((`p`.`family_id` = `f`.`id`))) left join `users` `u` on((`p`.`created_by` = `u`.`id`))) ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
