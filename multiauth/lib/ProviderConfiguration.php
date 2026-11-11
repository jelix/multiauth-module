<?php
/**
 * @author     Laurent Jouanneau
 * @copyright  2026 Laurent Jouanneau
 * @license   MIT
 */
namespace Jelix\MultiAuth;

class ProviderConfiguration
{
    public readonly string $providerKey;

    public readonly string $providerName;

    public readonly string $providerConfigName;

    public readonly array $configuration;

    public function __construct($providerKey, $providerName, $providerConfigName, $configuration)
    {
        $this->providerKey = $providerKey;
        $this->providerName = $providerName;
        $this->configuration = $configuration;
        $this->providerConfigName = $providerConfigName;
    }
}