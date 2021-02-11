<?php
// +--------------------------------------------------------------------------+
// | Election Plugin - glFusion CMS                                              |
// +--------------------------------------------------------------------------+
// | ajax_handler.php                                                         |
// |                                                                          |
// | Save poll answers.                                                       |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2009-2016 by the following authors:                        |
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

require_once '../lib-common.php';

if (!in_array('election', $_PLUGINS)) {
    COM_404();
    die();
}
use \glFusion\Cache\Cache;
use Election\Election;
use Election\Voter;
use Election\Answer;
use Election\Views\Results;

$retval = array();

$pid = '';
$aid = 0;

if (isset ($_POST['pid'])) {
    $pid = COM_sanitizeID(COM_applyFilter ($_POST['pid']));
    if (isset ($_POST['aid'])) {
        $aid = $_POST['aid'];
    }
}

if ( $pid == '' || $aid == 0 ) {
    $retval['statusMessage'] = 'Error Processing Election Vote';
    $retval['html'] = Election::getInstance($pid)->Render();
} else {
    $Election = Election::getInstance($pid);
    if (!$Election->canVote()) {
        $retval['statusMessage'] = 'This poll is not open for voting';
    } elseif (
        isset($_POST['aid']) &&
        count($_POST['aid']) == $Election->numQuestions()
    ) {
        $retval = ELECTION_saveVote_AJAX($pid,$aid);
    } else {
        $eMsg = $LANG_ELECTION['answer_all'] . ' "' . $Election->getTopic() . '"';
        $retval['statusMessage'] = $eMsg;
    }
}
$c = Cache::getInstance()->deleteItemsByTag('story');

$return["json"] = json_encode($retval);
echo json_encode($return);


function ELECTION_saveVote_AJAX($pid, $aid)
{
    global $_USER, $LANG_ELECTION;

    $retval = array('html' => '','statusMessage' => '');
    $Election = Election::getInstance($pid);
    if (!$Election->canVote()) {
        $retval['statusMessage'] = 'This poll is not available for voting';
        $retval['html'] = $Election::listElection();
    } elseif ($Election->alreadyVoted()) {
        $retval['statusMessage'] = 'You have already voted on this poll';
        $retval['html'] = (new Results($pid))->Render();
    } else {
        if ((new Election($pid))->saveVote($aid)) {
            $eMsg = $LANG_ELECTION['savedvotemsg'] . ' "' . $Election->getTopic() . '"';
        } else {
            $eMsg = "There was an error recording your vote";
        }
        $retval['statusMessage'] = $eMsg;
        $retval['html'] = (new Results($pid))->Render();
    }
    return $retval;
}

?>
