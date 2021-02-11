<?php
/**
 * Class to manage locale settings.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     polls
 * @version     v0.0.2
 * @since       v0.0.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Polls\gettext;
use Polls\Config;

/* Class to hold a single domain included in $text_domains. */
class domain {
  var $l10n;
  var $path;
  var $codeset;
}


/**
 * Manage locale settings for the Polls plugin.
 * @package polls
 */
class MO
{
    /** Language domain, e.g. plugin name.
     * @var string */
    private static $domain = NULL;

    /** Variable to save the original locale if changed by init().
     * @var string */
    private static $old_locale = NULL;

    /** Supported language name=>locale mapping.
     * @var array */
    private static $lang2locale = array(
        'dutch_utf-8' => 'nl_NL',
        'finnish_utf-8' => 'fi_FI',
        'german_utf-8' => 'de_DE',
        'polish_utf-8' => 'pl_PL',
        'czech_utf-8' => 'cs_CZ',
        'english_utf-8' => 'en_US',
        'french_canada_utf-8' => 'fr_CA',
        'spanish_colombia_utf-8' => 'es_CO',
    );

    private $_emulate = 0;
    private $currentlocale = '';
    private $text_domains = array();
    private $default_domain = '';

    /**
     * Initialize a language.
     * Sets the language domain and checks the requested language
     *
     * @access  public  so that notifications may set the language as needed.
     * @param   string  $lang   Language name, default is set by lib-common.php
     */
    public static function init($lang = NULL)
    {
        global $_CONF, $LANG_LOCALE;

        // Set the language domain to separate strings from the global
        // namespace.
        self::$domain = Config::PI_NAME;

        $locale = $LANG_LOCALE;
        if (empty($lang)) {
            $lang = COM_getLanguage();
        }

        // If not using the system language, then the locale
        // hasn't been determined yet.
        if (!empty($lang) && $lang != $_CONF['language']) {
            // Save the current locale for reset()
            self::$old_locale = setlocale(LC_MESSAGES, "0");

            // Validate and use the appropriate locale code.
            // Tries to look up the locale for the language first.
            // Then uses the global locale (ignoring the requested language).
            // Defaults to 'en_US' if a supportated locale wasn't found.
            if (isset(self::$lang2locale[$lang])) {
                $locale = self::$lang2locale[$lang];
            } elseif (isset($LANG_LOCALE) && !empty($LANG_LOCALE)) {
                // Not found, try the global variable
                $locale = $LANG_LOCALE;
            } else {
                // global not set, fall back to US english
                $locale = 'en_US';
            }
        }
        // Set the locale for messages.
        // This is the only part that's needed here.
        $results = setlocale(
            LC_MESSAGES,
            $locale.'.utf8', $locale
        );
        if ($results) {
            $dom = self::_bind_textdomain_codeset(self::$domain, 'UTF-8');
            $dom = self::_bindtextdomain(self::$domain, __DIR__ . "/../../locale");
        }
    }


    public static function create($lang = NULL)
    {
        global $_CONF, $LANG_LOCALE;

        // Set the language domain to separate strings from the global
        // namespace.
        $domain = Config::PI_NAME;

        $locale = $LANG_LOCALE;
        if (empty($lang)) {
            $lang = COM_getLanguage();
        }
        // If not using the system language, then the locale
        // hasn't been determined yet.
        if (!empty($lang) && $lang != $_CONF['language']) {
            // Save the current locale for reset()
            self::$old_locale = setlocale(LC_MESSAGES, "0");

            // Validate and use the appropriate locale code.
            // Tries to look up the locale for the language first.
            // Then uses the global locale (ignoring the requested language).
            // Defaults to 'en_US' if a supportated locale wasn't found.
            if (isset(self::$lang2locale[$lang])) {
                $locale = self::$lang2locale[$lang];
            } elseif (isset($LANG_LOCALE) && !empty($LANG_LOCALE)) {
                // Not found, try the global variable
                $locale = $LANG_LOCALE;
            } else {
                // global not set, fall back to US english
                $locale = 'en_US';
            }
        }
        $obj = new self;
        $old_locale = $obj->_setlocale(LC_MESSAGES, $locale);
        $obj->_bind_textdomain_codeset($domain, 'UTF-8');
        $obj->_bindtextdomain($domain, __DIR__ . "/../../locale");
        $obj->_textdomain($domain);
        return $obj;
    }



    /**
     * Reset the locale back to the previously-defined value.
     * Called after processes that change the locale for a specific user,
     * such as system-generated notifications.
     */
    public static function reset()
    {
        if (self::$old_locale !== NULL) {
            setlocale(LC_MESSAGES, self::$old_locale);
        }
    }


    /**
     * Initialize the locale for a specific user ID.
     *
     * @uses    self::init()
     * @param   integer $uid    User ID
     */
    public static function initUser($uid=0)
    {
        global $_USER;

        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $lang = DB_getItem($_TABLES['users'], 'language', 'uid = ' . (int)$uid);
        self::init($lang);
    }


    /**
     * Get a singular or plural language string as needed.
     *
     * @param   string  $single     Singular language string
     * @param   string  $plural     Plural language string
     * @return  string      Appropriate language string
     */
    public static function dngettext($single, $plural, $number)
    {
        if (!self::$domain) {
            self::init();
        }
        return \dngettext(self::$domain, $single, $plural, $number);
    }
    public static function _n($single, $plural, $number)
    {
        return self::dngettext($single, $plural, $number);
    }


    /**
     * Get a normal, singular language string.
     *
     * @param   string  $txt        Text string
     * @return  string      Translated text
     */
    public function dgettext($txt)
    {
        /*if (!self::$domain) {
            self::init();
    }*/
        //return \dgettext(self::$domain, $txt);
        $l10n = $this->_get_reader();
        return $this->_encode($l10n->translate($txt));
    }
    public static function _($txt)
    {
        return 'dummy';
        return self::dgettext($txt);
    }
    
    
    /**
     * Sets a requested locale, if needed emulates it.
     *
     * @param   integer $category   Category to set, e.g. LC_MESSAGES
     * @param   string  $locale     Name of locale
     */
    public function _setlocale($category, $locale=0)
    {
        if ($locale === 0) { // use === to differentiate between string "0"
            if ($this->currentlocale != '') {
                return $this->currentlocale;
            } else {
                // obey LANG variable, maybe extend to support all of LC_* vars
                // even if we tried to read locale without setting it first
                return $this->_setlocale($category, $this->currentlocale);
            }
        } else {
            if (function_exists('setlocale')) {
                $ret = setlocale($category, $locale);
                if (
                    ($locale == '' && !$ret) || // failed setting it by env
                    ($locale != '' && $ret != $locale)
                ) { // failed setting it
                    // Failed setting it according to environment.
                    $this->currentlocale = self::_get_default_locale($locale);
                    $this->_emulate = 1;
                  } else {
                    $this->currentlocale = $ret;
                    $this->_emulate = 0;
                  }
            } else {
                // No function setlocale(), emulate it all.
                $this->currentlocale = self::_get_default_locale($locale);
                $this->_emulate = 1;
            }
            // Allow locale to be changed on the go for one translation domain.
            /*global $text_domains, $default_domain;
            unset($text_domains[$default_domain]->l10n);
            return $CURRENTLOCALE;*/
        }
    }
    
    
    /**
     * Returns passed in $locale, or environment variable $LANG if $locale == ''.
     *
     * @param   string  $locale Fallback locale
     */
    private function _get_default_locale($locale)
    {
        if ($locale == '') {    // emulate variable support
            return getenv('LANG');
        } else {
            return $locale;
        }
    }
    
    
    /**
     * Sets the path for a domain.
     */
    public function _bindtextdomain($domain, $path)
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
        if (!array_key_exists($domain, $this->text_domains)) {
            // Initialize an empty domain object.
            $this->text_domains[$domain] = new domain();
        }
        $this->text_domains[$domain]->path = $path;
    }
    
    
    /**
     * Specify the character encoding in which the messages from the DOMAIN message catalog will be returned.
     */
    public function _bind_textdomain_codeset($domain, $codeset)
    {
        $this->text_domains[$domain]->codeset = $codeset;
    }


    /**
     * Convert the given string to the encoding set by bind_textdomain_codeset.
     */
    private function _encode($text)
    {
        $source_encoding = mb_detect_encoding($text);
        $target_encoding = $this->_get_codeset();
        if ($source_encoding != $target_encoding) {
            return mb_convert_encoding($text, $target_encoding, $source_encoding);
        } else {
            return $text;
        }
    }
    
    
    /**
     * Sets the default domain.
     */
    private function _textdomain($domain)
    {
        $this->default_domain = $domain;
    }


    
    /**
     * Get the codeset for the given domain.
     */
    private function _get_codeset($domain=null)
    {
        global $text_domains, $default_domain, $LC_CATEGORIES;
        if (!isset($domain)) $domain = $this->default_domain;
        return (isset($text_domains[$domain]->codeset))? $text_domains[$domain]->codeset : ini_get('mbstring.internal_encoding');
    }

    
    /**
     * Utility function to get a StreamReader for the given text domain.
     */
    private function _get_reader($domain=null, $category=5, $enable_cache=true)
    {
        //global $text_domains, $default_domain, $LC_CATEGORIES;
        if (!isset($domain)) $domain = $this->default_domain;
        if (!isset($this->text_domains[$domain]->l10n)) {
            // get the current locale
            $locale = $this->_setlocale(LC_MESSAGES, 0);
            $bound_path = isset($this->text_domains[$domain]->path) ?
                $this->text_domains[$domain]->path : './';
            //$subpath = $LC_CATEGORIES[$category] ."/$domain.mo";
            $subpath = "LC_MESSAGES/$domain.mo";

            $locale_names = $this->get_list_of_locales($locale);
            $input = null;
            foreach ($locale_names as $locale) {
                $full_path = $bound_path . $locale . "/" . $subpath;
                if (file_exists($full_path)) {
                    $input = new FileReader($full_path);
                    break;
                }
            }

            if (!array_key_exists($domain, $this->text_domains)) {
              // Initialize an empty domain object.
              $this->text_domains[$domain] = new domain();
            }
            $this->text_domains[$domain]->l10n = new Reader($input, $enable_cache);
        }
        return $this->text_domains[$domain]->l10n;
    }

    
    /**
     * Return a list of locales to try for any POSIX-style locale specification.
     */
    private function get_list_of_locales($locale)
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

}


/**
 * Get a single or plural text string as needed.
 *
 * @param   string  $single     Text when $number is singular
 * @param   string  $plural     Text when $number is plural
 * @param   float   $number     Number used in the string
 * @return  string      Appropriate text string
 */
function _n($single, $plural, $number)
{
    return MO::dngettext($single, $plural, $number);
}


/**
 * Get a single text string, automatically applying the domain.
 *
 * @param   string  $txt    Text to be translated
 * @return  string      Translated string
 */
function _($txt)
{
    return MO::dgettext($txt);
}

?>
