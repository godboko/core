<?php
/**
 * Multi Language System
 *
 * @package core
 * @subpackage multilanguage
 * @category Xaraya Web Applications Framework
 * @version 2.4.0
 * @copyright see the html/credits.html file in this release
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @link http://www.xaraya.info
 *
 * @author Marco Canini <marco@xaraya.com>
 * @author Roger Raymond <roger@asphyxia.com>
 * @author Marcel van der Boom <mrb@hsdev.com>
 * @author Volodymyr Metenchuk <voll@xaraya.com>
 * @author Marc Lutolf
 * @todo Dynamic Translations
 * @todo Timezone and DST support (default offset is supported now)
 * @todo Write standard core translations
 * @todo Complete changes as described in version 0.9 of MLS RFC
 * @todo Implements the request(ed) locale APIs for backend interactions
 * @todo See how utf-8 works for xml backend
 */

/**
 * Multilange package defines
 */
define('XARMLS_SINGLE_LANGUAGE_MODE', 'SINGLE');
define('XARMLS_BOXED_MULTI_LANGUAGE_MODE', 'BOXED');
define('XARMLS_UNBOXED_MULTI_LANGUAGE_MODE', 'UNBOXED');


define('XARMLS_DNTYPE_CORE', 1);
define('XARMLS_DNTYPE_THEME', 2);
define('XARMLS_DNTYPE_MODULE', 3);
define('XARMLS_DNTYPE_PROPERTY', 4);
define('XARMLS_DNTYPE_BLOCK', 5);

sys::import('xaraya.locales');
sys::import('xaraya.transforms.xarCharset');
sys::import('xaraya.mlsbackends.reference');

// Legacy calls

function xarMLSGetMode()
{   
    return xarMLS::getMode(); 
}
function xarMLSGetSiteLocale()
{   
    return xarMLS::getSiteLocale(); 
}
function xarMLSListSiteLocales()
{   
    return xarMLS::listSiteLocales(); 
}
function xarMLSGetCurrentLocale()
{   
    return xarMLS::getCurrentLocale(); 
}
function xarMLSGetCharsetFromLocale($locale)
{   
    return xarMLS::getCharsetFromLocale($locale); 
}
function xarML($rawstring/*, ...*/)
{
    return call_user_func_array(array('xarMLS', 'translate'), func_get_args());
}
function xarMLByKey($key/*, ...*/)
{   
    return call_user_func_array(array('xarMLS', 'translateByKey'), func_get_args());
}
function xarLocaleGetInfo($locale)
{   
    return xarMLS::localeGetInfo($locale); 
}
function xarLocaleGetString($localeInfo)
{   
    return xarMLS::localeGetString($localeInfo); 
}
function xarLocaleGetList($filter=array())
{   
    return xarMLS::localeGetList($filter); 
}
function xarMLS_userTime($time=null,$flag=1)
{   
    return xarMLS::userTime($time,$flag); 
}
function xarMLS_userOffset($timestamp = null)
{   
    return xarMLS::userOffset($timestamp); 
}
function xarMLS_setCurrentLocale($locale)
{   
    return xarMLS::setCurrentLocale($locale); 
}
function xarMLS_loadTranslations($dnType, $dnName, $ctxType, $ctxName)
{   
    return xarMLS::_loadTranslations($dnType, $dnName, $ctxType, $ctxName); 
}
function xarMLSLoadTranslations($path)
{   
    return xarMLS::loadTranslations($path); 
}
function xarMLS_convertFromInput($var, $method)
{   
    return xarMLS::convertFromInput($var, $method); 
}
function xarMLS__parseLocaleString($locale)
{   
    return xarMLS::parseLocaleString($locale); 
}
function xarMLS__mkdirr($path)
{   
    return xarMLS::mkdirr($path); 
}
function xarMLS__iswritable($directory=NULL)
{   
    return xarMLS::iswritable($directory=NULL); 
}

class xarMLS extends Object
{
    /**
     * Initializes the Multi Language System
     *
     * @throws Exception
     * @return boolean true
     */
    static public function init(&$args)
    {
        switch ($args['MLSMode']) {
        case XARMLS_SINGLE_LANGUAGE_MODE:
        case XARMLS_BOXED_MULTI_LANGUAGE_MODE:
            $GLOBALS['xarMLS_mode'] = $args['MLSMode'];
            break;
        case XARMLS_UNBOXED_MULTI_LANGUAGE_MODE:
            $GLOBALS['xarMLS_mode'] = $args['MLSMode'];
            if (!function_exists('mb_http_input')) {
                // mbstring required
                throw new Exception('xarMLS::init: Mbstring PHP extension is required for UNBOXED MULTI language mode.');
            }
            break;
        default:
            $GLOBALS['xarMLS_mode'] = 'BOXED';
            //throw new Exception('xarMLS::init: Unknown MLS mode: '.$args['MLSMode']);
        }
        $GLOBALS['xarMLS_backendName'] = $args['translationsBackend'];
    
        // USERLOCALE FIXME Delete after new backend testing
        $GLOBALS['xarMLS_localeDataLoader'] = new xarMLS__LocaleDataLoader();
        $GLOBALS['xarMLS_localeDataCache'] = array();
    
        $GLOBALS['xarMLS_currentLocale'] = ''; // <-- FIXME: this causes problems
    
        $GLOBALS['xarMLS_defaultLocale'] = $args['defaultLocale'];
        $GLOBALS['xarMLS_allowedLocales'] = $args['allowedLocales'];
    
        $GLOBALS['xarMLS_newEncoding'] = new xarCharset;
    
        $GLOBALS['xarMLS_defaultTimeZone'] = !empty($args['defaultTimeZone']) ?
                                             $args['defaultTimeZone'] : @date_default_timezone_get();
        $GLOBALS['xarMLS_defaultTimeOffset'] = isset($args['defaultTimeOffset']) ?
                                               $args['defaultTimeOffset'] : 0;
    
        // Set the timezone
        date_default_timezone_set ($GLOBALS['xarMLS_defaultTimeZone']);
    
        // Register MLS events
        // These should be done before the xarMLS_setCurrentLocale function
        // These are now registered during base module init
        // @CHECKME: <chris> grep -R xarEvents::trigger . finds no results
        // It appears these events are never raised ?
        // In addition, these seem more like exceptions than 'events' ?
        //xarEvents::register('MLSMissingTranslationString');
        //xarEvents::register('MLSMissingTranslationKey');
        //xarEvents::register('MLSMissingTranslationDomain');
    
        // FIXME: this was previously conditional on User subsystem initialisation,
        // but in the 2.x flow we need it earlier apparently, so made this unconditional
        // *AND* commented out the assertion on running this once per request lower
        // in this file. We need to investigate this better after the MLS refactoring
        self::setCurrentLocale($args['defaultLocale']);
        return true;
    }

    /**
     * Gets the current MLS mode
     *
     * @author Marco Canini <marco@xaraya.com>
     * @return integer MLS Mode
     */
    static public function getMode()
    {
        return isset($GLOBALS['xarMLS_mode']) ? $GLOBALS['xarMLS_mode'] : 'BOXED';
    }

    /**
     * Returns the site locale if running in SINGLE mode,
     * returns the site default locale if running in BOXED or UNBOXED mode
     *
     * @author Marco Canini <marco@xaraya.com>
     * @return string the site locale
     */
    static public function getSiteLocale() { return $GLOBALS['xarMLS_defaultLocale']; }

    /**
     * Returns an array of locales available in the site
     *
     * @author Marco Canini <marco@xaraya.com>
     * @return array of locales
     */
    static public function listSiteLocales()
    {
        $mode = self::getMode();
        if ($mode == XARMLS_SINGLE_LANGUAGE_MODE) {
            return array($GLOBALS['xarMLS_defaultLocale']);
        } else {
            return $GLOBALS['xarMLS_allowedLocales'];
        }
    }

    /**
     * Gets the current locale
     *
     * @author Marco Canini <marco@xaraya.com>
     * @return string current locale
     */
    static public function getCurrentLocale() { return $GLOBALS['xarMLS_currentLocale']; }

    /**
     * Gets the charset component from a locale
     *
     * @author Marco Canini <marco@xaraya.com>
     * @return string the charset name
     * @throws BAD_PARAM
     */
    static public function getCharsetFromLocale($locale)
    {
        if (!$parsedLocale = self::parseLocaleString($locale)) return; // throw back
        return $parsedLocale['charset'];
    }

    // I18N API
    
    /**
     * Translates a string
     *
     * @author Marco Canini <marco@xaraya.com>
     * @return string the translated string, or the original string if no translation is available
     */
    static public function translate($rawstring/*, ...*/)
    {
        // if an empty string is passed in, just return an empty string. it's
        // the most sensible thing to do
        $string = trim($rawstring);
        if($string == '') return $rawstring;
        
        $start = strpos($rawstring, $string);
        $prefix = substr($rawstring,0,$start);
        $suffix = substr($rawstring,$start+strlen($string));
    
        // Make sure string is sane
        // - hex 0D -> ''
        // - space around newline -> ' '
        // - multiple newlines -> 1 newline
    //    $string = preg_replace(array('[\x0d]','/[\t ]+/','/\s*\n\s*/'), array('',' ',"\n"),$string);
    
        if (isset($GLOBALS['xarMLS_backend'])) {
            $trans = $GLOBALS['xarMLS_backend']->translate($string,1);
        } else {
            // This happen in rare cases when xarML is called before self::init has been called
            $trans = $string;
        }
    
        if (empty($trans)) {
            // FIXME: postpone
            //xarEvt_fire('MLSMissingTranslationString', $string);
            $trans = $string;
        }
        if (func_num_args() > 1) {
            $args = func_get_args();
            if (is_array($args[1])) $args = $args[1]; // Only the second argument is considered if it's an array
            else array_shift($args); // Drop $string argument
            $trans = self::bindVariables($trans, $args);
        }
    
        return $prefix . $trans . $suffix;
    }

    /**
     * Return the translation associated to passed key
     *
     * @author Marco Canini <marco@xaraya.com>
     * @throws BadParameterException
     * @return string the translation string, or the key if no translation is available
     */
    static public function translateByKey($key/*, ...*/)
    {
        // Key must have a value and not contain spaces
        if(empty($key) || strpos($key," ")) throw new BadParameterException('key');
    
        if (isset($GLOBALS['xarMLS_backend'])) {
            $trans = $GLOBALS['xarMLS_backend']->translateByKey($key);
        } else {
            // This happen in rare cases when xarMLByKey is called before self::init has been called
            $trans = $key;
        }
        if (empty($trans)) {
            // FIXME: postpone
            //xarEvt_fire('MLSMissingTranslationKey', $key);
            $trans = $key;
        }
        if (func_num_args() > 1) {
            $args = func_get_args();
            if (is_array($args[1])) $args = $args[1]; // Only the second argument is considered if it's an array
            else array_shift($args); // Unset $string argument
            $trans = self::bindVariables($trans, $args);
        }
    
        return $trans;
    }

    // L10N API (Localisation)
    
    /**
     * Gets the locale info for the specified locale string.
     * Info is an array composed by the 'lang', 'country', 'specializer' and 'charset' items.
     *
     * @author Marco Canini <marco@xaraya.com>
     * @return array locale info
     */
    static public function localeGetInfo($locale) { return self::parseLocaleString($locale); }

    /**
     * Gets the locale string for the specified locale info.
     * Info is an array composed by the 'lang', 'country', 'specializer' and 'charset' items.
     *
     * @author Marco Canini <marco@xaraya.com>
     * @throws BadParameterException
     * @return string locale string
     */
    static public function localeGetString($localeInfo)
    {
        if (!isset($localeInfo['lang']) ||
            !isset($localeInfo['country']) ||
            !isset($localeInfo['specializer']) ||
            !isset($localeInfo['charset'])) {
            throw new BadParameterException('localeInfo');
        }
        if (strlen($localeInfo['lang']) != 2) throw new BadParameterException('localeInfo');
    
        $locale = strtolower($localeInfo['lang']);
        if (!empty($localeInfo['country'])) {
            if (strlen($localeInfo['country']) != 2) throw new BadParameterException('localeInfo');
    
            $locale .= '_'.strtoupper($localeInfo['country']);
        }
        if (!empty($localeInfo['charset'])) {
            $locale .= '.'.$localeInfo['charset'];
        } else {
            $locale .= '.utf-8';
        }
        if (!empty($localeInfo['specializer'])) {
            $locale .= '@'.$localeInfo['specializer'];
        }
        return $locale;
    }

    /**
     * Gets a list of locale string which met the specified filter criteria.
     * Filter criteria are set as item of $filter parameter, they can be one or more of the following:
     * lang, country, specializer, charset.
     *
     * @author Marco Canini <marco@xaraya.com>
     * @return array locale list
     */
    static public function localeGetList($filter=array())
    {
        $list = array();
        $locales = self::listSiteLocales();
        foreach ($locales as $locale) {
            $l = self::parseLocaleString($locale);
            if (isset($filter['lang']) && $filter['lang'] != $l['lang']) continue;
            if (isset($filter['country']) && $filter['country'] != $l['country']) continue;
            if (isset($filter['specializer']) && $filter['specializer'] != $l['specializer']) continue;
            if (isset($filter['charset']) && $filter['charset'] != $l['charset']) continue;
            $list[] = $locale;
        }
        return $list;
    }

    /**
     *  Returns a valid timestamp for the current user.  It will
     *  make adjustments for timezone and should be used in gmstrftime
     *  or gmdate functions only.
     *
     *  @author Roger Raymond <roger@asphyxia.com>
     *  @return int unix timestamp.
     */
    static public function userTime($time=null,$flag=1)
    {
        // get the current UTC time
        if (!isset($time)) {
            $time = time();
        }
        if ($flag) $time += self::userOffset($time) * 3600;
        // return the corrected timestamp
        return $time;
    }

    /**
     *  Returns the user's current tz offset (+ daylight saving) in hours
     *
     *  @author Roger Raymond <roger@asphyxia.com>
     *  @param int $timestamp optional unix timestamp that we want to get the offset for
     *  @return float tz offset + possible daylight saving adjustment
     */
    static public function userOffset($timestamp = null)
    {
        sys::import('xaraya.structures.datetime');
        $datetime = new XarDateTime();
        $datetime->setTimeStamp($timestamp);
        if (xarUserIsLoggedIn()) {
            $usertz = xarModItemVars::get('roles','usertimezone',xarSession::getVar('role_id'));
        } else {
            $usertz = xarConfigVars::get(null, 'Site.Core.TimeZone');
        }
        $useroffset = $datetime->getTZOffset($usertz);
    
        return $useroffset/3600;
    }

    /**
     * Sets current locale
     *
     * @author Marco Canini <marco@xaraya.com>
     * @param locale site locale
     */
    static public function setCurrentLocale($locale)
    {
        static $called = 0;
    
        // FIXME: during initialisation, the current locale was set, and it gets called
        // again during user subsystem initialisation, we have to provide better defaults
        // if we really want this to run only once.
    
        //assert('$called == 0; // Can only be called once during a page request');
        $called++;
    
        $mode = self::getMode();
        switch ($mode) {
        case XARMLS_SINGLE_LANGUAGE_MODE:
                $locale  = self::getSiteLocale();
                break;
        case XARMLS_UNBOXED_MULTI_LANGUAGE_MODE:
        case XARMLS_BOXED_MULTI_LANGUAGE_MODE:
            // check for locale availability
            $siteLocales = self::listSiteLocales();
            if (!in_array($locale, $siteLocales)) {
                // Locale not available, use the default
                $locale = self::getSiteLocale();
                xarLog::message("Falling back to default locale: $locale");
            }
        }
        // Set current locale
        $GLOBALS['xarMLS_currentLocale'] = $locale;
    
        $curCharset = self::getCharsetFromLocale($locale);
        if ($mode == XARMLS_UNBOXED_MULTI_LANGUAGE_MODE) {
            assert('$curCharset == "utf-8"; // Resetting MLS Mode to BOXED');
            // To be able to continue, we set the mode to BOXED
            if ($curCharset != "utf-8") {
                xarLog::message("Resetting MLS mode to BOXED");
                xarConfigVars::set(null, 'Site.MLS.MLSMode','BOXED');
            } else {
                if (!xarFuncIsDisabled('ini_set')) ini_set('mbstring.func_overload', 7);
                mb_internal_encoding($curCharset);
            }
        }
    
        //if ($mode == XARMLS_BOXED_MULTI_LANGUAGE_MODE) {
        //if (substr($curCharset, 0, 9) != 'iso-8859-' &&
        //$curCharset != 'windows-1251') {
        // Do not use mbstring for single byte charsets
    
        //}
        //}
    
        $alternatives = self::getLocaleAlternatives($locale);
        switch ($GLOBALS['xarMLS_backendName']) {
        case 'xml':
            sys::import('xaraya.mlsbackends.xml');
            $GLOBALS['xarMLS_backend'] = new xarMLS__XMLTranslationsBackend($alternatives);
            break;
        case 'php':
            sys::import('xaraya.mlsbackends.php');
            $GLOBALS['xarMLS_backend'] = new xarMLS__PHPTranslationsBackend($alternatives);
            break;
        case 'xml2php':
            sys::import('xaraya.mlsbackends.xml2php');
            $GLOBALS['xarMLS_backend'] = new xarMLS__XML2PHPTranslationsBackend($alternatives);
            break;
        }

        // Load core translations
        self::_loadTranslations(XARMLS_DNTYPE_CORE, 'xaraya', 'core:', 'core');
        //self::loadLocaleData($locale);
    }

    /**
     * Loads translations for the specified context
     *
     * @author Marco Canini <marco@xaraya.com>
     * @return boolean
     */
    static public function _loadTranslations($dnType, $dnName, $ctxType, $ctxName)
    {
        static $loadedCommons = array();
        static $loadedTranslations = array();
    
        if (!isset($GLOBALS['xarMLS_backend'])) {
            xarLog::message("xarMLS: No translation backend was selected for ". "$dnType.$dnName.$ctxType.$ctxName");
            return false;
        }
        if (empty($GLOBALS['xarMLS_currentLocale'])) {
            xarLog::message("xarMLS: No current locale was selected");
            return false;
        }
    
        // only load each translation once
        if (isset($loadedTranslations["$dnType.$dnName.$ctxType.$ctxName"])) {
            return $loadedTranslations["$dnType.$dnName.$ctxType.$ctxName"];
        }
    
        if ($GLOBALS['xarMLS_backend']->bindDomain($dnType, $dnName)) {
            if ($dnType == XARMLS_DNTYPE_THEME) {
                // Load common translations
                if (!isset($loadedCommons[$dnName.'theme'])) {
                    $loadedCommons[$dnName.'theme'] = true;
                    if (!$GLOBALS['xarMLS_backend']->loadContext('themes:', 'common')) return;
                }
            } elseif ($dnType == XARMLS_DNTYPE_MODULE) {
                // Handle in a special way the module type
                // for which it's necessary to load common translations
                if (!isset($loadedCommons[$dnName.'module'])) {
                    $loadedCommons[$dnName.'module'] = true;
                    if (!$GLOBALS['xarMLS_backend']->loadContext('modules:', 'common')) return;
                    if (!$GLOBALS['xarMLS_backend']->loadContext('modules:', 'version')) return;
                }
            } elseif ($dnType == XARMLS_DNTYPE_PROPERTY) {
                // Load common translations
                if (!isset($loadedCommons[$dnName.'property'])) {
                    $loadedCommons[$dnName.'property'] = true;
                    if (!$GLOBALS['xarMLS_backend']->loadContext('properties:', 'common')) return;
                }
            } elseif ($dnType == XARMLS_DNTYPE_BLOCK) {
                // Load common translations
                if (!isset($loadedCommons[$dnName.'block'])) {
                    $loadedCommons[$dnName.'block'] = true;
                    if (!$GLOBALS['xarMLS_backend']->loadContext('blocks:', 'common')) return;
                }
            }
    
            if (!$GLOBALS['xarMLS_backend']->loadContext($ctxType, $ctxName)) return;
            $loadedTranslations["$dnType.$dnName.$ctxType.$ctxName"] = true;
            return true;
        } else {
            // FIXME: postpone
            //xarEvt_fire('MLSMissingTranslationDomain', array($dnType, $dnName));
    
            $loadedTranslations["$dnType.$dnName.$ctxType.$ctxName"] = false;
            return false;
        }
    }

    /**
     * Load relevant translations for a specified relatvive path (be it file or directory)
     *
     * @author Marcel van der Boom <mrb@hsdev.com>
     * @return boolean true on success, false on failure
     * @todo slowly add more intelligence for more scopes. (core, version, init?)
     * @todo static hash on path to prevent double loading?
     * @todo is directory support needed? i.e. modules/base/ load all for base module? or how does this work?
     * @todo pnFile.php type files support needed?
     * @todo xarversion.php type files support
     * @todo xar(whatever)api.php type files support? (javascript for example)
     * @todo do we want core per file support?
     **/
    static public function loadTranslations($path)
    {
        if(!file_exists($path)) {
            xarLog::message("MLS: Trying to load translations for a non-existing path ($path)",XARLOG_LEVEL_WARNING);
            //die($path);
            return true;
        }
    
        // If this is a core file, get the translations and bail
        if (strpos($path, sys::lib()) === 0) {
            $translations = self::_loadTranslations(XARMLS_DNTYPE_CORE, 'xaraya', 'core:', 'core');
            return $translations;        
        }
        
        // Remove the parts of the path before the dnType marker ('xaraya', 'modules' etc.)
        if (strpos($path, sys::code()) === 0) {
            // This is a module, property or block file
            $path = substr($path, strlen(sys::code()));
        } elseif (strpos($path, xarTpl::getBasedir()) === 0) {
            // This is a theme file
        } else {
            throw new Exception('Unknown MLS path: ' . $path);
        }
        
        // Get a structured representation of the path.
        $pathElements = explode("/",$path);
    
        // Determine dnType
    
        $firstelement = array_shift($pathElements);
        if ($firstelement == 'modules') {
            $dnType = XARMLS_DNTYPE_MODULE;
            $possibleOverride = false;
            $ctxType= 'modules';
        } elseif ($firstelement == 'properties') {
            $dnType = XARMLS_DNTYPE_PROPERTY;
            $possibleOverride = false;
            $ctxType= 'properties';
        } elseif ($firstelement == 'blocks') {
            $dnType = XARMLS_DNTYPE_BLOCK;
            $possibleOverride = false;
            $ctxType= 'blocks';
        } else {
            $dnType = XARMLS_DNTYPE_THEME;
            $possibleOverride = true;
            $ctxType= 'themes';
        }
        $ctxType .= ":";
    
        // Determine dnName
        // The specifics within that Type are in the next element, overridden or not
        // NOTE: $pathElements changes here!
        $dnName = array_shift($pathElements);
    
        // Determine ctxName, which is just the basename of the file without extension it seems
        // CHECKME: there was a hardcoded substr(str,0,-3) here earlier
        // NOTE: $pathElements changes here!
        $ctxName = preg_replace('/^(xar)?(.+)\..*$/', '$2', array_pop($pathElements));
    
        // Determine ctxType further if needed (i.e. more path components are there)
        // Peek into the first element and unwind the rest of the path elements into $ctxType
        // xartemplates -> templates, xarblocks -> blocks, xarproperties -> properties etc.
        // NOTE: pnFile.php type files support needed?
        if(!empty($pathElements)) {
            $pathElements[0] = preg_replace('/^xar(.+)/','$1',$pathElements[0]);
            $ctxType .= implode("/",$pathElements);
        }
    
        // Ok, based on possible overrides, we load internal only, or interal plus overrides
        $ok = false;
        if($possibleOverride) {
            $ok= self::_loadTranslations(XARMLS_DNTYPE_MODULE,$dnName,$ctxType,$ctxName);
        }
        // And load the determined stuff
        // @todo: should we check for success on *both*, where is the exception here? further up the tree?
        $ok = self::_loadTranslations($dnType, $dnName, $ctxType, $ctxName);
        return $ok;
    }

    static public function convertFromInput($var, $method)
    {
        // FIXME: <marco> Can we trust browsers?
        if (self::getMode() == XARMLS_SINGLE_LANGUAGE_MODE ||
            !function_exists('mb_http_input')) {
            return $var;
        }
        // CHECKME: check this code
        return $var;
        // Cookies must contain only US-ASCII characters
        $inputCharset = strtolower(mb_http_input($method));
        $curCharset = self::getCharsetFromLocale(self::getCurrentLocale());
        if ($inputCharset != $curCharset) {
            $var = mb_convert_encoding($var, $curCharset, $inputCharset);
        }
        return $var;
    }

    // CHECKME: is this used anywhere?
    static private function xarMLS__convertFromCharset($var, $charset)
    {
        // FIXME: <marco> Can we trust browsers?
        if (self::getMode() == XARMLS_SINGLE_LANGUAGE_MODE ||
            !function_exists('mb_convert_encoding')) return $var;
        $curCharset = self::getCharsetFromLocale(self::getCurrentLocale());
        $var = mb_convert_encoding($var, $curCharset, $charset);
        return $var;
    }

    static private function bindVariables($string, $args)
    {
        // FIXME: <marco> Consider to use strtr to do the same, can we?
        $i = 1;
        foreach($args as $var) {
            $search = "#($i)";
            $string = str_replace($search, $var, $string);
            $i++;
        }
        return $string;
    }

    /**
     * Gets a list of alternatives for a certain locale.
     * The first alternative is the locale itself
     *
     * @author Marco Canini <marco@xaraya.com>
     * @return array alternative locales
     */
    static private function getLocaleAlternatives($locale)
    {
        if (!$parsedLocale = self::parseLocaleString($locale)) return; // throw back
        extract($parsedLocale); // $lang, $country, $charset
    
        $alternatives = array($locale);
        if (!empty($country) && !empty($specializer)) $alternatives[] = $lang.'_'.$country.'.'.$charset;
        if (!empty($country) && empty($specializer)) $alternatives[] = $lang.'.'.$charset;
    
        return $alternatives;
    }

    /**
     * Parses a locale string into an associative array composed of
     * lang, country, specializer and charset keys
     *
     * @author Marco Canini <marco@xaraya.com>
     * @return array parsed locale
     */
    static public function parseLocaleString($locale)
    {
        $res = array('lang'=>'', 'country'=>'', 'specializer'=>'', 'charset'=>'utf-8');
        // Match the locales standard format  : en_US.iso-8859-1
        // Thus: language code lowercase(2), country code uppercase(2), encoding lowercase(1+)
        if (!preg_match('/([a-z][a-z])(_([A-Z][A-Z]))?(\.([0-9a-z\-]+))?(@([0-9a-zA-Z]+))?/', $locale, $matches)) {
            throw new BadParameterException('locale');
        }
    
        $res['lang'] = $matches[1];
        if (!empty($matches[3])) $res['country'] = $matches[3];
        if (!empty($matches[5])) $res['charset'] = $matches[5];
        if (!empty($matches[7])) $res['specializer'] = $matches[7];
    
        return $res;
    }

    /**
     * Gets the single byte charset most typically used in the Web for the
     * requested language
     *
     * @author Marco Canini <marco@xaraya.com>
     * @return string the charset
     * @todo   Dont hardcode this
     */
    // CHECKME: is this used anywhere?
    static private function xarMLS__getSingleByteCharset($langISO2Code)
    {
        static $charsets = array(
            'af' => 'iso-8859-1', 'sq' => 'iso-8859-1',
            'ar' => 'iso-8859-6',  'eu' => 'iso-8859-1',  'bg' => 'iso-8859-5',
            'be' => 'iso-8859-5',  'ca' => 'iso-8859-1',  'hr' => 'iso-8859-2',
            'cs' => 'iso-8859-2',  'da' => 'iso-8859-1',  'nl' => 'iso-8859-1',
            'en' => 'iso-8859-1',  'eo' => 'iso-8859-3',  'et' => 'iso-8859-15',
            'fo' => 'iso-8859-1',  'fi' => 'iso-8859-1',  'fr' => 'iso-8859-1',
            'gl' => 'iso-8859-1',  'de' => 'iso-8859-1',  'el' => 'iso-8859-7',
            'iw' => 'iso-8859-8',  'hu' => 'iso-8859-2',  'is' => 'iso-8859-1',
            'ga' => 'iso-8859-1',  'it' => 'iso-8859-1',  //'ja' => '',
            'lv' => 'iso-8859-13', 'lt' => 'iso-8859-13', 'mk' => 'iso-8859-5',
            'mt' => 'iso-8859-3',  'no' => 'iso-8859-1',  'pl' => 'iso-8859-2',
            'pt' => 'iso-8859-1',  'ro' => 'iso-8859-2',  'ru' => 'windows-1251',
            'gd' => 'iso-8859-1',  'sr' => 'iso-8859-2',  'sk' => 'iso-8859-2',
            'sl' => 'iso-8859-2',  'es' => 'iso-8859-1',  'sv' => 'iso-8859-1',
            'tr' => 'iso-8859-9',  'uk' => 'iso-8859-5'
        );
    
        return @$charsets[$langISO2Code];
    }


    /**
     * Create directories tree
     *
     * @author Volodymyr Metenchuk <voll@xaraya.com>
     * @return boolean true
     */
    static public function mkdirr($path)
    {
        // Check if directory already exists
        if (is_dir($path) || empty($path)) {
            return true;
        }
    
        // Crawl up the directory tree
        $next_path = substr($path, 0, strrpos($path, '/'));
        if (self::mkdirr($next_path)) {
            if (!file_exists($path)) {
                $madeDir = @mkdir($path, 0700);
                if (!$madeDir) {
                    $msg = xarML("The directories under #(1) must be writeable by PHP.", $next_path);
                    xarLog::message($msg);
                    // throw new PermissionException?
                }
                return $madeDir;
            }
        }
        return false;
    }

    /**
     * Check directory writability and create directory if it doesn't exist
     *
     * @author Volodymyr Metenchuk <voll@xaraya.com>
     * @access protected
     * @return bool true
     */
    static public function iswritable($directory=NULL)
    {
        if ($directory == NULL) {
            $directory = getcwd();
        }
    
        if (file_exists($directory)) {
            if (!is_dir($directory)) return false;
            $isWritable = true;
            $isWritable &= is_writable($directory);
            $handle = opendir($directory);
            while ($isWritable && (false !== ($filename = readdir($handle)))) {
                if (($filename != ".") && ($filename != "..") && ($filename != "SCCS")) {
                    if (is_dir($directory."/".$filename)) {
                        $isWritable &= is_writable($directory."/".$filename);
                        $isWritable &= self::iswritable($directory."/".$filename);
                    } else {
                        $isWritable &= is_writable($directory."/".$filename);
                    }
                }
            }
            return $isWritable;
        } else {
            $isWritable = self::mkdirr($directory);
            return $isWritable;
        }
    }
}

?>