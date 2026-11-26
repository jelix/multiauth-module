<?php

use \Jelix\Installer\Module\Uninstaller;
use \Jelix\Installer\Module\API\PreInstallHelpers;
use \Jelix\Installer\Module\API\InstallHelpers;

class multiauthModuleUninstaller extends Uninstaller
{

    function preUninstall(PreInstallHelpers $helpers)
    {
    }

    function uninstall(InstallHelpers $helpers)
    {
        $appConfigIni = $helpers->getConfigIni();
        $val = $appConfigIni->getValue('driver', 'coordplugin_auth');
        if ($val == 'multiauth') {
            $appConfigIni->setValue('driver', 'Db', 'coordplugin_auth');
        }
    }

    function postUninstall(InstallHelpers $helpers)
    {
    }

}
