<?php
/**
 * Class to manage database table names.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     polls
 * @version     v3.0.0
 * @since       v3.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections;


/**
 * Class for product attribute groups.
 * @package shop
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
