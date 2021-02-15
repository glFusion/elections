<?php
/**
 * Define election status constants.
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
 * Constants for display modes (block, autotag, normal, etc.)
 * @package election
 */
class Status
{
    /** Poll is open.
     */
    public const OPEN = 0;

    /** Poll is administratively closed.
     */
    public const CLOSED = 1;

    /** Poll is archived.
     */
    public const ARCHIVED = 2;
}
