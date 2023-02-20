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
 * @copyright   Copyright (c) 2009-2022 The Above Authors
 * @package     elections
 * @version     v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}
global $_SQL, $ELECTION_UPGRADE;

use Elections\DB;

$_SQL[DB::key('answers')] = "CREATE TABLE " . DB::table('answers') . " (
  tid mediumint NOT NULL,
  qid mediumint NOT NULL,
  aid tinyint(3) unsigned NOT NULL default '0',
  answer varchar(255) default NULL,
  votes mediumint(8) unsigned default NULL,
  remark varchar(255) NULL,
  PRIMARY KEY (tid, qid, aid)
) ENGINE=MyISAM
";

$_SQL[DB::key('questions')] = "CREATE TABLE " . DB::table('questions') . " (
    tid mediumint unsigned NOT NULL,
    qid mediumint unsigned NOT NULL,
    ans_sort tinyint(1) unsigned NOT NULL default 0,
    question varchar(255) NOT NULL,
    PRIMARY KEY (tid, qid)
) ENGINE=MyISAM
";

$_SQL[DB::key('topics')] = "CREATE TABLE " . DB::table('topics') . " (
  `tid` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(128) NOT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `opens` datetime DEFAULT NULL,
  `closes` datetime DEFAULT NULL,
  `display` tinyint(4) NOT NULL DEFAULT 0,
  `status` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `hideresults` tinyint(1) NOT NULL DEFAULT 0,
  `commentcode` tinyint(4) NOT NULL DEFAULT 0,
  `owner_id` mediumint(8) unsigned NOT NULL DEFAULT 1,
  `group_id` mediumint(8) unsigned NOT NULL DEFAULT 1,
  `results_gid` mediumint(8) unsigned NOT NULL DEFAULT 1,
  `voteaccess` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `rnd_questions` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `decl_winner` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `show_remarks` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`tid`),
  UNIQUE `idx_pid` (`pid`),
  KEY `questions_date` (`created`),
  KEY `questions_display` (`display`),
  KEY `questions_commentcode` (`commentcode`),
  KEY `idx_enabled` (`status`)
) ENGINE=MyISAM";

$_SQL[DB::key('voters')] = "CREATE TABLE " . DB::table('voters') . " (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `tid` mediumint NOT NULL DEFAULT '',
  `ipaddress` varchar(255) NOT NULL DEFAULT '',
  `uid` mediumint(8) NOT NULL DEFAULT 1,
  `date` int(10) unsigned DEFAULT NULL,
  `votedata` text DEFAULT NULL,
  `pub_key` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `topicid` (`tid`)
) ENGINE=MyISAM";

$ELECTION_UPGRADE = array(
    '0.2.0' => array(
        'ALTER TABLE ' . DB::table('topics') . ' ADD show_remarks tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER decl_winner',
    ),
    '0.3.0' => array(
        "CREATE TABLE " . DB::table('votes') . " (
          `vid` varchar(20) NOT NULL,
          `tid` MEDIUMINT NOT NULLNULL,
          `qid` int(11) unsigned NOT NULL,
          `aid` int(11) unsigned NOT NULL,
          PRIMARY KEY (`vid`),
          KEY `idx_question` (`tid`, `qid`)
        ) ENGINE=MyISAM",
        'ALTER TABLE ' . DB::table('voters') . " CHANGE ipaddress ipaddress varchar(255) NOT NULL default ''",
        'ALTER TABLE ' . DB::table('topics') . " DROP PRIMARY KEY",
        'ALTER TABLE ' . DB::table('topics') . " ADD tid mediumint unsigned NOT NULL auto_increment primary key first",
        'ALTER TABLE ' . DB::table('topics') . " ADD UNIQUE `idx_pid` (`pid`)",
        // Drop the primary keys for questions and answers to convert to a "tid" field.
        // The "pid" field will be deleted and the key recreated in upgrade.php
        'ALTER TABLE ' . DB::table('questions') . " DROP PRIMARY KEY",
        'ALTER TABLE ' . DB::table('questions') . " ADD tid mediumint unsigned NOT NULL FIRST",
        'ALTER TABLE ' . DB::table('questions') . " ADD PRIMARY KEY (`tid`, `qid`)",
        'ALTER TABLE ' . DB::table('answers') . " DROP PRIMARY KEY",
        'ALTER TABLE ' . DB::table('answers') . " ADD tid mediumint unsigned NOT NULL FIRST",
        'ALTER TABLE ' . DB::table('answers') . " ADD PRIMARY KEY (`tid`, `qid`, `aid`)",
        'ALTER TABLE ' . DB::table('voters') . " CHANGE id id mediumint unsigned NOT NULL AUTO_INCREMENT",
        'ALTER TABLE ' . DB::table('voters') . " ADD tid mediumint unsigned NOT NULL AFTER `id`",
        'ALTER TABLE ' . DB::table('voters') . " DROP KEY IF EXISTS `pollid`",
        'ALTER TABLE ' . DB::table('voters') . " ADD KEY `topicid` (`tid`)",
        'ALTER TABLE ' . DB::table('questions') . " ADD ans_sort tinyint(1) unsigned NOT NULL default 0 after qid",
        // Update the ans_sort column based on questions.rnd_answers before dropping that column
    )
);

$_SQL[DB::key('votes')] = $ELECTION_UPGRADE['0.3.0'][0];

