<?php
// +--------------------------------------------------------------------------+
// | Election Plugin - glFusion CMS                                              |
// +--------------------------------------------------------------------------+
// | upgrade.php                                                              |
// |                                                                          |
// | Upgrade routines                                                         |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2009-2017 by the following authors:                        |
// |                                                                          |
// | Mark R. Evans          mark AT glfusion DOT org                          |
// |                                                                          |
// | Copyright (C) 2000-2008 by the following authors:                        |
// |                                                                          |
// | Authors: Tony Bibbs       - tony AT tonybibbs DOT com                    |
// |          Tom Willett      - twillett AT users DOT sourceforge DOT net    |
// |          Blaine Lang      - langmail AT sympatico DOT ca                 |
// |          Dirk Haun        - dirk AT haun-online DOT de                   |
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
    die ('This file can not be used on its own.');
}
use Elections\DB;
use Elections\Config;

function election_upgrade()
{
    global $_TABLES, $_CONF;

    $currentVersion = DB_getItem($_TABLES['plugins'],'pi_version',"pi_name='" . Config::PI_NAME . "'");

        default :
            DB_query(
                "UPDATE {$_TABLES['plugins']}
                SET pi_version='" . Config::get('pi_version') . "',
                pi_gl_version='" . Config::get('gl_version') . "'
                WHERE pi_name='" . Config::PI_NAME . "' LIMIT 1"
            );
            break;
    }
    if (DB_getItem(
        $_TABLES['plugins'],
        'pi_version',
        "pi_name='" . Config::PI_NAME . "'"
        ) == Config::get('pi_version')
    ) {
        return true;
    } else {
        return false;
    }
}
