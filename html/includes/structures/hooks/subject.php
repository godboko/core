<?php

sys::import('structures.hooks.request');

class BasicSubject extends RequestObject implements SplSubject
{

    function attach(SplObserver $observer)
    {

    }
    function detach(SplObserver $observer)
    {

    }
    function notify()
    {

    }
}
class HookSubject extends BasicSubject
{
    private $messenger;

    function attach(SplObserver $observer)
    {
        xarModAPIFunc('modules','admin','enablehooks',array('callerModName' => $this->getmodule(), 'hookModName' => $observer->getmodule()));
    }

    function detach(SplObserver $observer)
    {
        xarModAPIFunc('modules','admin','disablehooks',array('callerModName' => $this->getmodule(), 'hookModName' => $observer->getmodule()));
    }

    function getMessenger()
    {
        sys::import('structures.hooks.messenger');
        $this->messenger = new HookMessenger($this->module, $this->itemtype);
        return $this->messenger;
    }

    function notify()
    {
        if (empty($this->module)) $module = null;
        if ($this->itemtype == 'All') $itemtype = '';
        return xarModCallHooks($this->messenger->gethookObject(), $this->messenger->gethookAction(), $this->messenger->getitemid(), $extraInfo = NULL, $this->module, $this->itemtype);
    }
    function getHooklist()
    {
        if ($this->itemtype == "All") $this->itemtype = '';
        return xarModGetHookList($this->module, $this->messenger->gethookObject(), $this->messenger->gethookAction(), $this->itemtype);
    }
    function isHooked($hookModName)
    {
        if ($this->itemtype == "All") $this->itemtype = '';
        return xarModIsHooked($hookModName, $this->module, $this->itemtype);
    }

}
?>