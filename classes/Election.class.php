<?php
/**
 * Class to represent a election.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021-2022 Lee Garner <lee@leegarner.com>
 * @package     election
 * @version     v0.1.3
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
use Elections\Models\Token;
use Elections\Models\DataArray;
use Elections\Models\Request;
use Elections\Views\Results;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class for a single election.
 * @package elections
 */
class Election
{
    /** Election topic record ID.
     * @var integer */
    private $tid = 0;

    /** Election ID string, to be used in URLs.
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
    //private $commentcode = 0;

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

    /** Questions for this election. NULL to load questions on first query.
     * @var array */
    private $_Questions = NULL;

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
     * 0 = None, 1 = View Vote, 2 = Change Vote.
     * @var integer */
    private $mod_allowed = 0;

    /** Randomize questions when displayed?
     * @var boolean */
    private $rnd_questions = 0;

    /** Declare a winner, or just use as a poll?
     * @var boolean */
    private $decl_winner = 1;

    /** Flag to show the answer remark on the election form.
     * The remark is always shown on the results view.
     * @var boolean */
    private $show_remarks = 0;

    /** Cookie key, used to set the cookie indicating the user has voted.
     * This is changed when the election votes are reset.
     * @var string */
    private $cookie_key = '';

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

    /** Collection of error messages.
     * @var array */
    private $_errors = array();


    /**
     * Constructor.
     * Create a election object for the specified user ID, or the current
     * user if none specified.
     * If a key is requested, then just build the election for that key (requires a $uid).
     *
     * @param   integer $tid     Election ID, empty to create a new record
     */
    function __construct(?int $tid=NULL)
    {
        global $_CONF;

        if (empty($tid)) {
            // Creating a new election, set the default groups based on the
            // global login-required setting.
            $this->voting_gid = Config::get('def_voting_gid');
            $this->results_gid = Config::get('def_results_gid');
            $this->setPid(COM_makeSid());
            $this->setOwner();
            $this->mod_allowed = (int)Config::get('allow_votemod');
            $this->Opens = new \Date('now', $_CONF['timezone']);
            $this->Closes = NULL;
            $this->cookie_key = Token::create();
        } else {
            // Got an election ID string
            $this->setTid($tid);
            if (!$this->Read()) {
                $this->tid = 0;
            }
        }
    }


    /**
     * Get an instance of a election object.
     *
     * @param   string  $tid    Election record ID
     * @return  object      Election object
     */
    public static function getInstance(int $tid) : self
    {
        return new self($tid);
    }


    /**
     * Get an election by it's `pid` or descriptive ID.
     * Used to create friendlier guest-facing URLs.
     *
     * @param   string  $pid    Election PID
     * @return  object      New Election object
     */
    public static function getByPid(string $pid) : self
    {
        $retval = new self;
        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM " . DB::table('topics') . ' WHERE pid = ?',
                array($pid),
                array(Database::STRING)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (!empty($row)) {
            $retval->setVars(new DataArray($row), true);
        }
        return $retval;
    }


    /**
     * Get all the currently open election.
     *
     * @param   boolean $modes  Mode for display
     * @return  array       Array of Election objects
     */
    public static function getOpen($mode=NULL) : array
    {
        global $_CONF;

        $retval = array();
        if ($mode === NULL) {
            $mode = Modes::ALL;
        }
        $in_block = $mode == Modes::BLOCK ? ' AND display = 1' : '';
        $db = Database::getInstance();
        $queryBuilder = $db->conn->createQueryBuilder();
        $queryBuilder
            ->select('p.*')
            ->from(DB::table('topics'), 'p')
            ->where('p.status = :status')
            ->andWhere(':now BETWEEN opens AND closes')
            ->orderBy('p.pid', 'ASC')
            ->setParameter('now', $_CONF['_now']->toMYSQL(false))
            ->setParameter('status', Status::OPEN);
        try {
            $stmt = $queryBuilder->execute();
            foreach ($stmt->fetchAllAssociative() as $A) {
                $retval[$A['pid']] = new self;
                $retval[$A['pid']]->setVars(new DataArray($A));
            }
        } catch(Throwable $e) {
        }
        return $retval;
    }


    /**
     * Get a count of election in the system.
     * Only used for the admin menu, so no permission check is done.
     *
     * @return  integer     Number of election in the system
     */
    public static function countElections() : int
    {
        return Database::getInstance()->getCount(DB::table('topics'));
    }


    /**
     * Set the election record ID.
     *
     * @param   integer $tid    Record ID for election
     * @return  object  $this
     */
    public function setTid(int $tid) : self
    {
        $this->tid = $tid;
        return $this;
    }


    /**
     * Get the election record ID.
     *
     * @return  integer $tid    Record ID for election
     */
    public function getTid() : int
    {
        return $this->tid;
    }


    /**
     * Set the election record ID.
     *
     * @param   string  $id     Record ID for election
     * @return  object  $this
     */
    private function setPid(string $pid)
    {
        $this->pid = COM_sanitizeID($pid, false);
        return $this;
    }


    /**
     * Get the election reord ID.
     *
     * @return  string  Record ID of election
     */
    public function getPid() : string
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
    public function setOwner(int $uid=0) : self
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
     * @return  boolean     True if a new record, False if existing
     */
    public function isNew() : bool
    {
        return $this->tid == 0;
    }


    /**
     * Check if the election is open to submissions.
     *
     * @return  integer     1 if open, 0 if closed
     */
    public function isOpen() : int
    {
        global $_CONF;

        if (
            $this->status > 0 ||
            $this->Opens->toMySQL() > $_CONF['_now']->toMySQL() ||
            $this->Closes && $this->Closes->toMySQL() < $_CONF['_now']->toMySQL()
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
    public function canViewResults() : int
    {
        static $can_view = NULL;

        if ($can_view === NULL) {
            /*$admin = SEC_inGroup('Root');
            if ($admin) {
                $can_view = true;
            } else*/ if ($this->isOpen() && $this->hideresults) {
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
     * @param   array   $Votes  Array of Vote objects
     * @return  object  $this
     */
    public function withSelections(array $Votes) : self
    {
        $this->_selections = array();
        if (is_array($Votes)) {
            foreach ($Votes as $Vote) {
                $this->_selections[$Vote->qid] = $Vote;
            }
        }
        return $this;
    }


    /**
     * Set the private key to decode the vote record.
     *
     * @param   string  $key    Voter's private key
     * @return  object  $this
     */
    public function withAccessKey(string $key) : self
    {
        $this->_access_key = $key;
        return $this;
    }


    /**
     * Set the vote record ID to retrieve an existing vote.
     *
     * @param   integer $id     Voting record ID
     * @return  object  $this
     */
    public function withVoteId(int $id) : self
    {
        $this->_vote_id = (int)$id;
        return $this;
    }


    /**
     * Set the cookie key for this election to track if user has voted.
     *
     * @param   string  $key    Cookie value
     * @return  object  $this
     */
    public function withCookieKey(string $key) : self
    {
        $this->cookie_key = $key;
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
     * Check if this election allows votes to be edited.
     *
     * @return  boolean     True if updates are allowed, False if not
     */
    public function canUpdate() : bool
    {
        return ($this->mod_allowed >= 2 && $this->isOpen());
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
    public function numQuestions() : int
    {
        return count($this->getQuestions());
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
    public function getQuestions() : array
    {
        if ($this->_Questions === NULL) {
            $this->_Questions = Question::getByElection($this->tid, $this->rnd_questions);
        }
        return $this->_Questions;
    }


    /**
     * Get the comment code setting for this election.
     *
     * @return  integer     Comment code value
     */
    /*public function getCommentcode()
    {
        return (int)$this->commentcode;
    }*/


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
    public function declaresWinner() : bool
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
    private function _createDate($dt, bool $local) : \Date
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
    public function setOpenDate($dt=NULL, $local=false) : self
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
     * Get the "Show Vote" button to display with the secret key field.
     *
     * @param   string  $pid    Election ID
     * @return  string      HTML for the button
     */
    public static function getShowVoteButton($pid)
    {
        $T = new \Template(Config::path_template());
        $T->set_file('button', 'showvotebutton.thtml');
        $T->set_var(array(
            'pid'           => $pid,
            'action_url'    => Config::get('url'),
            'lang_enterkey' => MO::_('Enter Key'),
            'lang_showvote' => MO::_('Show Vote'),
        ) );
        $T->parse('output', 'button');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Read a single election record from the database
     *
     * @return  boolean     True on success, False on error
     */
    public function Read() : bool
    {
        $this->Questions = array();
        $db = Database::getInstance();
        $queryBuilder = $db->conn->createQueryBuilder();
        $queryBuilder
            ->select('t.*', 'count(v.tid) as vote_count')
            ->from(DB::table('topics'), 't')
            ->leftJoin('t', DB::table('voters'), 'v', 'v.tid=t.tid')
            ->where('t.tid = :tid')
            ->setParameter('tid', $this->tid);
        try {
            $stmt = $queryBuilder->execute();
            $A = $stmt->fetchAssociative();
            $this->setVars(new DataArray($A), true);
            return true;
        } catch(Throwable $e) {
            return false;
        }
    }


    /**
     * Set all values for this election into local variables.
     *
     * @param   array   $A          Array of values to use.
     * @param   boolean $fromdb     Indicate if $A is from the DB or a election.
     * @return  object  $this
     */
    function setVars(DataArray $A, bool $fromdb=false) : self
    {
        global $_CONF;

        $this->setTid($A->getInt('tid'));
        $this->setPid($A->getString('pid'));
        $this->topic = $A->getString('topic');
        $this->dscp = $A->getString('description');
        $this->inblock = $A->getInt('display');
        $this->status = $A->getInt('status');
        $this->rnd_questions = $A->getInt('rnd_questions');
        $this->decl_winner = $A->getInt('decl_winner');
        $this->show_remarks = $A->getInt('show_remarks');
        $this->hideresults = $A->getInt('hideresults');
        //$this->commentcode = (int)$A['commentcode'];
        $this->setOwner($A->getInt('owner_id'));
        $this->voting_gid = $A->getInt('group_id');
        $this->results_gid = $A->getInt('results_gid');
        $this->mod_allowed = $A->getInt('voteaccess');
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
            $this->withCookieKey($A['cookie_key']);
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
        return $this;
    }


    /**
     * Create the edit election for all the electionzer variables.
     * Checks the type of edit being done to select the right template.
     *
     * @param   string  $type   Type of editing- 'edit' or 'registration'
     * @return  string          HTML for edit election
     */
    public function edit(string $type = 'edit') : string
    {
        global $_CONF, $_GROUPS, $_USER, $MESSAGE;

        $db = Database::getInstance();
        $retval = COM_startBlock(
            MO::_('Edit Election'),
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $T = new \Template(Config::path_template() . 'admin/');
        $T->set_file(array(
            'editor' => 'editor.thtml',
            'question' => 'questions.thtml',
            'answer' => 'answeroptions.thtml',
        ) );

        if (!$this->isNew()) {       // if not a new record
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
            $Questions = Question::getByElection($this->tid);
        } else {
            $this->owner_id = (int)$_USER['uid'];
            $this->voting_gid = (int)SEC_getFeatureGroup ('election.edit');
            //$this->commentcode = (int)$_CONF['comment_code'];
            SEC_setDefaultPermissions($A, Config::get('default_permissions'));
            $Questions = array();
        }

        $ownername = COM_getDisplayName($this->owner_id);
        $T->set_var(array(
            'action_url' => Config::get('admin_url') . '/index.php',
            'lang_electionid' => MO::_('Election ID'),
            'tid' => $this->tid,
            'pid' => $this->pid,
            'lang_donotusespaces' => MO::_('Do not use spaces.'),
            'lang_topic' => MO::_('Topic'),
            'topic' => htmlspecialchars ($this->topic),
            'lang_mode' => MO::_('Comments'),
            'description' => $this->dscp,
            'lang_description' => MO::_('Description'),
            //'comment_options' => COM_optionList(DB::table('commentcodes'),'code,name',$this->commentcode),
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
            'min_date' => Dates::MIN_DATE,
            'max_date' => Dates::MAX_DATE,
            // user access info
            'lang_accessrights' => MO::_('Access Rights'),
            'lang_owner' => MO::_('Owner'),
            'owner_username' => $db->getItem(DB::table('users'), 'username', array('uid' => $this->owner_id)),
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
            'lang_edit' => MO::_('Edit your vote'),
            'lang_noaccess' => MO::_('Access Denied'),
            'lang_voteaccess' => MO::_('After-voting access for voters'),
            'voteaccess_' . $this->mod_allowed => 'selected="selected"',
            'lang_general' => MO::_('General'),
            'lang_questions' => MO::_('Questions'),
            'lang_permissions' => MO::_('Permissions'),
            'lang_back' => MO::_('Back to Listing'),
            'rndq_chk' => $this->rnd_questions ? 'checked="checked"' : '',
            'lang_rnd_q' => MO::_('Randomize question order?'),
            'lang_rnd_a' => MO::_('Sort displayed answers'),
            'lang_as_entered' => MO::_('As Entered'),
            'lang_random' => MO::_('Randomly'),
            'lang_alpha' => MO::_('Alphabetically'),
            'lang_decl_winner' => MO::_('Declares a winner?'),
            'lang_show_remarks' => MO::_('Show Answer Remarks on Election Form?'),
            'decl_chk' => $this->decl_winner ? 'checked="checked"' : '',
            'remarks_chk' => $this->show_remarks ? 'checked="checked"' : '',
            'timezone' => $_CONF['timezone'],
            'lang_resetresults' => $this->tid > 0 ? MO::_('Reset Results') : '',
            'lang_exp_reset' => MO::_('Reset all results for this election'),
            'lang_reset' => MO::_('Reset'),
            'opens_date' => $this->Opens->format('Y-m-d H:i', true),
            'closes_date' => $this->Closes ? $this->Closes->format('Y-m-d H:i', true) : '',
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
                    'sort_sel_' . $Questions[$j]->getAnswerSort() => 'selected="selected"',
                ) );
                $Answers = $Questions[$j]->getAnswers();
            } else {
                $Answers = array();
                $T->unset_var('hasdata');
                $T->unset_var('question_text');
            }
            $T->set_var(array(
                'lang_question' => MO::_('Question') . ' ' . $display_id,
                'lang_ans_sort' => MO::_('Sort Answers'),
                'lang_answer' => MO::_('Answer'),
                'lang_votes' => MO::_('Votes'),
                'lang_remark' => MO::_('Remark'),
                'lang_saveaddnew' => MO::_('Save and Add'),
            ) );
            $T->parse('qt', 'questiontab', true);

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
            $T->parse('answer_option', 'answer', true);
            $T->parse('question_list', 'question', true);
            $T->clear_var('AR');
            $T->clear_var ('answer_option');
            $T->clear_var('sort_sel_0');
            $T->clear_var('sort_sel_1');
            $T->clear_var('sort_sel_2');
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
     * @return  boolean     True on success, False on error
     */
    function Save(?DataArray $A=NULL) : bool
    {
        global $_CONF;

        $db = Database::getInstance();
        $retval = true;

        if ($A) {
            $this->setVars($A, false);
        }
        if ($this->Created === NULL) {
            $this->Created = clone $_CONF['_now'];
        }

        if (empty($this->topic)) {
            $this->addError(MO::_('A name is required.'));
        }
        if (empty($this->pid)) {
            $this->addError(MO::_('An identifier is required.'));
        }

        if (isset($A['resetresults']) && $A['resetresults'] == 1) {
            self::deleteVotes($this->tid);
            $this->cookie_key = Token::create();
        }

        $values = array(
            'pid' => $this->pid,
            'topic' => $this->topic,
            'description' => $this->dscp,
            'created' => $this->Created->toMySQL(false),
            'opens' => $this->Opens->toMySQL(false),
            'closes' => $this->Closes->toMySQL(false),
            'display' => $this->inblock,
            'status' => $this->status,
            'hideresults' => $this->hideresults,
            'owner_id' => $this->owner_id,
            'group_id' => $this->voting_gid,
            'results_gid' => $this->results_gid,
            'voteaccess' => $this->mod_allowed,
            'rnd_questions' => $this->rnd_questions,
            'decl_winner' => $this->decl_winner,
            'show_remarks' => $this->show_remarks,
            'cookie_key' => $this->cookie_key,
        );
        $types = array(
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
            Database::STRING,
        );
        $db = Database::getInstance();
        try {
            if ($this->isNew()) {
                $db->conn->insert(DB::table('topics'), $values, $types);
                $this->tid = $db->conn->lastInsertId();
            } else {
                $types[] = Database::INTEGER;
                $db->conn->update(
                    DB::table('topics'),
                    $values,
                    array('tid' => $this->tid),
                    $types
                );
            }
        } catch(\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // Duplicate pid value
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $this->addError(MO::_('Duplicate key violation, Election ID must be unique'));
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $this->addError(MO::_("An error occurred saving the election"));
        }
        if (!empty($this->_errors)) {
            return false;
        }

        // Got here, must have successfully saved the election record
        $Questions = Question::getByElection($this->tid);
        for ($i = 0; $i < Config::get('maxquestions'); $i++) {
            if (empty($A['question'][$i])) {
                break;
            }
            if (isset($Questions[$i])) {
                $Q = $Questions[$i];
            } else {
                $Q = new Question();
            }
            $Q->setTid($this->tid)
              ->setQid($i)
              ->setAnswerSort($A['ans_sort'][$i])
              ->setQuestion($A['question'][$i])
              ->setAnswers($A)
              ->Save();
        }

        // Now delete any questions that were removed.
        for (; $i < count($Questions); $i++) {
            $Questions[$i]->Delete();
        }
        CTL_clearCache();       // so autotags pick up changes
        $msg = '';              // no error message if successful
        PLG_itemSaved($this->tid, 'election');
        return true;
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
                ON v.tid = p.tid",
            'query_fields' => array('topic'),
            'default_filter' => 'AND' . self::getPermSql(),
            'group_by' => 'p.tid',
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
        static $message = '';      // to make sure the variable is set

        switch($fieldname) {
        case 'edit':
            $retval = FieldList::edit(array(
                'url' => Config::get('admin_url') . "/index.php?edit={$A['tid']}",
            ) );
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
                Config::get('admin_url') . '/index.php?preview=' . $A['tid']
            );
            break;

        case 'topic':
            $retval = htmlspecialchars($fieldvalue);
            $voted = Voter::hasVoted($A['tid'], $A['cookie_key'], $A['group_id']);
            $closed = ($A['closes'] < $extras['_now']) || $A['status'] > 0;
            if (
                !$closed &&
                !$voted &&
                SEC_inGroup($A['group_id'])
            ) {
                $retval = COM_createLink(
                    $retval,
                    COM_buildUrl(Config::get('url') . "/index.php?pid={$A['pid']}")
                );
            } elseif (
                SEC_inGroup($A['results_gid']) &&
                ($closed || !$A['hideresults'])
            ) {
                $retval = COM_createLink(
                    $retval,
                    Config::get('url') . "/index.php?results={$A['pid']}"
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
                    $retval .= '&nbsp;' . FieldList::buttonLink(array(
                        'text' => MO::_('Results'),
                        'url' => Config::get('url') . '/index.php?results=' . $A['pid'],
                        'style' => 'primary',
                    ) );
                }
            } else {
                // Election is open. Show the voting link if the user hasn't voted,
                // otherwise show the early results link if allowed.
                $retval = MO::_('Open');
                if (!Voter::hasVoted($A['tid'], $A['cookie_key'], $A['group_id'])) {
                    if (SEC_inGroup($A['group_id'], $_USER['uid'])) {
                        $retval .= '&nbsp;' . FieldList::buttonLink(array(
                            'text' => MO::_('Vote'),
                            'url' => Config::get('url') . '/index.php?pid=' . urlencode($A['pid']),
                            'style' => 'success',
                        ) );
                    } elseif (!$A['hideresults'] && SEC_inGroup('results_gid')) {
                        $retval .= '&nbsp;' . FieldList::buttonLink(array(
                            'text' => MO::_('Results'),
                            'url' => Config::get('url') . '/index.php?results=' . urlencode($A['pid']),
                            'style' => 'primary',
                        ) );
                    } elseif (COM_isAnonUser()) {
                        $message = MO::_('Log in to vote.');
                    }
                } elseif (!$A['voteaccess']) {
                    $message = MO::_('Your vote has been recorded.');
                }
            }
            break;
        case 'user_extra':
            if ($message != '') {
                $retval = $message;
            } elseif (Voter::hasVoted($A['tid'], $A['cookie_key'], $A['group_id'])) {
                if ($A['voteaccess']) {
                    $retval = self::getShowVoteButton($A['pid']);
                } else {
                    // Results available only after election closes
                    $retval = MO::_('Results available after closing.');
                }
            }
            break;
        case 'status':
            $fieldvalue = (int)$fieldvalue;
            $retval = FieldList::checkbox(array(
                'name' => 'ena_check',
                'value' => 1,
                'id' => 'togenabled' . $A['pid'],
                'checked' => $fieldvalue == 0,
                'onclick' => Config::PI_NAME . "_toggle(this,'{$A['tid']}','{$fieldname}','election');",
            ) );
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
                    Config::get('admin_url') . '/index.php?lv='.$A['tid']
                );
            }
            break;
        case 'results':
            if ($A['vote_count'] > 0) {
                $retval = FieldList::resultsLink(array(
                    'url' => Config::get('admin_url') . '/index.php?results=' . $A['pid']
                ) );
            } else {
                $retval = 'n/a';
            }
            break;
        case 'reset':
            if ($A['status'] == Status::ARCHIVED) {
                $retval = FieldList::refresh(array(
                    'disabled' => true,
                ) );
            } else { 
                $retval = FieldList::refresh(array(
                    'url' => Config::get('admin_url') . "/index.php?resetelection={$A['tid']}",
                    'attr' => array(
                        'onclick' => "return confirm('" .
                            MO::_('Are you sure you want to delete all of the results for this election?') .
                            "');",
                        'class' => 'uk-text-danger',
                    )
                ) );
            }
            break;
        case 'delete':
            $retval = FieldList::delete(array(
                'delete_url' => Config::get('admin_url') . '/index.php' . 
                    '?delete=x&amp;pid=' . $A['pid'] . '&amp;' . CSRF_TOKEN . '=' . $extras['token'],
                'attr' => array(
                    'title' => MO::_('Delete'),
                    'onclick' => "return doubleconfirm('" .
                        MO::_('Are you sure you want to delete this Poll?') . "','" .
                        MO::_('Are you absolutely sure you want to delete this Poll?  All questions, answers and comments that are associated with this Poll will also be permanently deleted from the database.') .
                        "');",
                )
            ) );
            break;
        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }


    /**
     * Display the election options.
     *
     * @param   boolean $preview    True if previewing by admin
     * @return  string      HTML for election form
     */
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
                    echo COM_refresh(Config::get('url') . '/index.php?results=' . $this->pid);
                } elseif ($this->disp_type == Modes::AUTOTAG) {
                    // In an autotag
                    return (new Results($this->tid))
                        ->withDisplayType($this->disp_type)
                        ->Render();
                } else {
                    // in a block, just add a link for now
                    return COM_createLink(
                        $this->getTopic() . ' (' . MO::_('Results') . ')',
                        Config::get('url') . '/index.php?results=' . $this->pid
                    );
                }
            } else {
                return $this->msgNoResultsWhileOpen();
            }
        } else {
            return $this->msgNoAccess();
        }
    }


    /**
     * Shows the election form.
     *
     * Shows an HTML formatted election for the given topic ID
     *
     * @param   boolean $preview    True if this is a preview, no submission
     * @return       string  HTML Formatted Election
     */
    public function showElectionForm(bool $preview=false) : string
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
            $aftervote_url = Config::get('url') . '/index.php?results=' . $this->pid;
            break;
        }
        $Questions = Question::getByElection($this->tid, $this->rnd_questions);
        $nquestions = count($Questions);
        if ($nquestions > 0) {
            $T = new \Template(Config::path_template());
            $T->set_file(array(
                'block' => 'block.thtml',
                'pquestions' => 'questions.thtml',
                //'comments' => 'comments.thtml',
            ) );
            if ($nquestions > 1) {
                $T->set_var('lang_topic', MO::_('Topic'));
                $T->set_var('topic', $filterS->filterData($this->topic));
                $T->set_var('lang_question', MO::_('Question'));
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
            $T->set_var(array(
                'tid' => $this->tid,
                'pid' => $this->pid,
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
                'topic' => $this->topic,
            ) );

            if ($nquestions == 1 || $this->disp_showall) {
                // Only one question (block) or showing all (main form)
                $T->set_var('lang_vote', MO::_('Vote'));
                $T->set_var('showall', true);
                if ($this->disp_type == Modes::BLOCK) {
                    //$T->set_var('use_ajax', true);
                } else {
                    $T->unset_var('use_ajax');
                }
            } else {
                $T->set_var('lang_vote', MO::_('Start Voting'));
                $T->unset_var('showall');
                $T->unset_var('autotag');
            }
            $T->set_var('lang_votes', MO::_('Votes'));

            $results = '';
            if (
                $this->status != Status::OPEN ||
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
            $T->set_var('results', $results);

            if (self::hasRights('edit')) {
                $editlink = COM_createLink(
                    MO::_('Edit'),
                    Config::get('admin_url') . '/index.php?edit=' . $this->tid
                );
                $T->set_var('edit_link', $editlink);
                $T->set_var('edit_icon', $editlink);
                $T->set_var('edit_url', Config::get('admin_url').'/index.php?edit=' . $this->tid);
            }

            for ($j = 0; $j < $nquestions; $j++) {
                $Q = $Questions[$j];
                $T->set_var('question', $filterS->filterData($Q->getQuestion()));
                $T->set_var('question_id', $j);
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
                    $T->set_var('lang_question_number', ($j+1));
                }
                if (isset($this->_selections[$Q->getQid()])) {
                    $T->set_var('old_aid', $this->_selections[$Q->getQid()]->aid);
                }

                $answers = $Q->getAnswers();
                $nanswers = count($answers);
                $T->set_block('pquestions', 'Answers', 'panswer');
                for ($i = 0; $i < $nanswers; $i++) {
                    $Answer = $answers[$i];
                    if (
                        isset($this->_selections[$j]) &&
                        (int)$this->_selections[$j]->aid == $Answer->getAid()
                    ) {
                        $T->set_var('selected', 'checked="checked"');
                    } else {
                        $T->clear_var('selected');
                    }

                    if (!empty($this->_access_key)) {
                        switch ($this->mod_allowed) {
                        case 0:
                        case 1:
                            $T->set_var('radio_disabled', 'disabled="disabled"');
                            break;
                        }
                    }

                    $T->set_var(array(
                        'answer_id' =>$Answer->getAid(),
                        'answer_text' => $filterS->filterData($Answer->getAnswer()),
                        'answer_remark' => $this->showRemarks() ? $Answer->getRemark() : '',
                        'rnd' => $random,
                    ) );
                    $T->parse('panswer', 'Answers', true);
                }
                $T->parse('questions', 'pquestions', true);
                $T->clear_var('panswer');
            }
            $T->set_var('lang_topics', MO::_('Topic'));
            $T->set_var('notification', $notification);
            $retval = $T->finish($T->parse('output', 'block')) . LB;
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
     * @param    array    $aid   selected answers
     * @return   string   HTML for election results
     */
    public function saveVote(array $aid, array $old_aid = array()) : string
    {
        global $_USER;

        $retval = '';
        if ($this->alreadyVoted() && !$this->canUpdate()) {
            if (!COM_isAjax()) {
                COM_setMsg(MO::_('Your vote has already been recorded.'));
            }
            return false;
        }

        $Request = Request::getInstance();

        // Set a browser cookie to block multiple votes from anonymous.
        // Done here since we have access to $aid.
        SEC_setCookie(
            Config::PI_NAME . '-' . $this->tid . '-' . $this->cookie_key,
            implode('-', $aid),
            time() + Config::get('cookietime')
        );

        $this->_aftervoteUrl = $Request->getString('aftervote_url');
        if (isset($Request['vid']) && !empty($Request['vid'])) {
            $vote_id = COM_decrypt($Request->getString('vid'));
        } else {
            $vote_id = 0;
        }

        // Record that this user has voted
        $Voter = Voter::create($this->tid, $aid, $vote_id);
        if ($Voter !== NULL) {
            // Increment the vote count for each answer
            $answers = count($aid);
            for ($i = 0; $i < $answers; $i++) {
                if (array_key_exists($i, $old_aid)) {
                    Answer::decrement($this->tid, $i, (int)$old_aid[$i]);
                }
                Answer::increment($this->tid, $i, (int)$aid[$i]);
            }

            // Set a return message, if not called via ajax
            if (!COM_isAjax()) {
                $T = new \Template(Config::path_template());
                $T->set_file('msg', 'votesaved.thtml');
                $T->set_var(array(
                    'lang_votesaved' => MO::_('Your vote has been recorded for'),
                    'lang_copykey' => MO::_('Copy your key to a safe location if you wish to verify your vote later.'),
                    'lang_yourkeyis' => MO::_('Your private key is'),
                    'lang_copyclipboard' => MO::_('Copy to clipboard'),
                    'lang_copy_success' => MO::_('Your private key was copied to your clipboard.'),
                    'lang_keyonetime' => MO::_('Your private key will not be displayed again.'),
                    'lang_newkey' => MO::_('A new private key is generated for each submission.'),
                    'prv_key' => $Voter->getId() . ':' . $Voter->getPrvKey(),
                    'topic' => $this->getTopic(),
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
        return Voter::hasVoted($this->tid, $this->cookie_key, $this->voting_gid);
    }


    /**
     * Shows all election in system unless archived.
     *
     * @return   string          HTML for election listing
     */
    public static function listElections()
    {
        global $_CONF, $_USER;

        $T = new \Template(Config::path_template());
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
        $filter_ors = array(
            // Open elections where the user has voting access
            "(status = " . Status::OPEN .
                " AND '$sql_now_utc' BETWEEN opens AND closes " .
                SEC_buildAccessSql('AND', 'group_id') . ')',
            // Closed elections where the user is allowed to view results
            "((hideresults = 0 OR status = " . Status::CLOSED . " OR closes < '$sql_now_utc')" .
                SEC_buildAccessSql('AND', 'results_gid') . ')',
        );
        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        $qb->select(
                'p.*',
                '(SELECT COUNT(v.id) FROM ' . DB::table('voters') . ' v WHERE v.tid = p.tid) AS vote_count'
            )
            ->from(DB::table('topics'), 'p')
            ->where('p.status <> ' . Status::ARCHIVED)
            ->andWhere('(' . implode(' OR ' , $filter_ors) . ')')
            ->andWhere($db->getAccessSql('', 'p.results_gid'));
        try {
            $data = $qb->execute()->fetchAllAssociative();
            $count = count($data);
            foreach ($data as $A) {
                $retval[$A['pid']] = new self;
                $retval[$A['pid']]->setVars(new DataArray($A));
            }
        } catch(Throwable $e) {
            $count = 0;
        }
        $data_arr = array();
        foreach ($data as $A) {
            $A['has_voted'] = Voter::hasVoted($A['tid'], $A['cookie_key'], $A['group_id']);
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
            'count' => $count,
            'msg_alert' => $count == 0 ?
                self::msgAlert(
                    MO::_('It appears that there are no elections available.')
                ) :
                '',
            ) );
        $T->parse('output', 'list');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Delete a election.
     *
     * @param   boolean $force  True to disregard access, e.g. user is deleted
     * @return  string          HTML redirect
     */
    public function delete($force=false) : void
    {
        global $_CONF, $_USER;

        if (
            !$this->isNew() &&
            ($force || self::hasRights('edit'))
        ) {
            $db = Database::getInstance();
            // Delete all questions, answers and votes
            Question::deleteElection($this->tid);
            Answer::deleteElection($this->tid);
            Voter::deleteElection($this->tid);
            // Now delete the election topic
            $db->conn->delete(
                DB::table('topics'),
                array('tid' => $this->tid),
                array(Database::STRING)
            );

            PLG_itemDeleted($this->tid, Config::PI_NAME);
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
    public static function deleteVotes(int $tid) : void
    {
        $Election = new self($tid);
        if (!$Election->isNew() && $Election->getStatus() < Status::ARCHIVED) {
            Answer::resetElection($Election->getTid());
            Voter::deleteElection($Election->getTid());
            try {
                Database::getInstance()->conn->update(
                    DB::table('topics'),
                    array('cookie_key' => Token::create()),
                    array('pid' => $pid),
                    array(Database::STRING, Database::STRING)
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
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

        $db = Database::getInstance();
        $retval = '';
        $retval .= COM_startBlock(
            'Election Votes for ' . $this->topic, '',
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
            'form_url'     => Config::get('admin_url') . '/index.php?lv='.$this->tid,
        );

        $sql = "SELECT voters.*, FROM_UNIXTIME(voters.date) as dt_voted, users.username
            FROM " . DB::table('voters') . " AS voters
            LEFT JOIN " . DB::table('users') . " AS users ON voters.uid=users.uid
            WHERE voters.tid = " . $this->tid;

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
    public static function moveUser(int $origUID, int $destUID) : void
    {
        $db = Database::getInstance();
        try {
            $db->conn->update(
                DB::table('topics'),
                array(
                    'owner_id' => $destUID,
                ),
                array(
                    'owner_id' => $origUID,
                ),
                array(
                    Database::INTEGER,
                    Database::INTEGER,
                )
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, "Error changing owner ID");
        }
        Voter::moveUser($origUID, $destUID);
    }


    /**
     * Sets a boolean field to the opposite of the supplied value.
     *
     * @param   integer $oldvalue   Old (current) value
     * @param   integer $tid        ID of record to modify
     * @return  integer     New value, or old value upon failure
     */
    public static function toggleEnabled(int $oldvalue, int $tid) : int
    {
        // Determing the new value (opposite the old)
        if ($oldvalue == 1) {
            $newvalue = 0;
        } elseif ($oldvalue == 0) {
            $newvalue = 1;
        } else {
            return $oldvalue;
        }

        try {
            Database::getInstance()->conn->update(
                DB::table('topics'),
                array('status' => $newvalue),
                array('tid' => $tid),
                array(Database::INTEGER, Database::STRING)
            );
            return $newvalue;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . '; ' . $e->getMessage());
            return $oldvalue;
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
        $T = new \Template(Config::path_template());
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


    /**
     * See if the answer remarks should be shown on the elections form.
     *
     * @return  boolean     True to show the remark for each answer.
     */
    public function showRemarks() : bool
    {
        return $this->show_remarks != 0;
    }


    /**
     * Add an error message.
     *
     * @param   string  $msg    Message to return from getErrors()
     * @return  object  $this
     */
    public function addError(string $msg) : self
    {
        $this->_errors[] = $msg;
        return $this;
    }


    /**
     * Get all the errors for display.
     *
     * @param   boolean $fmt    True to return a formatted list
     * @return  array|string    Raw message array, or formatted list
     */
    public function getErrors(bool $fmt=false)
    {
        if ($fmt) {
            if (!empty($this->_errors)) {
                $retval = self::msgAlert(
                    '<ul><li>' . implode('</li><li>', $this->_errors) . '</li></ul>'
                );
            } else {
                $retval = '';
            }
        } else {
            $retval = $this->_errors;
        }
        return $retval;
    }

}
