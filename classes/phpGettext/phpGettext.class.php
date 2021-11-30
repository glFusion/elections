<?php
/**
 * Base class for PHP-Gettext.
 * Contains static functions to used in place of native gettext functions.
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

/*
LC_CTYPE        0
LC_NUMERIC      1
LC_TIME         2
LC_COLLATE      3
LC_MONETARY     4
LC_MESSAGES     5
LC_ALL          6
*/

// LC_MESSAGES is not available if on Windows or php-gettext is not loaded
// while the other constants are already available from session extension.
if (!defined('LC_MESSAGES')) {
  define('LC_MESSAGES',	5);
}

/**
 * Base class for PHP-Gettext emulation functions.
 * @package elections
 */
class phpGettext
{
    /** Array of domain objects, indexed by domain name.
     * @var array */
    public static $text_domains = array();

    /** Default domain name.
     * @var string */
    public static $default_domain = 'messages';

    /** Array of valid LC_CATEGORY values.
     * @var array */
    public static $LC_CATEGORIES = array(
        'LC_CTYPE', 'LC_NUMERIC', 'LC_TIME', 'LC_COLLATE', 'LC_MONETARY', 'LC_MESSAGES', 'LC_ALL'
    );

    /** Flag to force emulation. Default = "no".
     * @var integer */
     public static $EMULATEGETTEXT = 0;

    /** Current locale, set by _setlocal().
     * @var string */
    public static $CURRENTLOCALE = '';


    /**
     * Return a list of locales to try for any POSIX-style locale specification.
     */
    public static function get_list_of_locales($locale)
    {
        /* Figure out all possible locale names and start with the most
         * specific ones.  I.e. for sr_CS.UTF-8@latin, look through all of
         * sr_CS.UTF-8@latin, sr_CS@latin, sr@latin, sr_CS.UTF-8, sr_CS, sr.
         */
        $locale_names = array();
        $lang = NULL;
        $country = NULL;
        $charset = NULL;
        $modifier = NULL;
        if ($locale) {
            if (preg_match("/^(?P<lang>[a-z]{2,3})"              // language code
                   ."(?:_(?P<country>[A-Z]{2}))?"           // country code
                   ."(?:\.(?P<charset>[-A-Za-z0-9_]+))?"    // charset
                   ."(?:@(?P<modifier>[-A-Za-z0-9_]+))?$/",  // @ modifier
                   $locale, $matches)
            ) {
                if (isset($matches["lang"])) $lang = $matches["lang"];
                if (isset($matches["country"])) $country = $matches["country"];
                if (isset($matches["charset"])) $charset = $matches["charset"];
                if (isset($matches["modifier"])) $modifier = $matches["modifier"];

                if ($modifier) {
                    if ($country) {
                        if ($charset) {
                            array_push($locale_names, "${lang}_$country.$charset@$modifier");
                        }
                        array_push($locale_names, "${lang}_$country@$modifier");
                    } elseif ($charset) {
                        array_push($locale_names, "${lang}.$charset@$modifier");
                    }
                    array_push($locale_names, "$lang@$modifier");
                }
                if ($country) {
                    if ($charset) {
                        array_push($locale_names, "${lang}_$country.$charset");
                    }
                    array_push($locale_names, "${lang}_$country");
                } elseif ($charset) {
                    array_push($locale_names, "${lang}.$charset");
                }
                array_push($locale_names, $lang);
            }

            // If the locale name doesn't match POSIX style, just include it as-is.
            if (!in_array($locale, $locale_names)) {
                array_push($locale_names, $locale);
            }
        }
        return $locale_names;
    }


    /**
     * Utility function to get a StreamReader for the given text domain.a
     *
     * @param   string  $domain     Domain name, null for default_domain
     * @param   integer $category   Category, default = LC_MESSAGES
     * @param   boolean $enable_cache   Flag to enable or disable caching.
     * @return  object      Reader object
     */
    public static function _get_reader($domain=null, $category=5, $enable_cache=true)
    {
        if (!isset($domain)) {
            $domain = self::$default_domain;
        }

        if (!isset(self::$text_domains[$domain]->l10n)) {
            // get the current locale
            $locale = self::_setlocale(LC_MESSAGES, 0);
            $bound_path = isset(self::$text_domains[$domain]->path) ?
                self::$text_domains[$domain]->path : './locale/';
            $subpath = self::$LC_CATEGORIES[$category] ."/$domain.mo";

            $locale_names = self::get_list_of_locales($locale);
            $input = null;
            foreach ($locale_names as $locale) {
                $full_path = $bound_path . $locale . "/" . $subpath;
                if (file_exists($full_path)) {
                    $input = new FileReader($full_path);
                    break;
                }
            }

            if (!array_key_exists($domain, self::$text_domains)) {
                // Initialize an empty domain object.
                self::$text_domains[$domain] = new Domain;
            }
            self::$text_domains[$domain]->l10n = new Reader($input, $enable_cache);
        }
        return self::$text_domains[$domain]->l10n;
    }


    /**
     * Returns whether we are using our emulated gettext API or PHP built-in one.
     *
     * @return  boolean     Current state of the gettext emulation flag.
     */
    public static function locale_emulation()
    {
        return self::$EMULATEGETTEXT;
    }


    /**
     * Checks if the current locale is supported on this system.
     *
     * @param   string  $function   Name of function to check
     * @return  boolean     False if function does not exist, or opposite of EMULATEGETTEXT
     */
    public static function _check_locale_and_function($function=false)
    {
        if ($function and !function_exists($function)) {
            return false;
        }
        return !self::$EMULATEGETTEXT;
    }


    /**
     * Get the codeset for the given domain.
     *
     * @param   string  $domain     Domain to get, default = $default_domain
     * @return  string      Codeset for domain, default = "UTF-8"
     */
    public static function _get_codeset($domain=null)
    {
        if (!isset($domain)) {
            $domain = self::$default_domain;
        }

        if (isset(self::$text_domains[$domain]->codeset)) {
            return self::$text_domains[$domain]->codeset;
        } else {
            $codeset = ini_get('mbstring.internal_encoding');
            if (!empty($codeset)) {
                return $codeset;
            } else {
                return 'UTF-8';
            }
        }
    }


    /**
     * Convert the given string to the encoding set by bind_textdomain_codeset.
     *
     * @param   string  $text       Text to convert
     * @return  string      Encoded text
     */
    public static function _encode($text)
    {
        $target_encoding = self::_get_codeset();
        if (function_exists("mb_detect_encoding")) {
            $source_encoding = mb_detect_encoding($text);
            if ($source_encoding != $target_encoding) {
                $text = mb_convert_encoding($text, $target_encoding, $source_encoding);
            }
        }
        return $text;
    }


    /**
     * Returns passed in $locale, or environment variable $LANG if $locale == ''.
     */
    public static function _get_default_locale($locale)
    {
        if ($locale == '')  {   // emulate variable support
            return getenv('LANG');
        } else {
            return $locale;
        }
    }


    /**
     * Sets a requested locale, if needed emulates it.
     */
    public static function _setlocale($category, $locale)
    {
        if ($locale === 0) { // use === to differentiate between string "0"
            if (self::$CURRENTLOCALE != '') {
                return self::$CURRENTLOCALE;
            } else {
                // obey LANG variable, maybe extend to support all of LC_* vars
                // even if we tried to read locale without setting it first
                return self::_setlocale($category, self::$CURRENTLOCALE);
            }
        } else {
            if (function_exists('setlocale')) {
                $ret = setlocale($category, $locale);
                if (
                    ($locale == '' and !$ret) ||    // failed setting it by env
                    ($locale != '' and $ret != $locale) // failed setting it
                ) {
                    // Failed setting it according to environment.
                    self::$CURRENTLOCALE = _get_default_locale($locale);
                    self::$EMULATEGETTEXT = 1;
                } else {
                    self::$CURRENTLOCALE = $ret;
                    self::$EMULATEGETTEXT = 0;
                }
            } else {
                // No function setlocale(), emulate it all.
                self::$CURRENTLOCALE = _get_default_locale($locale);
                self::$EMULATEGETTEXT = 1;
            }

            // Allow locale to be changed on the go for one translation domain.
            if (array_key_exists(self::$default_domain, self::$text_domains)) {
                unset(self::$text_domains[self::$default_domain]->l10n);
            }
            return self::$CURRENTLOCALE;
        }
    }

    /**
     * Sets the path for a domain.
     */
    public static function _bindtextdomain($domain, $path)
    {
        // ensure $path ends with a slash ('/' should work for both, but lets still play nice)
        if (substr(php_uname(), 0, 7) == "Windows") {
            if ($path[strlen($path)-1] != '\\' and $path[strlen($path)-1] != '/') {
                $path .= '\\';
                }
        } else {
            if ($path[strlen($path)-1] != '/') {
                $path .= '/';
            }
        }
        if (!array_key_exists($domain, self::$text_domains)) {
            // Initialize an empty domain object.
            self::$text_domains[$domain] = new Domain;
        }
        self::$text_domains[$domain]->path = $path;
    }


    /**
     * Specify the character encoding in which the messages from the DOMAIN message catalog will be returned.
     */
    public static function _bind_textdomain_codeset($domain, $codeset)
    {
        self::$text_domains[$domain]->codeset = $codeset;
    }


    /**
     * Sets the default domain.
     */
    public static function _textdomain($domain)
    {
        self::$default_domain = $domain;
    }

    /**
     * Lookup a message in the current domain.
     */
    public static function _gettext($msgid)
    {
        $l10n = self::_get_reader();
        return self::_encode($l10n->translate($msgid));
    }


    /**
     * Alias for gettext.
     */
    public static function __($msgid)
    {
        return self::_gettext($msgid);
    }

    /**
     * Plural version of gettext.
     */
    public static function _ngettext($singular, $plural, $number)
    {
        $l10n = self::_get_reader();
        return self::_encode($l10n->ngettext($singular, $plural, $number));
    }


    /**
     * Override the current domain.
     */
    public static function _dgettext($domain, $msgid)
    {
        $l10n = self::_get_reader($domain);
        return self::_encode($l10n->translate($msgid));
    }


    /**
     * Plural version of dgettext.
     */
    public static function _dngettext($domain, $singular, $plural, $number)
    {
        $l10n = self::_get_reader($domain);
        return self::_encode($l10n->ngettext($singular, $plural, $number));
    }


    /**
     * Overrides the domain and category for a single lookup.
     */
    public static function _dcgettext($domain, $msgid, $category)
    {
        $l10n = self::_get_reader($domain, $category);
        return self::_encode($l10n->translate($msgid));
    }


    /**
     * Plural version of dcgettext.
     */
    public static function _dcngettext($domain, $singular, $plural, $number, $category)
    {
        $l10n = self::_get_reader($domain, $category);
        return self::_encode($l10n->ngettext($singular, $plural, $number));
    }


    /**
     * Context version of gettext.
     */
    public static function _pgettext($context, $msgid)
    {
        $l10n = self::_get_reader();
        return self::_encode($l10n->pgettext($context, $msgid));
    }


    /**
     * Override the current domain in a context gettext call.
     */
    public static function _dpgettext($domain, $context, $msgid)
    {
        $l10n = self::_get_reader($domain);
        return self::_encode($l10n->pgettext($context, $msgid));
    }


    /**
     * Overrides the domain and category for a single context-based lookup.
     */
    public static function _dcpgettext($domain, $context, $msgid, $category)
    {
        $l10n = self::_get_reader($domain, $category);
        return self::_encode($l10n->pgettext($context, $msgid));
    }

    /**
     * Context version of ngettext.
     */
    public static function _npgettext($context, $singular, $plural)
    {
        $l10n = self::_get_reader();
        return self::_encode($l10n->npgettext($context, $singular, $plural));
    }


    /**
     * Override the current domain in a context ngettext call.
     */
    public static function _dnpgettext($domain, $context, $singular, $plural)
    {
        $l10n = self::_get_reader($domain);
        return self::_encode($l10n->npgettext($context, $singular, $plural));
    }

    /**
     * Overrides the domain and category for a plural context-based lookup.
     */
    public static function _dcnpgettext($domain, $context, $singular, $plural, $category)
    {
        $l10n = self::_get_reader($domain, $category);
        return self::_encode($l10n->npgettext($context, $singular, $plural));
    }

}


// Wrappers to use if the standard gettext functions are available,
// but the current locale is not supported by the system.
// Use the standard impl if the current locale is supported, use the
// custom impl otherwise.

function T_setlocale($category, $locale)
{
    return phpGettext::_setlocale($category, $locale);
}

function T_bindtextdomain($domain, $path)
{
    if (phpGettext::_check_locale_and_function()) {
        return bindtextdomain($domain, $path);
    } else {
        return phpGettext::_bindtextdomain($domain, $path);
    }
}

function T_bind_textdomain_codeset($domain, $codeset)
{
    // bind_textdomain_codeset is available only in PHP 4.2.0+
    if (phpGettext::_check_locale_and_function('bind_textdomain_codeset')) {
        return bind_textdomain_codeset($domain, $codeset);
    } else {
        return phpGettext::_bind_textdomain_codeset($domain, $codeset);
    }
}

function T_textdomain($domain)
{
    if (phpGettext::_check_locale_and_function()) {
        return textdomain($domain);
    } else {
        return phpGettext::_textdomain($domain);
    }
}

function T_gettext($msgid) {
    if (phpGettext::_check_locale_and_function()) {
        return gettext($msgid);
    } else {
        return phpGettext::_gettext($msgid);
    }
}
function T_($msgid) {
    if (phpGettext::_check_locale_and_function()) {
        return phpphpGettext::_($msgid);
    } else {
        return phpphpGettext::__($msgid);
    }
}

function T_ngettext($singular, $plural, $number)
{
    if (phpGettext::_check_locale_and_function()) {
        return ngettext($singular, $plural, $number);
    } else {
        return phpphpGettext::_ngettext($singular, $plural, $number);
    }
}

function T_dgettext($domain, $msgid)
{
    if (phpGettext::_check_locale_and_function()) {
        return dgettext($domain, $msgid);
    } else {
        return phpGettext::_dgettext($domain, $msgid);
    }
}

function T_dngettext($domain, $singular, $plural, $number)
{
    if (phpGettext::_check_locale_and_function()) {
        return dngettext($domain, $singular, $plural, $number);
    } else {
        return phpGettext::_dngettext($domain, $singular, $plural, $number);
    }
}

function T_dcgettext($domain, $msgid, $category)
{
    if (phpGettext::_check_locale_and_function()) {
        return dcgettext($domain, $msgid, $category);
    } else {
        return phpGettext::_dcgettext($domain, $msgid, $category);
    }
}

function T_dcngettext($domain, $singular, $plural, $number, $category)
{
    if (phpGettext::_check_locale_and_function()) {
        return dcngettext($domain, $singular, $plural, $number, $category);
    } else {
        return phpGettext::_dcngettext($domain, $singular, $plural, $number, $category);
    }
}

function T_pgettext($context, $msgid)
{
    if (phpGettext::_check_locale_and_function('pgettext')) {
      return pgettext($context, $msgid);
    } else {
        return phpGettext::_pgettext($context, $msgid);
    }
}

function T_dpgettext($domain, $context, $msgid)
{
    if (phpGettext::_check_locale_and_function('dpgettext')) {
        return dpgettext($domain, $context, $msgid);
    } else {
        return phpGettext::_dpgettext($domain, $context, $msgid);
    }
}

function T_dcpgettext($domain, $context, $msgid, $category)
{
    if (phpGettext::_check_locale_and_function('dcpgettext')) {
        return dcpgettext($domain, $context, $msgid, $category);
    } else {
        return phpGettext::_dcpgettext($domain, $context, $msgid, $category);
    }
}

function T_npgettext($context, $singular, $plural, $number)
{
    if (phpGettext::_check_locale_and_function('npgettext')) {
        return npgettext($context, $singular, $plural, $number);
    } else {
        return phpGettext::_npgettext($context, $singular, $plural, $number);
    }
}

function T_dnpgettext($domain, $context, $singular, $plural, $number)
{
    if (phpGettext::_check_locale_and_function('dnpgettext')) {
        return dnpgettext($domain, $context, $singular, $plural, $number);
    } else {
        return phpGettext::_dnpgettext($domain, $context, $singular, $plural, $number);
    }
}

function T_dcnpgettext(
    $domain, $context, $singular, $plural, $number, $category
) {
    if (phpGettext::_check_locale_and_function('dcnpgettext')) {
        return dcnpgettext(
            $domain, $context, $singular, $plural, $number, $category
        );
    } else {
        return phpGettext::_dcnpgettext(
            $domain, $context, $singular, $plural, $number, $category
        );
    }
}

// Wrappers used as a drop in replacement for the standard gettext functions
if (!function_exists('gettext')) {
    function bindtextdomain($domain, $path) {
        return phpGettext::_bindtextdomain($domain, $path);
    }
    function bind_textdomain_codeset($domain, $codeset) {
        return phpGettext::_bind_textdomain_codeset($domain, $codeset);
    }
    function textdomain($domain) {
        return phpGettext::_textdomain($domain);
    }
    function gettext($msgid) {
        return phpGettext::_gettext($msgid);
    }
    function _($msgid) {
        return phpGettext::__($msgid);
    }
    function ngettext($singular, $plural, $number) {
        return phpGettext::_ngettext($singular, $plural, $number);
    }
    function dgettext($domain, $msgid) {
        return phpGettext::_dgettext($domain, $msgid);
    }
    function dngettext($domain, $singular, $plural, $number) {
        return phpGettext::_dngettext($domain, $singular, $plural, $number);
    }
    function dcgettext($domain, $msgid, $category) {
        return phpGettext::_dcgettext($domain, $msgid, $category);
    }
    function dcngettext($domain, $singular, $plural, $number, $category) {
        return phpGettext::_dcngettext($domain, $singular, $plural, $number, $category);
    }
    function pgettext($context, $msgid) {
        return phpGettext::_pgettext($context, $msgid);
    }
    function npgettext($context, $singular, $plural, $number) {
        return phpGettext::_npgettext($context, $singular, $plural, $number);
    }
    function dpgettext($domain, $context, $msgid) {
        return phpGettext::_dpgettext($domain, $context, $msgid);
    }
    function dnpgettext($domain, $context, $singular, $plural, $number) {
        return phpGettext::_dnpgettext($domain, $context, $singular, $plural, $number);
    }
    function dcpgettext($domain, $context, $msgid, $category) {
        return phpGettext::_dcpgettext($domain, $context, $msgid, $category);
    }
    function dcnpgettext($domain, $context, $singular, $plural,
                         $number, $category) {
      return phpGettext::_dcnpgettext($domain, $context, $singular, $plural,
                          $number, $category);
    }
}
