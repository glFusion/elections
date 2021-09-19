<?php
/**
 * Reads a .mo file directly.
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

class FileReader extends StreamReader
{
    /** Position pointer within file.
     * @var integer */
    private $_pos;

    /** File descriptor.
     * @var resource */
    private $_fd;

    /** File length.
     * @var integer */
    private $_length;


    /**
     * Set internal properties and open the file.
     *
     * @param   string  $filename   Full path to file
     */
    public function __construct($filename)
    {
        if (file_exists($filename)) {
            $this->_length=filesize($filename);
            $this->_pos = 0;
            $this->_fd = fopen($filename,'rb');
            if (!$this->_fd) {
                $this->error = 3; // Cannot read file, probably permissions
                return false;
            }
        } else {
            $this->error = 2; // File doesn't exist
            return false;
        }
    }


    /**
     * Read a number of bytes from the file, starting at the current position.
     *
     * @param   integer $bytes  Number of bytes to read
     * @return  string      Data from the file
     */
    public function read($bytes)
    {
        if ($bytes) {
            fseek($this->_fd, $this->_pos);

            // PHP 5.1.1 does not read more than 8192 bytes in one fread()
            // the discussions at PHP Bugs suggest it's the intended behaviour
            $data = '';
            while ($bytes > 0) {
                $chunk  = fread($this->_fd, $bytes);
                $data  .= $chunk;
                $bytes -= strlen($chunk);
            }
            $this->_pos = ftell($this->_fd);

            return $data;
        } else {
            return '';
        }
    }


    /**
     * Seek to a specific position in the file.
     *
     * @param   integer $pos        Position to locate
     * @return  integer     New position value
     */
    public function seekto($pos)
    {
        fseek($this->_fd, $pos);
        $this->_pos = ftell($this->_fd);
        return $this->_pos;
    }


    /**
     * Get the current position within the file.
     *
     * @return  integer     Current position
     */
    public function currentpos()
    {
        return $this->_pos;
    }


    /**
     * Get the length of the file.
     *
     * @return  integer     Length of file
     */
    public function length()
    {
        return $this->_length;
    }


    /**
     * Close the file descriptor.
     */
    public function close()
    {
        fclose($this->_fd);
    }

}
