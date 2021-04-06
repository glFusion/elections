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
use Elections\DB;
use Elections\Config;

function election_upgrade($dvlp=false)
{
    global $_PLUGIN_INFO;

    $pi_name = Config::get('pi_name');
    if (isset($_PLUGIN_INFO[$pi_name])) {
        $current_ver = $_PLUGIN_INFO[$pi_name]['pi_version'];
    } else {
        return false;
    }
    $installed_ver = plugin_chkVersion_elections();

    // Check and set the version if not already up to date.
    // For updates with no SQL changes
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!ELECTION_do_set_version($installed_ver)) return false;
        $current_ver = $installed_ver;
    }
    USES_lib_install();
    require_once __DIR__ . '/install_defaults.php';
    _update_config($pi_name, $electionConfigData);
    return true;
}


/**
 * Actually perform any sql updates.
 * Gets the sql statements from the $UPGRADE array defined (maybe)
 * in the SQL installation file.
 *
 * @param   string  $version    Version being upgraded TO
 * @param   boolean $ignore_error   True to ignore SQL errors.
 * @return  boolean     True on success, False on failure
 */
function ELECTION_do_upgrade_sql($version, $ignore_error = false)
{
    global $_TABLES, $ELECTION_UPGRADE, $_DB_dbms, $_VARS;

    // If no sql statements passed in, return success
    if (
        !isset($ELECTION_UPGRADE[$version]) ||
        !is_array($ELECTION_UPGRADE[$version])
    ) {
        return true;
    }

    if (
        $_DB_dbms == 'mysql' &&
        isset($_VARS['database_engine']) &&
        $_VARS['database_engine'] == 'InnoDB'
    ) {
        $use_innodb = true;
    } else {
        $use_innodb = false;
    }

    // Execute SQL now to perform the upgrade
    COM_errorLog("--- Updating Elections to version $version");
    foreach($ELECTION_UPGRADE[$version] as $sql) {
        if ($use_innodb) {
            $sql = str_replace('MyISAM', 'InnoDB', $sql);
        }

        COM_errorLog("Elections Plugin $version update: Executing SQL => $sql");
        try {
            DB_query($sql, '1');
            if (DB_error()) {
                // check for error here for glFusion < 2.0.0
                COM_errorLog('SQL Error during update');
                //if (!$ignore_error) return false;
            }
        } catch (Exception $e) {
            COM_errorLog('SQL Error ' . $e->getMessage());
            //if (!$ignore_error) return false;
        }
    }
    COM_errorLog("--- Elections plugin SQL update to version $version done");
    return true;
}


/**
 * Update the plugin version number in the database.
 * Called at each version upgrade to keep up to date with
 * successful upgrades.
 *
 * @param   string  $ver    New version to set
 * @return  boolean         True on success, False on failure
 */
function ELECTION_do_set_version($ver)
{
    global $_TABLES, $_PLUGIN_INFO;

    // now update the current version number.
    $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '$ver',
            pi_gl_version = '" . Config::get('gl_version') . "',
            pi_homepage = '" . Config::get('pi_url') . "'
        WHERE pi_name = '" . Config::get('pi_name') . "'";

    $res = DB_query($sql, 1);
    if (DB_error()) {
        COM_errorLog("Error updating the " . Config::get('pi_display_name') . " Plugin version");
        return false;
    } else {
        COM_errorLog(Config::get('pi_display_name') . " version set to $ver");
        // Set in-memory config vars to avoid tripping SHOP_isMinVersion();
        Config::set('pi_version', $ver);
        $_PLUGIN_INFO[Config::get('pi_name')]['pi_version'] = $ver;
        return true;
    }
}

