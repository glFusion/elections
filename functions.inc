<?php
// +--------------------------------------------------------------------------+
// | Election Plugin - glFusion CMS                                              |
// +--------------------------------------------------------------------------+
// | functions.inc                                                            |
// |                                                                          |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2008-2018 by the following authors:                        |
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
    die('This file can not be used on its own.');
}

spl_autoload_register(function ($class)
{
    // project-specific namespace prefix
    $prefix = 'Elections\\';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // base directory for the namespace prefix
    $base_dir = __DIR__ . '/classes/';

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.class.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
use Elections\MO;
use Elections\DB;
use Elections\Config;
use Elections\Election;
use Elections\Voter;
use Elections\Views\Results;
use Elections\Models\Modes;
use Elections\Models\Groups;

// May later rely on the class autoloader
require_once __DIR__ . '/' . Config::PI_NAME . '.php';

require_once __DIR__ . '/language/lang_config.php';

// +---------------------------------------------------------------------------+
// | glFusion Plugin API Implementations                                       |
// +---------------------------------------------------------------------------+

/**
* Returns the items for this plugin that should appear on the main menu.
* NOTE: this MUST return the url/value pairs in the following format
* $<arrayname>[<label>] = <url>
*
* @return   mixed   menu entry, or boolean false if disabled / hidden
*/
function plugin_getmenuitems_elections()
{
    global $_CONF;

    if (
        Config::get('hidemenu') == 1 ||
        (
            COM_isAnonUser() && $_CONF['loginrequired']
        )
    ) {
        return false;
    }
    return array(
        MO::_('Elections') . ' <i class="uk-icon-check-square"></i>' => Config::get('url') . '/index.php',
    );
}

/**
* glFusion is checking to see if this plugin supports comments, tell it yes.
*
* NOTE: to support comments you must used the same date/time based ID for your
* widget.  In other words, to make primary keys for your plugin you should call
* makesid().  Comments are keyed off of that...it is a limitation on how glFusion
* does comments.
*
*/
function plugin_commentsupport_elections()
{
    // election will use comments
    return true;
}


function plugin_getcommenturlid_elections()
{
    $retval = array();
    $retval[] = Config::get('url') . '/index.php';
    $retval[] = 'pid';
    return $retval;
}

/**
 * Save a comment.
 *
 * @param   string  $title  comment title
 * @param   string  $comment comment text
 * @param   string  $id     Item id to which $cid belongs
 * @param   int     $pid    comment parent
 * @param   string  $postmode 'html' or 'text'
 * @return  mixed   false for failure, HTML string (redirect?) for success
 */
function plugin_savecomment_elections($title, $comment, $id, $pid, $postmode)
{
    global $_CONF, $_TABLES, $_USER;

    $retval = '';

    $commentcode = DB_getItem(
        DB::table('topics'),
        'commentcode',
        "pid = '" . DB_escapeString($id)."'"
    );
    if ($commentcode != 0) {
        return COM_refresh ($_CONF['site_url'] . '/index.php');
    }

    $ret = CMT_saveComment ($title, $comment, $id, $pid, Config::PI_NAME, $postmode);
    if ($ret > 0) {
        $retval .= CMT_commentForm(
            $title,
            $comment,
            $id,
            $pid,
            Config::PI_NAME,
            MO::_('Preview'),
            $postmode
        );
    } else { // success
        $retval = COM_refresh(Config::get('url') . "/index.php?pid=$id");
    }
    $c = glFusion\Cache::getInstance()->deleteItemsByTag('whatsnew');
    return $retval;
}

function plugin_editcomment_elections($cid,$id)
{
    echo COM_refresh(Config::get('url') . "/index.php?pid=$id");
    exit;
}

/**
 * election: delete a comment
 *
 * @param   int     $cid    Comment to be deleted
 * @param   string  $id     Item id to which $cid belongs
 * @return  mixed   false for failure, HTML string (redirect?) for success
 */
function plugin_deletecomment_elections($cid, $id)
{
    global $_CONF, $_USER;

    $retval = '';

    if (Election::hasRights('edit')) {
        CMT_deleteComment($cid, $id, Config::PI_NAME);
        $retval .= COM_refresh(Config::get('url') . "/index.php?pid=$id");
    } else {
        COM_errorLog ("User {$_USER['username']} (IP: {$_SERVER['REMOTE_ADDR']}) "
                    . "did not have permissions to delete comment $cid from poll $id");
        $retval .= COM_refresh ($_CONF['site_url'] . '/index.php');
    }
    $c = glFusion\Cache::getInstance()->deleteItemsByTag('whatsnew');
    return $retval;
}


function plugin_getwhatsnewcomment_elections()
{
    global $_CONF, $_USER;

    $commentrow = array();

    $tbl_topics = DB::table('topics');
    $tbl_comments = DB::table('comments');
    $stwhere = Election::getPermSQL(0, $tbl_topics);
    $sql = "SELECT DISTINCT COUNT(*) AS dups, {$tbl_topics}.topic AS title,
            {$tbl_topics}.pid AS sid, UNIX_TIMESTAMP(max({$tbl_comments}.date)) AS lastdate
            FROM {$tbl_comments} LEFT JOIN {$tbl_topics} ON (({$tbl_topics}.pid = {$tbl_comments}.sid)"
            .
            " AND ({$tbl_topics}.commentcode >= 0)" .
            /* COM_getLangSQL( 'pid', 'AND', $_TABLES['polltopics'] ) .*/ ")
            WHERE ({$tbl_comments}.queued = 0 AND {$tbl_comments}.date >= (DATE_SUB('".$_CONF['_now']->toMySQL(true)."', INTERVAL {$_CONF['newcommentsinterval']} SECOND)))
            AND ((({$stwhere}))) GROUP BY {$tbl_comments}.sid,type, {$tbl_topics}.topic,
            {$tbl_topics}.topic, {$tbl_topics}.pid
            ORDER BY 4 DESC LIMIT 15";

    $result = DB_query( $sql );
    $nrows = DB_numRows( $result );
    if ( $nrows > 0 ) {
        $filter = new sanitizer();
        $filter->setPostmode('text');

        for ($x = 0; $x < $nrows; $x++ ) {
            $A = DB_fetchArray($result);
            $cmtReturn['url'] = Config::get('url') . '/index.php?pid=' . $A['sid'] . '#comments';
            $cmtReturn['type'] = Config::PI_NAME;
            $cmtReturn['dups'] = $A['dups'];
            $cmtReturn['title'] = $filter->filterData($A['title']);
            $cmtReturn['lastdate'] = $A['lastdate'];
            $commentrow[] = $cmtReturn;
        }
    }
    return $commentrow;
}

/**
* glFusion is asking us to provide any items that show up in the type
* drop-down on search.php.  Let's users search for events.
*
* @return   array   (plugin name/entry title) pair for the dropdown
*
*/

function plugin_searchtypes_elections()
{
    return array(Config::get('pi_name') => ucfirst(Config::get('pi_name')));
}


/**
 * This searches for election matching the user query.
 * Returns a SearchCritaria object.
 *
 * @param   string  $query     Keywords user is looking for
 * @param   string  $dateStart Start date to get results for
 * @param   string  $dateEnd   End date to get results for
 * @param   string  $topic     The topic they were searching in
 * @param   string  $type      Type of items they are searching, or 'all' (deprecated)
 * @param   integer $author    Get all results by this author
 * @param   string  $keyType   Search key type: 'all', 'phrase', 'any'
 * @param   integer $page      Page number of current search (deprecated)
 * @param   integer $perpage   Number of results per page (deprecated)
 * @return  object      SearchCriteria
 */
function plugin_dopluginsearch_elections(
    $query, $dateStart, $dateEnd, $topic, $type, $author, $keyType, $page, $perpage
) {
    global $_CONF, $_DB_dbms, $_USER;

    // Make sure the query is SQL safe
    $query = trim(DB_escapeString($query));
    $uid = isset($_USER['uid']) ? $_USER['uid'] : 1;

    $tbl_topics = DB::table('topics');
    $pi_name = Config::get('pi_name');
    // Search election
    $sql = "SELECT p.pid AS id, p.topic AS title, p.description AS description, UNIX_TIMESTAMP(p.date) AS date, "
        . " p.owner_id AS uid, p.voters AS hits, CONCAT('/$pi_name/index.php?pid=',p.pid) AS url "
        . " FROM {$tbl_topics} AS p "
        . " WHERE (p.date <= '".$_CONF['_now']->toMySQL(true)."') AND "
        . Election::getPermSQL(0, 'p');

    if (!Election::hasRights('admin', $uid)) {
        $sql .= " AND ((p.hideresults = 0) OR (p.owner_id = {$uid})) ";
    }

    if (!empty($author)) {
        $sql .= "AND (p.owner_id = '{$author}') ";
    }

    $search = new SearchCriteria(
        Config::get('pi_name'),
        array(ucfirst(Config::get('pi_name')))
    );

    $columns = array('title' => 'p.topic', 'description' => 'p.description');
    $sql .= $search->getDateRangeSQL('AND', 'p.date', $dateStart, $dateEnd);
    list($sql, $ftsql) = $search->buildSearchSQL($keyType, $query, $columns, $sql);

    $search->setSQL($sql);
    $search->setFTSQL($ftsql);
    $search->setRank(2);
    return $search;
}


/**
 * Get the SearchCriteria object to search comments matching the user query.
 *
 * @param   string  $query      Keywords user is looking for
 * @param   string  $datestart  Start date to get results for
 * @param   string  $dateend    End date to get results for
 * @param   string  $topic      The topic they were searching in
 * @param   string  $type       Type of items they are searching
 * @param   integer $author     Get all results by this author (user ID)
 * @return  object      SearchCriteria object
 */
function plugin_dopluginsearch_comment_elections(
    $query, $datestart, $dateend, $topic, $type, $author, $keyType, $page, $perpage
)
{
    global $_USER, $_CONF, $_TABLES;

    $query = trim(DB_escapeString(htmlspecialchars($query)));
    $pi_name = Config::get('pi_name');

    $sql =  "SELECT c.cid AS id1, a.pid AS id, c.title AS title,
        c.comment AS description, UNIX_TIMESTAMP(c.date) AS date, c.uid AS uid, '0' AS hits,
        CONCAT('/$pi_name/index.php?pid=',a.pid,'#comments') AS url
        FROM {$_TABLES['users']} AS u, {$_TABLES['comments']} AS c
        LEFT JOIN " . DB::table('topics') . " a ON c.sid=a.pid
        WHERE (c.type='$pi_name' AND c.queued = 0) AND (u.uid = c.uid)
        AND " . Election::getPermSQL();
    if (!empty($author)) {
        $sql .= " AND (c.uid = '$author') ";
    }

    $search_c = new SearchCriteria('comments', 'Election > ' . MO::_('Comment'));
    $columns = array('comment','c.title');
    $sql .= $search_c->getDateRangeSQL('AND', 'UNIX_TIMESTAMP(c.date)', $datestart, $dateend);
    list($sql,$ftsql) = $search_c->buildSearchSQL($keyType, $query, $columns, $sql);
    $search_c->setSQL($sql);
    $search_c->setFTSQL($ftsql);
    $search_c->setRank(2);
    return $search_c;
}

/**
* Shows the statistics for the Election plugin on stats.php.
* If $showsitestats is 1 then we are to only print the overall stats in the
* 'site statistics box' otherwise we show the detailed stats
*
* @param    int     showsitestats   Flag to let us know which stats to get
*/
function plugin_showstats_elections($showsitestats)
{
    global $_CONF;

    USES_lib_admin();

    $retval = '';

    $sql = "SELECT p.pid, p.topic, (
            SELECT COUNT(v.id) FROM " . DB::table('voters') . " v
            WHERE v.pid = p.pid) as voters
        FROM " . DB::table('topics') . " p WHERE (voters > 0) AND " .
        Election::getPermSQL() . " ORDER BY voters DESC LIMIT 10";
    $result = DB_query($sql);
    $nrows  = DB_numRows ($result);
    if ($nrows > 0) {
        $header_arr = array(
            array(
                'text'         => MO::_('Election Topic'),
                'field'        => 'pid',
                'header_class' => 'stats-header-title',
            ),
            array(
                'text'         => MO::_('Votes'),
                'field'        => 'voters',
                'header_class' => 'stats-header-count',
                'field_class'  => 'stats-list-count',
            ),
        );
        $data_arr = array();
        $text_arr = array(
            'has_menu' => false,
            'title'    => MO::_('Top 10 Elections'),
        );

        $filter = new sanitizer();
        $filter->setPostmode('text');

        for ($i = 0; $i < $nrows; $i++) {
            $A = DB_fetchArray ($result);
            $url = Config::get('url') . '/index.php?pid=' . $A['pid']
                 . '&amp;aid=-1';
            $pid = COM_createLink($filter->filterData($A['topic']), $url);
            $voters = COM_NumberFormat ($A['voters']);
            $data_arr[] = array ('pid' => $pid, 'voters' => $voters);
        }
        $retval .= ADMIN_simpleList ('', $header_arr, $text_arr, $data_arr);
    } else {
        $retval .= COM_startBlock(
            MO::_('Top 10 Elections'),
            '',
            COM_getBlockTemplate('_admin_block', 'header')
        );
        $retval .= MO::_('It appears that there are no elections on this site or no one has ever voted.');
        $retval .= COM_endBlock (COM_getBlockTemplate('_admin_block', 'footer'));
    }

    return $retval;
}


/**
* New stats plugin API function for proper integration with the site stats
*
* @return   array(item text, item count);
*
*/
function plugin_statssummary_elections()
{
    list($total_election, $total_answers) = ELECTION_countElectionAndVotes ();

    $item_count = COM_numberFormat ($total_election)
                . ' (' . COM_numberFormat ($total_answers) . ')';

    return array(MO::_('Elections in the system'), $item_count);
}


/**
* This will put an option for election in the command and control block.
*/
function plugin_cclabel_elections()
{
    if (plugin_ismoderator_elections()) {
        return array(
            ucfirst(Config::get('pi_name')),
            Config::get('admin_url') . '/index.php',
            plugin_geticon_elections()
        );
    }
    return false;
}


/**
* Returns the administrative option for this plugin.
*/
function plugin_getadminoption_elections()
{
    global $_CONF;

    if (plugin_ismoderator_elections()) {
        return array(
            ucfirst(Config::get('pi_name')),
            Config::get('admin_url') . '/index.php',
            Election::countElection()
        );
    }
}


/**
 * Handle changing a user ID.
 *
 * @param   integer $origUID    Original user ID
 * @param   integer $destUID    New user ID
 */
function plugin_user_move_elections($origUID, $destUID)
{
    Election::moveUser($origUID, $destUID);
}


/**
* A user is about to be deleted. Update ownership of any election owned
* by that user or delete them.
*
* @param   uid   int   User id of deleted user
*
*/
function plugin_user_delete_elections($uid)
{
    global $_TABLES;    // needed for groups and group_assignments

    // Anonymize pollvoters by uid / ipaddress
    Voter::anonymize($uid);

    if (DB_count(DB::table('topics'), 'owner_id', intval($uid)) == 0) {
        // there are no election owned by this user
        return;
    }

    if (Config::get('delete_election') == 1) {
        // delete the election
        $result = DB_query ("SELECT pid FROM " . DB::table('topics') . " WHERE owner_id=".intval($uid));
        while ($A = DB_fetchArray($result, false)) {
            Election::deleteElection($pid);
        }
    } else {
        // assign ownership to a user from the Root group
        $rootgroup = DB_getItem(
            $_TABLES['groups'],
            'grp_id',
            "grp_name = 'Root'"
        );
        $result = DB_query(
            "SELECT DISTINCT ug_uid FROM {$_TABLES['group_assignments']}
            WHERE ug_main_grp_id = $rootgroup ORDER BY ug_uid LIMIT 1"
        );
        list($rootuser) = DB_fetchArray($result, false);
        Election::getInstance($pid)->setOwner($rootuser)->Save();
    }
}


/**
* Return the current version of code.
* Used in the Plugin Editor to show the registered version and code version
*/
function plugin_chkVersion_elections()
{
    return Config::get('pi_version');
}

/**
* glFusion informs us that we're about to be enabled or disabled
*
* @param    boolean     $enable     true = we're being enabled, false = disabled
* @return   void
*
*/
function plugin_enablestatechange_elections($enable)
{
    global $_TABLES;        // needed for blocks table

    $is_enabled = $enable ? 1 : 0;

    // toggle Election block
    DB_query(
        "UPDATE {$_TABLES['blocks']}
        SET is_enabled = $is_enabled
        WHERE (type = 'phpblock')
        AND (phpblockfn = 'phpblock_" . Config::get('pi_name') . "')"
    );
}

/**
* Display the current poll(s) in a side block
*
* @return   string  HTML for the poll(s) to be displayed (or an empty string)
*
*/
function phpblock_elections()
{
    $retval = '';
    $Election = Election::getOpen(Modes::BLOCK);
    foreach ($Election as $Election) {
        $retval .= $Election->withDisplaytype(Modes::BLOCK)
            ->withShowall(0)
            ->Render();
    }
    return $retval;
}


/**
* Returns the URL of the plugin's icon
*
* @return   string      URL of the icon
*
*/
function plugin_geticon_elections()
{
    return Config::get('url') . '/images/elections.png';
}

/**
* Plugin should display [a] comment[s]
*
* @param   string  $id     Unique idenifier for item comment belongs to
* @param   int     $cid    Comment id to display (possibly including sub-comments)
* @param   string  $title  Page/comment title
* @param   string  $order  'ASC' or 'DESC' or blank
* @param   string  $format 'threaded', 'nested', or 'flat'
* @param   int     $page   Page number of comments to display
* @param   boolean $view   True to view comment (by cid), false to display (by $pid)
* @return  mixed   results of calling the plugin_displaycomment_ function
*/
function plugin_displaycomment_elections($id, $cid, $title, $order, $format, $page, $view)
{
    global $_TABLES;

    $retval = '';

    $pid = $id;
    $aid = 0;

    $result = DB_query("SELECT status FROM " . DB::table('topics') . " WHERE pid='".DB_escapeString($pid)."'");
    if ( $result && DB_numRows($result) > 0 ) {
        $row = DB_fetchArray($result);
        $status = $row['status'];
        if ($is_open != 0) {
            $aid = -1; // poll closed - show result
        }
        $Election = Election::getInstance($pid);
        if (!isset ($_COOKIE['poll-'.$pid]) && !$Election->alreadyVoted() && $aid != -1 ) {
            //$retval .= ELECTION_pollVote($pid);
            $retval .= $Election->Render();
        } else {
            $retval .= (new Results($Election->getID()))->Render();
        }
    } else {
        return false;
    }
    return $retval;
}


/**
* Set template variables.
* A plugin should use its name as a prefix for the names of its
* template variables, e.g. 'election_xxx' and 'lang_election_xxx'.
* 'button_election' is an exception, as such a variable existed for header.thtml
*
* @param    string  $templatename   name of template, e.g. 'header'
* @param    ref     $template       reference of actual template
* @return   void
*/
function plugin_templatesetvars_elections($templatename, &$template)
{
    if ($templatename == 'header') {
        $template->set_var ('button_election', ucfirst(Config::get('pi_name')));
    }
}


/**
 * Get the plugin's CSS into the output header.
 *
 * @return  array   Array of CSS file paths.
 */
function plugin_getheadercss_elections()
{
    global $_CONF;

    $styles = array();
    $file = '/' . Config::get('pi_name') . '/style.css';

    if (@file_exists($_CONF['path_layout'].'plugins' . $file)) {
        $styles[] = $_CONF['path_layout'].'plugins/' . $file;
    } else {
        $styles[] = $_CONF['path_html'] . $file;
    }
    return $styles;
}


/**
* Update the Election plugin
*
* @return   integer     Number of message to display (true = generic success msg)
*/
function plugin_upgrade_elections()
{
    require_once __DIR__ . '/upgrade.php';
    return election_upgrade();
}


/**
* Election Autotags
* [poll: pid] - Displays a link to a poll using the Election Topic as the title.
*               A alternate title may be specified.
* [poll_vote: pid pid class:poll-autotag showall:1] - Class and showall not
*             required. Class specifies the css class and Showall if set to 1, shows all questions
* [poll_result: pid class:poll-autotag] - Class not required. Class specifies
*                                         the css class
*
*/
function plugin_autotags_elections($op, $content = '', $autotag = '')
{
    global $_CONF, $_TABLES;

    $skip = 0;
    $pi_name = Config::get('pi_name');

    if ($op == 'tagname' ) {
        return array(
            Config::KEY . '_vote',
            Config::KEY . '_result',
            Config::KEY,
        );
    } elseif ( $op == 'desc' ) {
        switch($content) {
        case Config::KEY . '_vote':
            return MO::_('HTML: renders a voting block for a Election on this site.  usage: [election_vote:<i>election_id</i>');
            break;
        case Config::KEY . '_result' :
            return MO::_('HTML: renders the results of a Election on this site.  usage: [election_result:<i>election_id</i>]');
            break;
        case Config::KEY:
            return MO::_('Link: to a Election on this site.  link_text defaults to the Election topic.  usage: [election:<i>election_id</i> {link_text}]');
            break;
        default :
            return '';
            break;
        }
    } elseif ($op == 'parse') {
        $tag = str_replace('&nbsp;',' ',$autotag['tagstr']);
        $parms = explode (' ', $tag);
        // Extra test to see if autotag was entered with a space
        // after the module name
        if (substr ($parms[0], -1) == ':') {
            $startpos = strlen ($parms[0]) + strlen ($parms[1]) + 2;
            $label = str_replace (']', '', substr ($tag, $startpos));
            $tagid = $parms[1];
        } else {
            $label = str_replace (']', '',
                     substr ($tag, strlen ($parms[0]) + 1));
            $parms = explode (':', $parms[0]);
            if (count ($parms) > 2) {
                // whoops, there was a ':' in the tag id ...
                array_shift ($parms);
                $tagid = implode (':', $parms);
            } else {
                $tagid = $parms[1];
            }
        }
        $autotag['parm1'] = str_replace(']','',$tagid);
        $autotag['parm2'] = $label;

        $class = 'poll-autotag';
        $showall = false;

        $px = explode (' ', trim ($autotag['parm2']));
        if (is_array ($px)) {
            foreach ($px as $part) {
                if (substr ($part, 0, 6) == 'class:') {
                    $a = explode (':', $part);
                    $class = $a[1];
                    $skip++;
                } elseif (substr ($part, 0, 8) == 'showall:') {
                    $a = explode (':', $part);
                    $val = isset($a[1]) ? (int)$a[1] : 1;
                    if ( $val != 0 ) {
                        $showall = true;
                    }
                    $skip++;
                } else {
                    break;
                }
            }

            if ($skip != 0) {
                if (count ($px) > $skip) {
                    for ($i = 0; $i < $skip; $i++) {
                        array_shift ($px);
                    }
                    $caption = trim (implode (' ', $px));
                } else {
                    $caption = '';
                }
            }
        } else {
            $caption = trim ($autotag['parm2']);
        }

        $pid = COM_applyFilter($autotag['parm1']);

        switch ($autotag['tag']) {
        case Config::KEY:
            $filter = new sanitizer();
            $filter->setPostmode('text');

            if (!empty($autotag['parm2'])) {
                $title = COM_applyFilter($autotag['parm2']);
            } else {
                $title = DB_getItem(
                    DB::table('topics'),
                    'topic',
                    "pid = '$pid'"
                );
            }
            if ( $title == '' ) {
                $retval = '';
            } else {
                $retval = COM_createLink(
                    $filter->filterData($title),
                    Config::get('url') . '/index.php?pid=' . $pid
                );
            }
            break;
        case Config::KEY . '_vote':
        case Config::KEY . '_result':
            if ($autotag['tag'] == Config::KEY . '_vote') {
                $retval = Election::getInstance($pid)
                    ->withShowall($showall)
                    ->withDisplaytype(Modes::AUTOTAG)
                    ->Render();
            } else {
                $Results = new Results($pid);
                $retval = $Results->withDisplaytype(Modes::AUTOTAG)->Render();
            }

            if ( $retval != '' ) {
                $retval = '<div class="' . $class . '">' . $retval . '</div>';
            }
            break;
        }

        $content = str_replace($autotag['tagstr'], $retval, $content);
    }
    return $content;
}


/**
* Return information for a poll
*
* @param    string  $id         poll ID or '*'
* @param    string  $what       comma-separated list of properties
* @param    int     $uid        user ID or 0 = current user
* @param    array   $options    (reserved for future extensions)
* @return   mixed               string or array of strings with the information
*
*/
function plugin_getiteminfo_elections($id, $what, $uid = 0, $options = array())
{
    global $_CONF, $_TABLES;

    $properties = explode(',', $what);
    $fields = array();
    foreach ($properties as $p) {
        switch ($p) {
            case 'date-modified':
            case 'date' :
                $fields[] = 'UNIX_TIMESTAMP(date) AS unixdate';
                break;
            case 'id':
                $fields[] = 'pid';
                break;
            case 'description' :
            case 'searchidx' :
                $fields[] = 'description';
                $fields[] = 'topic';
                break;
            case 'title':
                $fields[] = 'topic';
                break;
            case 'url':
            case 'label':
                $fields[] = 'pid';
                $fields[] = 'hideresults';
                break;
            case 'author' :
            case 'author_name' :
                $fields[] = 'owner_id';
                break;
            case 'hits':
                $fields[] = '(SELECT COUNT(v.id) FROM ' . DB::table('voters') .
                    ' AS v WHERE v.pid = ' . DB::table('topics') . '.pid) AS voters';
                break;
            case 'perms' :
                $fields[] = 'owner_id';
                $fields[] = 'group_id';
                //$fields[] = 'perm_owner';
                //$fields[] = 'perm_group';
                //$fields[] = 'perm_members';
                //$fields[] = 'perm_anon';
                break;
            default:
                break;
        }
    }

    $fields = array_unique($fields);

    if (count($fields) == 0) {
        $retval = array();
        return $retval;
    }

    if ($id == '*') {
        $where = '';
        $permOp = ' WHERE ';
    } else {
        $where = " WHERE pid = '" . DB_escapeString($id) . "' ";
        $permOp = ' AND ';
    }
    $permSql = $permOp  . Election::getPermSQL($uid);
    $sql = 'SELECT ' . implode(',', $fields) . " FROM " . DB::table('topics') . " " . $where . $permSql;
    if ($id != '*') {
        $sql .= ' LIMIT 1';
    }

    $result = DB_query($sql);
    $numRows = DB_numRows($result);

    $filter = new sanitizer();
    $filter->setPostmode('text');

    $retval = array();
    for ($i = 0; $i < $numRows; $i++) {
        $A = DB_fetchArray($result);

        $props = array();
        foreach ($properties as $p) {
            switch ($p) {
            case 'date-modified':
            case 'date' :
                $props[$p] = $A['unixdate'];
                break;
            case 'id':
                $props['id'] = $A['pid'];
                break;
            case 'description' :
                $props['description'] = $filter->filterData($A['description']);
                break;
            case 'searchidx' :
                $props['searchidx'] = $A['topic'];
                if ($A['description'] != '') {
                    $props['searchidx'] .= ' ' . $A['description'];
                }
                break;
            case 'title':
                $props['title'] = $filter->filterData($A['topic']);
                break;
            case 'url':
                $props['url'] = Config::get('url') . '/index.php';
                if (empty($A['pid'])) {
                    $props['url'] .= '?pid=' . $A['pid'];
                }
                break;
            case 'label':
                $props['label'] = ucfirst(Config::get('pi_name'));
                break;
            case 'status':
                $props['status'] = 1; // stub - default
                break;
            case 'author':
                $props['author'] = $A['owner_id'];
                break;
            case 'author_name' :
                $props['author_name'] = COM_getDisplayName($A['owner_id']);
                break;
            case 'hits' :
                $props['hits'] = $A['voters'];
                break;
            case 'perms':
                $props['perms'] = array(
                    'owner_id' => $A['owner_id'],
                    'group_id' => $A['group_id'],
                    'perm_owner' => 3,
                    'perm_group' => 3,
                    'perm_members' => $A['group_id'] == Groups::LOGGED_IN ? 2 : 0,
                    'perm_anon' => $A['group_id'] == Groups::ALL_USERS ? 2 : 0,
                );
                break;
            default:
                $props[$p] = '';
                break;
            }
        }

        $mapped = array();
        foreach ($props as $key => $value) {
            if ($id == '*') {
                if ($value != '') {
                    $mapped[$key] = $value;
                }
            } else {
                $mapped[$key] = $value;
            }
        }

        if ($id == '*') {
            $retval[] = $mapped;
        } else {
            $retval = $mapped;
            break;
        }
    }

    if (($id != '*') && (count($retval) == 1)) {
        $tRet = array_values($retval);
        $retval = $tRet[0];
    }

    return $retval;
}


/**
 * Check if this is a plugin moderator.
 * The Moderator can edit election.
 *
 * @return  boolean     True for admins, False for regular users
 */
function plugin_ismoderator_elections()
{
    static $isMod = NULL;
    if ($isMod === NULL) {
        $isMod = Election::hasRights('admin,edit', 'OR');
    }
    return $isMod;
}


function plugin_getconfigelementhelp_elections($type, $option, $doclang = 'english' )
{
    global $_CONF;

    $retval = '';
    if ($doclang !== 'english') {
        $langs = array($doclang, 'english');
    } else {
        $langs = array($doclang);
    }
    foreach ($langs as $doclang) {
        $file = Config::PI_NAME . '/docs/' . $doclang . '/config.html';
        if (file_exists($_CONF['path_html'] . '/' . $file)) {
            $retval = $_CONF['site_url'] . '/' . $file;
            break;
        }
    }
    return array($retval,0);
}


/**
 * Create an array of groups for the voting group configuration.
 *
 * @return  array               Array of groups as name=>ID
 */
function plugin_configmanager_select_def_voting_gid_elections()
{
    global $_TABLES;

    $A = array();

    $sql = "SELECT grp_id, grp_name FROM {$_TABLES['groups']}
            ORDER BY grp_name ASC";
    $res = DB_query($sql);
    if ($res && DB_numRows($res) > 0) {
        while ($row = DB_fetchArray($res, false)) {
            $row['grp_name'] = htmlspecialchars($row['grp_name']);
            $A[$row['grp_name']] = $row['grp_id'];
        }
    }
    return $A;
}


/**
 * Create an array of groups for the results group configuration.
 *
 * @return  array               Array of groups as name=>ID
 */
function plugin_configmanager_select_def_results_gid_elections()
{
    global $_TABLES;

    $A = array();

    $sql = "SELECT grp_id, grp_name FROM {$_TABLES['groups']}
            ORDER BY grp_name ASC";
    $res = DB_query($sql);
    if ($res && DB_numRows($res) > 0) {
        while ($row = DB_fetchArray($res, false)) {
            $row['grp_name'] = htmlspecialchars($row['grp_name']);
            $A[$row['grp_name']] = $row['grp_id'];
        }
    }
    return $A;
}


/**
* Helper function: count number of election and total number of votes
*
* @return   array(number of election, number of votes);
*
*/
function ELECTION_countElectionAndVotes()
{
    global $_TABLES;

    $total_election = 0;
    $total_answers = 0;

    $result = DB_query(
        "SELECT COUNT(*) AS count FROM " . DB::table('topics') .
        ' WHERE ' . Election::getPermSQL()
    );
    $A = DB_fetchArray($result);
    $total_election = $A['count'];

    $result = DB_query ("SELECT pid FROM " . DB::table('topics') . ' WHERE ' . Election::getPermSQL());
    $nrows = DB_numRows ($result);
    if ($nrows > 0) {
        $topics = '';
        for ($i = 1; $i <= $nrows; $i++) {
            $A = DB_fetchArray($result);
            if ($i > 1) {
                $topics .= ',';
            }
            $topics .= "'" . $A['pid'] . "'";
        }
        $result = DB_query ("SELECT SUM(votes) FROM " . DB::table('answers') . " WHERE pid IN ({$topics})");
        $A = DB_fetchArray($result, true);
        $total_answers = $A[0];
    }

    return array ($total_election, $total_answers);
}


?>
