<?php
/**
 * Class to manage database table names.
 * This is used as the plugin was originally intended as a possible
 * replacement for the `polls` plugin, but may be deprecated in favor
 * of using static table names.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2020 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v0.2.0
 * @since       v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections;


/**
 * Abstract table names to allow easy renaming of the plugin.
 * @package elections
 */
class DB
{
    private static $tables = array(
        'topics' => Config::KEY . 'topics',
        'questions' => Config::KEY . 'questions',
        'answers' => Config::KEY . 'answers',
        'voters' => Config::KEY . 'voters',
        'votes' => Config::KEY . 'votes',
        // For consistency, core glFusion tables:
        'comments' => 'comments',
        'users' => 'users',
        'commentcodes' => 'commentcodes',
    );

    /**
     * Get the table name from the short key.
     *
     * @param   string  $key    Short name defined above
     * @return  string      Full database table name
     */
    public static function table($key)
    {
        global $_TABLES;
        if (isset(self::$tables[$key])) {
            return $_TABLES[self::$tables[$key]];
        } else {
            return NULL;
        }
    }


    /**
     * Get the key used to index the global `$_TABLES` array.
     *
     * @param   string  $key    Short name defined above
     * @return  string      Key into $_TABLES
     */
    public static function key($key)
    {
        if (isset(self::$tables[$key])) {
            return self::$tables[$key];
        } else {
            return '';
        }
    }
}
