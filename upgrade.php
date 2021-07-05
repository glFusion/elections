<?php
/**
 * Upgrade routines for the Elections plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.1.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}
use Elections\DB;
use Elections\Config;


/**
 * Upgrade the plugin to the current version.
 *
 * @param   boolean $dvlp   True for development upgrade (ignores errors)
 * @return  boolean     True on success, False on failure
 */
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
    ELECTION_remove_old_files()
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
        // Set in-memory config vars
        Config::set('pi_version', $ver);
        $_PLUGIN_INFO[Config::get('pi_name')]['pi_version'] = $ver;
        return true;
    }
}


/**
 * Remove a file, or recursively remove a directory.
 *
 * @param   string  $dir    Directory name
 */
function ELECTION_rmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . '/' . $object)) {
                    ELECTION_rmdir($dir . '/' . $object);
                } else {
                    @unlink($dir . '/' . $object);
                }
            }
        }
        @rmdir($dir);
    } elseif (is_file($dir)) {
        @unlink($dir);
    }
}


/**
 * Remove deprecated files
 * Errors in unlink() and rmdir() are ignored.
 */
function ELECTION_remove_old_files()
{
    global $_CONF;

    $paths = array(
        // private/plugins/elections
        __DIR__ => array(
            // 0.1.2
            'templates/votes_num.thtml',
        ),
        // public_html/elections
        $_CONF['path_html'] . Config::PI_NAME => array(
        ),
        // admin/plugins/elections
        $_CONF['path_html'] . 'admin/plugins/' . Config::PI_NAME => array(
        ),
    );

    foreach ($paths as $path=>$files) {
        foreach ($files as $file) {
            SHOP_log("removing $path/$file");
            SHOP_rmdir("$path/$file");
        }
    }
}
