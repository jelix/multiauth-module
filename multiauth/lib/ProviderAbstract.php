<?php
/**
 * @package    jelix
 * @subpackage auth_driver
 * @author     Laurent Jouanneau
 * @copyright  2019 Laurent Jouanneau
 * @license   MIT
 */
namespace Jelix\MultiAuth;

abstract class ProviderAbstract implements \Jelix\MultiAuth\ProviderPluginInterface
{
    protected $labelLocale = '';

    protected $multiAuthKey = '';
    protected $_params = array();
    protected $passwordHashMethod;
    protected $passwordHashOptions;

    protected $accountsDao;
    protected $accountsDaoProfile;

    public function __construct($params)
    {
        $this->_params = $params;
        $this->passwordHashOptions = $params['password_hash_options'];
        $this->passwordHashMethod = $params['password_hash_method'];
        $this->accountsDao = $params['accountsDao'];
        $this->accountsDaoProfile = $params['accountsDaoProfile'];
        if (isset($params['label'])) {
            $this->label = $params['label'];
        }
    }

    public function getLabel()
    {
        if (isset($this->_params['label'])) {
            return $this->_params['label'];
        } elseif ($this->labelLocale) {
            return \jLocale::get($this->labelLocale);
        }
        return $this->multiAuthKey;
    }

    public function getRegisterKey()
    {
        return $this->multiAuthKey;
    }

    public function setRegisterKey($key)
    {
        $this->multiAuthKey = $key;
    }

    public function getConfiguration()
    {
        return $this->_params;
    }

    /**
     * hash the given password
     * @param string $password the password to hash
     * @return string the hash password
     */
    public function cryptPassword($password, $forceOldHash = false)
    {
        if (!$forceOldHash && $this->passwordHashMethod) {
            return password_hash($password, $this->passwordHashMethod, $this->passwordHashOptions);
        }

        if (isset($this->_params['password_crypt_function'])) {
            $f = $this->_params['password_crypt_function'];
            if ($f != '') {
                if ($f[1] == ':') {
                    $t = $f[0];
                    $f = substr($f, 2);
                    if ($t == '1') {
                        return $f((isset($this->_params['password_salt'])?$this->_params['password_salt']:''), $password);
                    } elseif ($t == '2') {
                        return $f($this->_params, $password);
                    }
                }
                return $f($password);
            }
        }
        return $password;
    }

    /**
     * @param string $givenPassword     the password to verify
     * @param string $currentPasswordHash the hash of the real password
     * @return boolean|string false if password does not correspond. True if it is ok. A string
     * containing a new hash if it is ok and need to store a new hash
     */
    public function checkPassword($givenPassword, $currentPasswordHash)
    {
        if ($currentPasswordHash[0] == '$' && $this->passwordHashMethod) {
            // ok, we have hash for standard API, let's use standard API
            if (!password_verify($givenPassword, $currentPasswordHash)) {
                return false;
            }

            // check if rehash is needed,
            if (password_needs_rehash($currentPasswordHash, $this->passwordHashMethod, $this->passwordHashOptions)) {
                return password_hash($givenPassword, $this->passwordHashMethod, $this->passwordHashOptions);
            }
        } else {
            // verify with the old hash api
            if (!hash_equals($currentPasswordHash, $this->cryptPassword($givenPassword, true))) {
                return false;
            }

            if ($this->passwordHashMethod) {
                // if there is a method to hash with the standard API, let's rehash the password
                return password_hash($givenPassword, $this->passwordHashMethod, $this->passwordHashOptions);
            }
        }
        return true;
    }
}
