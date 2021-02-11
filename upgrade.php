<?php
// +--------------------------------------------------------------------------+
// | Election Plugin - glFusion CMS                                              |
// +--------------------------------------------------------------------------+
// | upgrade.php                                                              |
// |                                                                          |
// | Upgrade routines                                                         |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2009-2017 by the following authors:                        |
// |                                                                          |
// | Mark R. Evans          mark AT glfusion DOT org                          |
// |                                                                          |
// | Copyright (C) 2000-2008 by the following authors:                        |
// |                                                                          |
// | Authors: Tony Bibbs       - tony AT tonybibbs DOT com                    |
// |          Tom Willett      - twillett AT users DOT sourceforge DOT net    |
// |          Blaine Lang      - langmail AT sympatico DOT ca                 |
// |          Dirk Haun        - dirk AT haun-online DOT de                   |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | This program is free software; you can redistribute it and/or            |
// | modify it under the terms of the GNU General Public License              |
// | as published by the Free Software Foundation; either version 2           |
// | of the License, or (at your option) any later version.                   |
// |                                                                          |
// | This program is distributed in the hope that it will be useful,          |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with this program; if not, write to the Free Software Foundation,  |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.          |
// |                                                                          |
// +--------------------------------------------------------------------------+

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}
use Election\DB;
use Election\Config;

function election_upgrade()
{
    global $_TABLES, $_CONF;

    $currentVersion = DB_getItem($_TABLES['plugins'],'pi_version',"pi_name='elections'");

    switch( $currentVersion ) {
        case '2.0.0' :
        case '2.0.1' :
        case '2.0.2' :
        case '2.0.3' :
        case '2.0.4' :
        case '2.0.5' :
        case '2.0.6' :
        case '2.0.7' :
        case '2.0.8' :
        case '2.0.9' :
        case '2.1.0' :
            $c = config::get_instance();
            $c->add('displayblocks',0, 'select', 0, 0, 13, 85, true, 'elections');

        case '2.1.1' :
            DB_query("ALTER TABLE {$_TABLES['electionanswers']} CHANGE `pid` `pid` VARCHAR(128) NOT NULL DEFAULT '';",1);
            DB_query("ALTER TABLE {$_TABLES['electionquestions']} CHANGE `pid` `pid` VARCHAR(128) NOT NULL;",1);
            DB_query("ALTER TABLE {$_TABLES['electiontopics']} CHANGE `pid` `pid` VARCHAR(128) NOT NULL;",1);
            DB_query("ALTER TABLE {$_TABLES['electionvoters']} CHANGE `pid` `pid` VARCHAR(128) NOT NULL DEFAULT '';",1);

        case '2.1.2' :
            DB_query("ALTER TABLE {$_TABLES['electionvoters']} ADD `uid` MEDIUMINT NOT NULL DEFAULT '1' AFTER `ipaddress`;",1);
            DB_query("ALTER TABLE {$_TABLES['electiontopics']} ADD `login_required` TINYINT NOT NULL DEFAULT '0' AFTER `is_open`;",1);

        case '2.2.0' :
            DB_query("ALTER TABLE {$_TABLES['electionvoters']} CHANGE `pid` `pid` VARCHAR(128) NOT NULL DEFAULT '';",1);

        case '2.2.1' :
            DB_query("ALTER TABLE {$_TABLES['electionvoters']} ADD INDEX(`pid`);",1);
            DB_query("ALTER TABLE {$_TABLES['electiontopics']} ADD `description` TEXT NULL DEFAULT NULL AFTER `topic`;",1);

        case '2.2.2' :
            // no changes

        case '2.2.3' :
            DB_query("UPDATE `{$_TABLES['electiontopics']}` SET `date` = '1970-01-01 00:00:00' WHERE CAST(`date` AS CHAR(20)) = '0000-00-00 00:00:00';");
            DB_query("UPDATE `{$_TABLES['electiontopics']}` SET `date` = '1970-01-01 00:00:00' WHERE CAST(`date` AS CHAR(20)) = '1000-01-01 00:00:00';");
            DB_query("ALTER TABLE `{$_TABLES['electiontopics']}` CHANGE COLUMN `date` `date` DATETIME NULL DEFAULT NULL;",1);

        case '2.2.4':
            $tbl_topics = DB::table('topics');
            DB_query("ALTER TABLE $tbl_topics ADD opens datetime not null default '1970-01-01 00:00:00' after `date`", 1);
            DB_query("ALTER TABLE $tbl_topics ADD closes datetime not null default '9999-12-31 23:59:59' after `opens`", 1);
            DB_query("ALTER TABLE $tbl_topics ADD results_gid mediumint(8) unsigned not null default '1' after group_id", 1);

            // Consolidate the permission array to just voting and results group IDs.
            // If login is required, make sure the group is not "All Users".
            // Else if anonymous has access, set the group to "All Users".
            // Otherwise use the existing group ID.
            // Set the results access group to the same as the voting group.
            $res = DB_query("SELECT pid, group_id, perm_group, perm_members, perm_anon, login_required
                FROM " . DB::table('topics'));
            while ($A = DB_fetchArray($res, false)) {
                $voting_grp = $A['group_id'];
                if ($A['perm_members'] == 2) {
                    $voting_grp = 13;
                }
                if ($A['login_required']) {
                   if ($voting_grp == 2) {
                       $voting_grp = 13;
                   }
                } elseif ($A['perm_anon'] == 2) {
                    $voting_grp = 2;
                }
                $voting_grp = (int)$voting_grp;
                if ($voting_grp != $A['group_id']) {
                    $sql = "UPDATE " . DB::table('topics') . "
                        SET results_gid = $voting_grp, group_id = $voting_grp
                        WHERE pid = '" . DB_escapeString($A['pid']) . "'";
                    DB_query($sql);
                }
            }
            DB_query("ALTER TABLE $tbl_topics DROP perm_owner", 1);
            DB_query("ALTER TABLE $tbl_topics DROP perm_group", 1);
            DB_query("ALTER TABLE $tbl_topics DROP perm_members", 1);
            DB_query("ALTER TABLE $tbl_topics DROP perm_anon", 1);
            DB_query("ALTER TABLE $tbl_topics DROP login_required", 1);
            DB_query("ALTER TABLE $tbl_topics DROP voters", 1);
            DB_query("ALTER TABLE $tbl_topics DROP questions", 1);
            DB_query("ALTER TABLE $tbl_topics DROP statuscode", 1);
            DB_query("ALTER TABLE $tbl_topics ADD KEY `idx_enabled` (`is_open`)", 1);
            DB_query(
                "DELETE FROM `{$_TABLES['groups']}` WHERE grp_id='" .
                ucfirst(Config::PI_NAME) . " Admin'"
            );
            DB_query("ALTER TABLE " . DB::table('voters') . " ADD votedata text");
            DB_query("ALTER TABLE " . DB::table('voters') . " ADD pub_key varchar(20) not null default ''");

        default :
            DB_query(
                "UPDATE {$_TABLES['plugins']}
                SET pi_version='" . Config::get('pi_version') . "',
                pi_gl_version='" . Config::get('gl_version') . "'
                WHERE pi_name='" . Config::PI_NAME . "' LIMIT 1"
            );
            break;
    }
    if (DB_getItem(
        $_TABLES['plugins'],
        'pi_version',
        "pi_name='" . Config::PI_NAME . "'"
        ) == Config::get('pi_version')
    ) {
        return true;
    } else {
        return false;
    }
}
?>
