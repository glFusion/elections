<?php
/**
 * Class to describe question answers.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021-2023 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v0.3.0
 * @since       v0.1.0
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
class Answer
{
    const SORT_NONE = 0;
    const SORT_RAND = 1;
    const SORT_ALPHA = 2;
    const SORT_VOTES = 3;

    /** Election record ID.
     * @var integer */
    private $tid = 0;

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
     * @param   integer $q_id   Question ID
     * @param   integer $tid    Election ID
     * @param   integer $sort   Sorting order
     * @return  array       Array of Answer objects
     */
    public static function getByQuestion(int $q_id, int $tid, int $sort = 0) : array
    {
        global $_TABLES;

        $retval = array();
        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        $qb->select('a.*', 'count(v.aid) as total_votes')
           ->from($_TABLES['elections_answers'], 'a')
           ->leftJoin('a', $_TABLES['elections_votes'], 'v', 'v.tid=a.tid AND v.qid=a.qid AND v.aid=a.aid')
           ->where('a.qid = :q_id')
           ->andWhere('a.tid = :tid')
           ->groupBy('a.qid, a.aid')
           ->setParameter('q_id', $q_id, Database::INTEGER)
           ->setParameter('tid', $tid, Database::INTEGER);
        switch ($sort) {
        case self::SORT_NONE:
            $qb->orderBy('a.aid', 'ASC');
            break;
        case self::SORT_RAND:
            $qb->orderBy('RAND()');
            break;
        case self::SORT_ALPHA:
            $qb->orderBy('a.answer', 'ASC');
            break;
        case self::SORT_VOTES:
            $qb->orderBy('total_votes', 'DESC');
            break;
        }

        try {
            $data = $qb->execute()->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = NULL;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $retval[] = new self($A);
            }
        }
        return $retval;
    }


    /**
     * Get all the answers for a given question, ordered by score.
     *
     * @param   integer $q_id       Question ID
     * @param   integer $tid        Election ID
     * @return  array       Array of Answer objects
     */
    public static function getByScore(int $q_id, int $tid)
    {
        global $_TABLES;

        $q_id = (int)$q_id;
        $retval = array();
        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT a.*, count(v.vid) as vote_count
                FROM {$_TABLES['elections_answers']} a
                LEFT JOIN {$_TABLES['elections_votes']} v
                ON a.aid = v.aid AND a.qid=v.qid
                WHERE a.qid = ? AND a.tid = ?
                GROUP BY a.qid,a.aid
                ORDER BY vote_count DESC",
                array($q_id, $tid),
                array(Database::INTEGER, Database::INTEGER)
            );
        } catch (\Excepton $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = NULL;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
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

        $this->tid = $A['tid'];
        $this->qid = (int)$A['qid'];
        $this->aid = (int)$A['aid'];
        $this->votes = (int)$A['total_votes'];
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
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->insert(
                $_TABLES['elections_answers'],
                array(
                    'tid' => $this->getTid(),
                    'qid' => $this->getQid(),
                    'aid' => $this->getAid(),
                    'answer' => $this->answer,
                    'remark' => $this->remark,
                ),
                array(
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::STRING,
                    Database::STRING,
                )
            );
            return 0;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
            $db->conn->update(
                $_TABLES['elections_answers'],
                array(
                    'answer' => $this->answer,
                    'remark' => $this->remark,
                ),
                array(
                    'tid' => $this->getTid(),
                    'qid' => $this->getQid(),
                    'aid' => $this->getAid(),
                ),
                array(
                    Database::STRING,
                    Database::STRING,
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::INTEGER,
                )
            );
            return 0;
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return 6;
        }
    }


    /**
     * Delete the current answer from the question.
     *
     * @return  object  $this;
     */
    public function Delete()
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->delete(
                $_TABLES['elections_answers'],
                array('tid' => $this->tid, 'qid' => $this->qid, 'aid' => $this->aid),
                array(Database::INTEGER, Database::INTEGER, Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
        $this->aid = 0;
        $this->qid = 0;
        $this->tid = 0;
        return $this;
    }


    /**
     * Delete all the answers for an election.
     * Called when an election is deleted or the ID is changed.
     *
     * @param   integer $tid    Election ID
     */
    public static function deleteElection(int $tid) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->delete(
                $_TABLES['elections_answers'],
                array('tid' => $tid),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Reset all answers to zero votes for an election.
     *
     * @param   integer $tid    Election ID
     */
    public static function resetElection(int $tid) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->update(
                $_TABLES['elections_answers'],
                array('votes' => 0),
                array('tid' => $tid),
                array(Database::INTEGER, Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Set the election ID.
     *
     * @param   integer $tid    Election ID
     * @return  object  $this
     */
    public function setTid(int $tid) : self
    {
        $this->tid = $tid;
        return $this;
    }

    public function getTid() : int
    {
        return $this->tid;
    }


    /**
     * Set the question ID value for this answer.
     *
     * @param   integer $qid    Question ID
     * @return  object  $this
     */
    public function setQid(int $qid) : self
    {
        $this->qid = (int)$qid;
        return $this;
    }


    /**
     * Get the question ID.
     *
     * @return  integer     Question record ID
     */
    public function getQid() : int
    {
        return (int)$this->qid;
    }


    /**
     * Set the answer ID.
     *
     * @param   integer $a_id   Answer ID
     * @return  object  $this
     */
    public function setAid(int $a_id) : self
    {
        $this->aid = (int)$a_id;
        return $this;
    }


    /**
     * Get the answer ID.
     *
     * @return  integer     Answer ID
     */
    public function getAid() : int
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
    public function setVotes(int $val) : self
    {
        $this->votes = (int)$val;
        return $this;
    }


    /**
     * Get the number of votes given to this answer.
     *
     * @return  integer     Votes given
     */
    public function getVotes() : int
    {
        return (int)$this->votes;
    }


    /**
     * Set the value text.
     *
     * @param   string  $txt    Value text for the answer
     * @return  object  $this
     */
    public function setAnswer(string $txt) : self
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
    public function setRemark(string $txt) : self
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
    public function getAnswer() : string
    {
        return (string)$this->answer;
    }


    /**
     * Get the remark text for this answer.
     *
     * @return  text        Remark text
     */
    public function getRemark() : string
    {
        return $this->remark;
    }


    /**
     * Increment the vote cound for an answer.
     *
     * @param   integer $tid    Election ID
     * @param   integer $qid    Question ID
     * @param   integer $aid    Answer ID
     */
    public static function Xincrement(int $tid, int $qid, int $aid) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->executeStatement(
                "UPDATE {$_TABLES['elections_answers']}
                SET votes = votes + 1
                WHERE tid = ?
                AND qid = ?
                AND aid = ?",
                array($tid, $qid, $aid),
                array(Database::INTEGER, Database::INTEGER, Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Decrement the number of votes received for an answer.
     * Used when votes are edited.
     *
     * @param   integer $tid    Election ID
     * @param   integer $qid    Question ID
     * @param   integer $aid    Answer ID
     * @return  void
     */
    public static function Xdecrement(int $tid, int $qid, int $aid) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->executeStatement(
                "UPDATE {$_TABLES['elections_answers']}
                SET votes = (case when votes < 1 then 0 else (votes - 1) end)
                WHERE tid = ?
                AND qid = ?
                AND aid = ?",
               array($tid, $qid, $aid),
               array(Database::INTEGER, Database::INTEGER, Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }

}
