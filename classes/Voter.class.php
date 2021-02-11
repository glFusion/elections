<?php
/**
 * Class to describe voters.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2021 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v3.0.0
 * @since       v3.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections;
use Elections\Models\Groups;


/**
 * Class to manage election voters.
 * @package elections
 */
class Voter
{
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


    public function __construct($A=array())
    {
        if (is_array($A) && !empty($A)) {
            $this->withId($A['id'])
                 ->withPid($A['pid'])
                 ->withIpAddress($A['ipaddress'])
                 ->withUid($A['uid'])
                 ->withDate($A['date'])
                 ->withData($A['votedata'])
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

    public function withData($data)
    {
        $this->data = $data;
        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function withPubKey($key)
    {
        $this->pub_key = $key;
        return $this;
    }

    public function getPubKey()
    {
        return $this->pub_key;
    }


    public function withPrvKey($key)
    {
        $this->_prv_key = $key;
        return $this;
    }

    public function getPrvKey()
    {
        return $this->_prv_key;
    }


    public static function getInstance($vote_id)
    {
        $prv_key = NULL;
        if (is_string($vote_id) && strpos($vote_id, ':') !== false) {
            // An existing vote is being retrieved by the id:private_key string
            list($vote_id, $prv_key) = explode(':', $vote_id);
        }
        $vote_id = (int)$vote_id;
        $sql = "SELECT * FROM " . DB::table('voters') .
            " WHERE id = $vote_id";
        $res = DB_query($sql);
        if ($res) {
            $A = DB_fetchArray($res, false);
            $Voter = new self($A);
            if ($prv_key !== NULL) {
                $Voter->withPrvKey($prv_key);
            }
        } else {
            $Voter = new self;
        }
        return $Voter;
    }


    /**
     * Check if the user has already voted.
     * For anonymous, checks the IP address and the election cookie.
     *
     * @param   string  $pid    Election ID
     * @param   integer $voting_grp Group with access to vote
     * @return  boolean     True if the user has voted, False if not
     */
    public static function hasVoted($pid, $voting_grp=2)
    {
        global $_USER;

        // If logged in and the user ID is in the voters table,
        // we can trust that this user has voted.
        if (!COM_isAnonUser()) {
            if (DB_count(
                DB::table('voters'),
                 array('uid', 'pid'),
                 array((int)$_USER['uid'], DB_escapeString($pid)) ) > 0
            ) {
                return true;
            }
        }
        if ($voting_grp != Groups::ALL_USERS) {
            // If a login is required, return false now since there's no need
            // to check for anonymous votes.
            return false;
        }

        // For Anonymous we only have the cookie and IP address.
        if (isset($_COOKIE[Config::PI_NAME . '-' . $pid])) {
            return true;
        }

        $ip = DB_escapeString(self::getRealIpAddress());
        if (
            $ip != '' &&
            DB_count(
                DB::table('voters'),
                array('ipaddress', 'pid'),
                array($ip, DB_escapeString($pid))
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
     */
    public static function create($pid, $aid)
    {
        global $_USER;

        if ( COM_isAnonUser() ) {
            $userid = 1;
        } else {
            $userid = (int)$_USER['uid'];
        }

        $Voter = new self;
        $keys = self::createKeys();
        $data = COM_encrypt(json_encode($aid), $keys['prv_key'] . ':' . $keys['pub_key']);
        $data = DB_escapeString($data);
        $pub_key = DB_escapeString($keys['pub_key']);

        // This always does an insert so no need to provide key_field and key_value args
        $sql = "INSERT IGNORE INTO " . DB::table('voters') . " SET
            ipaddress = '" . DB_escapeString(Voter::getRealIpAddress()) . "',
            uid = '$userid',
            date = UNIX_TIMESTAMP(),
            pid = '" . DB_escapeString($pid) . "',
            votedata = '$data',
            pub_key = '$pub_key'";
        DB_query($sql);
        if (!DB_error()) {
            // Set the voter information. Save the private key in a class property
            // to be shown to the voter later.
            $Voter->withUid($userid)
                  ->withPrvKey($keys['prv_key'])
                  ->withPubKey($keys['pub_key'])
                  ->withPid($pid)
                  ->withId(DB_insertId());
            return $Voter;
        } else {
            return false;
        }
    }


    /**
     * Change the Election ID for all items if it was saved with a new ID.
     *
     * @param   string  $old_pid    Original Election ID
     * @param   string  $new_pid    New Election ID
     */
    public static function changePid($old_pid, $new_pid)
    {
        DB_query("UPDATE " . DB::table('voters') . "
            SET pid = '" . DB_escapeString($new_pid) . "'
            WHERE pid = '" . DB_escapeString($old_pid) . "'"
        );
    }


    /**
     * Delete all the voters for a election, when the election is deleted or reset.
     *
     * @param   string  $pid    Election ID
     */
    public static function deleteElection($pid)
    {
        DB_delete(DB::table('voters'), 'pid', $pid);
    }


    /**
     * Change the user ID in the voter record if changed in the system.
     *
     * @param   integer $origUID    Original user ID
     * @param   integer $destUID    New user ID
     */
    public static function moveUser($origUID, $destUID)
    {
        DB_query("UPDATE " . DB::table('voters') . "
            SET uid = '" . (int)$destUID . "'
            WHERE uid = '" . (int)$origUID . "'"
        );
    }


    /**
     * Retrieve and decode the data for a single vote.
     * The private key will be shown to the voter after the vote is saved. It
     * consists of the record ID and private key as `id:prv_key`.
     *
     * @return  array       JSON-decoded array, NULL or false on error
     */
    public function decodeData()
    {
        $retval = array();

        if ($this->getId() > 0) {
            // First decrypt the JSON string, then decode it into an array.
            $retval = COM_decrypt($this->getData(), $this->_prv_key . ':' . $this->getPubKey());
            if ($retval) {
                $retval = json_decode($retval, true);
            }
        }
        return $retval;
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
        $bytes = random_bytes(ceil($len / 2));
        $retval['pub_key'] = substr(bin2hex($bytes), 0, $len);

        // Make private key and set property.
        $bytes = random_bytes(ceil($len / 2));
        $retval['prv_key'] = substr(bin2hex($bytes), 0, $len);
        return $retval;
    }

}
