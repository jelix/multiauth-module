<?php
/**
 * @package    jelix
 * @subpackage auth_driver
 * @author     Laurent Jouanneau
 * @copyright  2019 Laurent Jouanneau*
 * @license   MIT
 */
namespace Jelix\MultiAuth;

interface ProviderPluginInterface {

    const FEATURE_CHANGE_PASSWORD = 1;
    const FEATURE_SUPPORT_PASSWORD = 2;
    const FEATURE_USE_MULTIAUTH_TABLE = 4;

    const VERIF_AUTH_BAD = 0;
    const VERIF_AUTH_OK = 1;
    const VERIF_AUTH_OK_USER_TO_UPDATE = 3;

    /**
     * ProviderPluginInterface constructor.
     * @param array $params configuration parameters
     */
    function __construct($params);

    /**
     * @return string a label to display
     */
    public function getLabel();

    /**
     * @param string $key a key given by multiauth, used internally
     */
    public function setRegisterKey($key);

    /**
     * @return string the key given by multiauth
     */
    public function getRegisterKey();

    /**
     * @return array configuration parameters
     */
    public function getConfiguration();

    /**
     * @return integer a combination of FEATURE_* constants
     */
    public function getFeature();

    /**
     * @param $login
     * @param $newpassword
     * @return mixed
     */
    public function changePassword($login, $newpassword);

    /**
     * @param object $user
     * @param string $login
     * @param string $password
     * @return int one of VERIF_AUTH_* const
     */
    public function verifyAuthentication($user, $login, $password);
}