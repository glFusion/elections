<?php
/**
 * Global configuration items for the Elections plugin.
 * These are either static items, such as the plugin name and table
 * definitions, or are items that don't lend themselves well to the
 * glFusion configuration system, such as allowed file types.
 *
 * @copyright   Copyright (c) 2000-2023 The following authors:
 * @author      Mark R. Evans <mark AT glfusion DOT org>
 * @author      Tony Bibbs <tony AT tonybibbs DOT com>
 * @author      Tom Willett <twillett AT users DOT sourceforge DOT net>
 * @author      Blaine Lang <langmail AT sympatico DOT ca>
 * @author      Dirk Haun <dirk AT haun-online DOT de>
 * @author      Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}
use Elections\Config;

Config::set('pi_version', '0.2.0.2');
Config::set('gl_version', '2.0.0');

// Add to $_TABLES array the tables your plugin uses
global $_DB_table_prefix;
$table_prefix = $_DB_table_prefix . Config::PI_NAME . '_';
$_TABLES['elections_answers']   = $table_prefix . 'answers';
$_TABLES['elections_questions'] = $table_prefix . 'questions';
$_TABLES['elections_topics']    = $table_prefix . 'topics';
$_TABLES['elections_voters']    = $table_prefix . 'voters';
$_TABLES['elections_votes']     = $table_prefix .'votes';
