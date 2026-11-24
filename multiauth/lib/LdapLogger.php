<?php
/**
 * @package    jelix
 * @subpackage auth_driver
 * @author     Laurent Jouanneau
 * @copyright  2026 Laurent Jouanneau
 * @license   MIT
 */
namespace Jelix\MultiAuth;

use jLog;
use Psr\Log\LoggerInterface;


class LdapLogger implements LoggerInterface
{

    public function emergency(string|\Stringable $message, array $context = array()): void
    {
        jLog::log($message, 'auth');
        jLog::log($message, 'error');

    }

    public function alert(string|\Stringable $message, array $context = array()): void
    {
        jLog::log($message, 'auth');
        jLog::log($message, 'error');
    }

    public function critical(string|\Stringable $message, array $context = array()): void
    {
        jLog::log($message, 'auth');
        jLog::log($message, 'error');
    }

    public function error(string|\Stringable $message, array $context = array()): void
    {
        jLog::log($message, 'auth');
        jLog::log($message, 'error');
    }

    public function warning(string|\Stringable $message, array $context = array()): void
    {
        jLog::log($message, 'auth');
    }

    public function notice(string|\Stringable $message, array $context = array()): void
    {
        jLog::log($message, 'auth');
    }

    public function info(string|\Stringable $message, array $context = array()): void
    {
        jLog::log($message, 'auth');
    }

    public function debug(string|\Stringable $message, array $context = array()): void
    {
        jLog::log($message, 'auth');
    }

    public function log($level, string|\Stringable $message, array $context = array()): void
    {
        jLog::log($message, 'auth');
    }
}
