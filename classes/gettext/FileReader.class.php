<?php
/*
   Copyright (c) 2003, 2005, 2006, 2009 Danilo Segan <danilo@kvota.net>.

   This file is part of PHP-gettext.

   PHP-gettext is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   PHP-gettext is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with PHP-gettext; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/
namespace Polls\gettext;

class FileReader
{
    /** Cursor position in the file.
     * @var integer */
    private $_pos = 0;

    /** File descriptor.
     * @var object */
    private $_fd = NULL;

    /** File length.
     * @var integer */
    private $_length = 0;


    /**
     * Constructor.
     *
     * @param   string  $filename   Full path to file
     */
    public function __construct($filename)
    {
        if (file_exists($filename)) {
            $this->_length = filesize($filename);
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
     * Read some bytes from the file starting at the current position.
     *
     * @param   integer $bytes      Number of bytes to read
     * @return  string      File contents
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
     * Move the cursor to a specific position in the file.
     *
     * @param   integer $pos        Desired cursor position
     * @return  integer     Actual new cursor position
     */
    public function seekto($pos)
    {
        fseek($this->_fd, $pos);
        $this->_pos = ftell($this->_fd);
        return $this->_pos;
    }


    /**
     * Get the current cursor position.
     *
     * @return  integer     Current cursor position
     */
    private function currentpos()
    {
        return $this->_pos;
    }


    /**
     * Get the file length.
     *
     * @return  integer     File length
     */
    private function length()
    {
        return $this->_length;
    }


    /**
     * Close the file descriptor.
     */
    private function close()
    {
        fclose($this->_fd);
    }

}
