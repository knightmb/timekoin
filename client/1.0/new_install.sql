SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `tk_client_db`
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

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
-- Table structure for table `balance_index`
--

CREATE TABLE IF NOT EXISTS `balance_index` (
  `block` int(10) unsigned NOT NULL,
  `public_key_hash` varchar(32) NOT NULL,
  `balance` bigint(20) unsigned NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

--
-- Dumping data for table `balance_index`
--


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
-- Table structure for table `my_transaction_queue`
--

CREATE TABLE IF NOT EXISTS `my_transaction_queue` (
  `timestamp` int(10) unsigned NOT NULL,
  `public_key` text NOT NULL,
  `crypt_data1` varchar(256) NOT NULL,
  `crypt_data2` varchar(256) NOT NULL,
  `crypt_data3` varchar(256) NOT NULL,
  `hash` varchar(64) NOT NULL,
  `attribute` varchar(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `my_transaction_queue`
--


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
('first_contact_server', '---ip=---domain=timekoin2.dyndns.org---subfolder=timekoin---port=80---code=guest---end'),
('first_contact_server', '---ip=---domain=timekoin.kicks-ass.net---subfolder=---port=1528---code=guest---end'),
('first_contact_server', '---ip=---domain=amaranthinetech.com---subfolder=timekoin---port=80---code=guest---end'),
('first_contact_server', '---ip=---domain=wanip.org---subfolder=timekoin---port=80---code=guest---end'),
('first_contact_server', '---ip=---domain=newdwpinc.homedns.org---subfolder=timekoin---port=80---code=guest---end'),
('first_contact_server', '---ip=---domain=amt-wisp1.dyndns.org---subfolder=timekoin---port=88---code=guest---end'),
('first_contact_server', '---ip=---domain=timekoin.com---subfolder=timekoin---port=80---code=guest---end'),
('graph_data_amount_total', ''),
('graph_data_range_recv', ''),
('graph_data_range_sent', ''),
('graph_data_trans_total', ''),
('max_active_peers', '5'),
('max_new_peers', '10'),
('password', '5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5'),
('public_key_font_size', '3'),
('refresh_realtime_home', '10'),
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

