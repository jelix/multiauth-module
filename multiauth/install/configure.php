<?php
/**
 * @author      Laurent Jouanneau
 * @copyright   2020 Laurent Jouanneau
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
 */
use \Jelix\Installer\Module\API\ConfigurationHelpers;

class multiauthModuleConfigurator extends \Jelix\Installer\Module\Configurator
{



    public function getDefaultParameters()
    {
        return array(
            'manualconfig' => false,
            'eps'=>array()
        );
    }

    public function configure(ConfigurationHelpers $helpers)
    {
        $cli = $helpers->cli();
        $this->parameters['eps'] = $cli->askEntryPoints(
            'Select entry points on which to setup the authentication plugin.',
            $helpers->getEntryPointsByType('classic'),
            true
        );

        $alreadyConfig = false;
        foreach($this->parameters['eps'] as $epId) {
            $ep = $helpers->getEntryPointsById($epId);
            if ($ep->getConfigIni()->getValue('auth','coordplugins')) {
                $alreadyConfig = true;
                break;
            }
        }
        if ($alreadyConfig) {
            $this->parameters['manualconfig'] = $cli->askConfirmation('Do you will modify yourself the existing auth.coord.ini.php configuration file?', false);
        }
        else {
            $this->parameters['manualconfig'] = false;
        }

        foreach($this->getParameter('eps') as $epId) {
            $this->configureEntryPoint($epId, $helpers);
        }
    }

    public function configureEntryPoint($epId, ConfigurationHelpers $helpers) {
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
            list($conf, $section) = $entryPoint->getCoordPluginConfig('auth');

            if (!$this->getParameter('manualconfig')) {
                $conf->setValue('driver', 'multiauth');
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
