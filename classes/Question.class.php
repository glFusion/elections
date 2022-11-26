<?php
/**
 * Base class to handle poll questions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v0.3.0
 * @since       v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections;
use glFusion\Database\Database;
use glFusion\Log\Log;


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

    /** Flag indicating how answers should be sorted for display.
     * @var boolean */
    private $ans_sort = 0;

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
    public function __construct($A=NULL, $ans_sort=0)
    {
        global $_USER;

        if (is_array($A)) {
            $this->setVars($A, true);
        }
        $this->ans_sort = (int)$ans_sort;
        if ($this->qid > -1 && !empty($this->pid)) {
            $this->Answers = Answer::getByQuestion($this->qid, $this->pid, $this->ans_sort);
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
        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM " . DB::table('questions') . " WHERE qid = $id",
                array($id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = NULL;
        }
        return $data;
    }


    /**
     * Get all the questions that appear on a given poll.
     *
     * @param   string  $pid    Election ID
     * @param   boolean $rnd_q  True to randomize question order
     * @param   boolean $ans_sort   Flag indicating how to sort answers
     * @return  array       Array of Question objects
     */
    public static function getByElection($pid, $rnd_q=false, $ans_sort=0)
    {
        $retval = array();
        $db = Database::getInstance();
        if ($rnd_q) {
            $order = ' ORDER BY RAND()';
        } else {
            $order = ' ORDER BY pid,qid ASC';
        }
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM " . DB::table('questions') . " WHERE pid = ? $order",
                array($pid),
                array(Database::STRING)
            )->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = NULL;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $retval[] = new self($A, $ans_sort);
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
     * Set the answers for this question and save to the DB.
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
                ->setRemark($A['remark'][$this->qid][$i])
                ->Save();
        }
        for (; $i < count($this->Answers); $i++) {
            $this->Answers[$i]->Delete();
            unset($this->Answers[$i]);
        }
        return $this;
    }


    /**
     * Set the question ID.
     *
     * @param   integer $qid    Question record ID
     * @return  object  $this
     */
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
    public static function deleteElection(string $pid) : void
    {
        $db = Database::getInstance();
        try {
            $db->conn->delete(
                DB::table('questions'),
                array('pid' => $pid),
                array(Database::STRING)
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
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
        $db = Database::getInstance();
        try {
            $db->conn->insert(
                DB::table('questions'),
                array(
                    'pid' => $this->pid,
                    'qid' => $this->getQid(),
                    'question' => $this->question,
                ),
                array(Database::STRING, Database::INTEGER, Database::STRING)
            );
            return 0;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
            try {
                $db->conn->update(
                    DB::table('questions'),
                    array('question' => $this->question),
                    array('qid' => $this->getQid()),
                    array(Database::STRING, Database::INTEGER)
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return 5;
            }
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return 5;
        }
    }


    /**
     * Delete the current question definition.
     */
    public function Delete() : void
    {
        $db = Database::getInstance();
        try {
            $db->conn->delete(
                DB::table('questions'),
                array('qid' => $this->qid),
                array(Database::INTEGER)
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
        try {
            $db->conn->delete(
                DB::table('answers'),
                array('qid' => $this->qid),
                array(Database::INTEGER)
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
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
    public static function changePid(string $old_pid, string $new_pid) : void
    {
        $db = Database::getInstance();
        try {
            $db->conn->update(
                DB::table('answers'),
                array('pid' => $new_pid),
                array('pid' => $old_pid),
                array(Database::STRING, Database::STRING)
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }

}
