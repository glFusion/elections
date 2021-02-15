<?php
// +--------------------------------------------------------------------------+
// | Election Plugin - glFusion CMS                                              |
// +--------------------------------------------------------------------------+
// | install_defaults.php                                                     |
// |                                                                          |
// | Initial Installation Defaults used when loading the online configuration |
// | records. These settings are only used during the initial installation    |
// | and not referenced any more once the plugin is installed.                |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | Copyright (C) 2000-2008 by the following authors:                        |
// |                                                                          |
// | Authors: Dirk Haun        - dirk AT haun-online DOT de                   |
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

    /*array(
        'name' => 'default_permissions',
        'default_value' => array (3, 2, 2, 2),
        'type' => '@select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 100,
        'set' => true,
        'group' => $pi_name,
    ),*/
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

?>
