<?php
/**
 * Database creation and update statements for the Election plugin.
 *
 * @author      Tony Bibbs <tony AT tonybibbs DOT com>
 * @author      Mark Limburg <mlimburg AT users DOT sourceforge DOT net>
 * @author      Jason Whittenburg - jwhitten AT securitygeeks DOT com>
 * @author      Dirk Haun         - dirk AT haun-online DOT de>
 * @author      Trinity Bays      - trinity93 AT gmail DOT com>                 |
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 The Above Authors
 * @package     elections
 * @version     v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}
use Elections\DB;

$_SQL[DB::key('answers')] = "CREATE TABLE " . DB::table('answers') . " (
  pid varchar(128) NOT NULL default '',
  qid mediumint(9) NOT NULL default 0,
  aid tinyint(3) unsigned NOT NULL default '0',
  answer varchar(255) default NULL,
  votes mediumint(8) unsigned default NULL,
  remark varchar(255) NULL,
  PRIMARY KEY (pid, qid, aid)
) ENGINE=MyISAM
";

$_SQL[DB::key('questions')] = "CREATE TABLE " . DB::table('questions') . " (
    qid mediumint(9) NOT NULL DEFAULT '0',
    pid varchar(128) NOT NULL,
    question varchar(255) NOT NULL,
    PRIMARY KEY (qid, pid)
) ENGINE=MyISAM
";

$_SQL[DB::key('topics')] = "CREATE TABLE " . DB::table('topics') . " (
 `pid` varchar(128) NOT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created` bigint(11) DEFAULT current_timestamp(),
  `opens` bigint(11) unsigned DEFAULT 0,
  `closes` bigint(11) DEFAULT 253402300799,
  `display` tinyint(4) NOT NULL DEFAULT 0,
  `status` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `hideresults` tinyint(1) NOT NULL DEFAULT 0,
  `commentcode` tinyint(4) NOT NULL DEFAULT 0,
  `owner_id` mediumint(8) unsigned NOT NULL DEFAULT 1,
  `group_id` mediumint(8) unsigned NOT NULL DEFAULT 1,
  `results_gid` mediumint(8) unsigned NOT NULL DEFAULT 1,
  `voteaccess` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `rnd_questions` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `rnd_answers` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `decl_winner` tinyint(1) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`pid`),
  KEY `idx_created` (`created`),
  KEY `idx_display` (`display`),
  KEY `idx_commentcode` (`commentcode`),
  KEY `idx_enabled` (`status`)
) ENGINE=MyISAM
";

$_SQL[DB::key('voters')] = "CREATE TABLE " . DB::table('voters') . " (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(128) NOT NULL DEFAULT '',
  `ipaddress` varchar(15) NOT NULL DEFAULT '',
  `uid` mediumint(8) NOT NULL DEFAULT 1,
  `date` int(10) unsigned DEFAULT NULL,
  `votedata` text DEFAULT NULL,
  `pub_key` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pollid` (`pid`)
) ENGINE=MyISAM
";
