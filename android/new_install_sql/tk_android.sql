SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `tk_android`
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
  `last_heartbeat` int(12) unsigned NOT NULL,
  `join_peer_list` int(10) unsigned NOT NULL,
  `failed_sent_heartbeat` smallint(5) unsigned NOT NULL,
  `code` varchar(256) NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

--
-- Dumping data for table `active_peer_list`
--


-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `timestamp` int(10) unsigned NOT NULL,
  `log` varchar(256) NOT NULL,
  `attribute` varchar(2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `activity_logs`
--


-- --------------------------------------------------------

--
-- Table structure for table `address_book`
--

CREATE TABLE IF NOT EXISTS `address_book` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(256) NOT NULL,
  `easy_key` varchar(48) NOT NULL,
  `full_key` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`(3)),
  KEY `easy_key` (`easy_key`(3)),
  KEY `full_key` (`full_key`(80))
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `address_book`
--


-- --------------------------------------------------------

--
-- Table structure for table `data_cache`
--

CREATE TABLE IF NOT EXISTS `data_cache` (
  `field_name` varchar(32) NOT NULL,
  `field_data` mediumtext NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `data_cache`
--

INSERT INTO `data_cache` (`field_name`, `field_data`) VALUES
('billfold_balance', ''),
('graph_data_amount_total', ''),
('graph_data_range_recv', ''),
('graph_data_range_sent', ''),
('graph_data_trans_total', ''),
('trans_history_sent_from', ''),
('trans_history_sent_to', '');

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
('server_private_key', 'Private Key Here'),
('server_public_key', 'Public Key Here');

-- --------------------------------------------------------

--
-- Table structure for table `new_peers_list`
--

CREATE TABLE IF NOT EXISTS `new_peers_list` (
  `IP_Address` varchar(46) NOT NULL,
  `domain` varchar(256) NOT NULL,
  `subfolder` varchar(256) NOT NULL,
  `port_number` smallint(5) unsigned NOT NULL,
  `poll_failures` tinyint(3) unsigned NOT NULL,
  `code` varchar(256) NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

--
-- Dumping data for table `new_peers_list`
--


-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE IF NOT EXISTS `options` (
  `field_name` varchar(32) NOT NULL,
  `field_data` varchar(256) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `options`
--

INSERT INTO `options` (`field_name`, `field_data`) VALUES
('default_timezone', ''),
('first_contact_server', '---ip=75.146.8.53---domain=---subfolder=timekoin---port=1528---code=guest---end'),
('first_contact_server', '---ip=75.146.8.54---domain=---subfolder=timekoin---port=80---code=guest---end'),
('first_contact_server', '---ip=75.146.8.55---domain=---subfolder=timekoin.com/timekoin---port=80---code=guest---end'),
('first_contact_server', '---ip=75.146.8.56---domain=---subfolder=timekoin---port=80---code=guest---end'),
('first_contact_server', '---ip=75.146.8.61---domain=---subfolder=---port=1528---code=guest---end'),
('max_active_peers', '5'),
('max_new_peers', '5'),
('password', '5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5'),
('public_key_font_size', '3'),
('refresh_realtime_home', '10'),
('standard_tabs_settings', '223'),
('update_available', '0'),
('username', 'ee27af0f210c0e6d81cb852197a04cb21f11bad4967365b5023ebd8cb513cbe8');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_queue`
--

CREATE TABLE IF NOT EXISTS `transaction_queue` (
  `timestamp` int(10) unsigned NOT NULL,
  `public_key` text NOT NULL,
  `crypt_data1` varchar(256) NOT NULL,
  `crypt_data2` varchar(256) NOT NULL,
  `crypt_data3` varchar(256) NOT NULL,
  `hash` varchar(64) NOT NULL,
  `attribute` varchar(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `transaction_queue`
--


