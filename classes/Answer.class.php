<?php
/**
 * Class to describe question answers.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v0.1.0
 * @since       v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections;


/**
 * Base class for poll questions.
 * @package elections
 */
class Answer
{
    /** Election record ID. (deprecate?)
     * @var string */
    private $pid = '';

    /** Question record ID.
     * @var integer */
    private $qid = -1;

    /** Answer record ID.
     * @var integer */
    private $aid = -1;

    /** Answer text.
     * @var string */
    private $answer = '';

    /** Number of votes given to this answer.
     * @var integer */
    private $votes = 0;

    /** Remark or help text.
     * @var string */
    private $remark = '';

    /** Flag to cause deletion of the answer record.
     * Used if the poll is edited and answers are removed.
     * @var boolean */
    private $deleteFlag = 0;


    /**
     * Constructor.
     *
     * @param   array   $A      DB record array, NULL for new record
     */
    public function __construct($A = NULL)
    {
        if (is_array($A)) {
            $this->setVars($A);
        }
    }


    /**
     * Get all the answers for a given question.
     *
     * @param   integer $q_id       Question ID
     * @param   string  $pid        Election ID
     * @param   boolean $rnd        True to get in random order
     * @return  array       Array of Answer objects
     */
    public static function getByQuestion($q_id, $pid, $rnd=false)
    {
        $q_id = (int)$q_id;
        $retval = array();
        $sql = "SELECT * FROM " . DB::table('answers') . "
            WHERE qid = '{$q_id}' AND pid = '" . DB_escapeString($pid) . "' ";
        if ($rnd) {
            $sql .= 'ORDER BY RAND()';
        } else {
            $sql .= 'ORDER BY aid ASC';
        }
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $retval[] = new self($A);
            }
        }
        return $retval;
    }


    /**
     * Get all the answers for a given question, ordered by score.
     *
     * @param   integer $q_id       Question ID
     * @param   string  $pid        Election ID
     * @return  array       Array of Answer objects
     */
    public static function getByScore($q_id, $pid)
    {
        $q_id = (int)$q_id;
        $retval = array();
        $sql = "SELECT * FROM " . DB::table('answers') . "
            WHERE qid = '{$q_id}' AND pid = '" . DB_escapeString($pid) . "'
            ORDER BY votes DESC";
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $retval[] = new self($A);
            }
        }
        return $retval;
    }


    /**
     * Set all variables for this field.
     * Data is expected to be from $_POST or a database record
     *
     * @param   array   $A      Array of name->value pairs
     * @param   boolean $fromDB Indicate whether this is read from the DB
     */
    public function setVars($A, $fromDB=false)
    {
        if (!is_array($A)) {
            return false;
        }

        $this->pid = $A['pid'];
        $this->qid = (int)$A['qid'];
        $this->aid = (int)$A['aid'];
        $this->votes = (int)$A['votes'];
        $this->answer = $A['answer'];
        $this->remark = $A['remark'];
        return $this;
    }


    /**
     * Save the field definition to the database.
     *
     * @param   array   $A  Array of name->value pairs
     * @return  string          Error message, or empty string for success
     */
    public function Save()
    {
        $answer = DB_escapeString($this->answer);
        $remark = DB_escapeString($this->remark);
        $sql = "INSERT INTO " . DB::table('answers'). " SET
                pid = '" . DB_escapeString($this->getPid()) . "',
                qid = '{$this->getQid()}',
                aid = '{$this->getAid()}',
                answer = '$answer',
                remark = '$remark'
            ON DUPLICATE KEY UPDATE
                answer = '$answer',
                remark = '$remark'";
                //votes = {$this->getVotes()}
        //echo $sql;die;
        DB_query($sql);
        if (DB_error()) {
            return 6;
        }
        return 0;
    }


    /**
     * Delete the current question definition.
     *
     * @return  object  $this;
     */
    public function Delete()
    {
        DB_delete(DB::table('answers'), 'aid', (int)$this->aid);
        $this->aid = 0;
        $this->qid = 0;
        $this->pid = '';
        return $this;
    }


    /**
     * Delete all the answers for a poll.
     * Called when a poll is deleted or the ID is changed.
     *
     * @param   string  $pid    Election ID
     */
    public static function deleteElection($pid)
    {
        DB_delete(DB::table('answers'), 'pid', $pid);
    }


    /**
     * Reset all answers to zero votes for a poll.
     *
     * @param   string  $pid    Election ID
     */
    public static function resetElection($pid)
    {
        DB_query(
            "UPDATE " . DB::table('answers') .
            " SET votes = 0
            WHERE pid = '" . DB_escapeString($pid) . "'"
        );
    }


    /**
     * Set the poll ID.
     *
     * @param   string  $pid    Election ID
     * @return  object  $this
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
        return $this;
    }

    public function getPid()
    {
        return $this->pid;
    }


    /**
     * Set the question ID.
     *
     * @param   integer $q_id   Question ID
     * @return  object  $this
     */
    public function setQid($q_id)
    {
        $this->qid = (int)$q_id;
        return $this;
    }


    /**
     * Get the question ID.
     *
     * @return  integer     Question record ID
     */
    public function getQid()
    {
        return (int)$this->qid;
    }


    /**
     * Set the answer ID.
     *
     * @param   integer $a_id   Answer ID
     * @return  object  $this
     */
    public function setAid($a_id)
    {
        $this->aid = (int)$a_id;
        return $this;
    }


    /**
     * Get the answer ID.
     *
     * @return  integer     Answer ID
     */
    public function getAid()
    {
        return (Int)$this->aid;
    }


    /**
     * Set the number of votes given to this answer.
     * Used when creating new answers.
     *
     * @param   integer $val    Number of votes
     * @return  object  $this
     */
    public function setVotes($val)
    {
        $this->votes = (int)$val;
        return $this;
    }


    /**
     * Get the number of votes given to this answer.
     *
     * @return  integer     Votes given
     */
    public function getVotes()
    {
        return (int)$this->votes;
    }


    /**
     * Set the value text.
     *
     * @param   string  $txt    Value text for the answer
     * @return  object  $this
     */
    public function setAnswer($txt)
    {
        $this->answer = $txt;
        return $this;
    }


    /**
     * Set the remark text when updating the answers.
     *
     * @param   string  $txt    Remark text
     * @return  object  $this
     */
    public function setRemark($txt)
    {
        $this->remark = $txt;
        return $this;
    }


    /**
     * Get the value text to display.
     *
     * @param   boolean $esc    True to escape for saving
     * @return  string      Value of the answer
     */
    public function getAnswer($esc = false)
    {
        return (string)$this->answer;
    }


    /**
     * Get the remark text for this answer.
     *
     * @return  text        Remark text
     */
    public function getRemark()
    {
        return $this->remark;
    }


    /**
     * Increment the vote cound for an answer.
     *
     * @param   string  $pid    Election ID
     * @param   integer $qid    Question ID
     * @param   integer $aid    Answer ID
     */
    public static function increment($pid, $qid, $aid)
    {
        $sql = "UPDATE " . DB::table('answers') . "
            SET votes = votes + 1
            WHERE pid = '" . DB_escapeString($pid) . "'
            AND qid = '" . (int)$qid . "'
            AND aid = '" . (int)$aid . "'";
        DB_query($sql, 1);
    }


    /**
     * Change the Election ID for all items if it was saved with a new ID.
     *
     * @param   string  $old_pid    Original Election ID
     * @param   string  $new_pid    New Election ID
     */
    public static function changePid($old_pid, $new_pid)
    {
        DB_query("UPDATE " . DB::table('pollquestions') . "
            SET pid = '" . DB_escapeString($new_pid) . "'
            WHERE pid = '" . DB_escapeString($old_pid) . "'"
        );
    }

}

?>
