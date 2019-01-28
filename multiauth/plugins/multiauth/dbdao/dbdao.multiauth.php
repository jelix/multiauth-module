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
 * it uses a table of a  database to check authentication
 *
 * @package    jelix
 * @subpackage multiauth_provider
 */
class dbdaoProvider extends \Jelix\MultiAuth\Provider\ProviderAbstract {

    protected $labelLocale = 'multiauth~multiauth.provider.dbdao.label';

    function __construct($params) {
        parent::__construct($params);
        if (!isset($this->_params['profile'])) {
            $this->_params['profile'] = '';
        }
    }

    public function getFeature() {
        return self::FEATURE_CHANGE_PASSWORD |
            self::FEATURE_SUPPORT_PASSWORD;
    }

    public function changePassword($login, $newpassword){
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        return $dao->updatePassword($login, $this->cryptPassword($newpassword));
    }

    /**
     * @param object $user   we don't use property of the accounts for now
     * @param string $login
     * @param string $password
     * @return mixed
     */
    public function verifyAuthentication($user, $login, $password){
        if (trim($password) == '') {
            return self::VERIF_AUTH_BAD;
        }
        $daouser = jDao::get($this->_params['dao'], $this->_params['profile']);
        $userRec = $daouser->getByLogin($login);
        if (!$userRec) {
            return self::VERIF_AUTH_BAD;
        }

        $result = $this->checkPassword($password, $userRec->password);
        if ($result === false) {
            return self::VERIF_AUTH_BAD;
        }

        if ($result !== true) {
            // it is a new hash for the password, let's update it persistently
            $userRec->password = $result;
            return self::VERIF_AUTH_OK_USER_TO_UPDATE;
        }

        return self::VERIF_AUTH_OK;
    }
}
