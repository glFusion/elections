<?php
/**
 * glFusion API functions for the Elections plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021-2022 Lee Garner
 * @package     elections
 * @version     v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

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
use glFusion\Database\Database;
use glFusion\Log\Log;

// May later rely on the class autoloader
require_once __DIR__ . '/' . Config::PI_NAME . '.php';

require_once __DIR__ . '/language/lang_config.php';

if (file_exists(__DIR__ . '/config.php')) {
    include_once __DIR__ . '/config.php';
}

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
        Config::get('pi_display_name') . ' <i class="uk-icon-check-square"></i>' => Config::get('url') . '/index.php',
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

    $db = Database::getInstance();
    $commentcode = $db->getItem(
        DB::table('topics'),
        'commentcode',
        array('pid', $id)
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
    $c = glFusion\Cache::getInstance()->deleteItemsByTag('whatsnew');

    if (Election::hasRights('edit')) {
        CMT_deleteComment($cid, $id, Config::PI_NAME);
        $retval .= COM_refresh(Config::get('url') . "/index.php?pid=$id");
    } else {
        Log::write('system', Log::ERROR,
            "User {$_USER['username']} (IP: {$_SERVER['REMOTE_ADDR']}) "
            . "did not have permissions to delete comment $cid from poll $id"
        );
        $retval .= COM_refresh ($_CONF['site_url'] . '/index.php');
    }
    return $retval;
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
    return array(Config::PI_NAME => ucfirst(Config::PI_NAME));
}


/**
 * Get additional Javascript files to import in the header.
 *
 * @return  array   Array of filenames to import
 */
function plugin_getheaderjs_elections()
{
    $files = array(
        __DIR__ . '/js/util.js',  // uikit v2/v3 compatible notifications
    );
    return $files;
}


/**
* New stats plugin API function for proper integration with the site stats
*
* @return   array(item text, item count);
*
*/
function plugin_statssummary_elections()
{
    global $_TABLES;

    $db = Database::getInstance();
    $total_election = 0;
    $total_answers = 0;

    try {
        $elections = $db->conn->executeQuery(
            "SELECT pid FROM " . DB::table('topics') .
            ' WHERE ' . Election::getPermSQL()
        )->fetchAllAssociative();
    } catch (\Throwable $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        $result = false;
    }
    if (is_array($elections)) {
        $total_election = count($elections);
        if ($total_election > 0) {
            $topics = array();
            for ($i = 0; $i < $total_election; $i++) {
                $A = $elections[$i];
                $topics[] = $A['pid'];
            }
            try {
                $data = $db->conn->executeQuery(
                    "SELECT SUM(votes) AS totalvotes FROM " . DB::table('answers') . " WHERE pid IN (?)",
                    array($topics),
                    array(Database::PARAM_STR_ARRAY)
                )->fetchAssociative();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
                $data = false;
            }
            if (is_array($data)) {
                $total_answers = (int)$data['totalvotes'];
            }
        }
    }
    $item_count = COM_numberFormat($total_election)
        . ' (' . COM_numberFormat ($total_answers) . ')';
    return array(MO::_('Elections (Votes) in the system'), $item_count);
}


/**
 * This will put an option for election in the command and control block.
 */
function plugin_cclabel_elections()
{
    if (plugin_ismoderator_elections()) {
        return array(
            Config::get('pi_display_name'),
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
            Election::countElections()
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
 * A user is about to be deleted.
 * Update ownership of any election owned by that user or delete them.
 *
 * @param   integer $uid    User id of deleted user
 */
function plugin_user_delete_elections($uid)
{
    global $_TABLES;    // needed for groups and group_assignments

    // Anonymize pollvoters by uid / ipaddress
    Voter::anonymize($uid);
    $db = Database::getInstance();

    if ($db->getCount(DB::table('topics'), 'owner_id', $uid, Database::INTEGER) == 0) {
        // there are no election owned by this user
        return;
    }

    try {
        $pids = $db->conn->executeQuery(
            "SELECT pid FROM " . DB::table('topics') . " WHERE owner_id = ?",
            array($uid),
            array(Database::INTEGER)
        )->fetchAllAssociative();
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        $data = NULL;
    }

    if (!is_array($pids) || empty($pids)) {
        return;
    }

    if (Config::get('delete_election') == 1) {
        // delete the elections
        foreach ($pids as $A) {
            Election::deleteElection($A['pid']);
        }
    } else {
        // assign ownership to a user from the Root group
        $rootgroup = $db->getItem(
            $_TABLES['groups'],
            'grp_id',
            array('grp_name' => 'Root')
        );
        try {
            $data = $db->conn->executeQuery(
                "SELECT DISTINCT ug_uid FROM {$_TABLES['group_assignments']}
                WHERE ug_main_grp_id = ? ORDER BY ug_uid LIMIT 1",
                array($rootgroup),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
            $data = NULL;
        }
        foreach ($pids as $A) {
            Election::getInstance($A['pid'])->setOwner($rootuser)->Save();
        }
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
* @param    boolean $enable     true = we're being enabled, false = disabled
* @return   void
*/
function plugin_enablestatechange_elections($enable)
{
    global $_TABLES;        // needed for blocks table

    $is_enabled = $enable ? 1 : 0;

    // toggle Election block
    $db = Database::getInstance();
    try {
        $db->conn->executeUpdate(
            "UPDATE {$_TABLES['blocks']}
            SET is_enabled = $is_enabled
            WHERE (type = 'phpblock')
            AND (phpblockfn = 'phpblock_" . Config::get('pi_name') . "')",
            array($is_enabled, 'phpblock_' . Config::PI_NAME),
            array(Database::INTEGER, Database::STRING)
        );
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
    }
}


/**
* Display the current poll(s) in a side block
*
* @return   string  HTML for the poll(s) to be displayed (or an empty string)
*/
function phpblock_elections()
{
    $retval = '';
    $Elections = Election::getOpen(Modes::BLOCK);
    if (Config::get('block_num_q') < 1) {
        foreach ($Elections as $Election) {
            $retval .= COM_createLink(
                $Election->getTopic(),
                Config::get('url') . '/index.php?pid=' . $Election->getId()
            ) . "<br />\n";
        }
        if (count($Elections) > 1) {
            $retval .= "<br />" . COM_createLink(
                MO::_('List All'),
                Config::get('url') . '/index.php'
            );
        }
    } else {
        foreach ($Elections as $Election) {
            $retval .= $Election->withDisplaytype(Modes::BLOCK)
                ->withShowall(0)
                ->Render();
        }
    }
    return $retval;
}


/**
 * Returns the URL of the plugin's icon.
 *
 * @return   string      URL of the icon
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

    $Election = Election::getInstance($pid);
    if (!$Election->isOpen()) {
        $aid = -1; // poll closed - show result
    }
    if (!isset($_COOKIE['poll-'.$pid]) && !$Election->alreadyVoted() && $aid != -1 ) {
        $retval .= $Election->Render();
    } else {
        $retval .= (new Results($Election->getID()))->Render();
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
    $file = 'style.css';
    $paths = array (
        __DIR__  . '/css/',
        $_CONF['path_layout'] . 'plugins/' . Config::PI_NAME . '/',
    );

    foreach ($paths as $path) {
        if (@file_exists($path . $file)) {
            $styles[] = $path . $file;
        }
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
    return ELECTIONS_upgrade();
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
    $db = Database::getInstance();

    if ($op == 'tagname' ) {
        return array(
            Config::PI_NAME . '_vote',
            Config::PI_NAME . '_result',
            Config::PI_NAME,
        );
    } elseif ( $op == 'desc' ) {
        switch($content) {
        case Config::PI_NAME . '_vote':
            return MO::_('HTML: renders a voting block for a Election on this site.  usage: [election_vote:<i>election_id</i>');
            break;
        case Config::PI_NAME . '_result' :
            return MO::_('HTML: renders the results of a Election on this site.  usage: [election_result:<i>election_id</i>]');
            break;
        case Config::PI_NAME:
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
                    if ($val != 0) {
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
        case Config::PI_NAME:
            $filter = new sanitizer();
            $filter->setPostmode('text');

            if (!empty($autotag['parm2'])) {
                $title = COM_applyFilter($autotag['parm2']);
            } else {
                $title = $db->getItem(
                    DB::table('topics'),
                    'topic',
                    array('pid' => $pid)
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
        case Config::PI_NAME . '_vote':
            $retval = Election::getInstance($pid)
                ->withShowall($showall)
                ->withDisplaytype(Modes::AUTOTAG)
                ->Render();
            break;
        case Config::PI_NAME . '_result':
            $Results = new Results($pid);
            $retval = $Results->withDisplaytype(Modes::AUTOTAG)->Render();
            break;
        }

        if ( $retval != '' ) {
            $retval = '<div class="' . $class . '">' . $retval . '</div>';
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
                $fields[] = 'UNIX_TIMESTAMP(created) AS unixdate';
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

    $qb = Database::getInstance()->conn->createQueryBuilder();
    $qb->select(
        'UNIX_TIMESTAMP(created) AS unixdate', 'pid', 'topic', 'description',
        'hideresults', 'owner_id', 'group_id'
    )->from(DB::table('topics'))
     ->where(Election::getPermSQL($uid));

    if ($id !== '*') {
        $qb->andWhere('pid = :pid')
           ->setParameter('pid', $id, Database::STRING);
    }

    if ($id != '*') {
        $qb->setFirstResult(0)->setMaxResults(1);
    }

    try {
        $stmt = $qb->execute();
    } catch (\Throwable $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        $stmt = NULL;
    }
    if ($stmt) {
        $numRows = $stmt->rowCount();
    } else {
        return '';
    }

    $filter = new sanitizer();
    $filter->setPostmode('text');

    $retval = array();
    for ($i = 0; $i < $numRows; $i++) {
        $A = $stmt->fetchAssociative();
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
 * Run scheduled tasks.
 * - Delete old carts that aren't assigned to any user.
 * - Call PLG_itemDeleted to notify plugins that an item is not available
 */
function plugin_runScheduledTask_elections()
{
    $cutoff = time() - (Config::get('archive_days', 365) * 86400);
    $db = Database::getInstance();
    try {
        $db->conn->executeUpdate(
            "UPDATE " . DB::table('topics') . " SET status = ? WHERE closes < FROM_UNIXTIME(?)",
            array(\Elections\Models\Status::ARCHIVED, $cutoff),
            array(Database::INTEGER, Database::STRING)
        );
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
    }
}


/**
 * Return information about the plugin's tables.
 *
 * @return  array       Array of table_name=>table_info
 */
function plugin_getContentTableInfo_elections()
{
    $contentItems = array(
        DB::table('questions') => array(
            'plugin' => Config::PI_NAME,
            'primary_key' => 'pid',
            'columns' => array(
                'question',
            )
        ),
        DB::table('answers') => array(
            'plugin' => Config::PI_NAME,
            'primary_key' => 'pid,qid,aid',
            'columns' => array(
                'answer',
            )
        ),
        DB::table('topics') => array(
            'plugin' => Config::PI_NAME,
            'primary_key' => 'pid,qid',
            'columns' => array(
                'topic',
                'description',
            )
        ),
    );
    return $contentItems;
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
    $db = Database::getInstance();
    try {
        $data = $db->conn->executeQuery(
            "SELECT grp_id, grp_name FROM {$_TABLES['groups']}
            ORDER BY grp_name ASC"
        )->fetchAllAssociative();
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        $data = NULL;
    }

    if (is_array($data)) {
        foreach ($data as $row) {
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

    $db = Database::getInstance();
    try {
        $data = $db->conn->executeQuery(
            "SELECT grp_id, grp_name FROM {$_TABLES['groups']}
            ORDER BY grp_name ASC"
        )->fetchAllAssociative();
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        $data = NULL;
    }

    if (is_array($data)) {
        foreach ($data as $row) {
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

    $db = Database::getInstance();
    $total_election = 0;
    $total_answers = 0;

    try {
        $elections = $db->conn->executeQuery(
            "SELECT pid FROM " . DB::table('topics') .
            ' WHERE ' . Election::getPermSQL()
        )->fetchAllAssociative();
    } catch (\Throwable $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        $result = false;
    }
    if (is_array($elections)) {
        $total_election = count($elections);
        if ($total_election > 0) {
            $topics = '';
            for ($i = 0; $i < $total_election; $i++) {
                $A = $elections[$i];
                if ($i > 0) {
                    $topics .= ',';
                }
                $topics .= "'" . $A['pid'] . "'";
            }
            try {
                $total_answers = (int)$db->conn->executeQuery(
                    "SELECT SUM(votes) FROM " . DB::table('answers') . " WHERE pid IN ({$topics})"
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
                $total_answers = 0;
            }
        }
    }
    return array ($total_election, $total_answers);
}
