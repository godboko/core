<?php
/**
 * @package modules\base
 * @category Xaraya Web Applications Framework
 * @version 2.4.0
 * @copyright see the html/credits.html file in this release
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @link http://www.xaraya.info
 * @link http://xaraya.info/index.php/release/68.html
 *
 * @author mikespub <mikespub@xaraya.com>
 */
sys::import('modules.base.xarproperties.dropdown');
/**
 * Handle the language list property
 */
class LanguageListProperty extends SelectProperty
{
    public $id         = 36;
    public $name       = 'language';
    public $desc       = 'Language List';

    function getOptions()
    {
        if (count($this->options) > 0) {
            return $this->options;
        }
        
        $options = array();
        $list = xarMLSListSiteLocales();
        asort($list);

        foreach ($list as $locale) {
            $locale_data =& xarMLSLoadLocaleData($locale);
            $name = $locale_data['/language/display'] . " (" . $locale_data['/country/display'] . ")";
            $options[] = array('id'   => $locale,
                                     'name' => $name,
                                    );
        }
        return $options;
    }
}
?>