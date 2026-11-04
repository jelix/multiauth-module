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

    public function emergency($message, array $context = array())
    {
        jLog::log($message, 'auth');
        jLog::log($message, 'error');

    }

    public function alert($message, array $context = array())
    {
        jLog::log($message, 'auth');
        jLog::log($message, 'error');
    }

    public function critical($message, array $context = array())
    {
        jLog::log($message, 'auth');
        jLog::log($message, 'error');
    }

    public function error($message, array $context = array())
    {
        jLog::log($message, 'auth');
        jLog::log($message, 'error');
    }

    public function warning($message, array $context = array())
    {
        jLog::log($message, 'auth');
    }

    public function notice($message, array $context = array())
    {
        jLog::log($message, 'auth');
    }

    public function info($message, array $context = array())
    {
        jLog::log($message, 'auth');
    }

    public function debug($message, array $context = array())
    {
        jLog::log($message, 'auth');
    }

    public function log($level, $message, array $context = array())
    {
        jLog::log($message, 'auth');
    }
}
