<?php
/**
 * @author      Laurent Jouanneau
 * @copyright   2020 Laurent Jouanneau
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
 */
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

        $helpers->getConfigIni()->setValue('driver', 'multiauth', 'coordplugin_auth');

        foreach($this->getParameter('eps') as $epId) {
            $this->configureEntryPoint($epId, $helpers);
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
                    'automaticAccountCreation' => true,
                    'compatiblewithdb' => true,
                    'password_crypt_function' => 'sha1',
                ), 'multiauth');

                $conf->save();
            }
        }
    }
}
