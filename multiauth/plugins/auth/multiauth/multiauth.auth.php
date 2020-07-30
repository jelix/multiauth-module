<?php
/**
 * @package    jelix
 * @subpackage auth_driver
 * @author     Laurent Jouanneau
 * @copyright  2019 Laurent Jouanneau
 * @license   MIT
 */

use \Jelix\MultiAuth\ProviderPluginInterface;

/**
 * authentication driver allowing to authenticate against multiple authentication
 * provider.
 *
 * It uses a database to store users, but authentication is made through
 * different provider : database, ldap etc..
 *
 * @package    jelix
 * @subpackage auth_driver
 */
class multiauthAuthDriver extends jAuthDriverBase implements jIAuthDriver2
{

    /**
     * @var ProviderPluginInterface[]
     */
    protected $providers = array();

    /**
     * @var ProviderPluginInterface
     */
    protected $dbAccountProvider = null;

    protected $automaticAccountCreation = false;

    public function __construct($params)
    {
        if (property_exists(jApp::config(), 'multiauth') && is_array(jApp::config()->multiauth)) {
            $params = array_merge($params, jApp::config()->multiauth);
        }

        parent::__construct($params);
        if (!isset($this->_params['profile'])) {
            $this->_params['profile'] = '';
        }
        if (!isset($this->_params['dao'])) {
            throw new Exception("Dao selector is missing into the multiauth configuration");
        }
        if (!isset($this->_params['providers'])) {
            /** @var ProviderPluginInterface $plugin */
            $plugin = jApp::loadPlugin('dbaccounts', 'multiauth', '.multiauth.php', 'dbaccountsProvider', array());
            if (is_null($plugin)) {
                throw new Exception('Plugin dbaccounts for multiAuth not found');
            }
            $plugin->setRegisterKey('dbaccounts');
            $this->providers['dbaccounts'] = $plugin;
            $this->dbAccountProvider = $plugin;
        } else {
            $config = jAuth::loadConfig();
            foreach ($this->_params['providers'] as $providerInfo) {
                $providerConfig = array(
                    'password_hash_method' => $this->passwordHashMethod,
                    'password_hash_options' => $this->passwordHashOptions,
                    'accountsDao' => $this->_params['dao'],
                    'accountsDaoProfile'=> $this->_params['profile'],
                );
                if (isset($this->_params['password_crypt_function'])) {
                    $providerConfig['password_crypt_function'] = $this->_params['password_crypt_function'];
                }
                if (strpos($providerInfo, ':') !== false) {
                    list($provider, $providerSection) = explode(':', $providerInfo);

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
                    $provider = $providerInfo;
                }

                /** @var ProviderPluginInterface $plugin */
                $plugin = jApp::loadPlugin(
                    $provider,
                    'multiauth',
                    '.multiauth.php',
                    $provider.'Provider',
                    $providerConfig
                );
                if (is_null($plugin)) {
                    throw new Exception('Plugin '.$provider.' for multiAuth not found');
                }
                $plugin->setRegisterKey($providerInfo);
                $this->providers[$providerInfo] = $plugin;
                if ($plugin->getFeature() & ProviderPluginInterface::FEATURE_USE_MULTIAUTH_TABLE) {
                    if ($this->dbAccountProvider) {
                        throw new Exception("Multiauth plugin does not accept multiple providers based on the account table");
                    }
                    $this->dbAccountProvider = $plugin;
                }
            }
        }

        if (isset($this->_params['automaticAccountCreation'])) {
            $this->automaticAccountCreation = $this->_params['automaticAccountCreation'];
        }
    }

    public function saveNewUser($user)
    {
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $dao->insert($user);
        return true;
    }

    public function removeUser($login)
    {
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $dao->deleteByLogin($login);
        return true;
    }

    public function updateUser($user)
    {
        if (!is_object($user)) {
            throw new jException('multiauth~ldap.error.object.user.unknown');
        }

        if ($user->login == '') {
            throw new jException('multiauth~ldap.error.user.login.unset');
        }
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $dao->update($user);
        return true;
    }

    public function getUser($login)
    {
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        return $dao->getByLogin($login);
    }

    public function createUserObject($login, $password)
    {
        $user = jDao::createRecord($this->_params['dao'], $this->_params['profile']);
        $user->login = $login;
        if (strpos($password, '!!multiauth:') !== false) {
            $user->password = $password;
        } else {
            $user->password = $this->cryptPassword($password);
        }
        return $user;
    }

    public function getUserList($pattern)
    {
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        if ($pattern == '%' || $pattern == '') {
            return $dao->findAll();
        } else {
            return $dao->findByLogin($pattern);
        }
    }

    public function canChangePassword($login)
    {
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $user = $dao->getByLogin($login);
        if (preg_match("/^!!multiauth:(.+)!!$/", $user->password, $m)) {
            if (isset($this->providers[$m[1]])) {
                $p = $this->providers[$m[1]];
                if ($p->getFeature() & ProviderPluginInterface::FEATURE_CHANGE_PASSWORD) {
                    return true;
                }
            }
            return false;
        }
        else if ($user->password === '!!ldapdao password!!') {
            // in case of not updated user table after a migration from ldapdao, use the first ldap provider
            foreach ($this->providers as $provider) {
                if (get_class($provider) == 'ldapProvider') {
                    if ($provider->getFeature() & ProviderPluginInterface::FEATURE_CHANGE_PASSWORD) {
                        return true;
                    }
                    return false;
                }
            }
            return false;
        }
        return true;
    }

    public function changePassword($login, $newpassword)
    {
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $user = $dao->getByLogin($login);

        if (preg_match("/^!!multiauth:(.+)!!$/", $user->password, $m)) {
            if (isset($this->providers[$m[1]])) {
                $provider = $this->providers[$m[1]];
            } else {
                $provider = null;
            }
        } else if ($user->password === '!!ldapdao password!!') {
            // in case of not updated user table after a migration from ldapdao, use the first ldap provider
            $found = false;
            foreach ($this->providers as $provider) {
                if (get_class($provider) == 'ldapProvider') {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $provider = null;
            }
        } else {
            $provider = $this->dbAccountProvider;
        }
        if ($provider && $provider->getFeature() & ProviderPluginInterface::FEATURE_CHANGE_PASSWORD) {
            return $provider->changePassword($login, $newpassword);
        }
        return false;
    }

    public function verifyPassword($login, $password)
    {
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $user = $dao->getByLogin($login);
        $createdUser = false;
        if (!$user) {
            $createdUser = true;
            $user = $this->createUserObject($login, $password);
            if (jApp::isModuleEnabled('jcommunity')) {
                $user->status = 1; // STATUS_VALID
            }
        }

        $providers = & $this->providers;

        if (!$createdUser) {
            // we're trying to get the right provider for the authentication,
            // with the content of the password field

            if (preg_match("/^!!multiauth:(.*)!!$/", $user->password, $m)) {
                // ok we have an external provider
                if (isset($this->providers[$m[1]])) {
                    $providers = array($m[1] => $this->providers[$m[1]]);
                }
            } elseif ($this->dbAccountProvider &&
                !preg_match("/^[\\-!]{2}/", $user->password) && // ldapdao stored '!!ldapdao password!!' and jcas stored '--no password--'
                $user->password != 'no password' &&
                $user->password != ''
            ) {
                // this is an internal provider, a db account provider
                $providers = array($this->dbAccountProvider->getRegisterKey() => $this->dbAccountProvider);
            }
        }

        foreach ($providers as $pKey => $provider) {
            $useAccountTableForPassword = (
                ($provider->getFeature() & ProviderPluginInterface::FEATURE_USE_MULTIAUTH_TABLE)
                || get_class($provider) == 'dbaccountsProvider'
            );

            if ($useAccountTableForPassword && $createdUser) {
                // it does not make sens to check the password of an inexistant
                // user if the provider is relying on the account table to check
                // password
                continue;
            }

            $result = $provider->verifyAuthentication($user, $login, $password);

            if ($result & ProviderPluginInterface::VERIF_AUTH_OK ||
                $result & ProviderPluginInterface::VERIF_AUTH_OK_USER_TO_UPDATE
            ) {
                if ($createdUser) {
                    // sometimes a provider may change the login field by the same
                    // login but with a different letter case. So let's retry
                    // to get the user with this "new" login name
                    if ($dao->getByLogin($user->login)) {
                        $createdUser = false;
                    }
                }

                if (!$useAccountTableForPassword) {
                    $pass = '!!multiauth:'.$provider->getRegisterKey().'!!';
                    if ($user->password != $pass) {
                        $user->password = $pass;
                        if (!$createdUser) {
                            $dao->updatePassword($login, $user->password);
                        }
                    }
                }

                if ($createdUser) {
                    $pConf = $provider->getConfiguration();
                    if (isset($pConf['automaticAccountCreation'])) {
                        $automaticAccountCreation = $pConf['automaticAccountCreation'];
                    } else {
                        $automaticAccountCreation = $this->automaticAccountCreation;
                    }
                    if ($automaticAccountCreation && ! $useAccountTableForPassword) {
                        // WARNING: we should not create the user if the provider
                        // is using the same table of multiauth, else anybody unregistered
                        // can login with any password, and then become a registered user
                        jAuth::saveNewUser($user);
                    } else {
                        return false;
                    }
                } else {
                    if ($result & ProviderPluginInterface::VERIF_AUTH_OK_USER_TO_UPDATE) {
                        jAuth::updateUser($user);
                    }
                }
                return $user;
                break;
            }
        }

        return false;
    }

    /**
     * @return ProviderPluginInterface[]
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * @param $login
     * @return  ProviderPluginInterface
     */
    public function getProviderForLogin($login, $password='')
    {
        if ($password == '') {
            $user = $this->getUser($login);
            $password = $user->password;
        }

        if (preg_match("/^!!multiauth:(.*)!!$/", $password, $m)) {
            if (isset($this->providers[$m[1]])) {
                return $this->providers[$m[1]];
            }
            return null;
        } else if ($password === '!!ldapdao password!!') {
            // in case of not updated user table after a migration from ldapdao, return the first ldap provider
            foreach ($this->providers as $provider) {
                if (get_class($provider) == 'ldapProvider') {
                    return $provider;
                }
            }
        }
        return $this->dbAccountProvider;
    }

    /**
     * @return ProviderPluginInterface|null
     */
    public function getDbAccountProvider()
    {
        return $this->dbAccountProvider;
    }

    public function updateProviderInAccount($login, $providerKey)
    {
        if (!isset($this->providers[$providerKey])) {
            throw new Exception('bad provider '.$providerKey);
        }
        $feat = $this->providers[$providerKey]->getFeature();
        if ($feat & ProviderPluginInterface::FEATURE_USE_MULTIAUTH_TABLE) {
            throw new Exception('Cannot set provider '.$providerKey);
        }

        $password = '!!multiauth:'.$providerKey.'!!';
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $dao->updatePassword($login, $password);
    }
}
