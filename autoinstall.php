<?php
/**
 * Automatic installation functions for the Elections plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_DB_dbms, $_TABLES;

require_once __DIR__ . '/functions.inc';
require_once __DIR__ . '/sql/mysql_install.php';
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
        'table' => $_TABLES['elections_answers'],
        'sql' => $_SQL['elections_answers'],
    ),
    array(
        'type' => 'table',
        'table' => $_TABLES['elections_questions'],
        'sql' => $_SQL['electsions_questions'],
    ),
    array(
        'type' => 'table',
        'table' => $_TABLES['elections_topics'],
        'sql' => $_SQL['electsions_topics'],
    ),
    array(
        'type' => 'table',
        'table' => $_TABLES['elections_voters'],
        'sql' => $_SQL['electsions_voters'],
    ),
    array(
        'type' => 'table',
        'table' => $_TABLES['elections_votes'],
        'sql' => $_SQL['electsions_votes'],
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
            'elections_answers',
            'elections_topics',
            'elections_voters',
            'elections_questions',
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
