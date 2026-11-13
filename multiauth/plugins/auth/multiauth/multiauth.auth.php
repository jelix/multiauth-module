<?php
/**
 * @package    jelix
 * @subpackage auth_driver
 * @author     Laurent Jouanneau
 * @copyright  2019-2026 Laurent Jouanneau
 * @license   MIT
 */

use Jelix\MultiAuth\ProvidersConfigurations;
use Jelix\MultiAuth\ProviderPluginInterface;

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

    protected ProvidersConfigurations $configuration;

    /**
     * @param array $params
     * @throws jException
     */
    public function __construct($params)
    {
        if (property_exists(jApp::config(), 'multiauth') && is_array(jApp::config()->multiauth)) {
            $params = array_merge($params, jApp::config()->multiauth);
        }

        parent::__construct($params);

        $this->configuration = new ProvidersConfigurations($params);
        $this->providers = $this->configuration->loadProviders();
        $this->dbAccountProvider = $this->configuration->getDBAccountProvider();
    }

    /**
     * @return jDaoFactoryBase
     */
    public function getDao()
    {
        return jDao::get($this->configuration->dao, $this->configuration->profile);
    }

    public function saveNewUser($user)
    {
        $this->getDao()->insert($user);
        return true;
    }

    public function removeUser($login)
    {
        $this->getDao()->deleteByLogin($login);
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
        $this->getDao()->update($user);
        return true;
    }

    public function getUser($login)
    {
        return $this->getDao()->getByLogin($login);
    }

    public function createUserObject($login, $password)
    {
        $user = jDao::createRecord($this->configuration->dao, $this->configuration->profile);
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
        $dao = $this->getDao();
        if ($pattern == '%' || $pattern == '') {
            return $dao->findAll();
        } else {
            return $dao->findByLogin($pattern);
        }
    }

    public function canChangePassword($login)
    {
        $dao = $this->getDao();
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
        $dao = $this->getDao();
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
        $dao = $this->getDao();
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
                        $automaticAccountCreation = $this->configuration->isAutomaticAccountCreation();
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
        $this->getDao()->updatePassword($login, $password);
    }
}
