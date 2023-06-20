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
use Elections\Models\Vote;
use Elections\Models\Request;
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

$Request = Request::getInstance()->withArgNames(array('pid'));;
$pid = COM_applyFilter($Request->getString('pid'));
$tid = $Request->getInt('tid');
$type = $Request->getString('type');
if ( $type != '' && $type != 'article' ) {
    if (!in_array($type,$_PLUGINS)) {
        $type = '';
    }
}

$expected = array(
    'reply', 'votebutton', 'results', 'showvote',
);
list($action, $actionval) = $Request->getAction($expected, 'listelections');
if ($action == 'reply') {
    // Handle a comment submission
    echo COM_refresh(
        $_CONF['site_url'] . '/comment.php?sid=' . $tid . '&pid=' . $pid . '&type=' . $type
    );
    exit;
}
$order = COM_applyFilter($Request->getString('order'));
$mode = COM_applyFilter($Request->getString('mode')); 
$msg = $Request->getInt('msg');

if ($pid != '') {
    $Election = Election::getByPid($pid);
}

switch ($action) {
case 'votebutton':
    // Get the answer array and check that the number is right, and the user hasn't voted
    $aid = $Request->getArray('aid');
    $Votes = array();
    foreach ($aid as $qid=>$ans_id) {
        $Votes[] = new Vote(array(
            'qid' => $qid,
            'aid' => $ans_id,
            'tid' => $tid,
        ) );
    }
    if ($Election->alreadyVoted() && !$Election->canUpdate()) {
        COM_setMsg(MO::_('Your vote has already been recorded.'), 'error', true);
        COM_refresh(Config::get('url') . '/index.php');
    } else {
        $old_aid = $Request->getArray('old_aid');
        if (count($aid) == $Election->numQuestions()) {
            if (
                $Election->saveVote($aid, $old_aid) &&
                !$Election->hideResults()
            ) {
                COM_refresh(Config::get('url') . '/index.php?results=x&tid=' . $Election->getTid());
            } else {
                COM_refresh(Config::get('url') . '/index.php');
            }
        } else {
            $page .= COM_showMessageText(MO::_('Please answer all remaining questions.'),
                '',
                true,
                'error'
            );
            $page .= $Election->withSelections($Votes)->Render();
        }
    }
    break;

case 'results':
    $Election = Election::getByPid($actionval);
    if ($Election->canViewResults()) {
        $page .= (new Results($Election->getTid()))
            ->withCommentMode($mode)
            ->withCommentOrder($order)
            ->Render();
    } else {
        $page .= Election::listElections();
    }
    $title = MO::_('Results');
    break;

case 'showvote':
    $Voter = Voter::getInstance($Request->getString('votekey'));
    $Election = Election::getByPid($pid);
    $data = $Voter->getVoteRecords();
    if (
        $data !== NULL &&
        $Voter->getTid() == $Election->getTid() // verify right election is selected
    ) {
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
        if (!empty($pid)) {
            $Election = Election::getInstance($pid);
        }
    }
    if (isset($Election) && !$Election->isNew()) {
        if ($msg > 0) {
            $page .= COM_showMessage($msg, Config::get('pi_name'));
        }
        if (isset($Request['aid'])) {
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
