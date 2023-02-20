<?php
/**
 * Define a single vote data structure.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     election
 * @version     v0.3.0
 * @since       v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections\Models;


/**
 * Data structure of a vote record.
 * @package election
 */
class Vote
{
    /** Vote record ID.
     * @var string */
    public $vid = '';

    /** Election topic ID.
     * @var integer */
    public $tid = 0;

    /** Question ID.
     * @var integer */
    public $qid = '';

    /** Answer ID selected by the voter.
     * @var integer */
    public $aid = '';


    /**
     * Load the properties from the supplied array.
     *
     * @param   array   $A  Array of properties, e.g. a database record
     */
    public function __construct(?array $A = NULL)
    {
        if (is_array($A)) {
            foreach (array('vid', 'tid', 'qid', 'aid') as $key) {
                if (isset($A[$key])) {
                    $this->$key = $A[$key];
                }
            }
        }
    }

}
