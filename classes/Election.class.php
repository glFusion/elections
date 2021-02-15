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
 * @package election
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
        $this->Opens = new \Date;
        $this->Closes = new \Date;
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
            AND '" . $CONF['_now']->toMySQL(false) . "' BETWEEN opens AND closes " .
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
            if (SEC_inGroup('Root')) {
                $can_view = true;
            } elseif (
                $this->isNew() ||
                !SEC_inGroup($this->results_gid) ||
                ($this->isOpen() && $this->hideresults)
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
                $A['opens_date'] = Dates::MIN_DATE;
            }
            if (empty($A['opens_time'])) {
                $A['opens_time'] = Dates::MIN_TIME;
            }
            $this->setOpenDate($A['opens_date'] . ' ' . $A['opens_time'], true);
            if (empty($A['closes_date'])) {
                $A['closes_date'] = Dates::MAX_DATE;
            }
            if (empty($A['closes_time'])) {
                $A['closes_time'] = Dates::MAX_TIME;
            }
            $this->setClosingDate($A['closes_date'] . ' ' . $A['closes_time'], true);
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
        global $_CONF, $_GROUPS, $_USER, $LANG25, $LANG_ACCESS,
           $LANG_ADMIN, $MESSAGE, $LANG_ELECTION;

        $retval = COM_startBlock(
            $LANG25[5], '',
            COM_getBlockTemplate ('_admin_block', 'header')
        );

        $T = new \Template(__DIR__ . '/../templates/admin/');
        $T->set_file(array(
            'editor' => 'editor.thtml',
            'question' => 'questions.thtml',
            'answer' => 'answeroption.thtml',
        ) );

        if (!empty($this->pid)) {       // if not a new record
            // Get permissions for election
            if (!self::hasRights('edit')) {
                // User doesn't have write access...bail
                $retval .= COM_startBlock ($LANG25[21], '',
                               COM_getBlockTemplate ('_msg_block', 'header'));
                $retval .= $LANG25[22];
                $retval .= COM_endBlock (COM_getBlockTemplate ('_msg_block', 'footer'));
                COM_accessLog("User {$_USER['username']} tried to illegally submit or edit election $pid.");
                return $retval;
            }
            if (!empty($this->owner_id)) {
                $delbutton = '<input type="submit" value="' . $LANG_ADMIN['delete']
                    . '" name="delete"%s>';
                $jsconfirm = ' onclick="return confirm(\'' . $MESSAGE[76] . '\');"';
                $T->set_var(array(
                    'delete_option' => sprintf($delbutton, $jsconfirm),
                    'delete_option_no_confirmation' => sprintf ($delbutton, ''),
                    'delete_button' => true,
                    'lang_delete'   => $LANG_ADMIN['delete'],
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

        $open_date = $this->Opens->format('Y-m-d', true);
        if ($open_date == Dates::MIN_DATE) {
            $open_date = '';
        }
        $open_time= $this->Opens->format('H:i:s', true);
        if ($open_time == Dates::MIN_TIME) {
            $open_time = '';
        }
        $close_date = $this->Closes->format('Y-m-d', true);
        if ($close_date == Dates::MAX_DATE) {
            $close_date = '';
        }
        $close_time= $this->Closes->format('H:i:s', true);
        if ($close_time == Dates::MAX_TIME) {
            $close_time = '';
        }
        $ownername = COM_getDisplayName($this->owner_id);
        $T->set_var(array(
            'action_url' => Config::get('admin_url') . '/index.php',
            'lang_electionid' => $LANG25[6],
            'id' => $this->pid,
            'old_pid' => $this->old_pid,
            'lang_donotusespaces' => $LANG25[7],
            'lang_topic' => $LANG25[9],
            'topic' => htmlspecialchars ($this->topic),
            'lang_mode' => $LANG25[1],
            'description' => $this->dscp,
            'lang_description' => $LANG_ELECTION['description'],
            'comment_options' => COM_optionList(DB::table('commentcodes'),'code,name',$this->commentcode),
            'lang_appearsonhomepage' => $LANG25[8],
            'lang_status' => $LANG_ELECTION['status'],
            'lang_open' => $LANG_ELECTION['open'],
            'lang_closed' => $LANG_ELECTION['closed'],
            'lang_archived' => $LANG_ELECTION['archived'],
            'open_'.$this->status => 'selected="selected"',

            'lang_hideresults' => $LANG25[37],
            //'lang_login_required' => $LANG25[43],
            'hideresults_explain' => $LANG25[38],
            'topic_info' => $LANG25[39],
            'display' => $this->inblock ? 'checked="checked"' : '',
            'hideresults' => $this->hideresults ? 'checked="checked"' : '',
            'lang_opens' => $LANG_ELECTION['opens'],
            'lang_closes' => $LANG_ELECTION['closes'],
            'opens_date' => $open_date,
            'opens_time' => $open_time,
            'closes_date' => $close_date,
            'closes_time' => $close_time,
            'min_date' => Dates::MIN_DATE,
            'max_date' => Dates::MAX_DATE,
            'min_time' => Dates::MIN_TIME,
            'max_time' => Dates::MAX_TIME,
            // user access info
            'lang_accessrights' => $LANG_ACCESS['accessrights'],
            'lang_owner' => $LANG_ACCESS['owner'],
            'owner_username' => DB_getItem(DB::table('users'), 'username', "uid = {$this->owner_id}"),
            'owner_name' => $ownername,
            'owner' => $ownername,
            'owner_id' => $this->owner_id,
            'lang_voting_group' => $LANG_ELECTION['voting_group'],
            'lang_results_group' => $LANG_ELECTION['results_group'],
            'group_dropdown' => SEC_getGroupDropdown($this->voting_gid, 3),
            'res_grp_dropdown' => SEC_getGroupDropdown($this->results_gid, 3, 'results_gid'),
            'lang_answersvotes' => $LANG25[10],
            'lang_save' => $LANG_ADMIN['save'],
            'lang_cancel' => $LANG_ADMIN['cancel'],
            'lang_datepicker' => $LANG_ELECTION['datepicker'],
            'lang_timepicker' => $LANG_ELECTION['timepicker'],
            'lang_view' => $LANG_ELECTION['view_vote'],
            'lang_noaccess' => $LANG_ELECTION['noaccess'],
            'lang_voteaccess' => $LANG_ELECTION['allow_votemod'],
            'voteaccess_' . $this->mod_allowed => 'selected="selected"',
            'rndq_chk' => $this->rnd_questions ? 'checked="checked"' : '',
            'rnda_chk' => $this->rnd_answers ? 'checked="checked"' : '',
            'lang_rnd_q' => $LANG_ELECTION['rnd_questions'],
            'lang_rnd_a' => $LANG_ELECTION['rnd_answers'],
            'lang_decl_winner' => $LANG_ELECTION['declares_winner'],
            'decl_chk' => $this->decl_winner ? 'checked="checked"' : '',
            'timezone' => $_CONF['timezone'],
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

            $T->set_var('question_tab', $LANG25[31] . " $display_id");
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
            $T->set_var('lang_question', $LANG25[31] . " $display_id");
            $T->set_var('lang_saveaddnew', $LANG25[32]);

            $T->parse('qt','questiontab',true);

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
                $T->parse ('answer_option', 'answer', true);
            }
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
        global $LANG_ELECTION, $_CONF;

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
            return $LANG_ELECTION['err_name_required'];
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
        global $_CONF, $_IMAGE_TYPE, $LANG_ADMIN, $LANG25, $LANG_ACCESS, $LANG_ELECTION;

        $retval = '';

        // writing the actual list
        $header_arr = array(      # display 'text' and use table field 'field'
            array(
                'text' => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
                'width' => '25px',
            ),
            array(
                'text' => $LANG25[9],
                'field' => 'topic',
                'sort' => true,
            ),
            array(
                'text' => $LANG25[20],
                'field' => 'vote_count',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ELECTION['results'],
                'field' => 'results',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG25[3],
                'field' => 'created',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ELECTION['opens'],
                'field' => 'opens',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ELECTION['closes'],
                'field' => 'closes',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ELECTION['open'],
                'field' => 'status',
                'sort' => true,
                'align' => 'center',
                'width' => '35px',
            ),
            array(
                'text' => $LANG_ADMIN['reset'],
                'field' => 'reset',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
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
            'instructions' => $LANG25[19],
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
        global $_CONF, $LANG25, $LANG_ACCESS, $LANG_ADMIN, $LANG_ELECTION, $_USER;

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
        /*case 'user_action':
            if (
                $A['closes'] < $extras['_now'] &&
                $A['status'] &&
                !Voter::hasVoted($A['pid']) &&
                SEC_inGroup($A['group_id'])
            ) {
                $retval = COM_createLink(
                    $LANG_ELECTION['vote'],
                    Config::get('url') . "/index.php?pid={$A['pid']}"
                );
            } elseif (SEC_inGroup($A['results_gid'])) {
                $retval = COM_createLink(
                    $LANG_ELECTION['results'],
                    Config::get('url') . "/index.php?results=x&pid={$A['pid']}"
                );
            }*/
        case 'user_action':
            if (Voter::hasVoted($A['pid'], $A['group_id'])) {
                $retval = '<form action="' . Config::get('url') . '/index.php" method="post">';
                $retval .= '<input type="text" size="15" placeholder="Enter Key" name="votekey" value="" />';
                $retval .= '<input type="hidden" name="pid" value="' . $A['pid'] . '" />';
                $retval .= '<button type="submit" style="float:right;" class="uk-button uk-button-mini uk-button-primary" name="showvote">';
                $retval .= 'Show Vote</button></form>';
//                $retval = $LANG_ELECTION['s_alreadyvoted'];
            } elseif (
                $A['closes'] < $extras['_now'] &&
                $A['opens'] < $extras['_now']
            ) {
                $retval = $LANG_ELECTION['closed'];
            } else {
                $retval = $LANG_ELECTION['open'];
                $retval .= '&nbsp;<a href="' . Config::get('url') . '/index.php?pid=' .
                    $A['pid'] . '" style="float:right;" class="uk-button uk-button-mini uk-button-success">' .
                    $LANG_ELECTION['vote'] . '</button>';
            }
            break;
        case 'status':
            if ($fieldvalue == 2) {
                $retval .= $LANG_ELECTION['archived'];
                break;
            } elseif ($fieldvalue == 0) {
                $switch = 'checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                    id=\"togenabled{$A['pid']}\"
                    onclick='" . Config::PI_NAME . "_toggle(this,\"{$A['pid']}\",\"status\",".
                    "\"election\");' />" . LB;
            break;
        case 'display':
            if ($A['display'] == 1) {
                $retval = $LANG25[25];
            } else {
                $retval = $LANG25[26];
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
            $retval = COM_createLink(
                '<i class="uk-icon-refresh uk-text-danger"></i>',
                Config::get('admin_url') . "/index.php?resetelection&pid={$A['pid']}",
                array(
                    'onclick' => "return confirm('{$LANG_ELECTION['confirm_reset']}?');",
                )
            );
            break;
        case 'delete':
            $attr['title'] = $LANG_ADMIN['delete'];
            $attr['onclick'] = "return doubleconfirm('" . $LANG25[41] . "','" . $LANG25[42] . "');";
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


    /**
     * Shows a election form
     *
     * Shows an HTML formatted election for the given topic ID
     *
     * @return       string  HTML Formatted Election
     */
    public function Render()
    {
        global $_CONF, $LANG_ELECTION, $LANG01, $_USER, $LANG25, $_IMAGE_TYPE;

        $filterS = new \sanitizer();
        $filterS->setPostmode('text');

        $retval = '';

        // If the current user can't vote, decide what to do or display.
        if (
            !$this->canVote() &&
            (empty($this->_access_key) || !is_array($this->_selections))
        ) {
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
                    // in a block, return nothing
                    return $retval;
                }
            } elseif (empty($this->_access_key)) {
                // Can't vote, and can't view results. Return nothing.
                return $retval;
            }
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
                $election->set_var('lang_topic', $LANG25[34]);
                $election->set_var('topic', $filterS->filterData($this->topic));
                $election->set_var('lang_question', $LANG25[31]);
            }
            // create a random number to ID fields if multiple blocks showing
            $random = rand(0,100);
            $election->set_var(array(
                'id' => $this->pid,
                'old_pid' => $this->old_pid,
                'num_votes' => COM_numberFormat($this->_vote_count),
                'vote_url' => Config::get('url') . '/index.php',
                'ajax_url' => Config::get('url') . '/ajax_handler.php',
                'url' => Config::get('url') . '/index.php',
                'description' => $this->disp_type != Modes::BLOCK ? $this->dscp : '',
                'lang_back_to_list' => $LANG_ELECTION['back_to_list'],
                'can_submit' => $this->mod_allowed == 2 || $this->_access_key == '',
                'vote_id' => COM_encrypt($this->_vote_id),
                'lang_back' => $LANG_ELECTION['back_to_list'],
            ) );

            if ($nquestions == 1 || $this->disp_showall) {
                // Only one question (block) or showing all (main form)
                $election->set_var('lang_vote', $LANG_ELECTION['vote']);
                $election->set_var('showall',true);
                if ($this->disp_type == Modes::AUTOTAG) {
                    $election->set_var('autotag', true);
                } else {
                    $election->unset_var('autotag');
                }
            } else {
                $election->set_var('lang_vote', $LANG_ELECTION['start_election']);
                $election->unset_var('showall');
                $election->unset_var('autotag');
            }
            $election->set_var('lang_votes', $LANG_ELECTION['votes']);

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
                $results = COM_createLink($LANG_ELECTION['results'],
                    Config::get('url') . '/index.php?pid=' . $this->pid
                    . '&amp;aid=-1');
            }
            $election->set_var('results', $results);

            if (self::hasRights('edit')) {
                $editlink = COM_createLink(
                    $LANG25[27],
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
                    $notification = $LANG25[35] . " $nquestions " . $LANG25[36];
                    $nquestions = 1;
                } else {
                    $election->set_var('lang_question_number', " ". ($j+1));
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
            $election->set_var('lang_topics', $LANG_ELECTION['topics']);
            $election->set_var('notification', $notification);
            if ($this->commentcode >= 0 ) {
                USES_lib_comment();

                $num_comments = CMT_getCount(Config::PI_NAME, $this->pid);
                $election->set_var('num_comments',COM_numberFormat($num_comments));
                $election->set_var('lang_comments', $LANG01[3]);

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
            COM_setMsg("There are no questions for this election", 'error');
            COM_refresh(Config::get('url') . '/index.php');
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
        global $_USER, $LANG_ELECTION;

        $retval = '';

        if ($this->alreadyVoted()) {
            if (!COM_isAjax()) {
                COM_setMsg($LANG_ELECTION['alreadyvoted']);
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
                    'lang_votesaved' => $LANG_ELECTION['savedvotemsg'],
                    'lang_copykey' => $LANG_ELECTION['msg_copykey'],
                    'lang_yourkeyis' => $LANG_ELECTION['msg_yourkeyis'],
                    'lang_copyclipboard' => $LANG_ELECTION['copy_clipboard'],
                    'lang_copy_success' => $LANG_ELECTION['copy_clipboard_success'],
                    'prv_key' => $Voter->getId() . ':' . $Voter->getPrvKey(),
                    'mod_allowed' => $this->mod_allowed,
                ) );
                $T->parse('output', 'msg');
                $msg = $T->finish($T->get_var('output'));
                COM_setMsg($msg, 'success', true);
            }
            return true;
        } else {
            COM_setMsg($LANG_ELECTION['msg_errorsaving'], 'error');
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
        return Voter::hasVoted($this->pid, $this->voting_gid);
    }


    /**
     * Shows all election in system unless archived.
     *
     * @return   string          HTML for election listing
     */
    public static function listElections()
    {
        global $_CONF, $_USER,
           $LANG25, $LANG_LOGIN, $LANG_ELECTION;

        $retval = '';

        USES_lib_admin();

        $header_arr = array(
            array(
                'text' => $LANG25[9],
                'field' => 'topic',
                'sort' => true,
            ),
            array(
                'text' => $LANG25[20],
                'field' => 'vote_count',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ELECTION['opens'],
                'field' => 'opens',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ELECTION['closes'],
                'field' => 'closes',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ELECTION['message'],
                'field' => 'user_action',
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
            'title' => $LANG_ELECTION['electiontitle'],
            'instructions' => "",
            'icon' => '', 'form_url' => '',
        );
        $sql_now_utc = $_CONF['_now']->toMySQL(false);
        $extras = array(
            'token' => 'dummy',
            '_now' => $sql_now_utc,
            'is_admin' => false,
        );
        $filter = "WHERE (status = " . Status::CLOSED . " AND '$sql_now_utc' BETWEEN opens AND closes " .
                SEC_buildAccessSql('AND', 'group_id') .
            ") OR (
                (hideresults = 0 OR status = " . Status::OPEN . " OR closes < '$sql_now_utc')" .
                SEC_buildAccessSql('AND', 'results_gid') .
            ')';
        $sql = "SELECT COUNT(*) AS count FROM " . DB::table('topics') . ' ' . $filter;
        //echo $sql;die;
        $count = 0;
        $res = DB_query("SELECT COUNT(*) AS count FROM " . DB::table('topics') . ' ' . $filter);
        if ($res) {
            $A = DB_fetchArray($res, false);
            $count = (int)$A['count'];
        }
        if (plugin_ismoderator_elections()) {
            $retval .= '<div class="floatright"><a class="uk-button uk-button-small uk-button-danger" href="' .
                Config::get('admin_url') . '/index.php">Admin</a></div>' . LB;
        }
        $sql = "SELECT p.*,
                (SELECT COUNT(v.id) FROM " . DB::table('voters') . " v WHERE v.pid = p.pid) AS vote_count
                FROM " . DB::table('topics') . " AS p " . $filter;
                /*WHERE is_open = 1 AND ('$sql_now' BETWEEN opens AND closes " .
                SEC_buildAccessSql('AND', 'group_id') .
                ") OR (closes < '$sql_now' " . SEC_buildAccessSql('AND', 'results_gid') . ')';*/
        //echo $sql;die;
        $res = DB_query($sql);
        $data_arr = array();
        while ($A = DB_fetchArray($res, false)) {
            $A['has_voted'] = Voter::hasVoted($A['pid'], $A['group_id']);
            $data_arr[] = $A;
        }
        $retval .= ADMIN_simpleList(
            array(__CLASS__, 'getListField'), $header_arr, $text_arr, $data_arr, '', '', $extras
        );
        /*$query_arr = array(
            'table' => DB::key('topics'),
            'sql' => "SELECT p.*,
                (SELECT COUNT(v.id) FROM " . DB::table('voters') . " v WHERE v.pid = p.pid) AS vote_count
                FROM " . DB::table('topics') . " p",
            'query_fields' => array('topic'),
            'default_filter' => "WHERE status = 1 AND ('$sql_now_utc' BETWEEN opens AND closes " .
                SEC_buildAccessSql('AND', 'group_id') .
                ") OR (closes < '$sql_now_utc' " . SEC_buildAccessSql('AND', 'results_gid') . ')',
            'query' => '',
            'query_limit' => 0,
        );
        $retval .= ADMIN_list(
            Config::PI_NAME . '_' . __FUNCTION__,
            array(__CLASS__, 'getListField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr, '', $extras
        );*/
        if ($count == 0) {
            $retval .= '<div class="uk-alert uk-alert-danger">' . $LANG_ELECTION['stats_none'] . '</div>';
        }
        return $retval;
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
                COM_refresh(Config::get('admin_url') . '/index.php?msg=20');
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
        if (!$Election->isNew()) {
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
        global $_CONF, $_IMAGE_TYPE, $LANG_ADMIN, $LANG_ELECTION, $LANG25, $LANG_ACCESS;

        $retval = '';
        $menu_arr = array (
            array(
                'url' => Config::get('admin_url') . '/index.php',
                'text' => $LANG_ADMIN['list_all'],
            ),
            array(
                'url' => Config::get('admin_url') . '/index.php?edit=x',
                'text' => $LANG_ADMIN['create_new'],
            ),
            array(
                'url' => $_CONF['site_admin_url'],
                'text' => $LANG_ADMIN['admin_home']),
        );

        $retval .= COM_startBlock(
            'Election Votes for ' . $this->pid, '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $retval .= ADMIN_createMenu(
            $menu_arr,
            $LANG25[19],
            plugin_geticon_elections()
        );

        $header_arr = array(
            array(
                'text' => $LANG_ELECTION['username'],
                'field' => 'username',
                'sort' => true,
            ),
            array(
                'text' => $LANG_ELECTION['ipaddress'],
                'field' => 'ipaddress',
                'sort' => true,
            ),
            array(
                'text' => $LANG_ELECTION['date_voted'],
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
            'instructions' => $LANG25[19],
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

}
