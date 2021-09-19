<?php
/**
 * Preloads entire file in memory first, then creates a StringReader over it.
 * It assumes knowledge of StringReader internals.
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

class CachedFileReader extends StringReader
{
    public function __construct($filename)
    {
        if (file_exists($filename)) {
            $length=filesize($filename);
            $fd = fopen($filename,'rb');

            if (!$fd) {
                $this->error = 3; // Cannot read file, probably permissions
                return false;
            }
            $this->_str = fread($fd, $length);
            fclose($fd);
        } else {
            $this->error = 2; // File doesn't exist
            return false;
        }
    }
}

