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
use Jelix\IniFile\IniModifierInterface;

class ProvidersConfigurations
{

    public static function loadFromIniFile(IniModifierInterface $configIni) : ProvidersConfigurations
    {
        $authConfigFile = $configIni->getValue('auth', 'coordplugins');
        if (!$authConfigFile || !is_string($authConfigFile) || strlen($authConfigFile) < 2) {
            throw new Exception('auth.coord.ini.php not declared');
        }
        $authConfigFilePath = jApp::appSystemPath($authConfigFile);
        if (!file_exists($authConfigFilePath)) {
            throw new Exception('auth.coord.ini.php not found');
        }

        $confIni = parse_ini_file($authConfigFilePath, true);
        $authConfig = jAuth::loadConfig($confIni);
        if (isset($authConfig['multiauth'])) {
            $configMultiauth = $authConfig['multiauth'];
            if (property_exists(jApp::config(), 'multiauth') && is_array(jApp::config()->multiauth)) {
                $configMultiauth = array_merge($configMultiauth, jApp::config()->multiauth);
            }
            return new self($configMultiauth);
        }
        throw new Exception('multiauth configuration not found');
    }


    public static function loadFromApplication() : ProvidersConfigurations
    {
        $authConfig = jAuth::loadConfig();
        if (isset($authConfig['multiauth'])) {
            $configMultiauth = $authConfig['multiauth'];
            if (property_exists(jApp::config(), 'multiauth') && is_array(jApp::config()->multiauth)) {
                $configMultiauth = array_merge($configMultiauth, jApp::config()->multiauth);
            }
            return new self($configMultiauth);
        }
        throw new Exception('multiauth configuration not found');
    }

    protected $authConfig = array();

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
        if (!isset($multiauthConfig['dao'])) {
            throw new Exception("Dao selector is missing into the multiauth configuration");
        }
        
        if (!isset($multiauthConfig['providers'])) {
            $this->providersConfig[] = new ProviderConfiguration('dbaccounts', 'dbaccounts', '', array());
        }
        else {
            $config = jAuth::loadConfig();
            foreach ($multiauthConfig['providers'] as $providerKey) {
                $providerConfig = array(
                    'password_hash_method' => $multiauthConfig['password_hash_method'],
                    'password_hash_options' => $multiauthConfig['password_hash_options'],
                    'accountsDao' => $multiauthConfig['dao'],
                    'accountsDaoProfile'=> $multiauthConfig['profile'],
                    'accountsFormSelector' => isset($multiauthConfig['form']) ? $multiauthConfig['form'] : '',
                );
                if (isset($multiauthConfig['password_crypt_function'])) {
                    $providerConfig['password_crypt_function'] = $multiauthConfig['password_crypt_function'];
                }
                if (strpos($providerKey, ':') !== false) {
                    list($provider, $providerSection) = explode(':', $providerKey);

                    $sectionFound = false;
                    if (isset($config[$providerSection]) && is_array($config[$providerSection])) {
                        $providerConfig = array_merge($providerConfig, $config[$providerSection]);
                        $sectionFound = true;
                    }

                    if (property_exists(jApp::config(), $providerSection)) {
                        $providerConfig = array_merge($providerConfig, jApp::config()->$providerSection);
                        $sectionFound = true;
                    }

                    if (!$sectionFound) {
                        throw new Exception("Section '$providerSection' to configure the provider '$provider' for multiauth, is missing");
                    }
                } else {
                    $provider = $providerKey;
                    $providerSection = '';
                }

                $this->providersConfig[] = new ProviderConfiguration($providerKey, $provider, $providerSection, $providerConfig);
            }
        }

        if (isset($multiauthConfig['automaticAccountCreation'])) {
            $this->automaticAccountCreation = $multiauthConfig['automaticAccountCreation'];
        }

        $this->multiauthConfig = $multiauthConfig;
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
    public function loadProviders()
    {
        $providers = array();
        $dbAccountProvider = null;
        foreach ($this->providersConfig as $providerConf) {

            /** @var ProviderPluginInterface $plugin */
            $plugin = jApp::loadPlugin(
                $providerConf->providerName,
                'multiauth',
                '.multiauth.php',
                $providerConf->providerName.'Provider',
                $providerConf->configuration
            );
            if (is_null($plugin)) {
                throw new Exception('Plugin '.$providerConf->providerName.' for multiAuth not found');
            }
            $plugin->setRegisterKey($providerConf->providerKey);
            $providers[$providerConf->providerKey] = $plugin;
            if ($plugin->getFeature() & ProviderPluginInterface::FEATURE_USE_MULTIAUTH_TABLE) {
                if ($dbAccountProvider) {
                    throw new Exception("Multiauth plugin does not accept multiple providers based on the account table");
                }
                $dbAccountProvider = $plugin;
            }
            else {
                \jLog::log($providerConf->providerKey. ' not db account provider');
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

    public function getProvidersConfig()
    {
        return $this->providersConfig;
    }
}