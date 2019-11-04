<?php
/**
 * @package    jelix
 * @subpackage auth_driver
 * @author     Laurent Jouanneau
 * @copyright  2019 Laurent Jouanneau
 * @license   MIT
 */

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
    protected $labelLocale = 'multiauth~multiauth.provider.ldap.label';

    /**
     * default user attributes list
     * @var array
     */
    protected $_default_attributes = array(
        "cn"=>"lastname",
        "name"=>"firstname"
    );

    protected $uriConnect = '';

    /**
     * @inheritdoc
     */
    public function __construct($params)
    {
        if (!extension_loaded('ldap')) {
            throw new jException('multiauth~ldap.error.extension.unloaded');
        }
        parent::__construct($params);

        if (!isset($this->_params['ldapprofile']) || $this->_params['ldapprofile'] == '') {
            throw new jException('multiauth~ldap.error.ldap.profile.missing');
        }

        $profile = jProfiles::get('ldap', $this->_params['ldapprofile']);
        $this->_params = array_merge($this->_params, $profile);

        // default ldap parameters
        $_default_params = array(
            'hostname'      =>  'localhost',
            'port'          =>  389,
            'tlsMode'       => '',
            'adminUserDn'      =>  null,
            'adminPassword'      =>  null,
            'protocolVersion'   =>  3,
            'searchUserBaseDN' => '',
            'searchGroupFilter' => '',
            'searchGroupKeepUserInDefaultGroups' => true,
            'searchGroupProperty' => '',
            'searchGroupBaseDN' => ''
        );

        // iterate each default parameter and apply it to actual params if missing in $params.
        foreach ($_default_params as $name => $value) {
            if (!isset($this->_params[$name]) || $this->_params[$name] == '') {
                $this->_params[$name] = $value;
            }
        }

        if ($this->_params['searchUserBaseDN'] == '') {
            throw new jException('multiauth~ldap.error.search.base.missing');
        }

        if (!isset($this->_params['searchAttributes']) || $this->_params['searchAttributes'] == '') {
            $this->_params['searchAttributes'] = $this->_default_attributes;
        } else {
            $attrs = explode(",", $this->_params['searchAttributes']);
            $this->_params['searchAttributes'] = array();
            foreach ($attrs as $attr) {
                if (strpos($attr, ':') === false) {
                    $attr = trim($attr);
                    $this->_params['searchAttributes'][$attr] = $attr;
                } else {
                    $attr = explode(':', $attr);
                    $this->_params['searchAttributes'][trim($attr[0])] = trim($attr[1]);
                }
            }
        }

        if (!isset($this->_params['searchUserFilter']) || $this->_params['searchUserFilter'] == '') {
            throw new jException('multiauth~ldap.error.searchUserFilter.missing');
        }
        if (!is_array($this->_params['searchUserFilter'])) {
            $this->_params['searchUserFilter'] = array($this->_params['searchUserFilter']);
        }

        if (!isset($this->_params['bindUserDN']) || $this->_params['bindUserDN'] == '') {
            throw new jException('multiauth~ldap.error.bindUserDN.missing');
        }
        if (!is_array($this->_params['bindUserDN'])) {
            $this->_params['bindUserDN'] = array($this->_params['bindUserDN']);
        }

        $uri = $this->_params['hostname'].':'.$this->_params['port'];

        if (preg_match('!^ldap(s?)://!', $uri, $m)) { // old way to specify ldaps protocol
            if (isset($m[1]) && $m[1] == 's') {
                $this->_params['tlsMode'] = 'ldaps';
            }
            elseif ($this->_params['tlsMode'] == 'ldaps') {
                $this->_params['tlsMode'] = 'starttls';
            }
        }
        else {
            if ($this->_params['tlsMode'] == 'ldaps' || $this->_params['port'] == 636 ) {
                $this->uriConnect = 'ldaps://'.$uri;
                $this->_params['tlsMode'] = 'ldaps';
            }
            else {
                $this->uriConnect = 'ldap://'.$uri;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getFeature()
    {
        return self::FEATURE_SUPPORT_PASSWORD;
    }

    /**
     * @inheritdoc
     */
    public function changePassword($login, $newpassword)
    {
        throw new jException('multiauth~ldap.error.unsupported.password.change');
    }

    /**
     * @inheritdoc
     */
    public function verifyAuthentication($user, $login, $password)
    {
        if (trim($password) == '' || trim($login) == '') {
            // we don't want Unauthenticated Authentication
            // and Anonymous Authentication
            // https://tools.ietf.org/html/rfc4513#section-5.1
            return self::VERIF_AUTH_BAD;
        }

        $connectAdmin = $this->_bindLdapAdminUser();
        if (!$connectAdmin) {
            return self::VERIF_AUTH_BAD;
        }

        // see if the user exists into the ldap directory
        $userLdapAttributes = $this->searchLdapUserAttributes($connectAdmin, $login, $user);
        if ($userLdapAttributes === false) {
            jLog::log('multiauth ldap: user '.$login.' not found into the ldap', 'auth');
            return self::VERIF_AUTH_BAD;
        }

        $connect = $this->_getLinkId();
        if (!$connect) {
            jLog::log('multiauth ldap: impossible to connect to ldap', 'auth');
            return self::VERIF_AUTH_BAD;
        }
        // authenticate user. let's try with all configured DN
        $userDn = $this->bindUser($connect, $userLdapAttributes, $user->login, $password);
        ldap_close($connect);

        if ($userDn === false) {
            jLog::log('multiauth ldap: cannot authenticate to ldap with given bindUserDN for the login ' . $login. '. Wrong DN or password', 'auth');
            foreach ($this->bindUserDnTries as $dn) {
                jLog::log('multiauth ldap:  tried to connect with bindUserDN=' . $dn, 'auth');
            }
            return self::VERIF_AUTH_BAD;
        }

        // retrieve the user group (if relevant)
        $userGroups = $this->searchUserGroups($connectAdmin, $userDn, $userLdapAttributes, $user->login);
        ldap_close($connectAdmin);
        if ($userGroups !== false) {
            // the user is at least in a ldap group, so we synchronize ldap groups
            // with jAcl2 groups
            $this->synchronizeAclGroups($user->login, $userGroups);
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
     * @return string[]|false  ldap attributes or false if not found
     */
    protected function searchLdapUserAttributes($connect, $login, $user)
    {
        $searchAttributes = array_keys($this->_params['searchAttributes']);
        foreach ($this->_params['searchUserFilter'] as $searchUserFilter) {
            $filter = str_replace(
                array('%%LOGIN%%', '%%USERNAME%%'), // USERNAME deprecated
                $login,
                $searchUserFilter
            );
            $search = ldap_search(
                $connect,
                $this->_params['searchUserBaseDN'],
                $filter,
                $searchAttributes
            );
            if ($search && ($entry = ldap_first_entry($connect, $search))) {
                $attributes = ldap_get_attributes($connect, $entry);
                return $this->readLdapAttributes($attributes, $user);
            }
        }
        return false;
    }

    protected $bindUserDnTries = array();

    protected function bindUser($connect, $userAttributes, $login, $password)
    {
        $bind = false;
        $this->bindUserDnTries = array();
        foreach ($this->_params['bindUserDN'] as $dn) {
            if (preg_match('/^\\$\w+$/', trim($dn))) {
                $dnAttribute = substr($dn, 1);
                if (isset($userAttributes[$dnAttribute])) {
                    $realDn = $userAttributes[$dnAttribute];
                } else {
                    continue;
                }
            } elseif (preg_match_all('/(\w+)=%\?%/', $dn, $m)) {
                $realDn = $dn;
                foreach ($m[1] as $k => $attr) {
                    if (isset($userAttributes[$attr])) {
                        $realDn = str_replace($m[0][$k], $attr.'='.$userAttributes[$attr], $realDn);
                    } else {
                        continue 2;
                    }
                }
            } else {
                $realDn = str_replace(
                    array('%%LOGIN%%', '%%USERNAME%%'), // USERNAME deprecated
                    $login,
                    $dn
                );
            }
            $bind = @ldap_bind($connect, $realDn, $password);
            if ($bind) {
                break;
            } else {
                jLog::log('multiauth ldap: error when trying to connect with '.$realDn.': '.ldap_errno($connect).':'.ldap_error($connect), 'auth');
                $this->bindUserDnTries[] = $realDn;
            }
        }
        return ($bind ? $realDn : false);
    }


    protected function readLdapAttributes($attributes, $user)
    {
        $mapping = $this->_params['searchAttributes'];
        $ldapAttributes = array();
        foreach ($attributes as $ldapAttr => $attr) {
            if (isset($attr['count']) && $attr['count'] > 0) {
                if ($attr['count'] > 1) {
                    $val = array_shift($attr);
                } else {
                    $val = $attr[0];
                }
                $ldapAttributes[$ldapAttr] = $val;
                if (isset($mapping[$ldapAttr])) {
                    $objAttr = $mapping[$ldapAttr];
                    unset($mapping[$ldapAttr]);
                    if ($objAttr != '') {
                        $user->$objAttr = $val;
                    }
                }
            }
        }

        foreach ($mapping as $ldapAttr => $objAttr) {
            if ($objAttr != '' && !isset($user->$objAttr)) {
                $user->$objAttr = '';
            }
        }
        return $ldapAttributes;
    }

    protected function searchUserGroups($connect, $userDn, $userLdapAttributes, $login)
    {
        // Do not search for groups if no group filter passed
        // Usefull to forbid the driver to sync groups from LDAP and loose all related groups for the user
        if ($this->_params['searchGroupFilter'] == '') {
            return false;
        }

        $searchStr = array_keys($userLdapAttributes);
        $searchStr[] = 'USERDN';
        $searchStr[] = 'LOGIN';
        $searchStr[] = 'USERNAME'; // USERNAME deprecated
        $searchStr = array_map(function ($val) {
            return '%%'.$val.'%%';
        }, $searchStr);
        $values = array_values($userLdapAttributes);
        // escape parenthesis
        $values = array_map(function ($val) {
            return str_replace(
                array('(', ')'),
                array('\\(', '\\)'),
                $val
            );
        }, $values);
        $values[] = $userDn;
        $values[] = $login;
        $values[] = $login;

        $filter = str_replace(
            $searchStr,
            $values,
            $this->_params['searchGroupFilter']
        );
        $grpProp = $this->_params['searchGroupProperty'];

        $groups = array();
        $search = ldap_search(
            $connect,
            $this->_params['searchGroupBaseDN'],
            $filter,
            array($grpProp)
        );
        if ($search) {
            $entry = ldap_first_entry($connect, $search);
            if ($entry) {
                do {
                    $attributes = ldap_get_attributes($connect, $entry);
                    if (isset($attributes[$grpProp]) && $attributes[$grpProp]['count'] > 0) {
                        $groups[] = $attributes[$grpProp][0];
                    }
                } while ($entry = ldap_next_entry($connect, $entry));
            }
        }
        return $groups;
    }

    /**
     * open the connection to the ldap server
     */
    protected function _getLinkId()
    {
        if ($connect = ldap_connect($this->uriConnect)) {
            ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, $this->_params['protocolVersion']);
            ldap_set_option($connect, LDAP_OPT_REFERRALS, 0);

            if ($this->_params['tlsMode'] == 'starttls') {
                if (!ldap_start_tls($connect)) {
                    jLog::log('ldapdao: connection error: impossible to start TLS connection: '.ldap_errno($connect).':'.ldap_error($connect), 'auth');
                    return false;
                }
            }
            return $connect;
        }
        return false;
    }

    /**
     * open the connection to the ldap server
     * and bind to the admin user
     * @return resource|false the ldap connection
     */
    protected function _bindLdapAdminUser()
    {
        $connect = $this->_getLinkId();
        if (!$connect) {
            jLog::log('multiauth ldap: impossible to connect to ldap', 'auth');
            return false;
        }

        if ($this->_params['adminUserDn'] == '') {
            $bind = ldap_bind($connect);
        } else {
            $bind = ldap_bind($connect, $this->_params['adminUserDn'], $this->_params['adminPassword']);
        }
        if (!$bind) {
            if ($this->_params['adminUserDn'] == '') {
                jLog::log('multiauth ldap: impossible to authenticate to ldap as anonymous admin user', 'auth');
            } else {
                jLog::log('multiauth ldap: impossible to authenticate to ldap with admin user '.$this->_params['adminUserDn'], 'auth');
            }
            ldap_close($connect);
            return false;
        }
        return $connect;
    }

    /**
     * @inheritdoc
     */
    public function userExists($login)
    {
        $connectAdmin = $this->_bindLdapAdminUser();
        if (!$connectAdmin) {
            return false;
        }

        // see if the user exists into the ldap directory
        foreach ($this->_params['searchUserFilter'] as $searchUserFilter) {
            $filter = str_replace(
                array('%%LOGIN%%', '%%USERNAME%%'), // USERNAME deprecated
                $login,
                $searchUserFilter
            );
            $search = ldap_search(
                $connectAdmin,
                $this->_params['searchUserBaseDN'],
                $filter,
                array('dn')
            );
            if ($search && ($entry = ldap_first_entry($connectAdmin, $search))) {
                return true;
            }
        }
        return false;
    }
}
