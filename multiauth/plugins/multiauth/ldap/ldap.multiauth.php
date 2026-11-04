<?php
/**
 * @package    jelix
 * @subpackage auth_driver
 * @author     Laurent Jouanneau
 * @copyright  2019 Laurent Jouanneau
 * @license   MIT
 */

use Jelix\Ldap\LdapClient;
use Jelix\MultiAuth\LdapLogger;
use \Jelix\MultiAuth\ProviderAbstract;

/**
 * authentication provider for the multiauth plugin
 *
 * it checks authentication into an ldap
 *
 * @package    jelix
 * @subpackage multiauth_provider
 * @internal see https://tools.ietf.org/html/rfc4510
 */
class ldapProvider extends ProviderAbstract
{
    /**
     * @var LdapClient
     */
    protected $ldapClient;

    /**
     * @inheritdoc
     */
    public function __construct($params)
    {
        parent::__construct($params);

        if (!isset($this->_params['ldapprofile']) || $this->_params['ldapprofile'] == '') {
            throw new jException('multiauth~ldap.error.ldap.profile.missing');
        }

        $profile = jProfiles::get('ldap', $this->_params['ldapprofile']);
        $this->_params = array_merge($this->_params, $profile);

        // default parameters
        $_default_params = array(
            'searchGroupKeepUserInDefaultGroups' => true,
        );

        // iterate each default parameter and apply it to actual params if missing in $params.
        foreach ($_default_params as $name => $value) {
            if (!isset($this->_params[$name]) || $this->_params[$name] == '') {
                $this->_params[$name] = $value;
            }
        }

        $this->_params['featureCreateUser'] = false;
        $this->_params['featureUpdateUser'] = false;
        $this->_params['featureDeleteUser'] = false;


        $logger = new LdapLogger();

        $this->ldapClient = new LdapClient($this->_params, $logger);
    }

    /**
     * @inheritdoc
     */
    public function getFeature()
    {
        $feature = self::FEATURE_SUPPORT_PASSWORD;
        if ($this->ldapClient->hasFeature($this->ldapClient::FEATURE_CHANGE_PASSWORD)) {
            $feature |= self::FEATURE_CHANGE_PASSWORD;
        }

        return $feature;
    }

    /**
     * @inheritdoc
     */
    public function changePassword($login, $newpassword)
    {
        if ($this->ldapClient->hasFeature($this->ldapClient::FEATURE_CHANGE_PASSWORD)) {
            return $this->ldapClient->changePassword($login, $newpassword);
        }
        throw new jException('multiauth~ldap.error.unsupported.password.change');
    }

    /**
     * @inheritdoc
     */
    public function verifyAuthentication($user, $login, $password)
    {
        try {
            $ldapAttributes = $this->ldapClient->verifyAuthentication($login, $password);
            if ($ldapAttributes === false) {
                return self::VERIF_AUTH_BAD;
            }

            foreach($ldapAttributes->userAttributes as $attribute => $value) {
                $user->$attribute = $value;
            }

            if ($ldapAttributes->groups) {
                // the user is at least in a ldap group, so we synchronize ldap groups
                // with jAcl2 groups
                $this->synchronizeAclGroups($user->login, $ldapAttributes->groups);
            }

        } catch(Exception $e) {
            return self::VERIF_AUTH_BAD;
        }

        return self::VERIF_AUTH_OK;
    }

    protected function synchronizeAclGroups($login, $userGroups)
    {
        if ($this->_params['searchGroupKeepUserInDefaultGroups']) {
            // Add default groups
            $gplist = jDao::get('jacl2db~jacl2group', 'jacl2_profile')
                ->getDefaultGroups();
            foreach ($gplist as $group) {
                $idx = array_search($group->name, $userGroups);
                if ($idx === false) {
                    $userGroups[] = $group->name;
                }
            }
        }

        // we know the user group: we should be sure it is the same in jAcl2
        $gplist = jDao::get('jacl2db~jacl2groupsofuser', 'jacl2_profile')
            ->getGroupsUser($login);
        $groupsToRemove = array();
        foreach ($gplist as $group) {
            if ($group->grouptype == 2) { // private group
                continue;
            }
            $idx = array_search($group->name, $userGroups);
            if ($idx !== false) {
                unset($userGroups[$idx]);
            } else {
                $groupsToRemove[] = $group->name;
            }
        }
        foreach ($groupsToRemove as $group) {
            jAcl2DbUserGroup::removeUserFromGroup($login, $group);
        }
        foreach ($userGroups as $newGroup) {
            if (jAcl2DbUserGroup::getGroup($newGroup)) {
                jAcl2DbUserGroup::addUserToGroup($login, $newGroup);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function userExists($login)
    {
        try {
            return $this->ldapClient->userExists($login);
        }
        catch(Exception $e) {
            return false;
        }
    }

}
