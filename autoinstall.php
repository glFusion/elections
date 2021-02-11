<?php
// +--------------------------------------------------------------------------+
// | Election Plugin - glFusion CMS                                              |
// +--------------------------------------------------------------------------+
// | autoinstall.php                                                          |
// |                                                                          |
// | glFusion Auto Installer module                                           |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2009-2015 by the following authors:                        |
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

global $_DB_dbms;

require_once __DIR__ . '/functions.inc';
require_once __DIR__ . '/sql/mysql_install.php';
use Elections\DB;
use Elections\Config;

$ucPI_NAME = ucfirst(Config::PI_NAME);

// +--------------------------------------------------------------------------+
// | Plugin installation options                                              |
// +--------------------------------------------------------------------------+

$INSTALL_plugin[Config::PI_NAME] = array(
    'installer' => array(
        'type' => 'installer',
        'version' => '1',
        'mode' => 'install',
    ),
    'plugin' => array(
        'type' => 'plugin',
        'name' => Config::get('pi_name'),
        'ver' => Config::get('pi_version'),
        'gl_ver' => Config::get('gl_version'),
        'url' => Config::get('pi_url'),
        'display' => Config::get('pi_display_name'),
    ),
    array(
        'type' => 'table',
        'table' => DB::table('answers'),
        'sql' => $_SQL[DB::key('answers')],
    ),
    array(
        'type' => 'table',
        'table' => DB::table('questions'),
        'sql' => $_SQL[DB::key('questions')],
    ),
    array(
        'type' => 'table',
        'table' => DB::table('topics'),
        'sql' => $_SQL[DB::key('topics')],
    ),
    array(
        'type' => 'table',
        'table' => DB::table('voters'),
        'sql' => $_SQL[DB::key('voters')],
    ),
    array(
        'type' => 'feature',
        'feature' => Config::PI_NAME . '.admin',
        'desc' => 'Full admin access to ' . $ucPI_NAME,
        'variable' => 'admin_feature_id',
    ),
    array(
        'type' => 'feature',
        'feature' => Config::PI_NAME . '.edit',
        'desc' => 'Ability to edit Election',
        'variable' => 'edit_feature_id',
    ),
    array(
        'type' => 'mapping',
        'findgroup' => 'Root',
        'feature' => 'edit_feature_id',
        'log' => 'Adding feature to the admin group',
    ),
    array(
        'type' => 'mapping',
        'findgroup' => 'Root',
        'feature' => 'admin_feature_id',
        'log' => 'Adding feature to the admin group',
    ),
    array(
        'type' => 'block',
        'name' => Config::PI_NAME . '_block',
        'title' => $ucPI_NAME,
        'phpblockfn' => 'phpblock_' . Config::PI_NAME,
        'block_type' => 'phpblock',
        'is_enabled' => 0,
        'group_id' => 1,
    ),
);


/**
* Puts the datastructures for this plugin into the glFusion database
*
* Note: Corresponding uninstall routine is in functions.inc
*
* @return   boolean True if successful False otherwise
*
*/
function plugin_install_elections()
{
    global $INSTALL_plugin;

    COM_errorLog("Attempting to install the " . Config::get('pi_display_name') . " plugin", 1);
    $ret = INSTALLER_install($INSTALL_plugin[Config::get('pi_name')]);
    if ($ret > 0) {
        return false;
    }
    return true;
}


/**
* Loads the configuration records for the Online Config Manager
*
* @return   boolean     true = proceed with install, false = an error occured
*
*/
function plugin_load_configuration_elections()
{
    global $_CONF;

    require_once __DIR__ . '/install_defaults.php';

    return plugin_initconfig_elections();
}


/**
* Automatic uninstall function for plugins
*
* @return   array
*
* This code is automatically uninstalling the plugin.
* It passes an array to the core code function that removes
* tables, groups, features and php blocks from the tables.
* Additionally, this code can perform special actions that cannot be
* foreseen by the core code (interactions with other plugins for example)
*
*/
function plugin_autouninstall_elections()
{
    $out = array (
        /* give the name of the tables, without $_TABLES[] */
        'tables' => array(
            DB::key('answers'),
            DB::key('topics'),
            DB::key('voters'),
            DB::key('questions'),
        ),
        /* give the full name of the group, as in the db */
        'groups' => array(
        ),
        /* give the full name of the feature, as in the db */
        'features' => array(
            Config::PI_NAME . '.admin',
            Config::PI_NAME . '.edit',
        ),
        /* give the full name of the block, including 'phpblock_', etc */
        'php_blocks' => array(
            'phpblock_' . Config::PI_NAME,
        ),
        /* give all vars with their name */
        'vars'=> array()
    );
    return $out;
}

?>
