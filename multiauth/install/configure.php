<?php
/**
 * @author      Laurent Jouanneau
 * @copyright   2020-2026 Laurent Jouanneau
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
 */

use Jelix\IniFile\IniModifier;
use Jelix\IniFile\IniReader;
use \Jelix\Installer\Module\API\ConfigurationHelpers;
use Jelix\Routing\UrlMapping\EntryPointUrlModifier;

class multiauthModuleConfigurator extends \Jelix\Installer\Module\Configurator
{

    public function getDefaultParameters()
    {
        return array(
            'manualconfig' => false,
            'eps'=>array()
        );
    }

    public function declareUrls(EntryPointUrlModifier $registerOnEntryPoint)
    {
        // no controllers so no urls to declare
    }

    public function configure(ConfigurationHelpers $helpers)
    {
        $cli = $helpers->cli();

        if (isset($this->parameters['noconfigfile'])) {
            $this->parameters['manualconfig'] = $this->parameters['noconfigfile'];
            unset($this->parameters['noconfigfile']);
        }

        if (!isset($this->parameters['eps'])
            || !count($this->parameters['eps']))
        {
            $this->parameters['eps'] = $cli->askEntryPoints(
                'Select entry points on which to setup the authentication plugin.',
                $helpers->getEntryPointsByType('classic'),
                true
            );
        }
        $appConfigIni = $helpers->getConfigIni();
        if (!$this->parameters['manualconfig'] && !$appConfigIni->isSection('multiauth')) {
            $appSConfigIni = $helpers->getSingleConfigIni();
            $appSConfigIni->import(new IniModifier(__DIR__.'/appconfig.ini.php'));
            $appSConfigIni->save();
        }
        else {
            $appConfigIni->setValue('driver', 'multiauth', 'coordplugin_auth');
        }

        foreach($this->getParameter('eps') as $epId) {
            $this->configureEntryPoint($epId, $helpers);
        }

        if (!$this->parameters['manualconfig']) {
            $pIni = $helpers->getProfilesIni();
            if (!$pIni->isSection('ldap:multiauthldap')) {
                $pIni->import(new IniReader(__DIR__.'/ldapprofile.ini.php'));
                $pIni->save();
            }
        }
    }

    public function configureEntryPoint($epId, ConfigurationHelpers $helpers)
    {
        $entryPoint = $helpers->getEntryPointsById($epId);

        $configIni = $entryPoint->getConfigIni();

        $authconfig = $configIni->getValue('auth','coordplugins');

        if (!$authconfig) {
            $pluginIni = 'auth.coord.ini.php';
            $authconfig = dirname($entryPoint->getConfigFile()).'/auth.coord.ini.php';

            // no configuration, let's install the plugin for the entry point
            $configIni->setValue('auth', $authconfig, 'coordplugins');
            $helpers->copyFile('auth_multi.coord.ini.php', 'config:'.$pluginIni);
        }
        else {
            /** @var \Jelix\IniFile\IniModifierInterface $conf */
            list($conf, $section) = $entryPoint->getCoordPluginConfig('auth');

            if (!$this->getParameter('manualconfig') && !$conf->isSection('multiauth')) {
                $conf->setValues(array(
                    'dao' => 'jauthdb~jelixuser',
                    'profile' => 'jauth',
                    'form' => 'jauthdb_admin~jelixuser',
                    'providers' => array('dbaccounts'),
                    'compatiblewithdb' => true,
                    'password_crypt_function' => 'sha1',
                ), 'multiauth');

                $conf->save();
            }
        }
    }

    public function unconfigure(ConfigurationHelpers $helpers)
    {
        $appConfigIni = $helpers->getConfigIni();
        $val = $appConfigIni->getValue('driver', 'coordplugin_auth');
        if ($val == 'multiauth') {
            $appConfigIni->setValue('driver', 'Db', 'coordplugin_auth');
        }
    }
}
