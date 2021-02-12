<?php
/**
 * Base class to handle poll questions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v3.0.0
 * @since       v3.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections;


/**
 * Base class for poll questions.
 * @package elections
 */
class Question
{
    /** Question record ID.
     * @var integer */
    private $qid = -1;

    /** Related poll's record ID.
     * @var string */
    private $pid = '';

    /** Question text.
     * @var string */
    private $question = '';

    /** Flage to delete the question.
     * Used if the poll is edited and a question is removed.
     * @var boolean */
    private $deleteFlag = 0;

    /** HTML filter.
     * @var object */
    private $filterS = NULL;

    /** Array of answer objects.
     * @var array */
    private $Answers = array();


    /**
     * Constructor.
     *
     * @param   array   $A      Optional data record
     */
    public function __construct($A=NULL)
    {
        global $_USER;

        if (is_array($A)) {
            $this->setVars($A, true);
        }
        if ($this->qid > -1 && !empty($this->pid)) {
            $this->Answers = Answer::getByQuestion($this->qid, $this->pid);
        }
    }


    /**
     * Read this field definition from the database and load the object.
     *
     * @see     self::setVars()
     * @param   integer $id     Record ID of question
     * @return  array           DB record array
     */
    public static function Read($id = 0)
    {
        $id = (int)$id;
        $sql = "SELECT * FROM " . DB::table('questions') . " WHERE qid = $id";
        $res = DB_query($sql, 1);
        if (DB_error() || !$res) return false;
        $A = DB_fetchArray($res, false);
        return $A;
    }


    /**
     * Get all the questions that appear on a given poll.
     *
     * @param   string  $pid    Election ID
     * @return  array       Array of Question objects
     */
    public static function getByElection($pid)
    {
        $retval = array();
        $sql = "SELECT * FROM " . DB::table('questions') . "
            WHERE pid = '" . DB_escapeString($pid) . "'
            ORDER BY pid,qid ASC";
        $res = DB_query($sql, 1);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = new self($A);
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
        $this->qid = (int)$A['qid'];
        $this->pid = COM_sanitizeID($A['pid']);
        $this->question = $A['question'];
        return $this;
    }


    /**
     * Set the poll ID. Used when creating a new question.
     *
     * @param   string  $pid    Election ID
     * @return  object  $this
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
        return $this;
    }


    /**
     * Set the question text. Used when creating a new question.
     *
     * @param   string  $q      Question text
     * @return  object  $this
     */
    public function setQuestion($q)
    {
        $this->question = $q;
        return $this;
    }


    /**
     * Set the answers for this question.
     *
     * @param   array   $A      Array of anwer strings
     * @return  object  $this
     */
    public function setAnswers($A)
    {
        for ($i = 0; $i < Config::get('maxanswers'); $i++) {
            if ($A['answer'][$this->qid][$i] == '') break;
            if (!isset($this->Answers[$i])) {
                $this->Answers[$i] = new Answer;
            }
            $this->Answers[$i]->setAnswer($A['answer'][$this->qid][$i])
                ->setQid($this->qid)
                ->setPid($this->pid)
                ->setAid($i)
                ->setVotes($A['votes'][$this->qid][$i])
                ->setRemark($A['remark'][$this->qid][$i])
                ->Save();
        }
        for (; $i < count($this->Answers); $i++) {
            $this->Answers[$i]->Delete();
            unset($this->Answers[$i]);
        }
        return $this;
    }


    public function setQid($qid)
    {
        $this->qid = (int)$qid;
        return $this;
    }
    
    
    /**
     * Get the record ID for this question.
     *
     * @return  integer     Record ID
     */
    public function getQid()
    {
        return (int)$this->qid;
    }


    /**
     * Get the text for this question.
     *
     * @return  string      Question text to display
     */
    public function getQuestion()
    {
        return $this->question;
    }


    /**
     * Get the possible answers for this question.
     *
     * @return  array       Array of answer records
     */
    public function getAnswers()
    {
        return $this->Answers;
    }


    /**
     * Delete all the questions for a poll.
     * Called when a poll is deleted or the ID is changed.
     *
     * @param   string  $pid    Election ID
     */
    public static function deleteElection($pid)
    {
        DB_delete(DB::table('questions'), 'pid', $pid);
    }


    /**
     * Render the question.
     *
     * @param   integer $q_num  Sequential question number, e.g. first=1, etc.
     * @param   integer $num_q  Total number of questions for this quiz
     * @return  string  HTML for the question form
     */
    public function Render($cnt, $aid)
    {
        global $_CONF;

        $T = new \Template(__DIR__ . '/../templates/admin/');
        $T->set_file(array(
            'question' => 'questions.thtml',
        ) );
        $T->set_var('poll_question', $this->getQuestion());
        $T->set_var('question_id', $cnt);
        $notification = "";
        $Answers = $this->getAnswers();
        $nanswers = count($Answers);
        for ($i=0; $i < $nanswers; $i++) {
            $Answer = $Answers[$j];
            if (($j < count($aid)) && ($aid[$j] == $Answer->getAid())) {
                $poll->set_var('selected', ' checked="checked"');
            }

            $T->set_var(array(
                'answer_id' => $Answer->getAid(),
                'answer_text' => $this->filterS->filterData($Answer->getAnswer()),
            ) );
            $T->set_var('poll_answers', 'panswer',true);
            $T->clear_var('selected');
            $poll->parse('poll_questions', 'pquestions', true);
            $poll->clear_var('poll_answers');

            $T->parse('Answer', 'AnswerRow', true);
        }
        $T->parse('output', 'question');
        $retval .= $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Create the input selection for one answer.
     * Does not display the text for the answer, only the input element.
     * Must be overridden by the actual question class (radio, etc.)
     *
     * @param   integer $a_id   Answer ID
     * @return  string          HTML for input element
     */
    protected function makeSelection($a_id)
    {
        return '';
    }


    /**
     * Check whether the supplied answer ID is correct for this question.
     *
     * @param   integer $a_id   Answer ID
     * @return  float       Percentage of options correct.
     */
    public function Verify($a_id)
    {
        return (float)0;
    }


    /**
     * Get the ID of the correct answer.
     * Returns an array regardless of the actuall numbrer of possibilities
     * to ensure uniform handling by the caller.
     *
     * @return   array      Array of correct answer IDs
     */
    public function getCorrectAnswers()
    {
        return array();
    }


    /**
     * Save the question definition to the database.
     *
     * @param   array   $A  Array of name->value pairs
     * @return  string          Error message, or empty string for success
     */
    public function Save()
    {
        $sql = "INSERT INTO " . DB::table('questions') . " SET 
            pid = '" . DB_escapeString($this->pid) . "',
            qid = {$this->getQid()},
            question = '" . DB_escapeString($this->question) . "'
            ON DUPLICATE KEY UPDATE
            question = '" . DB_escapeString($this->question) . "'";
        DB_query($sql, 1);
        if (DB_error()) {
            return 5;
        }
        return 0;
    }


    /**
     * Delete the current question definition.
     */
    public function Delete()
    {
        DB_delete(DB::table('questions'), 'qid', $this->qid);
        DB_delete(DB::table('answers'), 'qid', $this->qid);
    }


    /**
     * Save a submitted answer to the database.
     *
     * @param   mixed   $value  Data value to save
     * @param   integer $res_id Result ID associated with this field
     * @return  boolean     True on success, False on failure
     */
    public function SaveData($value, $res_id)
    {
        $res_id = (int)$res_id;
        if ($res_id == 0)
            return false;

        return Value::Save($res_id, $this->questionID, $value);
    }


    /**
     * Get all the questions for a result set.
     *
     * @param   array   $ids    Array of question ids, from the resultset
     * @return  array       Array of question objects
     */
    public static function getByIds($ids)
    {
        $questions = array();
        foreach ($ids as $id) {
            $questons[] = new self($id);
        }
        return $questions;
    }


    /**
     * Change the Election ID for all items if it was saved with a new ID.
     *
     * @param   string  $old_pid    Original Election ID
     * @param   string  $new_pid    New Election ID
     */
    public static function changePid($old_pid, $new_pid)
    {
        DB_query("UPDATE " . DB::table('answers') . "
            SET pid = '" . DB_escapeString($new_pid) . "'
            WHERE pid = '" . DB_escapeString($old_pid) . "'"
        );
    }

}

?>
