<?php
/**
 * AJAX handler for the Elections plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021-2023 Lee Garner
 * @package     elections
 * @version     v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
require_once '../lib-common.php';

if (!in_array('elections', $_PLUGINS)) {
    COM_404();
    die();
}
use \glFusion\Cache\Cache;
use Elections\Election;
use Elections\Voter;
use Elections\Answer;
use Elections\Views\Results;
use Elections\MO;
use Elections\Config;
use Elections\Models\Request;

$retval = array();

$pid = '';
$aid = 0;

$Request = Request::getInstance();
$pid = COM_sanitizeID($Request->getString('pid'));
if (!empty($pid)) {
    $aid = $Request->getArray('aid');
}
if (empty($pid) || empty($aid)) {
    $retval['statusMessage'] = MO::_('There was an error recording your vote.');
    $retval['html'] = Election::getInstance($pid)->Render();
} else {
    // Have an election topic and answer array
    $Election = Election::getInstance($pid);
    if (!$Election->canVote()) {
        $retval['statusMessage'] = MO::_('This election is not open for voting.');
    } elseif (
        count($aid) == $Election->numQuestions()
    ) {
        $retval = $Election->saveVote_AJAX($aid);
    } else {
        $eMsg = MO::_('Please answer all remaining questions.') .
            ' "' . $Election->getTopic() . '"';
        $retval['statusMessage'] = $eMsg;
    }
}
$c = Cache::getInstance()->deleteItemsByTag('story');

$return["json"] = json_encode($retval);
echo json_encode($return);


function XELECTION_saveVote_AJAX($pid, $aid)
{
    global $_USER;

    $retval = array('html' => '','statusMessage' => '');
    $Election = Election::getInstance($pid);
    if (!$Election->canVote()) {
        $retval['statusMessage'] = MO::_('This election is not open for voting.');
        $retval['html'] = Election::listElections();
    } elseif ($Election->alreadyVoted()) {
        $retval['statusMessage'] = MO::_('Your vote has already been recorded.');
        $retval['html'] = '';
    } else {
        $El = new Election($pid);
        if ($El->saveVote($aid)) {
            $retval['statusMessage'] = MO::_('Your vote has been recorded.') .
                ' "' . $Election->getTopic() . '"';
            $retval['html'] = MO::_('Your vote has been recorded.');
            if ($El->canViewResults()) {
                $retval['html'] .= '<br />' . COM_createLink(
                    $El->getTopic() . ' (' . MO::_('Results') . ')',
                    Config::get('url') . '/index.php?results=x&pid=' . $pid
                );
            }
        } else {
            $retval['statusMessage'] = MO::_('There was an error recording your vote.');
            $retval['html'] = $retval['statusMessage'];
        }
    }
    return $retval;
}
