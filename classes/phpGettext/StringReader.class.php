<?php
/**
 * Reads a string.
 *
 * @author      Danilo Segan <danilo at kvota dot net>
 * @author      Nico Kaiser <nico at siriux dot net>
 * @author      Steven Armstrong <sa at c-area dot ch>
 * @copyright   Copyright (c) 2005 Steven Armstrong <sa at c-area dot ch>
 * @copyright   Copyright (c) 2009 Danilo Segan <danilo at kvota dot net>
 * @package     elections
 * @version     v0.1.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @see         https://launchpad.net/php-gettext
 * @filesource
 */
namespace Elections\phpGettext;


/**
 * Use a single string as the data source.
 */
class StringReader extends StreamReader
{
    /** Pointer to the current position in the string.
     * @var integer */
    private $_pos;

    /** String being processed.
     * @var string */
    private $_str;


    /**
     * Set the local properties.
     *
     * @param   string  $str    String to process
     */
    public function __construct($str='')
    {
        $this->_str = $str;
        $this->_pos = 0;
    }


    /**
     * Read a number of bytes from the file, starting at the current position.
     *
     * @param   integer $bytes  Number of bytes to read
     * @return  string      Data from the file
     */
    public function read($bytes)
    {
        $data = substr($this->_str, $this->_pos, $bytes);
        $this->_pos += $bytes;
        if (strlen($this->_str) < $this->_pos) {
            $this->_pos = strlen($this->_str);
        }
        return $data;
    }


    /**
     * Seek to a specific position in the string.
     *
     * @param   integer $pos        Position to locate
     * @return  integer     New position value
     */
    public function function seekto($pos)
    {
        $this->_pos = $pos;
        if (strlen($this->_str) < $this->_pos) {
            $this->_pos = strlen($this->_str);
        }
        return $this->_pos;
    }


    /**
     * Get the current position within the string.
     *
     * @return  integer     Current position
     */
    public function currentpos()
    {
        return $this->_pos;
    }


    /**
     * Get the length of the string.
     *
     * @return  integer     Length of string
     */
    public function length()
    {
        return strlen($this->_str);
    }

}
