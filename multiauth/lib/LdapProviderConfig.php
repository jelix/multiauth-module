<?php
/**
 * @author     Laurent Jouanneau
 * @copyright  2026 Laurent Jouanneau
 * @license   MIT
 */
namespace Jelix\MultiAuth;

use jException;
use jProfiles;

class LdapProviderConfig
{
    public static function hasConfiguration(array $providerConfig): bool
    {
        if (!isset($providerConfig['ldapprofile']) || $providerConfig['ldapprofile'] == '') {
            return false;
        }
        try {
            $profile = jProfiles::get('ldap', $providerConfig['ldapprofile']);
        }
        catch(\Exception $e) {
            return false;
        }
        return !!$profile;
    }

    public static function loadFullConfiguration(array $providerConfig) : array
    {
        if (!isset($providerConfig['ldapprofile']) || $providerConfig['ldapprofile'] == '') {
            throw new ProviderConfigurationException(\jLocale::get('multiauth~ldap.error.ldap.profile.missing'), 8);
        }
        try {
            $profile = jProfiles::get('ldap', $providerConfig['ldapprofile']);
        }
        catch(\Exception $e) {
            throw new ProviderConfigurationException('Ldap profile is missing', 9);
        }

        $providerConfig = array_merge($providerConfig, $profile);

        // default parameters
        $_default_params = array(
            'searchGroupKeepUserInDefaultGroups' => true,
        );

        // iterate each default parameter and apply it to actual params if missing in $params.
        foreach ($_default_params as $name => $value) {
            if (!isset($providerConfig[$name]) || $providerConfig[$name] == '') {
                $providerConfig[$name] = $value;
            }
        }

        return $providerConfig;
    }
}
