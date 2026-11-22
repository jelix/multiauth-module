<?php
/**
 * @author     Laurent Jouanneau
 * @copyright  2026 Laurent Jouanneau
 * @license   MIT
 */
namespace Jelix\MultiAuth;

class ProviderConfiguration
{
    /**
     * @var string the name of the key of the provider indicated into the list of providers (`<provider name>:<provider conf name>`)
     */
    public readonly string $providerKey;

    /**
     * @var string type of the provider (ldap, dbdao, dbaccounts...)
     */
    public readonly string $providerName;

    /**
     * @var string name of the configuration section containing the provider configuration
     */
    public readonly string $providerConfigName;

    public readonly bool $providerConfigExists;

    /**
     * @var array configuration of the provider
     */
    public array $configuration;

    public function __construct($providerKey, $providerName, $configuration, $providerConfigName = '', $providerConfigExists = false)
    {
        $this->providerKey = $providerKey;
        $this->providerName = $providerName;
        $this->configuration = $configuration;
        $this->providerConfigName = $providerConfigName;
        if ($providerConfigName == '') {
            $providerConfigExists = false;
        }
        $this->providerConfigExists = $providerConfigExists;
    }

    public function isEnabled()
    {
        return $this->configuration['enabled'] ?? true;
    }
}