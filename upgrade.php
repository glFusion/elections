<?php
/**
 * Upgrade routines for the Elections plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.1.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}
use Elections\DB;
use Elections\Config;
use glFusion\Database\Database;
use glFusion\Log\Log;

/** Include the table creation strings */
require_once __DIR__ . "/sql/mysql_install.php";


/**
 * Upgrade the plugin to the current version.
 *
 * @param   boolean $dvlp   True for development upgrade (ignores errors)
 * @return  boolean     True on success, False on failure
 */
function ELECTIONS_upgrade($dvlp=false)
{
    global $_PLUGIN_INFO;

    $pi_name = Config::PI_NAME;
    if (isset($_PLUGIN_INFO[$pi_name])) {
        $current_ver = $_PLUGIN_INFO[$pi_name]['pi_version'];
    } else {
        return false;
    }
    $installed_ver = plugin_chkVersion_elections();

    if (!COM_checkVersion($current_ver, '0.2.0')) {
        $current_ver = '0.2.0';
        if (!ELECTIONS_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!ELECTIONS_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.3.0')) {
        $current_ver = '0.3.0';
        if (!ELECTIONS_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!ELECTIONS_do_set_version($current_ver)) return false;
    }

    // Check and set the version if not already up to date.
    // For updates with no SQL changes
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!ELECTIONS_do_set_version($installed_ver)) return false;
        $current_ver = $installed_ver;
    }
    USES_lib_install();
    require_once __DIR__ . '/install_defaults.php';
    _update_config($pi_name, $electionConfigData);
    ELECTIONS_remove_old_files();
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
function ELECTIONS_do_upgrade_sql($version, $ignore_error = false)
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
    Log::write('system', Log::INFO, "--- Updating Elections to version $version");
    $db = Database::getInstance();
    foreach($ELECTION_UPGRADE[$version] as $sql) {
        if ($use_innodb) {
            $sql = str_replace('MyISAM', 'InnoDB', $sql);
        }

        Log::write('system', Log::DEBUG, "Elections Plugin $version update: Executing SQL => $sql");
        try {
            $db->conn->executeStatement($sql);
        } catch (Exception $e) {
            COM_errorLog(__FUNCTION__ . ': ' . $e->getMessage());
        }
    }
    Log::write('system', Log::INFO, "--- Elections plugin SQL update to version $version done");
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
function ELECTIONS_do_set_version($ver)
{
    global $_TABLES, $_PLUGIN_INFO;

    $db = Database::getInstance();
    try {
        $db->conn->update(
            $_TABLES['plugins'],
            array(
                'pi_version' => $ver,
                'pi_gl_version' => Config::get('gl_version'),
                'pi_homepage' => Config::get('pi_url'),
            ),
            array('pi_name' => Config::get('pi_name'))
            array(
                Database::STRING,
                Database::STRING,
                Database::STRING,
                Database::STRING,
            )
        );
        Config::set('pi_version', $ver);
        $_PLUGIN_INFO[Config::get('pi_name')]['pi_version'] = $ver;
    } catch (\Throwable $e) {
        Log::write('system', Log::ERROR, $e->getMessage());
        return false;
    }
    return true;
}


/**
 * Remove a file, or recursively remove a directory.
 *
 * @param   string  $dir    Directory name
 */
function ELECTIONS_rmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . '/' . $object)) {
                    ELECTIONS_rmdir($dir . '/' . $object);
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
function ELECTIONS_remove_old_files()
{
    global $_CONF;

    $paths = array(
        // private/plugins/elections
        __DIR__ => array(
            // 0.1.2
            'templates/votes_num.thtml',
            // 0.1.3
            'templates/answer.thtml',
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
            Log::write('system', Log::DEBUG, "Removing $path/$file.");
            ELECTIONS_rmdir("$path/$file");
        }
    }
}
