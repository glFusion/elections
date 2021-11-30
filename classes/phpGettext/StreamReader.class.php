<?php
/**
 * Reads a stream.
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
 * Simple class to wrap file streams, string streams, etc.
 * seek() is essential, and it should be byte stream.
 */
abstract class StreamReader
{
    /**
     * Read a number of bytes from the stream.
     *
     * @return  boolean     False
     */
    abstract public function read($bytes);


    /**
     * Seek to a position in the stream.
     *
     * @return  boolean     False
     */
    abstract public function seekto($position);

    // returns current position
    public function currentpos()
    {
        return false;
    }


    // returns length of entire stream (limit for seekto()s)
    public function length()
    {
        return false;
    }

    public function close()
    {
        return true;
    }

}
