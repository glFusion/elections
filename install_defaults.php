<?php
/**
 * Configuration defaults for the Elections plugin for glFusion.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v0.1.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die('This file can not be used on its own!');
}
use Elections\Config;
$pi_name = Config::PI_NAME;

/*
 * Election default settings.
 * Initial Installation Defaults used when loading the online configuration
 * records. These settings are only used during the initial installation
 * and not referenced any more once the plugin is installed
 */
/** @var global config data */
global $electionConfigData;
$electionConfigData = array(
    array(
        'name' => 'sg_main',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'fs_main',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'hidemenu',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'maxquestions',
        'default_value' => '10',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'maxanswers',
        'default_value' => '10',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'answerorder',
        'default_value' => '10',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 50,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'cookietime',
        'default_value' => '86400',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'addresstime',
        'default_value' => '604800',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 70,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'delete_election',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 80,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'displayblocks',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 13,
        'sort' => 90,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'def_voting_gid',
        'default_value' => '2',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,     // use helper function
        'sort' => 100,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'def_results_gid',
        'default_value' => '2',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,     // use helper function
        'sort' => 100,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'allow_votemod',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 110,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'archive_days',
        'default_value' => '365',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 120,
        'set' => true,
        'group' => $pi_name,
    ),
    array(
        'name' => 'block_num_q',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 14,
        'sort' => 130,
        'set' => true,
        'group' => $pi_name,
    ),
);


/**
 * Initialize Election plugin configuration.
 * Creates the database entries for the configuation if they don't already exist.
 *
 * @return  boolean     true: success; false: an error occurred
 */
function plugin_initconfig_elections()
{
    global $_CONF, $electionConfigData;

    $pi_name = Config::get('pi_name');
    $c = \config::get_instance();
    if (!$c->group_exists($pi_name)) {
        USES_lib_install();
        foreach ($electionConfigData AS $cfgItem) {
            _addConfigItem($cfgItem);
        }
    } else {
        COM_errorLog(
            __FUNCTION__ . ': ' . Config::PI_NAME . ' config group already exists'
        );
    }
    return true;
}
