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
    $retval['statusMessage'] = MO::_('There was an error recording your vote.');
    $retval['html'] = Election::getInstance($pid)->Render();
} else {
    $Election = Election::getInstance($pid);
    if (!$Election->canVote()) {
        $retval['statusMessage'] = MO::_('This election is not open for voting.');
    } elseif (
        isset($_POST['aid']) &&
        count($_POST['aid']) == $Election->numQuestions()
    ) {
        $retval = ELECTION_saveVote_AJAX($pid,$aid);
    } else {
        $eMsg = MO::_('Please answer all remaining questions.') .
            ' "' . $Election->getTopic() . '"';
        $retval['statusMessage'] = $eMsg;
    }
}
$c = Cache::getInstance()->deleteItemsByTag('story');

$return["json"] = json_encode($retval);
echo json_encode($return);


function ELECTION_saveVote_AJAX($pid, $aid)
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
