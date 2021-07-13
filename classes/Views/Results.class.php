<?php
/**
 * Class to represent the results view for an election.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2021 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v0.1.2
 * @since       v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections\Views;
use Elections\Election;
use Elections\Answer;
use Elections\Config;
use Elections\Models\Modes;
use Elections\Models\Status;
use Elections\MO;


/**
 * Class for a single poll.
 * @package elections
 */
class Results
{
    private $cmt_order = 'DESC';
    private $cmt_mode = '';
    private $displaytype = 0;
    private $pid = '';
    private $showComments = 1;
    private $Election = NULL;
    private $isAdmin = false;


    /**
     * Set the poll ID if supplied, and the comment mode to the default.
     *
     * @param   string  $pid    Optionall Election ID
     */
    public function __construct($pid='')
    {
        global $_CONF;

        if (!empty($pid)) {
            $this->withElection($pid);
        }
        $this->withCommentMode($_CONF['comment_mode']);
    }


    /**
     * Set the ID of the poll to show, if not set in the constructor.
     *
     * @param   string|object  $pid    Election ID or object
     * @return  object  $this
     */
    public function withElection($pid)
    {
        if (is_string($pid)) {
            $this->pid = $pid;
            $this->Election = Election::getInstance($pid);
        } elseif (is_object($pid) && $pid instanceof Election) {
            $this->pid = $pid->getID();
            $this->Election = $pid;
        }
        return $this;
    }


    /**
     * Set the comment order, ASC or DESC.
     *
     * @param   string  $order  Comment display order
     * @return  object  $this
     */
    public function withCommentOrder($order)
    {
        if ($order == 'DESC') {
            $this->cmt_order = $order;
        } else {
            $order = 'ASC';
        }
        return $this;
    }


    /**
     * Set the display type. Normal (0), Autotag or Print.
     *
     * @param   integer $type   Display type flag.
     * @return  object  $this
     */
    public function withDisplayType($type)
    {
        $this->displaytype = (int)$type;
        return $this;
    }


    /**
     * Set the comment mode, e.g. "nested".
     *
     * @param   string  $mode   Comment display mode
     * @return  object  $this
     */
    public function withCommentMode($mode)
    {
        $this->cmt_mode = $mode;
        return $this;
    }


    /**
     * Set the flag to show comments, or not.
     *
     * @param   boolean $flag   True to show comments, False to suppress
     * @return  object  $this
     */
    public function withComments($flag)
    {
        $this->showComments = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Set the Admin flag to indicate if this view is called from the admin area.
     *
     * @param   boolean $flag   True if this is an admin view, False if not
     * @return  object  $this
     */
    public function withAdmin($flag)
    {
        $this->isAdmin = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Shows the results of a poll.
     * Shows the poll results for a given poll topic.
     *
     * @return     string   HTML Formated Election Results
     */
    public function Render()
    {
        global $_CONF, $_TABLES, $_USER, $_IMAGE_TYPE,
           $_COM_VERBOSE, $_SYSTEM;

        $retval = '';

        if ($this->Election->isNew()/* || !$this->Election->canViewResults()*/) {
            // Invalid poll or no access
            return '';
        }

        if (
            $this->Election->hideResults() &&
            $this->Election->isOpen()
        ) {
            if (
                $this->displaytype == Modes::NORMAL &&
                !$this->isAdmin &&
                (
                    !isset($_USER['uid']) ||
                    $_USER['uid'] != $this->Election->getOwnerID() ||
                    !Election::hasRights('edit')
                )
            ) {
                // Normal mode, show a message if not an owner or admin
                $msg = $this->Election->msgNoResultsWhileOpen();
                $retval = COM_showMessageText($msg,'', true,'error');
                $retval .= Election::listElections();
                return $retval;
            }
        }

        $poll = new \Template(array(
            Config::path_template() . $SYSTEM['framework'],
            Config::path_template(),
        ) );
        $poll->set_file(array(
            'result' => 'result.thtml',
            'question' => 'question.thtml',
            'comments' => 'comments.thtml',
            'votes_bar' => 'votes_bar.thtml',
        ) );

        if ($this->isAdmin) {
            $list_url = Config::get('admin_url') . '/index.php';
        } else {
            $list_url = Config::get('url') . '/index.php';
        }

        $filter = new \sanitizer();
        $filter->setPostmode('text');
        $poll->set_var(array(
            //'layout_url'    => $_CONF['layout_url'],
            'topic'     => $filter->filterData($this->Election->getTopic()),
            'pid'       => $this->pid,
            'num_votes' => COM_numberFormat($this->Election->numVotes()),
            'lang_votes' => MO::_('Votes'),
            'admin_url' => Config::get('admin_url') . '/index.php',
            'polls_url' => $this->isAdmin ? '' : Config::get('url') . '/index.php',
            'isOpen' => $this->Election->isOpen(),
            'adminView' => $this->Election->hideResults(),
            'lang_back' => MO::_('Back to Listing'),
            'lang_is_open' => MO::_('Early results, election is open.'),
            'lang_question' => MO::_('Question'),
            'url'       => $list_url,
        ) );

        if ($this->Election->getStatus() == Status::ARCHIVED) {
            $poll->set_var('alert_msg', MO::_('Election is archived and not visible to regular voters.'));
        }
        if ($this->displaytype == Modes::NORMAL && Election::hasRights('edit')) {
            $editlink = COM_createLink(
                MO::_('Edit'),
                Config::get('admin_url') . '/index.php?edit=x&amp;pid=' . $this->pid);
            $poll->set_var(array(
                'edit_link' => $editlink,
                'edit_url' => Config::get('admin_url') . '/index.php?edit=x&amp;pid=' . $this->pid,
                'edit_icon' => COM_createLink(
                    '<i class="uk-icon-edit tooltip"></i>',
                    Config::get('admin_url') . '/index.php?edit=x&amp;pid=' . $this->pid,
                    array(
                        'title' => MO::_('Edit'),
                    )
                ),
            ) );
        }
        $questions = $this->Election->getQuestions();
        $nquestions = count($questions);
        for ($j = 0; $j < $nquestions; $j++) {
            if ($nquestions >= 1) {
                $counter = ($j + 1) . "/$nquestions" ;
            }
            $Q = $questions[$j];
            $poll->set_var(array(
                'lang_question' => MO::_('Question'),
                'lang_question_number' => $counter,
                'question' =>$filter->filterData($Q->getQuestion())
            ) );
            $Answers = Answer::getByScore($Q->getQid(), $this->pid);
            $nanswers = count($Answers);
            $q_totalvotes = 0;
            $winner_votes = -1;

            // If the poll has closed, get the winning scores.
            foreach ($Answers as $idx=>$A) {
                $q_totalvotes += $A->getVotes();
                if ($A->getVotes() > $winner_votes) {
                    $winner_votes = $A->getVotes();
                }
            }
            // For open polls, the winner is not highlighted.
            if ($this->Election->isOpen()) {
                $winner_votes = -1;
            }

            for ($i=1; $i<=$nanswers; $i++) {
                $A = $Answers[$i - 1];
                if ($q_totalvotes == 0) {
                    $percent = 0;
                } else {
                    $percent = $A->getVotes() / $q_totalvotes;
                }
                $width = (int)($percent * 100 );
                $winner = ($this->Election->declaresWinner()) && ($A->getVotes() == $winner_votes);
                $poll->set_var(array(
                    'answer_text' => $filter->filterData($A->getAnswer()),
                    'remark_text' => $filter->filterData($A->getRemark()),
                    'answer_percent' => sprintf('%.2f', $percent * 100),
                    'winner' => $winner,
                    'bar_width' => $width,
                ) );
                $poll->parse('votes', 'votes_bar', true);
            }
            $poll->parse('questions', 'question', true);
            $poll->clear_var('votes');
        }

        if ($this->Election->getCommentcode() >= 0 ) {
            USES_lib_comments();
            $num_comments = CMT_getCount(Config::PI_NAME, $this->pid);
            $poll->set_var('num_comments',COM_numberFormat($num_comments));
            $poll->set_var('lang_comments', MO::_('Comments'));
            $comment_link = CMT_getCommentLinkWithCount(
                Config::PI_NAME,
                $this->pid,
                Config::get('url') . '/index.php?pid=' . $this->pid,
                $num_comments,
                0
            );
            $poll->set_var('comments_url', $comment_link['link_with_count']);
            $poll->parse('comments', 'comments', true);
        } else {
            $poll->set_var('comments_url', '');
            $poll->set_var('comments', '');
        }

        $poll->set_var('lang_topics', MO::_('Topics'));
        if ($this->isAdmin && $this->displaytype !== Modes::PRINT) {
            $poll->set_var(array(
                'print_url' => Config::get('admin_url') . '/index.php?presults=x&pid=' . urlencode($this->pid),
                'lang_print' => MO::_('Print'),
            ) );
        }
        $retval .= $poll->finish($poll->parse('output', 'result' ));

        if (
            $this->showComments &&
            $this->Election->getCommentcode() >= 0 &&
            $this->displaytype != Modes::AUTOTAG
        ) {
            $delete_option = Election::hasRights('edit') ? true : false;
            USES_lib_comment();

            $page = isset($_GET['page']) ? COM_applyFilter($_GET['page'],true) : 0;
            if (isset($_POST['order'])) {
                $this->cmt_order  =  $_POST['order'] == 'ASC' ? 'ASC' : 'DESC';
            } elseif (isset($_GET['order']) ) {
                $this->cmt_order =  $_GET['order'] == 'ASC' ? 'ASC' : 'DESC';
            } else {
                $this->cmt_order = 'DESC';
            }
            if (isset($_POST['mode'])) {
                $this->withCommentMode(COM_applyFilter($_POST['mode']));
            } elseif (isset($_GET['mode'])) {
                $this->withCommentMode(COM_applyFilter($_GET['mode']));
            }
            $retval .= CMT_userComments(
                $this->pid, $filter->filterData($this->Election->getTopic()), Config::PI_NAME,
                $this->cmt_order, $this->cmt_mode, 0, $page, false,
                $delete_option, $this->Election->getCommentcode(), $this->Election->getOwnerID()
            );
        }
        return $retval;
    }


    /**
     * Create a printable results page.
     *
     * @return  string      HTML for printable page.
     */
    public function Print()
    {
        $retval = '';
        $retval .= '<html><head>' . LB;
        $retval .= '<link rel="stylesheet" type="text/css" href="' . _css_out() . '">' . LB;
        $retval .= '</head><body>' . LB;
        $retval .= $this->withDisplayType(Modes::PRINT)->withComments(false)->Render();
        $retval .= '</body></html>' . LB;
        return $retval;
    }


    /**
     * Create the list of voting records for this poll.
     *
     * @return  string      HTML for voting list
     */
    public function listVotes()
    {
        global $_CONF, $_TABLES, $_IMAGE_TYPE;

        $retval = '';
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
                'field' => 'date_voted',
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

        $sql = "SELECT * FROM {$_TABLES['pollvoters']} AS voters
            LEFT JOIN {$_TABLES['users']} AS users ON voters.uid=users.uid
            WHERE voters.pid='" . DB_escapeString($this->pid) . "'";

        $query_arr = array(
            'table' => 'pollvoters',
            'sql' => $sql,
            'query_fields' => array('uid'),
            'default_filter' => '',
        );
        $token = SEC_createToken();
        $retval .= ADMIN_list (
            Config::PI_NAME, array(__CLASS__, 'getListField'), $header_arr,
            $text_arr, $query_arr, $defsort_arr, '', $token
        );
        $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $retval;
    }

}

?>
