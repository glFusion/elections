<?php
/**
 * Language file for the new Elections plugin for glFusion.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
use Elections\MO;
use Elections\Config;

// Localization of the Admin Configuration UI
$LANG_configsections[Config::PI_NAME] = array(
    'label' => Config::get('pi_display_name'),
    'title' => MO::_('Elections Configuration'),
);

$LANG_confignames[Config::PI_NAME] = array(
    'hidemenu' => MO::_('Hide Menu Entry'),
    'maxquestions' => MO::_('Max. Questions per Election'),
    'maxanswers' => MO::_('Max. Options per Question'),
    'answerorder' => MO::_('Sort Results'),
    'cookietime' => MO::_('Voter Cookie Valid Duration'),
    'addresstime' => MO::_('Voter IP Address Valid Duration'),
    'delete_election' => MO::_('Delete Elections with Owner'),
    'aftersave' => MO::_('After Saving Election'),
    'displayblocks' => MO::_('Display glFusion Blocks'),
    'def_voting_gid' => MO::_('Default group allowed to vote'),
    'def_results_gid' => MO::_('Default group allowed to view results'),
    'allow_votemod' => MO::_('Default after-voting access'),
    'archive_days' => MO::_('Days after which closed elections are archived'),
    'block_num_q' => MO::_('Number of questions shown in blocks'),
);

$LANG_configsubgroups[Config::PI_NAME] = array(
    'sg_main' => MO::_('Main Settings'),
);

$LANG_fs[Config::PI_NAME] = array(
    'fs_main' => MO::_('General Settings'),
    'fs_permissions' => MO::_('Default Permissions'),
);

$LANG_configSelect[Config::PI_NAME] = array(
    0 => array(
        1 => MO::_('True'),
        0 => MO::_('False'),
    ),
    2 => array(
        'submitorder' => MO::_('As Submitted'),
        'voteorder' => MO::_('By Votes'),
    ),
    3 => array(
        0 => MO::_('No Access'),
        1 => MO::_('View Vote'),
        2 => MO::_('Modify Vote'),
    ),
    13 => array(
        0 => MO::_('Left Blocks'),
        1 => MO::_('Right Blocks'),
        2 => MO::_('Left & Right Blocks'),
        3 => MO::_('None'),
    ),
    14 => array(
        0 => MO::_('Links Only'),
        1 => MO::_('One Qustoin'),
    ),
);

// Legacy, pre-2.0
$LANG_configselects[Config::PI_NAME] = array(
    0 => array(
        MO::_('True') => 1,
        MO::_('False') => 2,
    ),
    2 => array(
        MO::_('As Submitted') => 'submitorder',
        MO::_('By Votes') => 'voteorder',
    ),
    3 => array(
        MO::_('No Access') => 0,
        MO::_('View Vote') => 1,
        MO::_('Modify Vote') => 2,
    ),
    13 => array(
        MO::_('Left Blocks') => 0,
        MO::_('Right Blocks') => 1,
        MO::_('Left & Right Blocks') => 2,
        MO::_('None') => 3,
    ),
    14 => array(
        MO::_('Links Only') => 0,
        MO::_('One Qustoin') => 1,
    ),
);

