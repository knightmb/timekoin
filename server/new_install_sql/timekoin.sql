SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `timekoin`
--

-- --------------------------------------------------------

--
-- Table structure for table `active_peer_list`
--

CREATE TABLE IF NOT EXISTS `active_peer_list` (
  `IP_Address` varchar(46) NOT NULL,
  `domain` varchar(256) NOT NULL,
  `subfolder` varchar(256) NOT NULL,
  `port_number` smallint(5) unsigned NOT NULL,
  `last_heartbeat` bigint(20) unsigned NOT NULL,
  `join_peer_list` bigint(20) unsigned NOT NULL,
  `failed_sent_heartbeat` smallint(5) unsigned NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `timestamp` bigint(20) unsigned NOT NULL,
  `log` varchar(256) NOT NULL,
  `attribute` varchar(2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- Table structure for table `balance_index`
--

CREATE TABLE IF NOT EXISTS `balance_index` (
  `block` bigint(20) unsigned NOT NULL,
  `public_key_hash` varchar(64) NOT NULL,
  `balance` bigint(20) unsigned NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- Table structure for table `generating_peer_list`
--

CREATE TABLE IF NOT EXISTS `generating_peer_list` (
  `public_key` text NOT NULL,
  `join_peer_list` bigint(20) unsigned NOT NULL,
  `last_generation` bigint(20) unsigned NOT NULL,
  `IP_Address` varchar(46) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `generating_peer_queue`
--

CREATE TABLE IF NOT EXISTS `generating_peer_queue` (
  `timestamp` bigint(20) unsigned NOT NULL,
  `public_key` text NOT NULL,
  `IP_Address` varchar(46) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `ip_activity`
--

CREATE TABLE IF NOT EXISTS `ip_activity` (
  `timestamp` bigint(20) unsigned NOT NULL,
  `ip` varchar(46) NOT NULL,
  `attribute` varchar(2) NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- Table structure for table `ip_banlist`
--

CREATE TABLE IF NOT EXISTS `ip_banlist` (
  `when` bigint(20) unsigned NOT NULL,
  `ip` varchar(46) NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- Table structure for table `main_loop_status`
--

CREATE TABLE IF NOT EXISTS `main_loop_status` (
  `field_name` varchar(32) NOT NULL,
  `field_data` bigint(20) unsigned NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

--
-- Dumping data for table `main_loop_status`
--

INSERT INTO `main_loop_status` (`field_name`, `field_data`) VALUES
('State Goes Here', 0);

-- --------------------------------------------------------

--
-- Table structure for table `my_keys`
--

CREATE TABLE IF NOT EXISTS `my_keys` (
  `field_name` varchar(32) NOT NULL,
  `field_data` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `my_keys`
--

INSERT INTO `my_keys` (`field_name`, `field_data`) VALUES
('server_private_key', 'Private Key Goes Here'),
('server_public_key', 'Public Key Goes Here');

-- --------------------------------------------------------

--
-- Table structure for table `my_transaction_queue`
--

CREATE TABLE IF NOT EXISTS `my_transaction_queue` (
  `timestamp` bigint(20) unsigned NOT NULL,
  `public_key` text NOT NULL,
  `crypt_data1` varchar(4096) NOT NULL,
  `crypt_data2` varchar(4096) NOT NULL,
  `crypt_data3` varchar(4096) NOT NULL,
  `hash` varchar(64) NOT NULL,
  `attribute` varchar(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `new_peers_list`
--

CREATE TABLE IF NOT EXISTS `new_peers_list` (
  `IP_Address` varchar(46) NOT NULL,
  `domain` varchar(256) NOT NULL,
  `subfolder` varchar(256) NOT NULL,
  `port_number` smallint(5) unsigned NOT NULL,
  `poll_failures` tinyint(3) unsigned NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE IF NOT EXISTS `options` (
  `field_name` varchar(32) NOT NULL,
  `field_data` varchar(4096) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `options`
--

INSERT INTO `options` (`field_name`, `field_data`) VALUES
('allow_ambient_peer_restart', '0'),
('allow_LAN_peers', '0'),
('auto_update_generation_IP', '1'),
('cli_mode', '1'),
('cli_port', ''),
('default_timezone', ''),
('first_contact_server', '---ip=---domain=timekoin.net---subfolder=timekoin---port=80---end'),
('first_contact_server', '---ip=107.197.232.123---domain=---subfolder=timekoin---port=80---end'),
('first_contact_server', '---ip=67.205.179.119---domain=---subfolder=timekoin---port=80---end'),
('first_contact_server', '---ip=2604:a880:400:d0::17c8:3001---domain=---subfolder=timekoin---port=80---end'),
('first_contact_server', '---ip=161.35.238.251---domain=---subfolder=timekoin---port=80---end'),
('first_contact_server', '---ip=2604:a880:4:1d0::14b:5000---domain=---subfolder=timekoin---port=80---end'),
('first_contact_server', '---ip=159.203.1.136---domain=---subfolder=timekoin---port=80---end'),
('first_contact_server', '---ip=2604:a880:cad:d0::c67:5001---domain=---subfolder=timekoin---port=80---end'),
('first_contact_server', '---ip=159.65.87.71---domain=---subfolder=timekoin---port=80---end'),
('first_contact_server', '---ip=2a03:b0c0:1:d0::cb8:5001---domain=---subfolder=timekoin---port=80---end'),
('generate_currency', '0'),
('generating_peers_hash', '0'),
('generation_IP', ''),
('generation_IP_v6', ''),
('generation_key_crypt', ''),
('max_active_peers', '8'),
('max_new_peers', '15'),
('network_mode', '1'),
('password', '5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5'),
('peer_failure_grade', '30'),
('perm_peer_priority', '0'),
('public_key_font_size', '3'),
('refresh_realtime_home', '5'),
('refresh_realtime_peerlist', '5'),
('refresh_realtime_queue', '5'),
('server_domain', ''),
('server_port_number', '1528'),
('server_request_max', '500'),
('server_subfolder', 'timekoin'),
('standard_tabs_settings', '255'),
('super_peer', '0'),
('timekoin_start_time', '0'),
('transaction_history_hash', '0'),
('transaction_queue_hash', '0'),
('trans_history_check', '0'),
('username', 'ee27af0f210c0e6d81cb852197a04cb21f11bad4967365b5023ebd8cb513cbe8');

-- --------------------------------------------------------

--
-- Table structure for table `quantum_balance_index`
--

CREATE TABLE IF NOT EXISTS `quantum_balance_index` (
  `public_key_hash` varchar(64) NOT NULL,
  `max_foundation` bigint(20) unsigned NOT NULL,
  `balance` bigint(20) unsigned NOT NULL,
  KEY `qbi_index` (`public_key_hash`(4))
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `quantum_balance_index`
--

INSERT INTO `quantum_balance_index` (`public_key_hash`, `max_foundation`, `balance`) VALUES
('', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `transaction_foundation`
--

CREATE TABLE IF NOT EXISTS `transaction_foundation` (
  `block` bigint(20) unsigned NOT NULL,
  `hash` varchar(64) NOT NULL,
  PRIMARY KEY (`block`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_history`
--

CREATE TABLE IF NOT EXISTS `transaction_history` (
  `timestamp` bigint(20) unsigned NOT NULL,
  `public_key_from` text NOT NULL,
  `public_key_to` text NOT NULL,
  `crypt_data1` varchar(4096) NOT NULL,
  `crypt_data2` varchar(4096) NOT NULL,
  `crypt_data3` varchar(4096) NOT NULL,
  `hash` varchar(64) NOT NULL,
  `attribute` varchar(1) NOT NULL,
  KEY `timestamp` (`timestamp`),
  KEY `public_key_from` (`public_key_from`(74)),
  KEY `public_key_to` (`public_key_to`(74)),
  KEY `hash` (`hash`(8)),
  KEY `attribute` (`attribute`(1))
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `transaction_queue`
--

CREATE TABLE IF NOT EXISTS `transaction_queue` (
  `timestamp` bigint(20) unsigned NOT NULL,
  `public_key` text NOT NULL,
  `crypt_data1` varchar(4096) NOT NULL,
  `crypt_data2` varchar(4096) NOT NULL,
  `crypt_data3` varchar(4096) NOT NULL,
  `hash` varchar(64) NOT NULL,
  `attribute` varchar(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
