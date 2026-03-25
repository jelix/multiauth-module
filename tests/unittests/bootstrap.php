<?php
require_once(__DIR__.'/../testapp/application.init.php');
require_once(__DIR__.'/../../multiauth/lib/ProviderPluginInterface.php');
require_once(__DIR__.'/../../multiauth/lib/ProviderAbstract.php');
require_once(__DIR__.'/../../multiauth/plugins/multiauth/dbaccounts/dbaccounts.multiauth.php');
require_once(__DIR__.'/../../multiauth/plugins/multiauth/dbdao/dbdao.multiauth.php');
require_once(__DIR__.'/../../multiauth/plugins/multiauth/ldap/ldap.multiauth.php');

define('TESTAPP_URL', 'http://multiauth.local/');
define('TESTAPP_URL_HOST', 'multiauth.local');
define('TESTAPP_URL_HOST_PORT', 'multiauth.local');
define('TESTAPP_HOST', 'multiauth.local');
define('TESTAPP_PORT', '');

define('TESTAPP_LDAP_HOST', 'ldap.jelix');


jApp::setEnv('jelixtests');
if (file_exists(jApp::tempPath())) {
    jAppManager::clearTemp(jApp::tempPath());
} else {
    jFile::createDir(jApp::tempPath(), intval("775",8));
}
