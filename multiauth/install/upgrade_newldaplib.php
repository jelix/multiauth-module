<?php
/**
 * @author    Laurent Jouanneau
 * @copyright   2026 Laurent Jouanneau
 * @license   MIT
 */

use Jelix\IniFile\IniModifier;
use Jelix\IniFile\IniModifierInterface;
use Jelix\Installer\Module\API\InstallHelpers;
use Jelix\Ldap\LdapMigration;
use Jelix\MultiAuth\LdapProviderConfig;
use Jelix\MultiAuth\ProvidersConfigurations;

class multiauthModuleUpgrader_newldaplib extends \Jelix\Installer\Module\Installer
{
    protected $targetVersions = array('1.3.0-beta.1');
    protected $date = '2026-11-11';


    public function install(InstallHelpers $helpers)
    {
        $migratedLdapProfiles = [];

        foreach($helpers->getEntryPointsList() as $entryPoint) {
            $multiauthConfig = ProvidersConfigurations::loadFromIniFile($entryPoint->getConfigIni());
            jProfiles::clear();

            $providersConfig = $multiauthConfig->getProvidersConfig();
            $profilesIni = $helpers->getProfilesIni();
            foreach ($providersConfig as $providerConf) {
                if ($providerConf->providerName != 'ldap') {
                    continue;
                }
                $providerConfig = $providerConf->configuration;
                if (!isset($providerConfig['ldapprofile']) || in_array($providerConfig['ldapprofile'], $migratedLdapProfiles)) {
                    continue;
                }

                $providerConfig = LdapProviderConfig::loadFullConfiguration($providerConfig);

                $migratedLdapProfiles[] = $providerConfig['ldapprofile'];

                $providerConfig = LdapMigration::migrateMultiauthConfiguration($providerConfig);
                $this->saveProfile($profilesIni, 'ldap:'.$providerConfig['ldapprofile'], $providerConfig);
                $profilesIni->save();
                jProfiles::clear();

                $authConfigFile = $entryPoint->getConfigIni()->getValue('auth', 'coordplugins');
                if (is_string($authConfigFile) && strlen($authConfigFile) > 1) {
                    $authConfigFilePath = jApp::appSystemPath($authConfigFile);
                    if (file_exists($authConfigFilePath)) {
                        $authConfigFilePath = jApp::appSystemPath($authConfigFile);
                        $ini = new IniModifier($authConfigFilePath);
                        $ini->removeValue('bindUserDN', $providerConf->providerConfigName);
                        $ini->save();
                    }
                }
            }
        }
    }

    protected function saveProfile(IniModifierInterface $profilesIni, $section, $configuration)
    {
        foreach($configuration as $param => $value) {
            if (isset(Jelix\Ldap\LdapClient::defaultParameters[$param])) {
                $profilesIni->setValue($param, $value, $section);
            }
        }
    }
}
