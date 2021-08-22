SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `tk_billfold_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `active_peer_list`
--

CREATE TABLE `active_peer_list` (
  `IP_Address` varchar(46) NOT NULL,
  `domain` varchar(256) NOT NULL,
  `subfolder` varchar(256) NOT NULL,
  `port_number` smallint(5) UNSIGNED NOT NULL,
  `last_heartbeat` bigint(20) UNSIGNED NOT NULL,
  `join_peer_list` bigint(20) UNSIGNED NOT NULL,
  `failed_sent_heartbeat` smallint(5) UNSIGNED NOT NULL,
  `code` varchar(256) NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `timestamp` bigint(20) UNSIGNED NOT NULL,
  `log` varchar(256) NOT NULL,
  `attribute` varchar(2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `address_book`
--

CREATE TABLE `address_book` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(256) NOT NULL,
  `easy_key` varchar(48) NOT NULL,
  `full_key` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `data_cache`
--

CREATE TABLE `data_cache` (
  `username` varchar(64) NOT NULL,
  `field_name` varchar(32) NOT NULL,
  `field_data` mediumtext NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `data_cache`
--

INSERT INTO `data_cache` (`username`, `field_name`, `field_data`) VALUES
('', 'billfold_balance', ''),
('', 'graph_data_amount_total', ''),
('', 'graph_data_range_recv', ''),
('', 'graph_data_range_sent', ''),
('', 'graph_data_trans_total', ''),
('', 'trans_history_sent_from', '');

-- --------------------------------------------------------

--
-- Table structure for table `main_loop_status`
--

CREATE TABLE `main_loop_status` (
  `field_name` varchar(32) NOT NULL,
  `field_data` bigint(20) UNSIGNED NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `my_keys`
--

CREATE TABLE `my_keys` (
  `field_name` varchar(32) NOT NULL,
  `field_data` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `my_keys`
--

INSERT INTO `my_keys` (`field_name`, `field_data`) VALUES
('server_private_key', '='),
('server_public_key', '=');

-- --------------------------------------------------------

--
-- Table structure for table `new_peers_list`
--

CREATE TABLE `new_peers_list` (
  `IP_Address` varchar(46) NOT NULL,
  `domain` varchar(256) NOT NULL,
  `subfolder` varchar(256) NOT NULL,
  `port_number` smallint(5) UNSIGNED NOT NULL,
  `poll_failures` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(256) NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE `options` (
  `field_name` varchar(32) NOT NULL,
  `field_data` varchar(4096) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `options`
--

INSERT INTO `options` (`field_name`, `field_data`) VALUES
('default_timezone', ''),
('email_FromAddress', ''),
('email_FromName', ''),
('email_Host', ''),
('email_Password', ''),
('email_Port', ''),
('email_Required', '0'),
('email_SMTPAuth', ''),
('email_Username', ''),
('first_contact_server', '---ip=---domain=timekoin.net---subfolder=timekoin---port=80---code=guest---end'),
('first_contact_server', '---ip=107.197.232.123---domain=---subfolder=timekoin---port=80---code=guest---end'),
('first_contact_server', '---ip=67.205.179.119---domain=---subfolder=timekoin---port=80---code=guest---end'),
('first_contact_server', '---ip=2604:a880:400:d0::17c8:3001---domain=---subfolder=timekoin---port=80---code=guest---end'),
('first_contact_server', '---ip=161.35.238.251---domain=---subfolder=timekoin---port=80---code=guest---end'),
('first_contact_server', '---ip=2604:a880:4:1d0::14b:5000---domain=---subfolder=timekoin---port=80---code=guest---end'),
('max_active_peers', '5'),
('max_new_peers', '10'),
('password', '5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5'),
('private_key_crypt', '0'),
('public_key_font_size', '3'),
('refresh_realtime_home', '10'),
('standard_tabs_settings', '255'),
('update_available', '0'),
('username', 'ee27af0f210c0e6d81cb852197a04cb21f11bad4967365b5023ebd8cb513cbe8');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_queue`
--

CREATE TABLE `transaction_queue` (
  `timestamp` bigint(20) UNSIGNED NOT NULL,
  `public_key` text NOT NULL,
  `crypt_data1` varchar(4096) NOT NULL,
  `crypt_data2` varchar(4096) NOT NULL,
  `crypt_data3` varchar(4096) NOT NULL,
  `hash` varchar(64) NOT NULL,
  `attribute` varchar(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `timestamp` bigint(20) NOT NULL,
  `status` mediumint(9) NOT NULL,
  `username` varchar(64) NOT NULL,
  `password` varchar(64) NOT NULL,
  `email` varchar(256) NOT NULL,
  `settings` text NOT NULL,
  `address_book` mediumtext NOT NULL,
  `my_keys` mediumtext NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `address_book`
--
ALTER TABLE `address_book`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`(3)),
  ADD KEY `easy_key` (`easy_key`(3)),
  ADD KEY `full_key` (`full_key`(80));

--
-- Indexes for table `data_cache`
--
ALTER TABLE `data_cache`
  ADD KEY `username` (`username`(6)) USING BTREE;

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`) USING BTREE,
  ADD KEY `status` (`status`) USING BTREE,
  ADD KEY `Username` (`username`(8)) USING BTREE;

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `address_book`
--
ALTER TABLE `address_book`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;


