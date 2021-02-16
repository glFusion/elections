<?php
// +--------------------------------------------------------------------------+
// | Election Plugin - glFusion CMS                                              |
// +--------------------------------------------------------------------------+
// | index.php                                                                |
// |                                                                          |
// | glFusion election administration page                                        |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2015-2017 by the following authors:                        |
// |                                                                          |
// | Mark R. Evans          mark AT glfusion DOT org                          |
// |                                                                          |
// | Copyright (C) 2000-2008 by the following authors:                        |
// |                                                                          |
// | Authors: Tony Bibbs        - tony AT tonybibbs DOT com                   |
// |          Mark Limburg      - mlimburg AT users DOT sourceforge DOT net   |
// |          Jason Whittenburg - jwhitten AT securitygeeks DOT com           |
// |          Dirk Haun         - dirk AT haun-online DOT de                  |
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

// Set this to true if you want to log debug messages to error.log
$_POLL_VERBOSE = false;

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';

USES_lib_admin();
use Elections\Config;
use Elections\Menu;
use Elections\Election;
use Elections\Views\Results;

$display = '';

if (!plugin_ismoderator_elections()) {
    COM_accessLog ("User {$_USER['username']} tried to access the election administration screen.");
    COM_404();
    exit;
}

// MAIN ========================================================================

$action = '';
$expected = array(
    'edit','save','delete','lv', 'results', 'presults', 'resetelection',
    'preview',
);
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
    } elseif (isset($_GET[$provided])) {
	$action = $provided;
    }
}

$pid = '';
if (isset($_POST['pid'])) {
    $pid = COM_sanitizeID(COM_applyFilter($_POST['pid']));
} elseif (isset($_GET['pid'])) {
    $pid = COM_sanitizeID(COM_applyFilter($_GET['pid']));
}

$msg = 0;
if (isset($_POST['msg'])) {
    $msg = COM_applyFilter($_POST['msg'], true);
} elseif (isset($_GET['msg'])) {
    $msg = COM_applyFilter($_GET['msg'], true);
}

$page = '';
$title = $LANG25[18];

switch ($action) {
case 'lv' :
    $title = $LANG25[5];
    $page .= Election::getInstance($pid)->listVotes();
    break;

case 'edit':
    $page = Menu::Admin();
    $page .= Election::getInstance($pid)->editElection();
    break;

case 'save':
    if (SEC_checktoken()) {
        if (!empty ($pid)) {
            $msg = Election::getInstance($_POST['old_pid'])->Save($_POST);
            if (!empty($msg)) {
                COM_setMsg($msg);
            }
            COM_refresh(Config::get('admin_url') . '/index.php');
        } else {
            $title = $LANG25[5];
            $page .= COM_startBlock(
                $LANG21[32],
                '',
                COM_getBlockTemplate('_msg_block', 'header')
            );
            $page .= $LANG25[17];
            $page .= COM_endBlock(COM_getBlockTemplate('_msg_block', 'footer'));
            $page .= ELECTION_edit ();
        }
    } else {
        COM_accessLog("User {$_USER['username']} tried to save election $pid and failed CSRF checks.");
        $page =  COM_refresh($_CONF['site_admin_url'] . '/index.php');
    }
    break;

case 'results':
    $page = Menu::Admin();
    $page .= (new Results($pid))->withAdmin(true)->Render();
    break;

case 'presults':
    echo (new Results($pid))->Print();
    exit;
    break;

case 'resetelection':
    Election::deleteVotes($pid);
    COM_refresh(Config::get('admin_url') . '/index.php');
    break;

case 'delete':
    if (empty($pid)) {
        COM_errorLog ('Ignored possibly manipulated request to delete a election.');
        $page .= COM_refresh(Config::get('admin_url') . '/index.php');
    } elseif (SEC_checktoken()) {
        $page .= Election::deleteElection($pid);
    } else {
        COM_accessLog("User {$_USER['username']} tried to illegally delete election $pid and failed CSRF checks.");
        echo COM_refresh($_CONF['site_admin_url'] . '/index.php');
    }
    break;

case 'preview':
    $Election = new Election($pid);
    if (isset($Election) && !$Election->isNew()) {
        $page .= $Election->Render(true);
    }
    break;

case 'listelections':
default:
    $title = $LANG25[18];
    $page .= ($msg > 0) ? COM_showMessage ($msg, Config::PI_NAME) : '';
    $page = Menu::Admin('listelections');
    $page .= Election::adminList();
    break;
}

$display .= COM_siteHeader($title);
$display .= $page;
$display .= COM_siteFooter();
echo $display;
