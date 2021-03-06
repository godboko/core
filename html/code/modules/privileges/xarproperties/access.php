<?php
/**
 * @package modules
 * @subpackage privileges module
 * @category Xaraya Web Applications Framework
 * @version 2.4.0
 * @copyright see the html/credits.html file in this release
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @link http://www.xaraya.info
 * @link http://xaraya.info/index.php/release/1098.html
 */

sys::import('modules.dynamicdata.class.properties.base');

class AccessProperty extends DataProperty
{
    public $id          = 30092;
    public $name        = 'access';
    public $desc        = 'Access';
    public $reqmodules  = array('privileges');

    public $group       = 0;
    public $level       = 100;
    public $failure     = 0;
    public $myself      = -6;
    public $allallowed  = array('Administrators');
    public $initialization_group_multiselect = false;
    public $validation_override              = false;


    public $module      = 'All';
    public $component   = 'All';
    public $instance    = 'All';

    function __construct(ObjectDescriptor $descriptor)
    {
        parent::__construct($descriptor);
        $this->tplmodule = 'privileges';
        $this->filepath  = 'modules/privileges/xarproperties';
        $this->template  = 'access';
    }

    public function checkInput($name = '', $value = null)
    {
        $dropdown = DataPropertyMaster::getProperty(array('name' => 'dropdown'));        
        $value = array();
        
        // Check the group
        if ($this->initialization_group_multiselect) {
            $multiselect = DataPropertyMaster::getProperty(array('name' => 'multiselect'));        
            $multiselect->options = $this->getgroupoptions();
            $multiselect->validation_override = $this->validation_override;
            if (!$multiselect->checkInput($name . '_group')) return false;
            $value['group'] = $multiselect->value;
            // The override is only meant for the groups
            $multiselect->validation_override = false;
        } else {
            $dropdown->options = $this->getgroupoptions();
            $dropdown->validation_override = $this->validation_override;
            if (!$dropdown->checkInput($name . '_group')) return false;
            $value['group'] = $dropdown->value;
            // The override is only meant for the groups
            $dropdown->validation_override = false;
        }
        
        // Check the level
        $dropdown->options = $this->getleveloptions();
        if (!$dropdown->checkInput($name . '_level')) return false;
        $value['level'] = $dropdown->value;
        
        // Check the failure behavior
        $dropdown->options = $this->getfailureoptions();
        if (!$dropdown->checkInput($name . '_failure')) return false;
        $value['failure'] = $dropdown->value;
        
        xarLog::message("DataProperty::validateValue: Skipping validation for " . $this->name);
        $this->setValue($value);
        return true;
    }

    public function showInput(Array $data = array())
    {
        if (isset($data['value'])) {
            $this->setValue($data['value']);
        } else {
            $this->setValue();
        }
        $value = $this->getValue();
        if (!isset($data['level'])) $data['level'] = $value['level'];
        if (!isset($data['group'])) $data['group'] = $value['group'];
        if (!isset($data['failure'])) {
            $data['failure'] = $value['failure'];
        } else {
            $data['showfailure'] = 1;
        }
        
        if (!isset($data['group_multiselect'])) {
            try {
                unserialize($data['group']);
                $data['group_multiselect'] = true;
            } catch(Exception $e) {
                $data['group_multiselect'] = false;
            }
        }
        if (!isset($value['group'])) $value['group'] = $data['group_multiselect'] ? array(0) : 0;
        
        if (!isset($data['groupoptions'])) $data['groupoptions'] = $this->getgroupoptions();
        if (!isset($data['leveloptions'])) $data['leveloptions'] = $this->getleveloptions();
        $data['failureoptions'] = $this->getfailureoptions();

        return parent::showInput($data);
    }

    public function showOutput(Array $data = array())
    {
        if (isset($data['value'])) {
            $this->setValue($data['value']);
        } else {
            $this->setValue();
        }
        $value = $this->getValue();
        if (!isset($data['level'])) $data['level'] = (isset($value['level'])) ? $value['level'] : 800;
        if (!isset($data['group'])) $data['group'] = (isset($value['group'])) ? $value['group'] : array();
        if (!isset($data['failure'])) $data['failure'] = (isset($value['failure'])) ? $value['failure'] : 1;
        
        if (!isset($data['group_multiselect'])) {
            try {
                unserialize($data['group']);
                $data['group_multiselect'] = true;
            } catch(Exception $e) {
                $data['group_multiselect'] = false;
            }
        }
        if (!isset($value['group'])) $value['group'] = $data['group_multiselect'] ? array(0) : 0;
        
        if (!isset($data['groupoptions'])) $data['groupoptions'] = $this->getgroupoptions();
        if (!isset($data['leveloptions'])) $data['leveloptions'] = $this->getleveloptions();
        $data['failureoptions'] = $this->getfailureoptions();

        return parent::showOutput($data);
    }

    function getgroupoptions()
    {
        $anonID = xarConfigVars::get(null,'Site.User.AnonymousUID');
        $options = xarRoles::getgroups();
        $firstlines = array(
            array('id' => 0, 'name' => xarML('No requirement')),
            array('id' => $this->myself, 'name' => xarML('Current User')),
            array('id' => $anonID, 'name' => xarML('Users not logged in')),
            array('id' => -$anonID, 'name' => xarML('Users logged in')),
        );
        return array_merge($firstlines, $options);
    }

    function getleveloptions()
    {
        sys::import('modules.privileges.class.securitylevel');
        $accesslevels = SecurityLevel::$displayMap;
        unset($accesslevels[-1]);
        $options = array();
        foreach ($accesslevels as $key => $value) $options[] = array('id' => $key, 'name' => $value);
        return $options;
    }

    function getfailureoptions()
    {
        $options = array(
                        array('id' => 0, 'name' => xarML('Fail silently')),
                        array('id' => 1, 'name' => xarML('Throw exception')),
                    );
        return $options;
    }
    
    function setValue($value=null)
    {
        if (!empty($value) && !is_array($value)) {
            $this->value = $value;
        } else {
            if (empty($value)) {
                $value = array(
                    'group' => $this->group,
                    'level' => $this->level,
                    'failure' => $this->failure,
                );
            }
            $this->value = serialize($value);
        }
    }

    public function getValue()
    {
        try {
            $value = unserialize($this->value);
        } catch(Exception $e) {
            $value = array(
                'group' => $this->group,
                'level' => $this->level,
                'failure' => $this->failure,
            );
        }
        return $value;
    }

    public function check(Array $data=array(), $exclusive=1)
    {
        // Some groups always have access
        foreach ($this->allallowed as $allowed) {
            if (xarIsParent($allowed, xarUserGetVar('uname'))) return true;        
        }
        
        // We need to be in the correct realm
        if ($this->checkRealm($data)) {
            $disabled = false;
            try {
                if (isset($data['group'])) $group = unserialize($data['group']);
                else $group = $this->group;
            } catch (Exception $e) {
                $group = $data['group'];
            }
            if (is_array($group)){
                // This is a multiselect
                $this->initialization_group_multiselect = true;
                if (in_array(0,$group)) $disabled = true;
            } else {
                // This is a dropdown
                $this->initialization_group_multiselect = false;
                if ((int)$group == 0) $disabled = true;
                $group = array($group);
            }

            if ($exclusive) {
                // We check the level only if group access is disabled
                if (!$disabled) {
                    return $this->checkGroup($group);
                } else {
                    return $this->checkLevel($data);
                }
            } else {
                // Both group access and level must be satisfied
                return $this->checkGroup($group) && $this->checkLevel($data);
            }
        } else {
            return false;
        }
    }
    
    public function checkRealm(Array $data=array())
    {
        // CHECKME
        return true;
    }
    
    public function checkLevel(Array $data=array())
    {
        if (isset($data['level']))     $this->level = (int)$data['level'];
        if (isset($data['module']))    $this->module = $data['module'];
        if (isset($data['component'])) $this->component = $data['component'];
        if (isset($data['instance']))  $this->instance = $data['instance'];

        $access = false;
        if (xarSecurityCheck('', 
                          0, 
                          $this->component, 
                          $this->instance, 
                          $this->module, 
                          '',
                          0,
                          $this->level)) {$access = true;
        }
        return $access;
    }
    
    public function checkGroup(Array $groups=array())
    {
        $anonID = xarConfigVars::get(null,'Site.User.AnonymousUID');
        $access = false;
        if (is_array($groups)) $this->initialization_group_multiselect = true;
        if ($this->initialization_group_multiselect) {
            foreach ($groups as $group) {
                $group = (int)$group;
                if ($group == $this->myself) {
                    $access = true;
                } elseif ($group == $anonID) {
                    if (!xarUserIsLoggedIn()) $access = true;
                } elseif ($group == -$anonID) {
                    if (xarUserIsLoggedIn()) $access = true;
                } elseif ($group) {
                    $rolesgroup = xarRoles::getRole($group);
                    $thisuser = xarCurrentRole();
                    if (is_object($rolesgroup)) {
                        if ($thisuser->isAncestor($rolesgroup)) $access = true;
                    } 
                }
                if ($access) break;
            }
        } else {
            $group = (int)$groups;
            if ($group == $this->myself) {
                $access = true;
            } elseif ($group == $anonID) {
                if (!xarUserIsLoggedIn()) $access = true;
            } elseif ($group == -$anonID) {
                if (xarUserIsLoggedIn()) $access = true;
            } elseif ($group) {
                $rolesgroup = xarRoles::getRole($group);
                $thisuser = xarCurrentRole();
                if (is_object($rolesgroup)) {
                    if ($thisuser->isAncestor($rolesgroup)) $access = true;
                } 
            }
        }
        return $access;
    }
    public function showHidden(Array $data = array())
    {
        if (isset($data['value'])) {
            $this->setValue($data['value']);
        } else {
            $this->setValue();
        }
        $value = $this->getValue();
        if (!isset($data['level'])) $data['level'] = $value['level'];
        if (!isset($data['group'])) $data['group'] = $value['group'];
        if (!isset($data['failure'])) {
            $data['failure'] = $value['failure'];
        } else {
            $data['showfailure'] = 1;
        }
        
        if (!isset($data['group_multiselect'])) {
            try {
                unserialize($data['group']);
                $data['group_multiselect'] = true;
            } catch(Exception $e) {
                $data['group_multiselect'] = false;
            }
        }
        if (!isset($value['group'])) $value['group'] = $data['group_multiselect'] ? array(0) : 0;
        
        if (!isset($data['groupoptions'])) $data['groupoptions'] = $this->getgroupoptions();
        if (!isset($data['leveloptions'])) $data['leveloptions'] = $this->getleveloptions();
        $data['failureoptions'] = $this->getfailureoptions();

        return parent::showHidden($data);
    }    
}

sys::import('modules.dynamicdata.class.properties.interfaces');

class AccessPropertyInstall extends AccessProperty implements iDataPropertyInstall
{

    public function install(Array $data=array())
    {
        $dat_file = sys::code() . 'modules/privileges/xardata/privileges_access_configurations-dat.xml';
        $data = array('file' => $dat_file);
        try {
            $objectid = xarMod::apiFunc('dynamicdata','util','import', $data);
        } catch (Exception $e) {
            //
        }
        return true;
    }
    
}
?>
