<?php
/**
 * Define constants for commonly-used group IDs
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     election
 * @version     v0.1.0
 * @since       v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections\Models;


/**
 * Constants for user group IDs
 * @package elections
 */
class Groups
{
    /** "All Users" group ID
     */
    public const ALL_USERS = 2;

    /** "Logged-In Users" group ID
     */
    public const LOGGED_IN = 13;
}
