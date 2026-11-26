<?php
/**
 * @author     Laurent Jouanneau
 * @copyright  2026 Laurent Jouanneau
 * @license   MIT
 */
namespace Jelix\MultiAuth;

use Exception;
use jApp;
use jAuth;
use Jelix\IniFile\IniModifier;
use Jelix\IniFile\IniModifierInterface;
use Jelix\Ldap\LdapClient;

class ProvidersConfigurations
{

    public static function loadFromIniFile(IniModifierInterface $configIni) : ProvidersConfigurations
    {
        $authConfigFile = $configIni->getValue('auth', 'coordplugins');
        if (!$authConfigFile || !is_string($authConfigFile) || strlen($authConfigFile) < 2) {
            throw new ProviderConfigurationException('auth.coord.ini.php not declared', 1);
        }
        $authConfigFilePath = jApp::appSystemPath($authConfigFile);
        if (!file_exists($authConfigFilePath)) {
            throw new ProviderConfigurationException('auth.coord.ini.php not found', 2);
        }

        $confIni = parse_ini_file($authConfigFilePath, true);
        $configMultiauth = self::loadMultiAuthConfig($confIni);
        return new self($configMultiauth);
    }

    /**
     * Load the multiauth configuration
     *
     * @return array
     * @throws ProviderConfigurationException  if configuration is not found.
     * @throws \jException
     */
    public static function loadMultiAuthConfig($authCoordPluginConf = null) : array
    {
        $authConfig = jAuth::loadConfig($authCoordPluginConf);
        if (isset($authConfig['multiauth'])) {
            $configMultiauth = $authConfig['multiauth'];
            if (property_exists(jApp::config(), 'multiauth') && is_array(jApp::config()->multiauth)) {
                $configMultiauth = array_merge($configMultiauth, jApp::config()->multiauth);
            }
            return $configMultiauth;
        }
        throw new ProviderConfigurationException('multiauth configuration not found', 3);
    }

    public static function loadFromApplication() : ProvidersConfigurations
    {
        $configMultiauth = self::loadMultiAuthConfig();
        return new self($configMultiauth);
    }

    protected $multiauthConfig = array();

    /**
     * @var ProviderConfiguration[]
     */
    protected $providersConfig = array();

    /**
     * @var ProviderPluginInterface|null
     */
    protected ?ProviderPluginInterface $dbAccountProvider = null;

    protected bool $automaticAccountCreation = false;

    /**
     * @param array $multiauthConfig the content of [multiauth] from auth.coord.ini.php
     *                          with consolidated configuration parameters, including
     *                          config parameters from the `[multiauth]` section
     *                          of the main configuration, and password_hash_* params.
     *
     * @throws Exception
     */
    public function __construct(array $multiauthConfig)
    {
        if (!isset($multiauthConfig['profile'])) {
            $multiauthConfig['profile'] = '';
        }

        $this->multiauthConfig = $multiauthConfig;

        if (!isset($multiauthConfig['dao'])) {
            throw new ProviderConfigurationException("Dao selector is missing into the multiauth configuration", 5);
        }
        
        if (!isset($multiauthConfig['providers'])) {
            $this->providersConfig[] = new ProviderConfiguration('dbaccounts', 'dbaccounts', array());
        }
        else {
            $authConfig = jAuth::loadConfig();
            foreach ($multiauthConfig['providers'] as $providerKey) {
                $providerConfig = $this->getDefaultProviderConfig();
                if (strpos($providerKey, ':') !== false) {
                    list($provider, $providerSection) = explode(':', $providerKey);

                    $providerSectionExists = false;
                    if (isset($authConfig[$providerSection]) && is_array($authConfig[$providerSection])) {
                        $providerConfig = array_merge($providerConfig, $authConfig[$providerSection]);
                        $providerSectionExists = true;
                    }

                    if (property_exists(jApp::config(), $providerSection)) {
                        $providerConfig = array_merge($providerConfig, jApp::config()->$providerSection);
                        $providerSectionExists = true;
                    }

                } else {
                    $provider = $providerKey;
                    $providerSection = '';
                    $providerSectionExists = false;
                }

                $this->providersConfig[] = new ProviderConfiguration($providerKey, $provider, $providerConfig, $providerSection, $providerSectionExists);
            }
        }

        if (isset($multiauthConfig['automaticAccountCreation'])) {
            $this->automaticAccountCreation = $multiauthConfig['automaticAccountCreation'];
        }


    }

    protected function getDefaultProviderConfig()
    {
        $providerConfig = array(
            'password_hash_method' => $this->multiauthConfig['password_hash_method'],
            'password_hash_options' => $this->multiauthConfig['password_hash_options'],
            'accountsDao' => $this->multiauthConfig['dao'],
            'accountsDaoProfile'=> $this->multiauthConfig['profile'],
            'accountsFormSelector' => isset($this->multiauthConfig['form']) ? $this->multiauthConfig['form'] : '',
            'enabled' => true,
        );
        if (isset($this->multiauthConfig['password_crypt_function'])) {
            $providerConfig['password_crypt_function'] = $this->multiauthConfig['password_crypt_function'];
        }
        return $providerConfig;
    }

    public function __get($name)
    {
        if (isset($this->multiauthConfig[$name])) {
            return $this->multiauthConfig[$name];
        }
        return null;
    }


    public function hasProviders()
    {
        return !(!isset($this->multiauthConfig['providers']) || empty($this->multiauthConfig['providers']));
    }

    /**
     * @return ProviderPluginInterface[]
     * @throws Exception
     */
    public function loadEnabledProviders()
    {
        $providers = array();
        $dbAccountProvider = null;
        foreach ($this->providersConfig as $providerConf) {

            if (!$providerConf->isEnabled()) {
                continue;
            }

            /** @var ProviderPluginInterface $plugin */
            $plugin = jApp::loadPlugin(
                $providerConf->providerName,
                'multiauth',
                '.multiauth.php',
                $providerConf->providerName.'Provider',
                $providerConf->configuration
            );
            if (is_null($plugin)) {
                throw new ProviderConfigurationException(
                    'Plugin '.$providerConf->providerName.' for multiAuth not found', 6,
                    $providerConf->providerName,
                    $providerConf->providerConfigName
                );
            }
            $plugin->setRegisterKey($providerConf->providerKey);
            $providers[$providerConf->providerKey] = $plugin;
            if ($plugin->getFeature() & ProviderPluginInterface::FEATURE_USE_MULTIAUTH_TABLE) {
                if ($dbAccountProvider) {
                    throw new ProviderConfigurationException("Multiauth plugin does not accept multiple providers based on the account table", 7);
                }
                $dbAccountProvider = $plugin;
            }
        }
        return $providers;
    }


    public function isAutomaticAccountCreation(): bool
    {
        return $this->automaticAccountCreation;
    }

    public function getDBAccountProvider(): ?ProviderPluginInterface
    {
        return $this->dbAccountProvider;
    }

    /**
     * @return ProviderConfiguration[]
     */
    public function getProvidersConfig()
    {
        return $this->providersConfig;
    }

    /**
     *
     * @return ProviderConfiguration|null  null if not found.
     */
    public function getLdapProviderConfig($getDefaultIfNotExists = false) : ?ProviderConfiguration
    {
        foreach($this->providersConfig as $providerConf) {
            if ($providerConf->providerName != 'ldap') {
                continue;
            }

            if ($providerConf->providerConfigName == '') {
                // no section name where to store a configuration, let's ignore it, as this is an error
                continue;
            }
            return $providerConf;
        }
        if ($getDefaultIfNotExists) {
            $providerConfig = $this->getDefaultProviderConfig();
            $providerConfig['enabled'] = false;
            $providerConfig['ldapprofile'] = 'multiauthldap';
            $providerConfig = array_merge(LdapClient::defaultParameters, $providerConfig);
            return new ProviderConfiguration('ldap:multiauth_ldap', 'ldap', $providerConfig, 'multiauth_ldap', false);
        }
        return null;
    }

    /**
     * Save the configuration of an ldap profider
     *
     * If the configuration section name of the provider is not given or if the configuration section does not exist, it is created.
     *
     * If the ldap profile does not exists, it is also created in profiles.ini.php
     *
     * @param ProviderConfiguration $ldapProviderConfig
     * @return ProviderConfiguration the updated provider configuration (new object instance)
     * @throws ProviderConfigurationException
     * @throws \Jelix\IniFile\IniException
     */
    public function setLdapProviderConfig(ProviderConfiguration $ldapProviderConfig) : ProviderConfiguration
    {
        if ($ldapProviderConfig->providerName != 'ldap') {
            throw new ProviderConfigurationException('Not an ldap provider', 30);
        }

        $providerConfigName = $ldapProviderConfig->providerConfigName ?: 'multiauth_ldap';

        // search the existing provider entry corresponding to the given provider config
        $found = false;
        foreach($this->providersConfig as $k => $providerConf) {
            if ($providerConf->providerName != 'ldap') {
                continue;
            }

            if ($providerConf->providerConfigName == $providerConfigName) {
                $found = $k;
                break;
            }
        }

        // save the ldap profile into profiles.ini.php
        $ldapConfig = $ldapProviderConfig->configuration;

        if (!isset($ldapConfig['ldapprofile']) || $ldapConfig['ldapprofile'] == '') {
            $ldapConfig['ldapprofile'] = 'multiauthldap';
        }
        $ldapProfile = $ldapConfig['ldapprofile'];

        $section = 'ldap:'.$ldapProfile;
        $profilesIni = new IniModifier(\jApp::varConfigPath('profiles.ini.php'));
        $extraParameters = array('searchGroupKeepUserInDefaultGroups');

        foreach($ldapConfig as $param => $value) {
            if (isset(LdapClient::defaultParameters[$param]) || in_array($param, $extraParameters)) {
                // IniModifier does not cleanly replace an array value (key[]=...)
                // by a string or by an array of another size: remove it first.
                if (is_array($value) || is_array($profilesIni->getValue($param, $section))) {
                    $profilesIni->removeValue($param, $section);
                }
                $profilesIni->setValue($param, $value, $section);
            }
        }
        $profilesIni->save();

        // create a new provider configuration object with updated properties
        $ldapProviderConfig = new ProviderConfiguration(
            $ldapProviderConfig->providerKey,
            $ldapProviderConfig->providerName,
            $ldapConfig,
            $providerConfigName,
            true
        );

        // update the list of providers in multiauth
        $ini = new IniModifier(\jApp::varConfigPath('liveconfig.ini.php'));

        if ($found === false) {
            $this->providersConfig[] = $ldapProviderConfig;

            $providersList = array_map(function($provConf)  {
                return $provConf->providerName.':'.$provConf->providerConfigName;
            }, $this->providersConfig);

            $ini->setValue('providers', $providersList, 'multiauth');
            $this->multiauthConfig['providers'] = $providersList;
        }
        else {
            $this->providersConfig[$k] = $ldapProviderConfig;
        }

        // update the configuration of the provider
        $ini->setValue('ldapprofile', $ldapProfile, $ldapProviderConfig->providerConfigName);
        $ini->setValue('enabled', $ldapConfig['enabled'] ?? false, $ldapProviderConfig->providerConfigName);
        $ini->save();

        return $ldapProviderConfig;
    }

}
