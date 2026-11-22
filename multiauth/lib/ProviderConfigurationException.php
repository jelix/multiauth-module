<?php
/**
 * @author     Laurent Jouanneau
 * @copyright  2026 Laurent Jouanneau
 * @license   MIT
 */
namespace Jelix\MultiAuth;

class ProviderConfigurationException extends \Exception
{

    public readonly string $providerName;

    public readonly string $providerConfigName;

    public function __construct(string $message = "", int $code = 0, string $providerName = '', string $providerConfigName = '')
    {
        $this->providerName = $providerName;
        $this->providerConfigName = $providerConfigName;

        parent::__construct($message, $code);
    }
}