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
class dbdaoProvider extends \Jelix\MultiAuth\ProviderAbstract {

    protected $labelLocale = 'multiauth~multiauth.provider.dbdao.label';

    /**
     * @inheritdoc
     */
    function __construct($params) {
        parent::__construct($params);
        if (!isset($this->_params['profile'])) {
            $this->_params['profile'] = '';
        }
    }

    /**
     * @inheritdoc
     */
    public function getFeature() {
        return self::FEATURE_CHANGE_PASSWORD |
            self::FEATURE_SUPPORT_PASSWORD;
    }

    /**
     * @inheritdoc
     */
    public function changePassword($userAccount, $login, $newpassword){
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        return $dao->updatePassword($login, $this->cryptPassword($newpassword));
    }

    /**
     * @param object $userAccount   we don't use property of the accounts for now
     * @param string $login
     * @param string $password
     * @return integer
     */
    public function verifyAuthentication($userAccount, $login, $password){
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
            $daouser->updatePassword($login, $result);
        }

        return self::VERIF_AUTH_OK;
    }

    /**
     * @inheritdoc
     */
    public function userExists($login) {
        $daouser = jDao::get($this->_params['dao'], $this->_params['profile']);
        $userRec = $daouser->getByLogin($login);
        return !!$userRec;
    }
}
