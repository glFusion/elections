<?php
/**
 * Class to represent a election.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     election
 * @version     v0.1.0
 * @since       v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections;
use Elections\Models\Dates;
use Elections\Models\Groups;
use Elections\Models\Modes;
use Elections\Models\Status;
use Elections\Views\Results;


/**
 * Class for a single election.
 * @package elections
 */
class Election
{
    /** Election ID.
     * @var string */
    private $pid = '';

    /** Old election ID. Used when editing.
     * @var string */
    private $old_pid = '';

    /** Election Topic.
     * @var string */
    private $topic = '';

    /** Election Description.
     * @var string */
    private $dscp = '';

    /** Date the election was added.
     * @var object */
    private $Created = NULL;

    /** Does the election appear in the election block?
     * @var boolean */
    private $inblock = 1;

    /** Is the election open to submissions?
     * @var boolean */
    private $status = 0;

    /** Opening date/time.
     * @var object */
    private $Opens = NULL;

    /** Closing date/time
     * @var object */
    private $Closes = NULL;

    /** Hide results while the election is open?
     * @var boolean */
    private $hideresults = 1;

    /** Comments enabled/closed/disabled/etc.?
     * @var integer */
    private $commentcode = 0;

    /** Is a login required to submit the election?
     * @var boolean */
    //private $login_required = 0;

    /** Owner ID.
     * @var integer */
    private $owner_id = 0;

    /** Voting Group ID.
     * @var integer */
    private $voting_gid = Groups::ALL_USERS;

    /** Results Group ID.
     * @var integer */
    private $results_gid = Groups::ALL_USERS;

    /** Is this a new record?
     * @var boolean */
    private $isNew = true;

    /** Questions for this election.
     * @var array */
    private $_Questions = array();

    /** Selections made for this election's questions.
     * @var array */
    private $_selections = array();

    /** Display modifier. Nonzero to show all questions, zero to show only one.
     * @var integer */
    private $disp_showall = 1;

    /** Display modifier. 0 for normal, 1 for block, 2 for autotag.
     * @var integer */
    private $disp_type = Modes::NORMAL;

    /** Modifications allowed by the voter after voting.
     * 0 = None, 1 = View Vote, 2 = Change Vote
     * @var integer */
    private $mod_allowed = 0;

    /** Randomize questions as displayed?
     * @var boolean */
    private $rnd_questions = 0;

    /** Randomize answers as displayed?
     * @var boolean */
    private $rnd_answers = 0;

    /** Declare a winner, or just use as a poll?
     * @var boolean */
    private $decl_winner = 1;

    /** Number of votes cast.
     * @var integer */
    private $_vote_count = 0;

    /** Voting record ID, used when viewing or changing previously-cast votes.
     * @var integer */
    private $_vote_id = 0;

    /** Decryption key to view votes.
     * @var string */
    private $_access_key = '';

    /** Flag to indicate admin access by root or election owner.
     * @var boolean */
    private $_isAdmin = false;

    /** URL to send the voter after voting.
     * @var string */
    private $_aftervoteUrl = '';


    /**
     * Constructor.
     * Create a election object for the specified user ID, or the current
     * user if none specified.
     * If a key is requested, then just build the election for that key (requires a $uid).
     *
     * @param   string  $pid     Election ID, empty to create a new record
     */
    function __construct($pid = '')
    {
        global $_CONF;

        $this->Opens = new \Date('now', $_CONF['timezone']);
        $this->Closes = clone $this->Opens;
        if (is_array($pid)) {
            $this->setVars($pid, true);
        } elseif (!empty($pid)) {
            $pid = COM_sanitizeID($pid);
            $this->setID($pid);
            if ($this->Read()) {
                $this->isNew = false;
                $this->old_pid = $this->pid;
            }
        } else {
            // Creating a new election, set the default groups based on the
            // global login-required setting.
            $this->voting_gid = Config::get('def_voting_gid');
            $this->results_gid = Config::get('def_results_gid');
            $this->setID(COM_makeSid());
            $this->setOwner();
            $this->mod_allowed = (int)Config::get('allow_votemod');
        }
        $this->_Questions = Question::getByElection($this->pid);
    }


    /**
     * Get an instance of a election object.
     *
     * @param   string  $pid    Election record ID
     * @return  object      Election object
     */
    public static function getInstance($pid)
    {
        return new self($pid);
    }


    /**
     * Get all election for operations which must cycle through each one.
     *
     * @return  array       Array of Election objects
     */
    public static function XgetAll()
    {
        $retval = array();
        $sql = "SELECT * FROM " . DB::table('topics');
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[$A['pid']] = new self($A);
        }
        return $retval;
    }


    /**
     * Get all the currently open election.
     *
     * @param   boolean $modes  Mode for display
     * @return  array       Array of Election objects
     */
    public static function getOpen($mode=NULL)
    {
        global $_CONF;

        if ($mode === NULL) {
            $mode = Modes::ALL;
        }
        $in_block = $mode == Modes::BLOCK ? ' AND display = 1' : '';
        $sql = "SELECT p.*,
            (SELECT count(v.id) FROM " . DB::table('voters') . " v
                WHERE v.pid = p.pid) as vote_count FROM " . DB::table('topics') . " p
            WHERE status = " . Status::OPEN . " $in_block
            AND '" . $_CONF['_now']->toMySQL(false) . "' BETWEEN opens AND closes " .
            SEC_buildAccessSql('AND', 'group_id') .
            " ORDER BY pid ASC";
        //echo $sql;die;
        $res = DB_query($sql);
        $retval = array();
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = new self($A);
        }
        return $retval;
    }


    /**
     * Get a count of election in the system.
     * Only used for the admin menu, so no permission check is done.
     *
     * @return  integer     Number of election in the system
     */
    public static function countElection()
    {
        $result = DB_query("SELECT COUNT(*) AS cnt FROM " . DB::table('topics'));
        $A = DB_fetchArray ($result);
        return (int)$A['cnt'];
    }


    /**
     * Set the election record ID.
     *
     * @param   string  $id     Record ID for election
     * @return  object  $this
     */
    private function setID($id)
    {
        $this->pid = COM_sanitizeID($id, false);
        return $this;
    }


    /**
     * Get the election reord ID.
     *
     * @return  string  Record ID of election
     */
    public function getID()
    {
        return $this->pid;
    }


    /**
     * Set the election topic.
     *
     * @param   string  $name   Name of election
     * @return  object  $this
     */
    private function setTopic($topic)
    {
        $this->topic = $topic;
        return $this;
    }


    /**
     * Get the election name.
     *
     * @return  string      Name of election
     */
    public function getName()
    {
        return $this->electionName;
    }


    /**
     * Get the status (open, closed or archived).
     *
     * @return  intger      Status flag
     */
    public function getStatus()
    {
        return (int)$this->status;
    }


    /**
     * Set the owner ID.
     *
     * @param   integer $uid    User ID of election owner
     * @return  object  $this
     */
    public function setOwner($uid = 0)
    {
        global $_USER;

        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $this->owner_id = (int)$uid;
        return $this;
    }


    /**
     * Check if this is a new record.
     *
     * @return  integer     1 if new, 0 if not
     */
    public function isNew()
    {
        return $this->isNew ? 1 : 0;
    }


    /**
     * Check if the election is open to submissions.
     *
     * @return  integer     1 if open, 0 if closed
     */
    public function isOpen()
    {
        global $_CONF;

        if (
            $this->status > 0 ||
            $this->Opens->toMySQL() > $_CONF['_now']->toMySQL() ||
            $this->Closes->toMySQL() < $_CONF['_now']->toMySQL()
        ) {
            return 0;
        } else {
            return 1;
        }
    }


    /**
     * Check if the current user can view the election results.
     *
     * @return  integer     1 if viewing allowed, 0 if not
     */
    public function canViewResults()
    {
        static $can_view = NULL;

        if ($can_view === NULL) {
            $admin = SEC_inGroup('Root');
            if ($admin) {
                $can_view = true;
            } elseif ($this->isOpen() && $this->hideresults) {
                $can_view = false;
            } elseif (
                $this->isNew() ||
                $this->status == Status::ARCHIVED ||
                !SEC_inGroup($this->results_gid)
            ) {
                $can_view = false;
            } else {
                $can_view = true;
            }
        }
        return $can_view;
    }


    /**
     * Set the showall flag.
     *
     * @param   integer $flag   1 to show all questions, 0 for only the first.
     * @return  object  $this
     */
    public function withShowall($flag)
    {
        $this->disp_showall = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Set the display type.
     *
     * @param   integer $flag   0 for normal, 1 if in a block, 2 for autotag.
     * @return  object  $this
     */
    public function withDisplaytype($flag)
    {
        $this->disp_type = (int)$flag;
        return $this;
    }


    /**
     * Set the selected answer array to pre-select answers.
     *
     * @param   array   Array of questionID->answerID pairs
     * @return  object  $this
     */
    public function withSelections($aid)
    {
        if (is_array($aid)) {
            $this->_selections = $aid;
        }
        return $this;
    }


    public function withKey($key)
    {
        $this->_access_key = $key;
        return $this;
    }

    public function withVoteId($id)
    {
        $this->_vote_id = (int)$id;
        return $this;
    }


    /**
     * Check if the current user may vote in this election.
     * Used to collect results from different fields that may be added,
     * such as a closing date.
     *
     * @return  integer     1 if voting allowed, 0 if not
     */
    public function canVote()
    {
        return SEC_inGroup($this->voting_gid) &&
            $this->isOpen() &&
            !$this->alreadyVoted();
    }


    /**
     * Get the election topic name.
     *
     * @return  string      Topic name
     */
    public function getTopic()
    {
        return $this->topic;
    }


    /**
     * Get the number of questions appearing on this election.
     *
     * @return  integer     Number of questions asked
     */
    public function numQuestions()
    {
        return count($this->_Questions);
    }


    /**
     * Check if the results are allowed to be shown.
     *
     * @return  integer     1 if hidden, 0 if shown
     */
    public function hideResults()
    {
        return $this->hideresults ? 1 : 0;
    }


    /**
     * Get the owner ID for this election.
     *
     * @return  integer     User ID of the election owner
     */
    public function getOwnerID()
    {
        return (int)$this->owner_id;
    }


    /**
     * Get the total number of votes cast in this election.
     * Read from the `voters` table when retrieving the election.
     *
     * @return  integer     Number of votes case
     */
    public function numVotes()
    {
        return (int)$this->_vote_count;
    }


    /**
     * Get the question objects for this election.
     *
     * @return  array       Array of Question objects
     */
    public function getQuestions()
    {
        return $this->_Questions;
    }


    /**
     * Get the comment code setting for this election.
     *
     * @return  integer     Comment code value
     */
    public function getCommentcode()
    {
        return (int)$this->commentcode;
    }


    /**
     * Get the group ID that can vote in this election.
     *
     * @return  integer     Voting group ID
     */
    public function getVotingGroup()
    {
        return (int)$this->voting_gid;
    }


    /**
     * Get the group ID that can view the results for this election.
     *
     * @return  integer     Results-viewing group ID
     */
    public function getResultsGroup()
    {
        return (int)$this->results_gid;
    }


    /**
     * See if this election declares a winner.
     * May be false if used for information only.
     *
     * @return  boolean     1 if a winner is declared, False if not
     */
    public function declaresWinner()
    {
        return $this->decl_winner ? 1 : 0;
    }


    /**
     * Create a date object based on a string or timestamp.
     *
     * @param   string|integer  $dt     Date string or timestamp
     * @param   boolean     $local      True for local time, False for UTC
     * @return  object      Date object
     */
    private function _createDate($dt, $local)
    {
        global $_CONF;

        if (is_numeric($dt)) {
            // Timestamp
            $retval = new \Date;
            $retval->setTimestamp($dt);
            $retval->setTimezone(new \DateTimezone($_CONF['timezone']));
        } elseif ($local) {
            // Date string using local time
            $retval = new \Date($dt, $_CONF['timezone']);
        } else {
            // Date string using UTC
            $retval = new \Date($dt);
            $retval->setTimezone(new \DateTimezone($_CONF['timezone']));
        }
        return $retval;
    }


    /**
     * Set the opening date, minimum date by default.
     *
     * @param   string|integer  $dt     Datetime string or timestamp
     * @return  object  $this
     */
    public function setOpenDate($dt=NULL, $local=false)
    {
        if (empty($dt)) {
            $dt = Dates::minDateTime();
        }
        $this->Opens = self::_createDate($dt, $local);
        return $this;
    }


    /**
     * Set the closing date, maximum date by default.
     *
     * @param   string|integer  $dt     Datetime string or timestamp
     * @return  object  $this
     */
    public function setClosingDate($dt=NULL, $local=false)
    {
        if (empty($dt)) {
            $dt = Dates::maxDateTime();
        }
        $this->Closes = self::_createDate($dt, $local);
        return $this;
    }


    /**
     * Set the closing date, maximum date by default.
     *
     * @param   string|integer  $dt     Datetime string or timestamp
     * @return  object  $this
     */
    public function setCreatedDate($dt=NULL, $local=NULL)
    {
        if (empty($dt)) {
            $dt = 'now';
        }
        $this->Created = self::_createDate($dt, $local);
        return $this;
    }


    /**
     * Read a single election record from the database
     *
     * @return  boolean     True on success, False on error
     */
    public function Read()
    {
        $this->Questions = array();

        $sql = "SELECT p.*, count(*) as vote_count FROM " . DB::table('topics') . " p
            LEFT JOIN " . DB::table('voters') . " v
            ON v.pid = p.pid
            WHERE p.pid = '" . DB_escapeString($this->pid) . "'";
        //echo $sql;die;
        $res1 = DB_query($sql, 1);
        if (!$res1 || DB_numRows($res1) < 1) {
            return false;
        }
        $A = DB_fetchArray($res1, false);
        $this->setVars($A, true);
        return true;
    }


    /**
     * Set all values for this election into local variables.
     *
     * @param   array   $A          Array of values to use.
     * @param   boolean $fromdb     Indicate if $A is from the DB or a election.
     */
    function setVars($A, $fromdb=false)
    {
        global $_CONF;

        if (!is_array($A)) {
            return false;
        }

        $this->setID($A['pid']);
        $this->topic = $A['topic'];
        $this->dscp = $A['description'];
        $this->inblock = isset($A['display']) && $A['display'] ? 1 : 0;
        $this->status = (int)$A['status'];
        $this->rnd_questions = isset($A['rnd_questions']) && $A['rnd_questions'] ? 1 : 0;
        $this->rnd_answers = isset($A['rnd_answers']) && $A['rnd_answers'] ? 1 : 0;
        $this->decl_winner = isset($A['decl_winner']) && $A['decl_winner'] ? 1 : 0;
        //$this->login_required = isset($A['login_required']) && $A['login_required'] ? 1 : 0;
        $this->hideresults = isset($A['hideresults']) && $A['hideresults'] ? 1 : 0;
        $this->commentcode = (int)$A['commentcode'];
        $this->setOwner($A['owner_id']);
        $this->voting_gid = (int)$A['group_id'];
        $this->results_gid = (int)$A['results_gid'];
        $this->mod_allowed = (int)$A['voteaccess'];
        if ($fromdb) {
            if (isset($A['vote_count'])) {
                $this->_vote_count = (int)$A['vote_count'];
            }
            if (!isset($A['created']) || $A['created'] === NULL) {
                $this->Created = clone $_CONF['_now'];
            } else {
                $this->setCreatedDate($A['created'], false);
            }
            $this->setOpenDate($A['opens'], false);
            $this->setClosingDate($A['closes'], false);
        } else {
            if (empty($A['opens_date'])) {
                $A['opens_date'] = Dates::minDateTime();
            }
            $this->setOpenDate($A['opens_date'], true);
            if (empty($A['closes_date'])) {
                $A['closes_date'] = Dates::maxDateTime();
            }
            $this->setClosingDate($A['closes_date'], true);
        }
    }


    /**
     * Create the edit election for all the electionzer variables.
     * Checks the type of edit being done to select the right template.
     *
     * @param   string  $type   Type of editing- 'edit' or 'registration'
     * @return  string          HTML for edit election
     */
    public function editElection($type = 'edit')
    {
        global $_CONF, $_GROUPS, $_USER, $MESSAGE;

        $retval = COM_startBlock(
            MO::_('Edit Election'),
            COM_getBlockTemplate ('_admin_block', 'header')
        );

        $T = new \Template(__DIR__ . '/../templates/admin/');
        $T->set_file(array(
            'editor' => 'editor.thtml',
            'question' => 'questions.thtml',
            'answer' => 'answeroptions.thtml',
        ) );

        if (!empty($this->pid)) {       // if not a new record
            // Get permissions for election
            if (!self::hasRights('edit')) {
                // User doesn't have write access...bail
                $retval .= COM_startBlock (MO::_('Access Denied'), '',
                               COM_getBlockTemplate ('_msg_block', 'header'));
                $retval .= MO::_('You are trying to access a poll that you do not have rights to.') .
                    MO::_('This attempt has been logged.') .
                    COM_createLink(
                        MO::_('Please go back to the poll administration screen.'),
                        Config::get('admin_url')
                    );
                $retval .= COM_endBlock (COM_getBlockTemplate ('_msg_block', 'footer'));
                COM_accessLog("User {$_USER['username']} tried to illegally submit or edit election $pid.");
                return $retval;
            }
            if (!empty($this->owner_id)) {
                $delbutton = '<input type="submit" value="' . MO::_('Delete')
                    . '" name="delete"%s>';
                $jsconfirm = ' onclick="return confirm(\'' . $MESSAGE[76] . '\');"';
                $T->set_var(array(
                    'delete_option' => sprintf($delbutton, $jsconfirm),
                    'delete_option_no_confirmation' => sprintf ($delbutton, ''),
                    'delete_button' => true,
                    'lang_delete'   => MO::_('Delete'),
                    'lang_delete_confirm' => $MESSAGE[76]
                ) );
            }
            $Questions = Question::getByElection($this->pid);
        } else {
            $this->owner_id = (int)$_USER['uid'];
            $this->voting_gid = (int)SEC_getFeatureGroup ('election.edit');
            $this->commentcode = (int)$_CONF['comment_code'];
            SEC_setDefaultPermissions($A, Config::get('default_permissions'));
            $Questions = array();
        }

        if ($this->old_pid == '') {
            // creating a new election, use empty date/time fields
            $open_date = '';
            $close_date = '';
        } else {
            $open_date = $this->Opens->format('Y-m-d H:i', true);
            if ($open_date == Dates::MIN_DATE) {
                $open_date = '';
            }
            $close_date = $this->Closes->format('Y-m-d H:i', true);
            if ($close_date == Dates::MAX_DATE) {
                $close_date = '';
            }
        }
        $ownername = COM_getDisplayName($this->owner_id);
        $T->set_var(array(
            'action_url' => Config::get('admin_url') . '/index.php',
            'lang_electionid' => MO::_('Election ID'),
            'id' => $this->pid,
            'old_pid' => $this->old_pid,
            'lang_donotusespaces' => MO::_('Do not use spaces.'),
            'lang_topic' => MO::_('Topic'),
            'topic' => htmlspecialchars ($this->topic),
            'lang_mode' => MO::_('Comments'),
            'description' => $this->dscp,
            'lang_description' => MO::_('Description'),
            'comment_options' => COM_optionList(DB::table('commentcodes'),'code,name',$this->commentcode),
            'lang_appearsonhomepage' => MO::_('Appears on Election Block'),
            'lang_status' => MO::_('Voting Status'),
            'lang_open' => MO::_('Open'),
            'lang_closed' => MO::_('Closed'),
            'lang_archived' => MO::_('Archived'),
            'open_'.$this->status => 'selected="selected"',
            'lang_hideresults' => MO::_('Hide results while open?'),
            'hideresults_explain' => MO::_('While the election is open, only the owner and administrators can see the results.'),
            'topic_info' => MO::_('The topic will be only displayed if there is more than 1 question.'),
            'display' => $this->inblock ? 'checked="checked"' : '',
            'hideresults' => $this->hideresults ? 'checked="checked"' : '',
            'lang_opens' => MO::_('Opens'),
            'lang_closes' => MO::_('Closes'),
            'opens_date' => $open_date,
            'closes_date' => $close_date,
            'min_date' => Dates::MIN_DATE,
            'max_date' => Dates::MAX_DATE,
            // user access info
            'lang_accessrights' => MO::_('Access Rights'),
            'lang_owner' => MO::_('Owner'),
            'owner_username' => DB_getItem(DB::table('users'), 'username', "uid = {$this->owner_id}"),
            'owner_name' => $ownername,
            'owner' => $ownername,
            'owner_id' => $this->owner_id,
            'lang_voting_group' => MO::_('Allowed to Vote'),
            'lang_results_group' => MO::_('Allowed to View Results'),
            'group_dropdown' => SEC_getGroupDropdown($this->voting_gid, 3),
            'res_grp_dropdown' => SEC_getGroupDropdown($this->results_gid, 3, 'results_gid'),
            'lang_save' => MO::_('Save'),
            'lang_cancel' => MO::_('Cancel'),
            'lang_datepicker' => MO::_('Date Picker'),
            'lang_timepicker' => MO::_('Time Picker'),
            'lang_view' => MO::_('View your vote'),
            'lang_noaccess' => MO::_('Access Denied'),
            'lang_voteaccess' => MO::_('After-voting access for voters'),
            'voteaccess_' . $this->mod_allowed => 'selected="selected"',
            'lang_general' => MO::_('General'),
            'lang_questions' => MO::_('Questions'),
            'lang_permissions' => MO::_('Permissions'),
            'lang_back' => MO::_('Back to Listing'),
            'rndq_chk' => $this->rnd_questions ? 'checked="checked"' : '',
            'rnda_chk' => $this->rnd_answers ? 'checked="checked"' : '',
            'lang_rnd_q' => MO::_('Randomize question order?'),
            'lang_rnd_a' => MO::_('Randomize answer order?'),
            'lang_decl_winner' => MO::_('Declares a winner?'),
            'decl_chk' => $this->decl_winner ? 'checked="checked"' : '',
            'timezone' => $_CONF['timezone'],
            'lang_resetresults' => $this->old_pid != '' ? MO::_('Reset Results') : '',
            'lang_exp_reset' => MO::_('Reset all results for this election'),
            'lang_reset' => MO::_('Reset'),
        ) );

        $T->set_block('editor','questiontab','qt');
        $maxQ = Config::get('maxquestions');

        for ($j = 0; $j < $maxQ; $j++) {
            $display_id = $j+1;
            if ($j > 0) {
                $T->set_var('style', 'style="display:none;"');
            } else {
                $T->set_var('style', '');
            }

            $T->set_var('question_tab', MO::_('Question') . " $display_id");
            $T->set_var('question_id', $j);
            if (isset($Questions[$j])) {
                $T->set_var(array(
                    'question_text' => $Questions[$j]->getQuestion(),
                    'question_id' => $j,
                    'hasdata' => true,
                ) );
                $Answers = $Questions[$j]->getAnswers();
            } else {
                $Answers = array();
                $T->unset_var('hasdata');
                $T->unset_var('question_text');
            }
            $T->set_var('lang_question', MO::_('Question') . " $display_id");
            $T->set_var('lang_answer', MO::_('Answer'));
            $T->set_var('lang_votes', MO::_('Votes'));
            $T->set_var('lang_remark', MO::_('Remark'));
            $T->set_var('lang_saveaddnew', MO::_('Save and Add'));

            $T->parse('qt','questiontab',true);

            $T->set_block('answer', 'AnswerRow', 'AR');
            for ($i = 0; $i < Config::get('maxanswers'); $i++) {
                if (isset($Answers[$i])) {
                    $T->set_var(array(
                        'answer_text' => htmlspecialchars ($Answers[$i]->getAnswer()),
                        'answer_votes' => $Answers[$i]->getVotes(),
                        'remark_text' => htmlspecialchars($Answers[$i]->getRemark()),
                    ) );
                } else {
                    $T->set_var(array(
                        'answer_text' => '',
                        'answer_votes' => '',
                        'remark_text' => '',
                    ) );
                }
                $T->parse ('AR', 'AnswerRow', true);
            }
            $T->parse ('answer_option', 'answer', true);
            $T->parse ('question_list', 'question', true);
            $T->clear_var ('answer_option');
        }
        $token = SEC_createToken();
        $T->set_var(array(
            'sectoken_name' => CSRF_TOKEN,
            'gltoken_name' => CSRF_TOKEN,
            'sectoken' => $token,
            'gltoken' => $token,
        ) );
        $T->parse('output','editor');
        $retval .= $T->finish($T->get_var('output'));
        $retval .= COM_endBlock (COM_getBlockTemplate ('_admin_block', 'footer'));
        return $retval;
    }


    /**
     * Save a election definition.
     * If creating a new election, or changing the Election ID of an existing one,
     * then the DB is checked to ensure that the ID is unique.
     *
     * @param   array   $A      Array of values (e.g. $_POST)
     * @return  string      Error message, empty on success
     */
    function Save($A = '')
    {
        global $_CONF;

        if (is_array($A)) {
            if (isset($A['old_pid'])) {
                $this->old_pid = $A['old_pid'];
            }
            $this->setVars($A, false);
        }
        if ($this->Created === NULL) {
            $this->Created = clone $_CONF['_now'];
        }

        $frm_name = $this->topic;
        if (empty($frm_name)) {
            return MO::_('A name is required.');
        }

        if (isset($A['resetresults']) && $A['resetresults'] == 1) {
            self::deleteVotes($this->pid);
        }

        // If saving a new record or changing the ID of an existing one,
        // make sure the new election ID doesn't already exist.
        $changingID = (!$this->isNew() && $this->pid != $this->old_pid);
        if ($this->isNew || $changingID) {
            $x = DB_count(DB::table('topics'), 'pid', $this->pid);
            if ($x > 0) {
                $this->pid = COM_makeSid();
                $changingID = true;     // treat as a changed ID if we have to create one
            }
        }

        if (!$this->isNew && $this->old_pid != '') {
            $sql1 = "UPDATE " . DB::table('topics') . " SET ";
            $sql3 = " WHERE pid = '{$this->old_pid}'";
        } else {
            $sql1 = "INSERT INTO " . DB::table('topics') . "  SET ";
            $sql3 = '';
        }
        $sql2 = "pid = '" . DB_escapeString($this->pid) . "',
            topic = '" . DB_escapeString($this->topic) . "',
            description = '" . DB_escapeString($this->dscp) . "',
            created = '" . $this->Created->toMySQL(false) . "',
            opens = '" . $this->Opens->toMySQL(false) . "',
            closes = '" . $this->Closes->toMySQL(false) . "',
            display = " . (int)$this->inblock . ",
            status = " . (int)$this->status . ",
            hideresults = " . (int)$this->hideresults . ",
            commentcode = " . (int)$this->commentcode . ",
            owner_id = " . (int)$this->owner_id . ",
            group_id = " . (int)$this->voting_gid . ",
            results_gid = " . (int)$this->results_gid . ",
            voteaccess = " . (int)$this->mod_allowed . ",
            rnd_questions = " . (int)$this->rnd_questions . ",
            rnd_answers = " . (int)$this->rnd_answers . ",
            decl_winner = " . (int)$this->decl_winner;
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql, 1);

        if (!DB_error()) {
            $Questions = Question::getByElection($this->old_pid);
            for ($i = 0; $i < Config::get('maxquestions'); $i++) {
                if (empty($A['question'][$i])) {
                    break;
                }
                if (isset($Questions[$i])) {
                    $Q = $Questions[$i];
                } else {
                    $Q = new Question();
                }
                $Q->setPid($this->pid)
                    ->setQid($i)
                    ->setQuestion($A['question'][$i])
                    ->setAnswers($A)
                    ->Save();
            }

            // Now delete any questions that were removed.
            for (; $i < count($Questions); $i++) {
                $Questions[$i]->Delete();
            }

            if (!$this->isNew && $changingID) {
                // Questions and answers were already saved above,
                // so just delete the old election IDs.
                Answer::deleteElection($this->old_pid);
                Question::deleteElection($this->old_pid);
                // Still need to update the voter records.
                Voter::changePid($this->old_pid, $this->pid);
            }

            CTL_clearCache();       // so autotags pick up changes
            $msg = '';              // no error message if successful
            PLG_itemSaved($this->pid, 'election', $this->old_pid);
        } else {
            COM_errorLog("Election::Save Error: $sql");
            $msg = "An error occurred saving the election";
        }
        return $msg;
    }


    /**
     * Wrapper for SEC_hasRights(), prepends the privilege with the plugin name.
     *
     * @param   string|array    $privs  Privileges needed, e.g. 'edit', 'admin'
     * @param   string          $oper   Operator
     * @return  boolean     True if the user has the requested privilege
     */
    public static function hasRights($privs, $oper='AND')
    {
        $pi_name = Config::get('pi_name');
        if (is_string($privs)) {
            $privs = explode(',', $privs);
        }
        foreach ($privs as $idx=>$priv) {
            $privs[$idx] = "{$pi_name}.{$priv}";
        }
        return SEC_hasRights($privs, $oper);
    }


    /**
     * Uses lib-admin to list the electionzer definitions and allow updating.
     *
     * @return  string  HTML for the list
     */
    public static function adminList()
    {
        global $_CONF;

        $retval = '';

        // writing the actual list
        $header_arr = array(      # display 'text' and use table field 'field'
            array(
                'text' => MO::_('Edit'),
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
                'width' => '25px',
            ),
            array(
                'text' => MO::_('Topic'),
                'field' => 'topic_preview',
                'sort' => true,
            ),
            array(
                'text' => MO::_('Vote Count'),
                'field' => 'vote_count',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => MO::_('Results'),
                'field' => 'results',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => MO::_('Created'),
                'field' => 'created',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => MO::_('Opens'),
                'field' => 'opens',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => MO::_('Closes'),
                'field' => 'closes',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => MO::_('Status'),
                'field' => 'status',
                'sort' => true,
                'align' => 'center',
                //'width' => '35px',
            ),
            array(
                'text' => MO::_('Reset'),
                'field' => 'reset',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => MO::_('Delete'),
                'field' => 'delete',
                'sort' => false,
                'align' => 'center',
                'width' => '35px',
            ),
        );
        $defsort_arr = array(
            'field' => 'created',
            'direction' => 'desc',
        );

        $text_arr = array(
            'has_extras'   => true,
            'instructions' => MO::_('To modify or delete an election, click on the edit icon of the election.') .
                MO::_('To create a new election, click on "Create New" above.'),
            'form_url'     => Config::get('admin_url') . '/index.php',
        );

        $query_arr = array(
            'table' => 'electiontopics',
            'sql' => "SELECT p.*, count(v.id) as vote_count
                FROM " . DB::table('topics') . " p
                LEFT JOIN " . DB::table('voters') . " v
                ON v.pid = p.pid",
            'query_fields' => array('topic'),
            'default_filter' => 'AND' . self::getPermSql(),
            'group_by' => 'p.pid',
        );
        $extras = array(
            'token' => SEC_createToken(),
            '_now' => $_CONF['_now']->toMySQL(false),
            'is_admin' => true,
        );

        $retval .= ADMIN_list (
            Config::PI_NAME . '_' . __FUNCTION__,
            array(__CLASS__, 'getListField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr, '', $extras
        );
        $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $retval;
    }


    /**
     * Determine what to display in the admin list for each form.
     *
     * @param   string  $fieldname  Name of the field, from database
     * @param   mixed   $fieldvalue Value of the current field
     * @param   array   $A          Array of all name/field pairs
     * @param   array   $icon_arr   Array of system icons
     * @param   array   $extras     Array of verbatim values
     * @return  string              HTML for the field cell
     */
    public static function getListField($fieldname, $fieldvalue, $A, $icon_arr, $extras)
    {
        global $_CONF, $_USER;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval = COM_createLink(
                '<i class="uk-icon-edit"></i>',
                Config::get('admin_url') . "/index.php?edit=x&amp;pid={$A['pid']}"
            );
            break;
        case 'created':
            $dt = new \Date($fieldvalue);
            $dt->setTimezone(new \DateTimezone($_USER['tzid']));
            $retval = $dt->format($_CONF['daytime'], true);
            break;
        case 'opens':
        case 'closes':
            $dt = new \Date($fieldvalue);
            $dt->setTimezone(new \DateTimezone($_USER['tzid']));
            if (
                $dt->toMySQL(true) <= Dates::minDateTime() ||
                $dt->toMySQL(true) >= Dates::maxDateTime()
            ) {
                $retval = '';
            } else {
                $retval = $dt->format($_CONF['dateonly'], true) . ' ' .
                    $dt->format($_CONF['timeonly'], true);
            }
            break;
        case 'topic_preview':
            $retval = COM_createLink(
                $A['topic'],
                Config::get('admin_url') . '/index.php?preview&pid=' . urlencode($A['pid'])
            );
            break;

        case 'topic':
            $retval = htmlspecialchars($fieldvalue);
            $voted = Voter::hasVoted($A['pid'], $A['group_id']);
            $closed = ($A['closes'] < $extras['_now']) || $A['status'] > 0;
            if (
                !$closed &&
                !$voted &&
                SEC_inGroup($A['group_id'])
            ) {
                $retval = COM_createLink(
                    $retval,
                    Config::get('url') . "/index.php?pid={$A['pid']}"
                );
            } elseif (
                SEC_inGroup($A['results_gid']) &&
                ($closed || !$A['hideresults'])
            ) {
                $retval = COM_createLink(
                    $retval,
                    Config::get('url') . "/index.php?results=x&pid={$A['pid']}"
                );
            }
            break;
        case 'user_action':
            if (
                $A['closes'] < $extras['_now'] ||
                $A['status'] == Status::CLOSED
            ) {
                // Show the button to see early results, if allowed.
                $retval = MO::_('Closed');
                if (SEC_inGroup('results_gid')) {
                    $retval .= '&nbsp;' . COM_createLink(
                        MO::_('Results'),
                        Config::get('url') . '/index.php?pid=' . urlencode($A['pid']),
                        array(
                            'class' => 'uk-button uk-button-mini uk-button-primary',
                            'style' => 'float:right;',
                        )
                    );
                }
            } else {
                // Election is open. Show the voting link if the user hasn't voted,
                // otherwise show the early results link if allowed.
                $retval = MO::_('Open');
                if (!Voter::hasVoted($A['pid'], $A['group_id'])) {
                    $retval .= '&nbsp;' . COM_createLink(
                        MO::_('Vote'),
                        Config::get('url') . '/index.php?pid=' . urlencode($A['pid']),
                        array(
                            'style' => 'float:right;',
                            'class' => 'uk-button uk-button-mini uk-button-success',
                        )
                    );
                } elseif (!$A['hideresults'] && SEC_inGroup('results_gid')) {
                    $retval .= '&nbsp;' . COM_createLink(
                        MO::_('Results'),
                        Config::get('url') . '/index.php?results&pid=' . urlencode($A['pid']),
                        array(
                            'class' => 'uk-button uk-button-mini uk-button-primary',
                            'style' => 'float:right;',
                        )
                    );
                }
            }
            break;
        case 'user_extra':
            if (Voter::hasVoted($A['pid'], $A['group_id'])) {
                if ($A['voteaccess']) {
                    $retval = '<form action="' . Config::get('url') . '/index.php" method="post">';
                    $retval .= '<input type="text" size="15" placeholder="Enter Key" name="votekey" value="" />';
                    $retval .= '<input type="hidden" name="pid" value="' . $A['pid'] . '" />';
                    $retval .= '<button type="submit" style="float:right;" class="uk-button uk-button-mini uk-button-primary" name="showvote">';
                    $retval .= MO::_('Show Vote') . '</button></form>';
                } else {
                    // Results available only after election closes
                    $retval = MO::_('Results available after closing.');
                }
            }
            break;
        case 'status':
            $fieldvalue = (int)$fieldvalue;
            if ($fieldvalue == Status::ARCHIVED) {
                $retval .= MO::_('Archived');
                break;
            } elseif ($fieldvalue == Status::OPEN) {
                $switch = 'checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                    id=\"togenabled{$A['pid']}\"
                    onclick='" . Config::PI_NAME . "_toggle(this,\"{$A['pid']}\",\"{$fieldname}\",".
                    "\"election\");' />" . LB;
            break;
        case 'display':
            if ($A['display'] == 1) {
                $retval = MO::_('Yes');
            } else {
                $retval = MO::_('No');
            }
            break;
        case 'voters':
        case 'vote_count':
            // add a link there to the list of voters
            $retval = COM_numberFormat($fieldvalue);
            if ($extras['is_admin'] && (int)$retval > 0) {
                $retval = COM_createLink(
                    $retval,
                    Config::get('admin_url') . '/index.php?lv=x&amp;pid='.urlencode($A['pid'])
                );
            }
            break;
        case 'results':
            if ($A['vote_count'] > 0) {
                $retval = COM_createLink(
                    '<i class="uk-icon-bar-chart"></i>',
                    Config::get('admin_url') . '/index.php?results=x&pid=' . urlencode($A['pid'])
                );
            } else {
                $retval = 'n/a';
            }
            break;
        case 'reset':
            if ($A['status'] == Status::ARCHIVED) {
                $retval = '<i class="uk-icon-refresh uk-text-disabled tooltip" title="' .
                    MO::_('Cannot reset archived elections.') . '"></i>';
            } else { 
                $retval = COM_createLink(
                    '<i class="uk-icon-refresh uk-text-danger"></i>',
                    Config::get('admin_url') . "/index.php?resetelection&pid={$A['pid']}",
                    array(
                        'onclick' => "return confirm('" .
                        MO::_('Are you sure you want to delete all of the results for this election?') .
                        "');",
                    )
                );
            }
            break;
        case 'delete':
            $attr['title'] = MO::_('Delete');
            $attr['onclick'] = "return doubleconfirm('" .
                MO::_('Are you sure you want to delete this Poll?') . "','" .
                MO::_('Are you absolutely sure you want to delete this Poll?  All questions, answers and comments that are associated with this Poll will also be permanently deleted from the database.') .
                "');";
            $retval = COM_createLink(
                '<i class="uk-icon-remove uk-text-danger"></i>',
                Config::get('admin_url') . '/index.php'
                    . '?delete=x&amp;pid=' . $A['pid'] . '&amp;' . CSRF_TOKEN . '=' . $extras['token'], $attr);
            break;
        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }


    public function Render($preview = false)
    {
        if (
            $preview ||
            $this->canVote() ||
            (!empty($this->_access_key) && is_array($this->_selections))
        ) {
            return $this->showElectionForm($preview);
        } elseif ($this->alreadyVoted() ) {
            if ($this->canViewResults()) {
                if ($this->disp_type == Modes::NORMAL) {
                    // not in a block or autotag, just refresh to the results page
                    COM_refresh(Config::get('url') . '/index.php?results&pid=' . $this->pid);
                } elseif ($this->disp_type == Modes::AUTOTAG) {
                    // In an autotag
                    return (new Results($this->pid))
                        ->withDisplayType($this->disp_type)
                        ->Render();
                } else {
                    // in a block
                    return '';
                }
            } else {
                return $this->msgNoResultsWhileOpen();
            }
        } else {
            return $this->msgNoAccess();
        }
    }

        /*// This is not an admin preview, the user can't vote (maybe already did),
        // and this is not a voter checking their vote. See if they can view the results.
        if (!$preview && !$this->canVote()) {
            if (empty($this->_access_key) || !is_array($this->_selections)) {
                if ($this->alreadyVoted()) {
                    if ($this->canViewResults()) {
                        if ($this->disp_type == Modes::NORMAL) {
                            // not in a block or autotag, just refresh to the results page
                            COM_refresh(Config::get('url') . '/index.php?results&pid=' . $this->pid);
                        } elseif ($this->disp_type == Modes::AUTOTAG) {
                            // In an autotag
                            return (new Results($this->pid))
                                ->withDisplayType($this->disp_type)
                                ->Render();
                        } else {
                            // in a block
                            return '';
                        }
                    } else {
                        return $this->msgNoResultsWhileOpen();
                    }
                                }
            } elseif (empty($this->_access_key) && $this->canViewResults()) {
                if ($this->disp_type == Modes::NORMAL) {
                    // not in a block or autotag, just refresh to the results page
                    COM_refresh(Config::get('url') . '/index.php?results&pid=' . $this->pid);
                } elseif ($this->disp_type == Modes::AUTOTAG) {
                    // In an autotag
                    return (new Results($this->pid))
                        ->withDisplayType($this->disp_type)
                        ->Render();
                } else {
                    // in a block, return nothing
                    return $retval;
                }
            }
        }*/


    /**
     * Shows a election form
     *
     * Shows an HTML formatted election for the given topic ID
     *
     * @return       string  HTML Formatted Election
     */
    public function showElectionForm($preview=false)
    {
        global $_CONF, $LANG01, $_USER, $LANG25, $_IMAGE_TYPE;

        $filterS = new \sanitizer();
        $filterS->setPostmode('text');

        $retval = '';

        $use_ajax = false;
        switch ($this->disp_type) {
        case Modes::AUTOTAG:
            $aftervote_url = COM_getCurrentURL();
            break;
        case Modes::BLOCK:
            $aftervote_url = '';
            $use_ajax = true;
            break;
        case Modes::NORMAL:
        default:
            $aftervote_url = Config::get('url') . '/index.php?results&pid=' . $this->pid;
            break;
        }
        $Questions = Question::getByElection($this->pid, $this->rnd_questions, $this->rnd_answers);
        $nquestions = count($Questions);
        if ($nquestions > 0) {
            $election = new \Template(__DIR__ . '/../templates/');
            $election->set_file(array(
                'panswer' => 'answer.thtml',
                'block' => 'block.thtml',
                'pquestions' => 'questions.thtml',
                'comments' => 'comments.thtml',
            ) );
            if ($nquestions > 1) {
                $election->set_var('lang_topic', MO::_('Topic'));
                $election->set_var('topic', $filterS->filterData($this->topic));
                $election->set_var('lang_question', MO::_('Question'));
            }
            if ($preview) {
                $back_url = Config::get('admin_url') . '/index.php';
                $can_submit = false;
                $topic_msg = MO::_('Preview - Submissions Disabled');
            } else {
                $back_url = Config::get('url') . '/index.php';
                $can_submit = $this->mod_allowed == 2 || $this->_access_key == '';
                $topic_msg = '';
            }

            // create a random number to ID fields if multiple blocks showing
            $random = rand(0,100);
            $election->set_var(array(
                'id' => $this->pid,
                'old_pid' => $this->old_pid,
                'uniqid' => uniqid(),
                'num_votes' => COM_numberFormat($this->_vote_count),
                'vote_url' => Config::get('url') . '/index.php',
                'ajax_url' => Config::get('url') . '/ajax_handler.php',
                'back_url' => $back_url,
                'description' => $this->disp_type != Modes::BLOCK ? $this->dscp : '',
                'lang_back' => MO::_('Back to Listing'),
                'can_submit' => $can_submit,
                'vote_id' => COM_encrypt($this->_vote_id),
                'lang_back' => MO::_('Back to Listing'),
                'disp_mode' => $this->disp_type,
                'aftervote_url' => $aftervote_url,
                'topic_msg' => $topic_msg,
            ) );

            if ($nquestions == 1 || $this->disp_showall) {
                // Only one question (block) or showing all (main form)
                $election->set_var('lang_vote', MO::_('Vote'));
                $election->set_var('showall', true);
                if ($this->disp_type == Modes::BLOCK) {
                    $election->set_var('use_ajax', true);
                } else {
                    $election->unset_var('use_ajax');
                }
            } else {
                $election->set_var('lang_vote', MO::_('Start Voting'));
                $election->unset_var('showall');
                $election->unset_var('autotag');
            }
            $election->set_var('lang_votes', MO::_('Votes'));

            $results = '';
            if (
                $this->status == Status::OPEN ||
                $this->hideresults == 0 ||
                (
                    $this->hideresults == 1 &&
                    (
                        self::hasRights('edit') ||
                        (
                            isset($_USER['uid'])
                            && ($_USER['uid'] == $this->owner_id)
                        )
                    )
                )
            ) {
                $results = COM_createLink(
                    MO::_('Results'),
                    Config::get('url') . '/index.php?pid=' . $this->pid
                        . '&amp;aid=-1'
                );
            }
            $election->set_var('results', $results);

            if (self::hasRights('edit')) {
                $editlink = COM_createLink(
                    MO::_('Edit'),
                    Config::get('admin_url') . '/index.php?edit=x&amp;pid=' . $this->pid
                );
                $election->set_var('edit_link', $editlink);
                $election->set_var('edit_icon', $editlink);
                $election->set_var('edit_url', Config::get('admin_url').'/index.php?edit=x&amp;pid=' . $this->pid);
            }

            for ($j = 0; $j < $nquestions; $j++) {
                $Q = $Questions[$j];
                $election->set_var('question', $filterS->filterData($Q->getQuestion()));
                $election->set_var('question_id', $j);
                $notification = "";
                if (!$this->disp_showall) {
                    $nquestions--;
                    $notification = sprintf(
                        MO::_n(
                            'This poll has %d more question',
                            'This poll has %d more questions',
                            $nquestions
                        ),
                        $nquestions
                    );
                    $nquestions = 1;
                } else {
                    $election->set_var('lang_question_number', ($j+1));
                }
                $answers = $Q->getAnswers($this->rnd_answers);
                $nanswers = count($answers);
                for ($i = 0; $i < $nanswers; $i++) {
                    $Answer = $answers[$i];
                    if (
                        isset($this->_selections[$j]) &&
                        (int)$this->_selections[$j] == $Answer->getAid()
                    ) {
                        $election->set_var('selected', 'checked="checked"');
                    } else {
                        $election->clear_var('selected');
                    }
                    if ($this->mod_allowed < 2 && $this->_access_key != '') {
                        $election->set_var('radio_disabled', 'disabled="disabled"');
                    }
                    $election->set_var(array(
                        'answer_id' =>$Answer->getAid(),
                        'answer_text' => $filterS->filterData($Answer->getAnswer()),
                        'rnd' => $random,
                    ) );
                    $election->parse('answers', 'panswer', true);
                }
                $election->parse('questions', 'pquestions', true);
                $election->clear_var('answers');
            }
            $election->set_var('lang_topics', MO::_('Topic'));
            $election->set_var('notification', $notification);
            if ($this->commentcode >= 0 ) {
                USES_lib_comment();

                $num_comments = CMT_getCount(Config::PI_NAME, $this->pid);
                $election->set_var('num_comments',COM_numberFormat($num_comments));
                $election->set_var('lang_comments', MO::_('Comments'));

                $comment_link = CMT_getCommentLinkWithCount(
                    Config::PI_NAME,
                    $this->pid,
                    Config::get('url') . '/index.php?pid=' . $this->pid,
                    $num_comments,
                    0
                );

                $election->set_var('comments_url', $comment_link['link_with_count']);
                $election->parse('comments', 'comments', true);
            } else {
                $election->set_var('comments', '');
                $election->set_var('comments_url', '');
            }
            $retval = $election->finish($election->parse('output', 'block')) . LB;

            if (
                $this->disp_showall &&
                $this->commentcode >= 0 &&
                $this->disp_type != Modes::AUTOTAG
            ) {
                $delete_option = self::hasRights('edit') ? true : false;

                USES_lib_comment();

                $page = isset($_GET['page']) ? COM_applyFilter($_GET['page'],true) : 0;
                if ( isset($_POST['order']) ) {
                    $order = $_POST['order'] == 'ASC' ? 'ASC' : 'DESC';
                } elseif (isset($_GET['order']) ) {
                    $order = $_GET['order'] == 'ASC' ? 'ASC' : 'DESC';
                } else {
                    $order = '';
                }
                if ( isset($_POST['mode']) ) {
                    $mode = COM_applyFilter($_POST['mode']);
                } elseif ( isset($_GET['mode']) ) {
                    $mode = COM_applyFilter($_GET['mode']);
                } else {
                    $mode = '';
                }
                $valid_cmt_modes = array('flat','nested','nocomment','threaded','nobar');
                if (!in_array($mode,$valid_cmt_modes)) {
                    $mode = '';
                }
                $retval .= CMT_userComments(
                    $this->pid, $filterS->filterData($this->topic), Config::PI_NAME,
                    $order, $mode, 0, $page, false,
                    $delete_option, $this->commentcode, $this->owner_id
                );
            }
        } else {
            if ($this->disp_showall) {
                // not in a block, safe to return to the list
                COM_setMsg(MO::_("There are no questions for this election"), 'error');
                COM_refresh(Config::get('url') . '/index.php');
            }
            // else, nothing is returned to avoid a redirect loop
        }
        return $retval;
    }


    /**
     * Saves a user's vote.
     * Saves the users vote, if allowed for the election $pid.
     * NOTE: all data comes from form $_POST.
     *
     * @param    string   $pid   election id
     * @param    array    $aid   selected answers
     * @return   string   HTML for election results
     */
    public function saveVote($aid)
    {
        global $_USER;

        $retval = '';

        if ($this->alreadyVoted()) {
            if (!COM_isAjax()) {
                COM_setMsg(MO::_('Your vote has already been recorded.'));
            }
            return false;
        }

        // Set a browser cookie to block multiple votes from anonymous.
        // Done here since we have access to $aid.
        SEC_setCookie(
            Config::PI_NAME . '-' . $this->pid,
            implode('-', $aid),
            time() + Config::get('cookietime')
        );

        if (isset($_POST['aftervote_url'])) {
            $this->_aftervoteUrl = $_POST['aftervote_url'];
        }

        // Record that this user has voted
        $Voter = Voter::create($this->pid, $aid);
        if ($Voter !== false) {
            // Increment the vote count for each answer
            $answers = count($aid);
            for ($i = 0; $i < $answers; $i++) {
                Answer::increment($this->pid, $i, $aid[$i]);
            }

            // Set a return message, if not called via ajax
            if (!COM_isAjax()) {
                $T = new \Template(__DIR__ . '/../templates/');
                $T->set_file('msg', 'votesaved.thtml');
                $T->set_var(array(
                    'lang_votesaved' => MO::_('Your vote has been recorded.'),
                    'lang_copykey' => MO::_('Copy your key to a safe location if you wish to verify your vote later.'),
                    'lang_yourkeyis' => MO::_('Your private key is'),
                    'lang_copyclipboard' => MO::_('Copy to clipboard'),
                    'lang_copy_success' => MO::_('Your private key was copied to your clipboard.'),
                    'lang_keyonetime' => MO::_('Your private key will not be displayed again.'),
                    'prv_key' => $Voter->getId() . ':' . $Voter->getPrvKey(),
                    'mod_allowed' => $this->mod_allowed,
                    'url' => Config::get('url') . '/index.php',
                ) );
                $T->parse('output', 'msg');
                $msg = $T->finish($T->get_var('output'));
                COM_setMsg($msg, 'success', true);
            }
            return true;
        } else {
            COM_setMsg(MO::_('There was an error recording your vote, please try again.'), 'error');
            return false;
        }
    }


    /**
     * Check if the user has already voted.
     * For anonymous, checks the IP address and the election cookie.
     *
     * @return  boolean     True if the user has voted, False if not
     */
    public function alreadyVoted()
    {
        if (Voter::hasVoted($this->pid, $this->voting_gid)) {
        //    var_dump($this);die;
        }
        return Voter::hasVoted($this->pid, $this->voting_gid);
    }


    /**
     * Shows all election in system unless archived.
     *
     * @return   string          HTML for election listing
     */
    public static function listElections()
    {
        global $_CONF, $_USER;

        $T = new \Template(__DIR__ . '/../templates/');
        $T->set_file('list', 'list.thtml');

        USES_lib_admin();

        $header_arr = array(
            array(
                'text' => MO::_('Topic'),
                'field' => 'topic',
                'sort' => true,
            ),
            array(
                'text' => MO::_('Vote Count'),
                'field' => 'vote_count',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => MO::_('Closes'),
                'field' => 'closes',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => MO::_('Action'),
                'field' => 'user_action',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => MO::_('Message'),
                'field' => 'user_extra',
                'sort' => true,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'created',
            'direction' => 'desc',
        );
        $text_arr = array(
            'has_menu' =>  false,
            'title' => MO::_('Elections'),
            'instructions' => "",
            'icon' => '', 'form_url' => '',
        );
        $sql_now_utc = $_CONF['_now']->toMySQL(false);
        $extras = array(
            'token' => 'dummy',
            '_now' => $sql_now_utc,
            'is_admin' => false,
        );
        $filter = "(status = " . Status::CLOSED . " AND '$sql_now_utc' BETWEEN opens AND closes " .
                SEC_buildAccessSql('AND', 'group_id') .
            ") OR (
                (hideresults = 0 OR status = " . Status::OPEN . " OR closes < '$sql_now_utc')" .
                SEC_buildAccessSql('AND', 'results_gid') .
            ')';
        $count = (int)DB_getItem(DB::table('topics'), 'count(*)', $filter);
        $sql = "SELECT p.*,
                (SELECT COUNT(v.id) FROM " . DB::table('voters') . " v WHERE v.pid = p.pid) AS vote_count
                FROM " . DB::table('topics') . " AS p WHERE " . $filter;
        $res = DB_query($sql);
        $count = DB_numRows($res);
        $data_arr = array();
        while ($A = DB_fetchArray($res, false)) {
            $A['has_voted'] = Voter::hasVoted($A['pid'], $A['group_id']);
            $data_arr[] = $A;
        }
        $T->set_var(array(
            'election_list' => ADMIN_simpleList(
                array(__CLASS__, 'getListField'),
                $header_arr, $text_arr, $data_arr, '', '', $extras
                ),
            'is_admin' => plugin_ismoderator_elections(),
            'admin_url' => Config::get('admin_url') . '/index.php',
            'lang_admin' => MO::_('Admin'),
            'msg_alert' => $count == 0 ?
                self::msgAlert(
                    MO::_('It appears that there are no elections available.')
                ) :
                '',
            ) );

/*        if (plugin_ismoderator_elections()) {
            $retval .= '<div class="floatright"><a class="uk-button uk-button-small uk-button-danger" href="' .
                Config::get('admin_url') . '/index.php">Admin</a></div>' . LB;
        }*/
        /*if ($count == 0) {
            $retval .= self::msgAlert(
                MO::_('It appears that there are no elections available.')
            );
            }*/
        $T->parse('output', 'list');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Delete a election.
     *
     * @param   string  $pid    ID of election to delete
     * @param   boolean $force  True to disregard access, e.g. user is deleted
     * @return  string          HTML redirect
     */
    public static function deleteElection($pid, $force=false)
    {
        global $_CONF, $_USER;

        $Election = self::getInstance($pid);
        if (
            !$Election->isNew() &&
            ($force || self::hasRights('edit'))
        ) {
            $pid = DB_escapeString($pid);
            // Delete all questions, answers and votes
            Question::deleteElection($Election->getID());
            Answer::deleteElection($Election->getID());
            Voter::deleteElection($Election->getID());
            // Now delete the election topic
            DB_delete(DB::table('topics'), 'pid', $pid);
            // Finally, delete any comments and notify other plugins
            // Finally, delete any comments and notify other plugins
            DB_delete(
                DB::table('comments'),
                array('sid', 'type'),
                array($pid,  Config::PI_NAME)
            );
            PLG_itemDeleted($pid, Config::PI_NAME);
            if (!$force) {
                // Don't redirect if this is done as part of user account deletion
                COM_setMsg(MO::_('Your election has been successfully deleted.'));
                COM_refresh(Config::get('admin_url') . '/index.php');
            }
        } else {
            if (!$force) {
                COM_accessLog ("User {$_USER['username']} tried to illegally delete election $pid.");
                // apparently not an administrator, return ot the public-facing page
                COM_refresh(Config::get('url') . '/index.php');
            }
        }
    }


    /**
     * Delete all the votes and reset answers to zero for the election.
     *
     * @param   string  $pid    Election ID
     */
    public static function deleteVotes($pid)
    {
        $Election = new self($pid);
        if (!$Election->isNew() && $Election->getStatus() < Status::ARCHIVED) {
            Answer::resetElection($Election->getID());
            Voter::deleteElection($Election->getID());
        }
    }


    /**
     * Create the list of voting records for this election.
     *
     * @return  string      HTML for voting list
     */
    public function listVotes()
    {
        global $_CONF;

        $retval = '';
        $retval .= COM_startBlock(
            'Election Votes for ' . $this->pid, '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $header_arr = array(
            array(
                'text' => MO::_('User Name'),
                'field' => 'username',
                'sort' => true,
            ),
            array(
                'text' => MO::_('IP Address'),
                'field' => 'ipaddress',
                'sort' => true,
            ),
            array(
                'text' => MO::_('Date Voted'),
                'field' => 'dt_voted',
                'sort' => true,
            ),
        );

        $defsort_arr = array(
            'field' => 'date',
            'direction' => 'desc',
        );
        $text_arr = array(
            'has_extras'   => true,
            'instructions' => '',
            'form_url'     => Config::get('admin_url') . '/index.php?lv=x&amp;pid='.urlencode($this->pid),
        );

        $sql = "SELECT voters.*, FROM_UNIXTIME(voters.date) as dt_voted, users.username
            FROM " . DB::table('voters') . " AS voters
            LEFT JOIN " . DB::table('users') . " AS users ON voters.uid=users.uid
            WHERE voters.pid='" . DB_escapeString($this->pid) . "'";

        $query_arr = array(
            'table' => 'electionvoters',
            'sql' => $sql,
            'query_fields' => array('uid'),
            'default_filter' => '',
        );
        $token = SEC_createToken();
        $retval .= ADMIN_list (
            Config::PI_NAME . '_' . __FUNCTION__,
            array(__CLASS__, 'getListField'), $header_arr,
            $text_arr, $query_arr, $defsort_arr, '', $token
        );
        $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $retval;
    }


    /**
     * Change the owner ID of election when the user ID is changed.
     *
     * @param   integer $origUID    Original user ID
     * @param   integer $destUID    New user ID
     */
    public static function moveUser($origUID, $destUID)
    {
        DB_query("UPDATE " . DB::table('topics') .
            " SET owner_id = ".(int)$destUID .
            " WHERE owner_id = ".(int)$origUID,1
        );
        Voter::moveUser($origUID, $destUID);
    }


    /**
     * Sets a boolean field to the opposite of the supplied value.
     *
     * @param   integer $oldvalue   Old (current) value
     * @param   string  $varname    Name of DB field to set
     * @param   integer $id         ID of record to modify
     * @return  integer     New value, or old value upon failure
     */
    public static function toggleEnabled($oldvalue, $id)
    {
        $id = DB_escapeString($id);
        // Determing the new value (opposite the old)
        if ($oldvalue == 1) {
            $newvalue = 0;
        } elseif ($oldvalue == 0) {
            $newvalue = 1;
        } else {
            return $oldvalue;
        }

        $sql = "UPDATE " . DB::table('topics') . "
                SET status = $newvalue
                WHERE pid = '$id'";
        // Ignore SQL errors since varname is indeterminate
        DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("Error toggling election: $sql");
            return $oldvalue;
        } else {
            return $newvalue;
        }
    }


    /**
     * Create the SQL clause to check access to view the election.
     *
     * @param   integer $uid    User ID to check, 0 to ignore
     * @param   string  $pfx    Table prefix
     * @return  string      SQL clause.
     */
    public static function getPermSql($uid = 0, $pfx='')
    {
        if ($pfx != '') $pfx = $pfx . '.';

        $sql = ' (';
        if ($uid > 0) {
            $sql .= "owner_id = '" . (int)$uid . "' OR ";
        }
        $sql .= SEC_buildAccessSql('', 'group_id') .
            SEC_buildAccessSql('OR', 'results_gid');
        $sql .= ') ';    // close the paren
        return $sql;
    }


    /**
     * General wrapper function to create an alert message.
     *
     * @param   string  $msg    Message to display
     * @return  string      HTML for formatted message block
     */
    public static function msgAlert($msg)
    {
        $T = new \Template(__DIR__ . '/../templates/');
        $T->set_file('alert', 'alert_msg.thtml');
        $T->set_var('message', $msg);
        $T->parse('output', 'alert');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Display a message if access to results is denied while election is open.
     *
     * @return  string      HTML for formatted message block
     */
    public function msgNoResultsWhileOpen()
    {
        $msg = '';
        if ($this->alreadyVoted()) {
            $msg = MO::_('Your vote has already been recorded.') . '<br />' . LB;
        }
        $msg .= MO::_('Election results will be available only after the election has closed.');
        return self::msgAlert($msg);
    }


    /**
     * Display a message if access is denied completely due to permissions.
     *
     * @return  string      HTML for formatted message block
     */
    public function msgNoAccess()
    {
        return self::msgAlert(
            MO::_('You are trying to access a poll that you do not have rights to.')
        );
    }


    /**
     * Get the URL to which the voter should be redirected after voting.
     * Default is the plugin's homepage.
     *
     * @return  string      Destination URL
     */
    public function getAftervoteUrl()
    {
        if (!empty($this->_aftervoteUrl)) {
            return $this->_aftervoteUrl;
        } else {
            return Config::get('url') . '/index.php';
        }
    }

}
