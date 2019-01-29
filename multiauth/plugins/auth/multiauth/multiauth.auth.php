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
class multiauthAuthDriver extends jAuthDriverBase implements jIAuthDriver2 {

    /**
     * @var ProviderPluginInterface[]
     */
    protected $providers = array();

    protected $automaticAccountCreation = false;

    function __construct($params) {
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
        }
        else {
            $config = jAuth::loadConfig();
            foreach($this->_params['providers'] as $providerInfo) {
                $providerConfig = array(
                    'password_hash_method' => $this->passwordHashMethod,
                    'password_hash_options' => $this->passwordHashOptions
                );
                if (isset($this->_params['password_crypt_function'])) {
                    $providerConfig['password_crypt_function'] = $this->_params['password_crypt_function'];
                }
                if (strpos($providerInfo, ':') !== false) {
                    list($provider, $providerSection) = explode(':', $providerInfo);
                    if (isset($config[$providerSection]) && is_array($config[$providerSection])) {
                        $providerConfig = array_merge($providerConfig, $config[$providerSection]);
                    }
                }
                else {
                    $provider = $providerInfo;

                }
                /** @var ProviderPluginInterface $plugin */
                $plugin = jApp::loadPlugin($provider, 'multiauth', '.multiauth.php',
                    $provider.'Provider', $providerConfig);
                if (is_null($plugin)) {
                    throw new Exception('Plugin '.$provider.' for multiAuth not found');
                }
                $plugin->setRegisterKey($providerInfo);
                $this->providers[$providerInfo] = $plugin;
            }
        }

        if (isset($this->_params['automaticAccountCreation'])) {
            $this->automaticAccountCreation = $this->_params['automaticAccountCreation'];
        }
    }

    public function saveNewUser($user){
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $dao->insert($user);
        return true;
    }

    public function removeUser($login){
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $dao->deleteByLogin($login);
        return true;
    }

    public function updateUser($user){
        if (!is_object($user)) {
            throw new jException('ldapdao~errors.object.user.unknown');
        }

        if ($user->login == '') {
            throw new jException('ldapdao~errors.user.login.unset');
        }
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $dao->update($user);
        return true;
    }

    public function getUser($login){
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        return $dao->getByLogin($login);
    }

    public function createUserObject($login, $password){
        $user = jDao::createRecord($this->_params['dao'], $this->_params['profile']);
        $user->login = $login;
        if (strpos($password, '!!multiauth:') !== false) {
            $user->password = $password;
        }
        else {
            $user->password = $this->cryptPassword($password);
        }
        return $user;
    }

    public function getUserList($pattern){
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        if($pattern == '%' || $pattern == ''){
            return $dao->findAll();
        }else{
            return $dao->findByLogin($pattern);
        }
    }

    public function canChangePassword($login) {
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
        return true;
    }

    public function changePassword($login, $newpassword) {
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $user = $dao->getByLogin($login);
        if (preg_match("/^!!multiauth:(.+)!!$/", $user->password, $m)) {
            if (isset($this->providers[$m[1]])) {
                $p = $this->providers[$m[1]];
                if ($p->getFeature() & ProviderPluginInterface::FEATURE_CHANGE_PASSWORD) {
                    return $p->changePassword($login, $newpassword);
                }
            }
            return false;
        }
        return $dao->updatePassword($login, $this->cryptPassword($newpassword));
    }

    public function verifyPassword($login, $password) {

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

        if (!$createdUser && preg_match("/^!!multiauth:(.*)!!$/", $user->password, $m) &&
            isset($this->providers[$m[1]])
        ) {
            // if we know the provider, just check password with this provider
            $providers = array($m[1] => $this->providers[$m[1]]);
        }
        else {
            $providers = & $this->providers;
        }

        foreach($providers as $pName => $provider) {
            $useAccountTableForPassword = (($provider->getFeature() & ProviderPluginInterface::FEATURE_USE_MULTIAUTH_TABLE)
                || get_class($provider) == 'dbaccountsProvider');

            if ($useAccountTableForPassword && $createdUser) {
                // it does not make sens to check the password of an inexistant
                // user if the provider is relying on the account table to check
                // password
                continue;
            }

            $result = $provider->verifyAuthentication($user, $login, $password);

            if ($result & ProviderPluginInterface::VERIF_AUTH_OK ||
                $result & ProviderPluginInterface::VERIF_AUTH_OK_USER_TO_UPDATE ||
                $result & ProviderPluginInterface::VERIF_AUTH_OK_PASSWORD_UPDATED
            ) {
                if (!$useAccountTableForPassword) {
                    $pass = '!!multiauth:'.$provider->getRegisterKey().'!!';
                    if ($user->password != $pass) {
                        $user->password = $pass;
                        $result |= ProviderPluginInterface::VERIF_AUTH_OK_PASSWORD_UPDATED;
                    }
                }

                if ($createdUser) {
                    $pConf = $provider->getConfiguration();
                    if (isset($pConf['automaticAccountCreation'])) {
                        $automaticAccountCreation = $pConf['automaticAccountCreation'];
                    }
                    else {
                        $automaticAccountCreation = $this->automaticAccountCreation;
                    }
                    if ($automaticAccountCreation && ! $useAccountTableForPassword) {
                        // WARNING: we should not create the user if the provider
                        // is using the same table of multiauth, else anybody unregistered
                        // can login with any password, and then become a registered user
                        jAuth::saveNewUser($user);
                    }
                    else {
                        return false;
                    }
                }
                else {
                    if ($result & ProviderPluginInterface::VERIF_AUTH_OK_PASSWORD_UPDATED &&
                        $useAccountTableForPassword) {
                        $dao->updatePassword($login, $user->password);
                    }

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
    public function getProviders() {
        return $this->providers;
    }
}
