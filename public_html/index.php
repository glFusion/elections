<?php
/**
 * Guest-facing entry point for the elections plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     election
 * @version     v0.1.2
 * @since       v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
require_once '../lib-common.php';
use Elections\Election;
use Elections\Voter;
use Elections\Menu;
use Elections\Config;
use Elections\Views\Results;
use Elections\MO;

if (!in_array(Config::get('pi_name'), $_PLUGINS)) {
    COM_404();
    exit;
}

// MAIN ========================================================================
//
// no pid will load a list of elections
// no aid will let you vote on the select election
// an aid greater than 0 will save a vote for that answer on the selected election
// an aid of -1 will display the select election

$display = '';
$page = '';
$title = MO::_('Elections');

$filter = sanitizer::getInstance();
$filter->setPostmode('text');

if (isset($_POST['pid'])) {
    $pid = COM_applyFilter($_POST['pid']);
} elseif (isset($_GET['pid'])) {
    $pid = COM_applyFilter($_GET['pid']);
} else {
    $pid = '';
}

$type = isset($_POST['type']) ? COM_applyFilter($_POST['type']) : '';
if ( $type != '' && $type != 'article' ) {
    if (!in_array($type,$_PLUGINS)) {
        $type = '';
    }
}

$expected = array(
    'reply', 'votebutton', 'results', 'showvote',
);
$action = 'listelections';
foreach ($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST[$provided];
    } elseif (isset($_GET[$provided])) {
        $action = $provided;
        $actionval = $_GET[$provided];
    }
}

if ($action == 'reply') {
    // Handle a comment submission
    echo COM_refresh(
        $_CONF['site_url'] . '/comment.php?sid=' . $pid . '&pid=' . $pid . '&type=' . $type
    );
    exit;
}

$aid = 0;
if ($pid != '') {
    if (isset ($_GET['aid'])) {
        $aid = -1; // only for showing results instead of questions
    } else if (isset ($_POST['aid'])) {
        $aid = $_POST['aid'];
    }
}

$order = '';
if (isset ($_REQUEST['order'])) {
    $order = COM_applyFilter ($_REQUEST['order']);
}
$mode = '';
if (isset ($_REQUEST['mode'])) {
    $mode = COM_applyFilter ($_REQUEST['mode']);
}
$msg = 0;
if (isset($_REQUEST['msg'])) {
    $msg = COM_applyFilter($_REQUEST['msg'], true);
}

if ($pid != '') {
    $Election = Election::getInstance($pid);
}

switch ($action) {
case 'votebutton':
    // Get the answer array and check that the number is right, and the user hasn't voted
    $aid = (isset($_POST['aid']) && is_array($_POST['aid'])) ? $_POST['aid'] : array();
    if ($Election->alreadyVoted() && !$Election->canUpdate()) {
        COM_setMsg(MO::_('Your vote has already been recorded.'), 'error', true);
        COM_refresh(Config::get('url') . '/index.php');
    } else {
        $old_aid = isset($_POST['old_aid']) ? $_POST['old_aid'] : array();
        if (count($aid) == $Election->numQuestions()) {
            if (
                $Election->saveVote($aid, $old_aid) &&
                !$Election->hideResults()
            ) {
                COM_refresh(Config::get('url') . '/index.php?results=x&pid=' . $Election->getID());
            } else {
                COM_refresh(Config::get('url') . '/index.php');
            }
        } else {
            $page .= COM_showMessageText(MO::_('Please answer all remaining questions.'),
                '',
                true,
                'error'
            );
            $page .= $Election->withSelections($aid)->Render();
        }
    }
    break;

case 'results':
    if ($Election->canViewResults()) {
        $page .= (new Results($Election->getID()))
            ->withCommentMode($mode)
            ->withCommentOrder($order)
            ->Render();
    } else {
        $page .= Election::listElections();
    }
    $title = MO::_('Results');
    break;

case 'showvote':
    $Voter = Voter::getInstance($_POST['votekey']);
    $data = $Voter->getVoteRecords();
    if (
        $data !== false &&
        $Voter->getPid() == $pid    // verify right election is selected
    ) {
        $Election = Election::getInstance($Voter->getPid());
        $page .= Election::msgAlert(
            sprintf(
                MO::_('Your vote was recorded as shown at %1$s on %2$s'),
                $Voter->getDate($_CONF['timeonly']),
                $Voter->getDate($_CONF['dateonly'])
            )
        );
        $page .= $Election->withAccessKey($Voter->getPrvKey())
                      ->withVoteId($Voter->getId())
                      ->withSelections($data)
                      ->Render();
    } else {
        COM_setMsg(MO::_('An invalid access key was entered.'), 'error');
        COM_refresh(Config::get('url') . '/index.php');
    }
    break;

default:
    if (!isset($Election)) {
        // Didn't get an election ID in the URL, see if there's one using
        // COM_buildUrl()
        COM_setArgNames(array('pid'));
        $pid = COM_getArgument('pid');
        if (!empty($pid)) {
            $Election = Election::getInstance($pid);
        }
    }
    if (isset($Election) && !$Election->isNew()) {
        if ($msg > 0) {
            $page .= COM_showMessage($msg, Config::get('pi_name'));
        }
        if (isset($_POST['aid'])) {
            $eMsg = MO::_('Please answer all remaining questions.') .
                ' "' . $filter->filterData($Election->getTopic()) . '"';
            $page .= COM_showMessageText($eMsg, MO::_('Results were not saved.'), true, 'error');
        }
        if (!$Election->isOpen() && $Election->canViewResults()) {
            $page .= (new Results($Election->getID()))
                ->withCommentMode($mode)
                ->withCommentOrder($order)
                ->Render();
        } elseif ($Election->canVote()) {
            $page .= $Election->Render();
        } else {
            COM_setMsg(
                MO::_("Voting for this election is unavailable.") . ' ' .
                MO::_("Either you've already voted, the election has been removed or you do not have sufficient permissions."),
                'error',
                true
            );
            COM_refresh(Config::get('url') . '/index.php');
        }
    } else {
        $title = MO::_('Elections');
        $page .= Election::listElections();
    }
    break;
}

$display = Menu::siteHeader($title);
$display .= $page;
$display .= Menu::siteFooter();
echo $display;
