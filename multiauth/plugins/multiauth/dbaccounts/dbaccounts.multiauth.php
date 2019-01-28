<?php
/**
 * @package    jelix
 * @subpackage auth_driver
 * @author     Laurent Jouanneau
 * @copyright  2019 Laurent Jouanneau
 * @license   MIT
 */


/**
 * authentication provider for the multiauth plugin
 *
 * it uses the same table for accounts used by multiauth
 *
 * @package    jelix
 * @subpackage multiauth_provider
 */
class dbaccountsProvider extends \Jelix\MultiAuth\Provider\ProviderAbstract {

    protected $labelLocale = 'multiauth~multiauth.provider.dbaccounts.label';

    function __construct($params) {

        parent::__construct($params);
        if (isset($this->_params['automaticAccountCreation'])) {
            unset($this->_params['automaticAccountCreation']);
        }
    }

    public function getFeature() {
        return self::FEATURE_SUPPORT_PASSWORD |
            self::FEATURE_USE_MULTIAUTH_TABLE;
    }

    /**
     * @param string $login
     * @param string $newpassword
     * @throws jException
     */
    public function changePassword($login, $newpassword){
        throw new jException('dbmultiauthProvider does not support password change directly');
    }

    public function verifyAuthentication($user, $login, $password){
        if (trim($password) == '') {
            return self::VERIF_AUTH_BAD;
        }

        $result = $this->checkPassword($password, $user->password);
        if ($result === false) {
            return self::VERIF_AUTH_BAD;
        }

        if ($result !== true) {
            // it is a new hash for the password, let's update it persistently
            $user->password = $result;
            return self::VERIF_AUTH_OK_USER_TO_UPDATE;
        }

        return self::VERIF_AUTH_OK;
    }
}
