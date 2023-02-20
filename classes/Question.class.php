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
use Elections\Models\DataArray;


/**
 * Base class for poll questions.
 * @package elections
 */
class Question
{
    /** Maximum answers allowed.
     * @todo: Make this a per-election setting
     * @var integer */
    const MAX_ANSWERS = 10;

    /** Related elections's topic ID.
     * @var integer */
    private $tid = 0;

    /** Question record ID.
     * @var integer */
    private $qid = -1;

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
    private $Answers = NULL;

    /** Flag to indicate question was written using the advanced editor.
     * @var int */
    protected $advanced_editor_mode = 1;


    /**
     * Constructor.
     *
     * @param   integer $qid    Question record ID
     */
    public function __construct(?int $tid=NULL, ?int $qid=NULL)
    {
        if (!empty($tid) && !empty($qid)) {
            $this->setQid($qid)
                 ->setTid($tid)
                 ->Read();
        }
        if ($this->qid > -1 && !empty($this->tid)) {
            $this->Answers = Answer::getByQuestion($this->qid, $this->tid, $this->ans_sort);
        }
    }


    /**
     * Read this field definition from the database and load the object.
     *
     * @see     self::setVars()
     * @return  array           DB record array
     */
    public function Read() : self
    {
        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM " . DB::table('questions') . " WHERE qid = ?",
                array($this->qid),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (!empty($data)) {
            $this->setVars(new DataArray($data, true));
        }
        return $this;
    }


    public static function getInstance(int $tid, int $qid) : self
    {
        $retval = new self;
        $retval->setTid($tid)
               ->setQid($qid)
               ->Read();
        return $retval;
    }


    /**
     * Get all the questions that appear on a given poll.
     *
     * @param   integer $tid    Election ID
     * @param   boolean $rnd_q  True to randomize question order
     * @return  array       Array of Question objects
     */
    public static function getByElection(int $tid, int $rnd_q=0) : array
    {
        $retval = array();
        $db = Database::getInstance();
        if ($rnd_q) {
            $order = ' ORDER BY RAND()';
        } else {
            $order = ' ORDER BY tid,qid ASC';
        }
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM " . DB::table('questions') . " WHERE tid = ? $order",
                array($tid),
                array(Database::INTEGER)
            )->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = NULL;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $Q = new self;
                $Q->setVars(new DataArray($A));
                $retval[] = $Q;
            }
        }
        return $retval;
    }


    /**
     * Set all variables for this field.
     * Data is expected to be from $_POST or a database record.
     *
     * @param   DataArray   $A      Array of name->value pairs
     * @return  object      $this
     */
    public function setVars(DataArray $A) : self
    {
        $this->tid = $A->getInt('tid');
        $this->qid = $A->getInt('qid');
        $this->ans_sort = $A->getInt('ans_sort');
        $this->question = $A->getString('question');
        return $this;
    }


    /**
     * Set the election ID. Used when creating a new question.
     *
     * @param   integer $tid    Election ID
     * @return  object  $this
     */
    public function setTid(int $tid) : self
    {
        $this->tid = $tid;
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
     * Set the sorting method when displaying answers.
     *
     * @param   integer $ans_sort   Sorting option
     * @return  object  $this
     */
    public function setAnswerSort(int $sort) : self
    {
        $this->ans_sort = $sort;
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
                ->setTid($this->tid)
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
    public function getQuestion() : string
    {
        return $this->question;
    }


    /**
     * Get the answer sorting option.
     *
     * @return  integer     Answer sorting option
     */
    public function getAnswerSort() : int
    {
        return $this->ans_sort;
    }


    /**
     * Get the possible answers for this question.
     *
     * @return  array       Array of answer records
     */
    public function getAnswers() : array
    {
        if ($this->Answers === NULL) {
            $this->Answers = Answer::getByQuestion($this->qid, $this->tid, $this->ans_sort);
        }
        return $this->Answers;
    }


    /**
     * Delete all the questions for a poll.
     * Called when a poll is deleted or the ID is changed.
     *
     * @param   integer $tid    Election ID
     */
    public static function deleteElection(int $tid) : void
    {
        $db = Database::getInstance();
        try {
            $db->conn->delete(
                DB::table('questions'),
                array('tid' => $tid),
                array(Database::INTEGER)
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
                    'tid' => $this->tid,
                    'qid' => $this->getQid(),
                    'ans_sort' => $this->getAnswerSort(),
                    'question' => $this->question,
                ),
                array(Database::STRING, Database::INTEGER, Database::INTEGER, Database::STRING)
            );
            return 0;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
            try {
                $db->conn->update(
                    DB::table('questions'),
                    array('question' => $this->question, 'ans_sort' => $this->getAnswerSort()),
                    array('tid' => $this->tid, 'qid' => $this->getQid()),
                    array(Database::STRING, Database::INTEGER, Database::INTEGER, Database::INTEGER)
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


    public function isNew() : bool
    {
        return $this->qid < 0;
    }


    /**
     * Edit a question definition.
     *
     * @return  string      HTML for editing form
     */
    public function edit() : string
    {
        global $_TABLES, $_CONF_QUIZ, $_CONF;

        $retval = '';
        $format_str = '';
        $listinput = '';

        $db = Database::getInstance();
        $T = new \Template($_CONF['path'] . '/plugins/elections/templates/admin');
        $T->set_file('editform', 'editquestion.thtml');

        SEC_setCookie(
            $_CONF['cookie_name'].'adveditor',
            SEC_createTokenGeneral('advancededitor'),
            time() + 1200, $_CONF['cookie_path'],
            $_CONF['cookiedomain'],
            $_CONF['cookiesecure'],
            false
        );

        // Set up the wysiwyg editor, if available
        $tpl_var = $_CONF_QUIZ['pi_name'] . '_entry';
        switch (PLG_getEditorType()) {
        case 'ckeditor':
            $T->set_var('show_htmleditor', true);
            PLG_requestEditor($_CONF_QUIZ['pi_name'], $tpl_var, 'ckeditor_quiz.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        case 'tinymce' :
            $T->set_var('show_htmleditor',true);
            PLG_requestEditor($_CONF_QUIZ['pi_name'], $tpl_var, 'tinymce_quiz.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        default :
            // don't support others right now
            $T->set_var('show_htmleditor', false);
            break;
        }

        // Get defaults from the form, if defined
        /*if ($this->quizID > 0) {
            $form = Quiz::getInstance($this->quizID);
        }*/
        if ($this->advanced_editor_mode) {
            $T->set_var(array(
                'default_visual_editor' => true,
                'adv_edit_mode' => 1,
            ) );
        } else {
            $T->set_var(array(
                'default_visual_editor' => false,
                'adv_edit_mode' => 0,
            ) );
        }
        $T->set_var(array(
            'topic' => $db->getItem(
                DB::table('topics'),
                'topic',
                array('tid' => $this->tid),
                array(Database::INTEGER)
            ),
            'tid'       => $this->tid,
            'qid'       => $this->qid,
            'question'      => $this->question,
            //'ena_chk'   => $this->enabled == 1 ? 'checked="checked"' : '',
            //'doc_url'   => QUIZ_getDocURL('question_def.html'),
            'editing'   => $this->isNew() ? '' : 'true',
            //'help_msg'  => $this->help_msg,
            //'postAnswerMsg' => $this->postAnswerMsg,
            //'can_delete' => $this->isNew() || $this->_wasAnswered() ? false : true,
            //'random_chk' => $this->randomizeAnswers ? 'checked="checked"' : '',
            'lang_pid' => MO::_('Election Topic'),
            'lang_question' => MO::_('Question'),
            'lang_answers' => MO::_('Answers'),
        ) );

        $T->set_block('editform', 'Answers', 'Ans');
        foreach ($this->Answers as $Answer) {
            $T->set_var(array(
                'aid'    => $Answer->getAid(),
                'ans_val'   => $Answer->getAnswer(),
            ) );
            $T->parse('Ans', 'Answers', true);
        }
        $count = count($this->Answers);
        for ($i = $count + 1; $i <= self::MAX_ANSWERS; $i++) {
            $T->set_var(array(
                'ans_id'    => $i,
                'ans_val'   => '',
                'ischecked' => '',
            ) );
            $T->parse('Ans', 'Answers', true);
        }
        $T->parse('output', 'editform');
        $retval .= $T->finish($T->get_var('output'));
        return $retval;
    }

}
