<?php
/**
 * Class to describe voters.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v0.3.0
 * @since       v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections;
use Elections\Models\Groups;
use Elections\Models\Vote;
use Elections\Models\Token;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class to manage election voters.
 * @package elections
 */
class Voter
{
    const KEY_COOKIE = 'elections_pkey';

    /** Voting record ID.
     * @var integer */
    private $id = 0;

    /** Related election ID.
     * @var string */
    private $pid = '';

    /** IP address of the voter.
     * @var string */
    private $ipaddress = '';

    /** Voter's user ID
     * @var integer */
    private $uid = 0;

    /** Timestamp that vote was cast.
     * @var integer */
    private $date = 0;

    /** Encrypted voting data.
     * @var string */
    private $votedata = '';

    /** Public key used for encryption.
     * @var string */
    private $pub_key = '';

    /** Private key used for encryption.
     * @var string */
    private $_prv_key = '';

    /** Encrypted string containing vote record IDs.
     * @var string */
    private $voterecords = '';

    /** Array of voting records.
     * @var array */
    private $_voteRecords = NULL;

    public function __construct($A=array())
    {
        if (is_array($A) && !empty($A)) {
            $this->withId($A['id'])
                 ->withPid($A['pid'])
                 ->withIpAddress($A['ipaddress'])
                 ->withUid($A['uid'])
                 ->withDate($A['date'])
                 ->withData($A['votedata'])
                 ->withVoteRecords($A['voterecords'])
                 ->withPubKey($A['pub_key']);
        }
    }


    public function withId($id)
    {
        $this->id = (int)$id;
        return $this;
    }


    public function getId()
    {
        return (int)$this->id;
    }


    public function withPid($pid)
    {
        $this->pid = $pid;
        return $this;
    }

    public function getPid()
    {
        return $this->pid;
    }


    public function withUid($uid)
    {
        $this->uid = (int)$uid;
        return $this;
    }

    public function withIpAddress($ip)
    {
        $this->ipaddress = $ip;
        return $this;
    }

    public function withDate($dt)
    {
        $this->date = (int)$dt;
        return $this;
    }

    public function getDate($fmt=NULL)
    {
        global $_USER, $_CONF;

        if ($fmt === NULL) {
            return $this->date;
        } else {
            return (new \Date($this->date, $_USER['tzid']))
                ->format($fmt, true);
        }
    }


    /**
     * Set the encrypted voting data field.
     *
     * @param   string  $data   Encrypted data string
     * @return  object  $this
     */
    public function withData($data)
    {
        $this->votedata = $data;
        return $this;
    }


    /**
     * Get the vote data value.
     *
     * @return  string      Encrypted voting data
     */
    public function getData()
    {
        return $this->votedata;
    }


    /**
     * Set the public key for a saved vote.
     *
     * @param   string  $key    Public key
     * @return  object  $this
     */
    public function withPubKey($key)
    {
        $this->pub_key = $key;
        return $this;
    }


    /**
     * Get the public key for a saved vote.
     *
     * @return  string      Public key
     */
    public function getPubKey()
    {
        return $this->pub_key;
    }


    /**
     * Set the private key for a saved vote.
     *
     * @param   string  $key    Private key
     * @return  object  $this
     */
    public function withPrvKey($key)
    {
        $this->_prv_key = $key;
        return $this;
    }


    /**
     * Get the private key assigned to this voter.
     * Called to display the key upon vote submission.
     *
     * @return  string      Private voting key
     */
    public function getPrvKey()
    {
        return $this->_prv_key;
    }


    /**
     * Set the value of the encrypted list of vote record IDs.
     *
     * @param   string  $records    Encrypted list of record IDs
     * @return  object  $this
     */
    private function withVoteRecords($records) : self
    {
        $this->voterecords = $records;
        return $this;
    }


    /**
     * Reads full vote records from the encrypted list of record IDs.
     *
     * @return  array       Array of Vote objects
     */
    public function getVoteRecords() : array
    {
        if ($this->_voteRecords !== NULL) {
            return $this->_voteRecords;
        }

        $this->_voteRecords = array();
        $ids = $this->decrypt($this->voterecords);
        if (!empty($ids)) {
            $db = Database::getInstance();
            try {
                $data = $db->conn->executeQuery(
                    "SELECT * FROM " . DB::table('votes') . "
                    WHERE vid IN (?)",
                    array($ids),
                    array(Database::PARAM_STR_ARRAY)
                )->fetchAll(Database::ASSOCIATIVE);
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, $e->getMessage());
                $data = NULL;
            }
            if (is_array($data)) {
                foreach ($data as $vals) {
                    $this->_voteRecords[$vals['vid']] = new Vote($vals);
                }
            }
        }
        return $this->_voteRecords;
    }


    /**
     * Get a specific voting record.
     *
     * @param   string|integer  $vote_id    Record ID or record:private_key
     * @return  object      Voter object
     */
    public static function getInstance($vote_id)
    {
        $prv_key = NULL;
        if (is_string($vote_id) && strpos($vote_id, ':') !== false) {
            // An existing vote is being retrieved by the id:private_key string
            list($vote_id, $prv_key) = explode(':', $vote_id);
        } elseif (isset($_COOKIE[self::KEY_COOKIE])) {
            $prv_key = $_COOKIE[self::KEY_COOKIE];
        }
        $db = Database::getInstance();
        $sql = "SELECT * FROM " . DB::table('voters') . " WHERE id = ?";
        try {
            $stmt = $db->conn->executeQuery(
                $sql,
                array($vote_id),
                array(Database::INTEGER)
            );
            $A = $stmt->fetch(Database::ASSOCIATIVE);
            $Voter = new self($A);
            SEC_setCookie(self::KEY_COOKIE, $prv_key, time() + 1800);
            if ($prv_key !== NULL) {
                $Voter->withPrvKey($prv_key);
            }
        } catch (\Throwable $e) {
            $Voter = new self;
        }
        if ($Voter->getId() > 0 && $Voter->getPrvKey() != NULL) {
            // Got a valid voter, now get all the voterecords
            $Voter->getVoteRecords();
        }

        return $Voter;
    }


    /**
     * Check if the user has already voted.
     * For anonymous, checks the IP address and the election cookie.
     *
     * @param   string  $pid            Election ID
     * @param   string  $cookie_key     Cookie key for the election
     * @param   integer $voting_grp     Group with access to vote
     * @return  boolean     True if the user has voted, False if not
     */
    public static function hasVoted(string $pid, string $cookie_key, int $voting_grp=2) : bool
    {
        global $_USER;

        $db = Database::getInstance();

        // If logged in and the user ID is in the voters table,
        // we can trust that this user has voted.
        if (!COM_isAnonUser()) {
            if (
                $db->getCount(
                    DB::table('voters'),
                    array('uid', 'pid'),
                    array($_USER['uid'], $pid),
                    array(Database::INTEGER, Database::STRING)
                ) > 0
            ) {
                return true;
            }
            // Can't return false yet since the voter may have been
            // anonymous when casting the vote.
        }

        if ($voting_grp != Groups::ALL_USERS) {
            // If a login is required, return false now since there's no need
            // to check for anonymous votes.
            return false;
        }

        // For Anonymous we only have the cookie and IP address.
        if (isset($_COOKIE[Config::PI_NAME . '-' . $pid . '-' . $cookie_key])) {
            return true;
        }

        // As a last resort, see if the voter's IP address is in the table.
        // This is less accurate due to NAT and proxies.
        $ip = self::getRealIpAddress();
        if (
            $ip != '' &&
            $db->getCount(
                DB::table('voters'),
                array('ipaddress', 'pid'),
                array($ip, $pid),
                array(Database::STRING, Database::STRING)
            ) > 0
        ) {
            return true;
        }

        // No vote found
        return false;
    }


    /**
     * Get the current user's actual IP address.
     *
     * @return  string      User's IP address
     */
    public static function getRealIpAddress()
    {
        return $_SERVER['REAL_ADDR'];
    }


    /**
     * Create a voter record.
     * This only inserts new records, no updates, so `INSERT IGNORE` is used
     * just to avoid SQL errors.
     *
     * @param   string  $pid    Election ID
     * @param   array   $aid    Answer data to be encrypted
     * @param   integer $vote_id    Existing vote record ID, if any
     * @return  object      Voter object
     */
    public static function create(string $pid, array $aid, int $vote_id=0) : ?object
    {
        global $_USER;

        if ( COM_isAnonUser() ) {
            $uid = 1;
        } else {
            $uid = (int)$_USER['uid'];
        }

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        if ($vote_id > 0) {
            $Voter = self::getInstance($vote_id);
            $qb->update(DB::table('voters'))
               ->set('ipaddress', ':ipaddress')
               ->set('uid', ':uid')
               ->set('date', 'UNIX_TIMESTAMP()')
               ->set('pid', ':pid')
               ->set('votedata', ':data')
               ->set('voterecords', ':voterecords')
               ->set('pub_key', ':pubkey')
               ->where('id = :id');
            // already have the keys.
        } else {
            $Voter = new self;
            $qb->insert(DB::table('voters'))
               ->values(
                    array(
                        'ipaddress' => ':ipaddress',
                        'uid' => ':uid',
                        'date' => 'UNIX_TIMESTAMP()',
                        'pid' => ':pid',
                        'votedata' => ':data',
                        'voterecords' => ':voterecords',
                        'pub_key' => ':pubkey',
                    )
                );
            $keys = self::createKeys();
            $Voter->withPubKey($keys['pub_key'])
                  ->withPrvKey($keys['prv_key']);
        }
        $records = $Voter->saveVoteRecords($pid, $aid);
        $record_ids = array_keys($records);

        $data = $Voter->encrypt(json_encode($aid));
        $voterecords = $Voter->encrypt(json_encode($record_ids));
        SEC_setCookie(self::KEY_COOKIE, '', time() - 1800);
        $qb->setParameter('ipaddress', self::getRealIpAddress(), Database::STRING)
           ->setParameter('uid', $uid, Database::INTEGER)
           ->setParameter('pid', $pid, Database::STRING)
           ->setParameter('data', $data, Database::STRING)
           ->setParameter('voterecords', $voterecords, Database::STRING)
           ->setParameter('pubkey', $Voter->getPubKey(), Database::STRING)
           ->setParameter('id', $Voter->getID());
        try {
            $qb->execute();
            $Voter->withUid($uid)
                  ->withPid($pid);
            if ($vote_id == 0) {
                $Voter->withId($db->conn->lastInsertId());
            }
            return $Voter;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, $e->getMessage());
            return NULL;
        }
    }


    private function saveVoteRecords(string $pid, array $aid) : array
    {
        global $_TABLES;

        $db = Database::getInstance();
        if (!empty($this->_voteRecords)) {
            $db->conn->executeStatement(
                "DELETE FROM " . DB::table('votes') . "
                WHERE vid IN (?)",
                array(array_keys($this->_voteRecords)),
                array(Database::PARAM_STR_ARRAY)
            );
        }
        $this->_voteRecords = array();
        foreach ($aid as $q=>$a) {
            $id = Token::create();
            try {
                $db->conn->insert(
                    DB::table('votes'),
                    array(
                        'vid' => $id,
                        'pid' => $pid,
                        'qid' => $q,
                        'aid' => $a,
                    ),
                    array(
                        Database::STRING,
                        Database::STRING,
                        Database::INTEGER,
                        Database::INTEGER,
                    )
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
            $this->_voteRecords[$id] = new Vote(array(
                'vid' => $id,
                'pid' => $pid,
                'qid' => $q,
                'aid' => $a,
            ) );
        }
        return $this->_voteRecords;
    }


    /**
     * Change the Election ID for all items if it was saved with a new ID.
     *
     * @param   string  $old_pid    Original Election ID
     * @param   string  $new_pid    New Election ID
     */
    public static function changePid(string $old_pid, string $new_pid) : void
    {
        try {
            Database::getInstance()->conn->update(
                DB::table('voters'),
                array('pid' => $new_pid),
                array('pid' => $old_pid),
                array(Database::STRING, Database::STRING)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Delete all the voters for a election, when the election is deleted or reset.
     *
     * @param   string  $pid    Election ID
     */
    public static function deleteElection(string $pid) : void
    {
        $db = Database::getInstance();
        try {
            $db->conn->delete(
                DB::table('voters'), array('pid' => $pid), array(Database::STRING)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }

        try {
            $db->conn->delete(
                DB::table('votes'), array('pid' => $pid), array(Database::STRING)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Change the user ID in the voter record if changed in the system.
     *
     * @param   integer $origUID    Original user ID
     * @param   integer $destUID    New user ID
     */
    public static function moveUser(int $origUID, int $destUID) : void
    {
        $db = Database::getInstance();
        try {
            $db->conn->update(
                DB::table('voters'),
                array('uid' => $destUID),
                array('uid' => $origUID),
                array(Database::INTEGER, Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
        try {
            $db->conn->delete(
                DB::table('votes'), array('pid' => $pid), array(Database::STRING)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Retrieve and decode the data for a single vote.
     * The private key will be shown to the voter after the vote is saved. It
     * consists of the record ID and private key as `id:prv_key`.
     *
     * @return  array       JSON-decoded array, NULL or false on error
     */
    public function decodeData() : array
    {
        $retval = array();

        if ($this->getId() > 0) {
            return $this->decrypt($this->getData());
        }
        return $retval;
    }


    /**
     * General decryption function.
     *
     * @param   string  $str    String to decrypt
     * @return  array       Array after json_decoding
     */
    private function decrypt(string $str) : array
    {
        $retval = COM_decrypt($str, $this->_prv_key . ':' . $this->getPubKey());
        if ($retval) {
            $retval = @json_decode($retval, true);
        }
        if (!is_array($retval)) {
            $retval = array();
        }
        return $retval;
    }


    /**
     * General encryption function, wrapper for COM_encrypt().
     *
     * @param   string  $str    String to be encrypted.
     * @return  string      Encrypted string
     */
    private function encrypt(string $str) : string
    {
        return COM_encrypt($str, $this->_prv_key . ':' . $this->getPubKey());
    }


    /**
     * Create a public and private key pair for voting data.
     * Also sets the prv_key property.
     *
     * @return  array   Hash of public and private keys
     */
    public static function createKeys()
    {
        $len = 16;      // Actual length of the token needed.
        $retval = array();

        // Make public key
        $retval['pub_key'] = Token::create();

        // Make private key and set property.
        $retval['prv_key'] = Token::create();
        return $retval;
    }


    /**
     * Change the voter record to "anonymous" when a user is deleted.
     *
     * @param   integer $uid    ID of user being deleted
     */
    public static function anonymize(int $uid) : void
    {
        try {
            Database::getInstance()->conn->update(
                DB::table('voters'),
                array('uid' => 1),
                array('uid' => $uid),
                array(Database::INTEGER, Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }

}
