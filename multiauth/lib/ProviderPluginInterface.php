<?php
/**
 * @package    jelix
 * @subpackage auth_driver
 * @author     Laurent Jouanneau
 * @copyright  2019 Laurent Jouanneau*
 * @license   MIT
 */
namespace Jelix\MultiAuth;

interface ProviderPluginInterface
{

    /** password of a user can be changed */
    const FEATURE_CHANGE_PASSWORD = 1;
    /** the provider support login/password */
    const FEATURE_SUPPORT_PASSWORD = 2;
    /**
     * the provider is using the password field in the account table of multiauth
     * to store the hashed password.
     */
    const FEATURE_USE_MULTIAUTH_TABLE = 4;

    const VERIF_AUTH_BAD = 0;
    const VERIF_AUTH_OK = 1;
    const VERIF_AUTH_OK_USER_TO_UPDATE = 3;

    /**
     * ProviderPluginInterface constructor.
     * @param array $params configuration parameters
     */
    public function __construct($params);

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
     * @param string $login
     * @param string $newpassword
     * @return boolean
     */
    public function changePassword($login, $newpassword);

    /**
     * @param object $userAccount
     * @param string $login
     * @param string $password
     * @return int one of VERIF_AUTH_* const
     */
    public function verifyAuthentication($userAccount, $login, $password);

    /**
     * @param string $login
     * @return boolean true if a user with this login exists
     */
    public function userExists($login);
}
