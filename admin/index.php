<?php
/**
 * Administrative entry point for the Elections plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner
 * @package     elections
 * @version     v0.1.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
require_once '../../../lib-common.php';
require_once '../../auth.inc.php';

USES_lib_admin();
use Elections\Config;
use Elections\Menu;
use Elections\Election;
use Elections\Views\Results;
use Elections\MO;

$display = '';

if (!plugin_ismoderator_elections()) {
    COM_accessLog(sprintf(
        MO::_('User %s tried to access the election administration screen.'),
        $_USER['username']
    ) );
    COM_404();
    exit;
}

$action = '';
$expected = array(
    'edit', 'save', 'delete', 'lv', 'resetelection',
    'results', 'presults', 'preview',
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
$title = MO::_('Election Administration');

switch ($action) {
case 'lv' :
    $title = MO::_('Edit Election');
    $page .= Election::getInstance($pid)->listVotes();
    break;

case 'edit':
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
            $title = MO::_('Edit Election');
            $page .= COM_startBlock(
                MO::_('Invalid security token'),
                '',
                COM_getBlockTemplate('_msg_block', 'header')
            );
            $page .= MO::_('Please enter an Election ID.');
            $page .= COM_endBlock(COM_getBlockTemplate('_msg_block', 'footer'));
            $page .= ELECTION_edit ();
        }
    } else {
        COM_accessLog(sprintf(
            MO::_("User %s tried to save election $pid and failed CSRF checks."),
            $_USER['username']
        ) );
        $page =  COM_refresh($_CONF['site_admin_url'] . '/index.php');
    }
    break;

case 'results':
    $page .= (new Results($pid))->withAdmin(true)->Render();
    $title = MO::_('Results');
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
        COM_errorLog(MO::_('Ignored possibly manipulated request to delete a election.'));
        $page .= COM_refresh(Config::get('admin_url') . '/index.php');
    } elseif (SEC_checktoken()) {
        $page .= Election::deleteElection($pid);
    } else {
        COM_accessLog(sprintf(
            MO::_("User %s tried to illegally delete election $pid and failed CSRF checks."),
            $_USER['username']
        ) );
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
    $title = MO::_('Election Administration');
    $page .= ($msg > 0) ? COM_showMessage($msg, Config::PI_NAME) : '';
    $action = 'listelections';
    $page .= Election::adminList();
    break;
}

$display .= Menu::siteHeader($title);
$display .= Menu::Admin($action);
$display .= $page;
$display .= Menu::siteFooter();
echo $display;
