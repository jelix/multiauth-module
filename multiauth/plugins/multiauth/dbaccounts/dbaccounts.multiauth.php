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
class dbaccountsProvider extends \Jelix\MultiAuth\ProviderAbstract {

    protected $labelLocale = 'multiauth~multiauth.provider.dbaccounts.label';

    /**
     * @inheritdoc
     */
    function __construct($params) {

        parent::__construct($params);
        if (isset($this->_params['automaticAccountCreation'])) {
            unset($this->_params['automaticAccountCreation']);
        }
    }

    /**
     * @inheritdoc
     */
    public function getFeature() {
        return self::FEATURE_CHANGE_PASSWORD | self::FEATURE_SUPPORT_PASSWORD |
            self::FEATURE_USE_MULTIAUTH_TABLE;
    }

    /**
     * @inheritdoc
     */
    public function changePassword($userAccount, $login, $newpassword){
        $userAccount->password = $this->cryptPassword($newpassword);
        return true;
    }

    /**
     * @inheritdoc
     */
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
            return self::VERIF_AUTH_OK_PASSWORD_UPDATED;
        }
        return self::VERIF_AUTH_OK;
    }

    /**
     * @inheritdoc
     */
    public function userExists($login) {
        // FIXME we should have dao selector so we could check if the user exists
        // FIXME for the moment, this method is not used so we don't care
        return false;
    }
}
