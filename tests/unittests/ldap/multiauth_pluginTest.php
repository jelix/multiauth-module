<?php
/**
 * @package     multiauth
 * @author      laurent Jouanneau
 * @copyright   2017-2022 laurent Jouanneau
 * @link        http://www.jelix.org
 * @licence     GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
 */


require_once(__DIR__.'/multiauth_plugin_trait.php');

/**
 * Tests API driver LDAP/DAO for jAuth, with no encrypted communication
 * @package     ldapdao
 */
class multiauth_pluginAuthTest extends \Jelix\UnitTests\UnitTestCase {
    use multiauth_plugin_trait;

    protected $ldapPort = 389;

    protected $ldapTlsMode = '';

    protected $ldapProfileName = 'multiauth';
}
