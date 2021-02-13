<?php
/**
* glFusion CMS
*
* UTF-8 Language File for Election Plugin
*
* @license GNU General Public License version 2 or later
*     http://www.opensource.org/licenses/gpl-license.php
*
*  Copyright (C) 2008-2018 by the following authors:
*   Mark R. Evans   mark AT glfusion DOT org
*
*  Based on prior work Copyright (C) 2001-2005 by the following authors:
*   Tony Bibbs - tony AT tonybibbs DOT com
*   Trinity Bays - trinity93 AT gmail DOT com
*
*/

if (!defined ('GVERSION')) {
    die ('This file cannot be used on its own.');
}
use Elections\Config;
global $LANG32;

$LANG_ELECTION = array(
    'election'          => 'Election',
    'results'           => 'Results',
    'votes'             => 'votes',
    'vote'              => 'Vote',
    'pastelection'         => 'Past Election',
    'savedvotetitle'    => 'Vote Saved',
    'savedvotemsg'      => 'Your vote was saved for the election.',
    'alreadyvoted'      => 'Your vote has already been recorded for this election.',
    'electiontitle'        => 'Election in System',
    'topics'            => 'Other elections',
    'stats_top10'       => 'Top Ten Elections',
    'stats_topics'      => 'Election Topic',
    'stats_votes'       => 'Votes',
    'stats_none'        => 'It appears that there are no election on this site or no one has ever voted.',
    'stats_summary'     => 'Election (Answers) in the system',
    'status'            => 'Voting Status',
    'answer_all'        => 'Please answer all remaining questions',
    'not_saved'         => 'Result not saved',
    'upgrade1'          => 'You installed a new version of the Election plugin. Please',
    'upgrade2'          => 'upgrade',
    'editinstructions'  => 'Please fill in the Election ID, at least one question and two answers for it.',
    'electionclosed'        => 'This election is closed for voting.',
    'electionhidden'        => 'Election results will be available only after the Election has closed.',
    'start_election'        => 'Start Election',
    'deny_msg' => 'Voting for this election is unavailable. Either you&apos;ve already voted, the election has been removed or you do not have sufficient permissions.',
    'login_required'    => '<a href="'.$_CONF['site_url'].'/users.php" rel="nofollow">Login</a> required to vote',
    'username'          => 'Username',
    'ipaddress'         => 'IP Address',
    'date_voted'        => 'Date Voted',
    'description'       => 'Description',
    'general'           => 'General',
    'questions'    => 'Election Questions',
    'permissions'       => 'Permissions',
    'vote'  => 'Vote',
'msg_updated' => 'Item(s) have been updated',
'msg_deleted' => 'Item(s) have been deleted',
'msg_nochange' => 'Item(s) are unchanged',
'datepicker' => 'Date Picker',
'timepicker' => 'Time Picker',
'closes' => 'Election Closes',
'opens' => 'Election Opens',
'voting_group' => 'Allowed to Vote',
'results_group' => 'Allowed to View Results',
'back_to_list' => 'Back to List',
'msg_results_open' => 'Early results, election is open',
'message' => 'Message',
'closed' => 'Election is Closed',
's_alreadyvoted'    => 'You have already voted',
'confirm_reset' => 'Are you sure you want to delete all of the results for this election?',
'viewing_vote' => 'You are viewing the vote that you previously cast on %1$s at %2$s.',
'msg_copykey' => 'Copy your key to a safe location if you wish to verify your vote later.',
'msg_errorsaving' => 'There was an error recording your vote, please try again.',
'msg_yourkeyis' => 'Your private access key is:',
'copy_clipboard' => 'Copy to clipboard',
'copy_clipboard_success' => 'Your private key was copied to your clipboard.',
'allow_votemod' => 'Voter access to their cast votes',
'view_vote' => 'View Vote',
'noaccess' => 'No Access',
'rnd_questions' => 'Randomize question order?',
'rnd_answers' => 'Randomize answer order?',
'declares_winner' => 'Declares a Winner?',
'open' => 'Open',
'closed' => 'Closed',
'archived' => 'Archived',
);

###############################################################################
# admin/plugins/election/index.php

$LANG25 = array(
    1 => 'Mode',
    2 => 'Please enter a topic, at least one question and at least one answer for that question.',
    3 => 'Election Created',
    4 => "Election %s saved",
    5 => 'Edit Election',
    6 => 'Election ID',
    7 => '(do not use spaces)',
    8 => 'Appears on Electionblock',
    9 => 'Topic',
    10 => 'Answers / Votes / Remark',
    11 => "There was an error getting election answer data about the election %s",
    12 => "There was an error getting election question data about the election %s",
    13 => 'Create Election',
    14 => 'save',
    15 => 'cancel',
    16 => 'delete',
    17 => 'Please enter a Election ID',
    18 => 'Election Administration',
    19 => 'To modify or delete a election, click on the edit icon of the election.  To create a new election, click on "Create New" above.',
    20 => 'Voters',
    21 => 'Access Denied',
    22 => "You are trying to access a election that you don't have rights to.  This attempt has been logged. Please <a href=\"{$_CONF['site_admin_url']}/election.php\">go back to the election administration screen</a>.",
    23 => 'New Election',
    24 => 'Admin Home',
    25 => 'Yes',
    26 => 'No',
    27 => 'Edit',
    28 => 'Submit',
    29 => 'Search',
    30 => 'Limit Results',
    31 => 'Question',
    32 => 'To remove this question from the election, remove its question text',
    33 => 'Open for Voting',
    34 => 'Election Topic:',
    35 => 'This election has',
    36 => 'more questions.',
    37 => 'Hide results while election is open',
    38 => 'While the election is open, only the owner &amp; administrators can see the results',
    39 => 'The topic will be only displayed if there are more than 1 questions.',
    40 => 'See all answers to this election',
    41 => 'Are you sure you want to delete this Election?',
    42 => 'Are you absolutely sure you want to delete this Election?  All questions, answers and comments that are associated with this Election will also be permanently deleted from the database.',
    43 => 'Login Required to Vote',
);

$LANG_PO_AUTOTAG = array(
    'desc_election'                 => 'Link: to a Election on this site.  link_text defaults to the Election topic.  usage: [election:<i>election_id</i> {link_text}]',
    'desc_result'          => 'HTML: renders the results of a Election on this site.  usage: [election_result:<i>election_id</i>]',
    'desc_vote'            => 'HTML: renders a voting block for a Election on this site.  usage: [election_vote:<i>election_id</i>]',
);

$PLG_elections_MESSAGE19 = 'Your election has been successfully saved.';
$PLG_elections_MESSAGE20 = 'Your election has been successfully deleted.';
$PLG_elections_MESSAGE21 = 'An invalid access key was entered.';

// Messages for the plugin upgrade
$PLG_elections_MESSAGE3001 = 'Plugin upgrade not supported.';
$PLG_elections_MESSAGE3002 = $LANG32[9];


// Localization of the Admin Configuration UI
$LANG_configsections[Config::PI_NAME] = array(
    'label' => ucfirst(Config::PI_NAME),
    'title' => 'Election Configuration'
);

$LANG_confignames[Config::PI_NAME] = array(
    'electionloginrequired' => 'Election Login Required',
    'hideelectionmenu' => 'Hide Election Menu Entry',
    'maxquestions' => 'Max. Questions per Election',
    'maxanswers' => 'Max. Options per Question',
    'answerorder' => 'Sort Results',
    'electioncookietime' => 'Voter Cookie Valid Duration',
    'electionaddresstime' => 'Voter IP Address Valid Duration',
    'delete_election' => 'Delete Election with Owner',
    'aftersave' => 'After Saving Election',
    'default_permissions' => 'Election Default Permissions',
    'displayblocks' => 'Display glFusion Blocks',
    'def_voting_gid' => 'Default group allowed to vote',
    'def_results_gid' => 'Default group allowed to view results',
    'allow_votemod' => 'Voter access to their cast votes',
);

$LANG_configsubgroups[Config::PI_NAME] = array(
    'sg_main' => 'Main Settings'
);

$LANG_fs[Config::PI_NAME] = array(
    'fs_main' => 'General Election Settings',
    'fs_permissions' => 'Default Permissions'
);

$LANG_configSelect[Config::PI_NAME] = array(
    0 => array(1=>'True', 0=>'False'),
    1 => array(true=>'True', false=>'False'),
    2 => array('submitorder'=>'As Submitted', 'voteorder'=>'By Votes'),
    3 => array(
        0 => 'No Access',
        1 => 'View Vote',
        //2 => 'Edit Vote',
    ),
    9 => array('item'=>'Forward to Election', 'list'=>'Display Admin List', 'plugin'=>'Display Public List', 'home'=>'Display Home', 'admin'=>'Display Admin'),
    12 => array(0=>'No access', 2=>'Read-Only', 3=>'Read-Write'),
    13 => array(0=>'Left Blocks', 1=>'Right Blocks', 2=>'Left & Right Blocks', 3=>'None')
);
