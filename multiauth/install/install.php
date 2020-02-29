<?php
/**
 * @author    Laurent Jouanneau
 * @copyright   2019-2020 Laurent Jouanneau
 */
use Jelix\IniFile\IniModifierInterface;
use Jelix\Installer\Module\API\DatabaseHelpers;
use Jelix\Installer\Module\API\InstallHelpers;
use Jelix\Installer\EntryPoint;

class multiauthModuleInstaller extends \Jelix\Installer\Module\Installer
{
    public function install(InstallHelpers $helpers)
    {
        $entryPoint = $helpers->getMainEntryPoint();

        $authConfig = $this->getAuthConf($entryPoint->getConfigIni());

        if ($authConfig['driver'] == 'ldapdao') {
            // we had ldapdao, let's update some things
            $this->restoreRights();
            $this->updatePasswordField($authConfig['ldapdao']['dao'], $authConfig['ldapdao']['profile']);
        }
    }



    protected function restoreRights()
    {
        $group = '';
        if (jAcl2DbUserGroup::getGroup('admin')) {
            $group = 'admin';
        } elseif (jAcl2DbUserGroup::getGroup('admins')) {
            $group = 'admins';
        }

        if ($group) {
            jAcl2DbManager::addRight($group, 'auth.users.create');
            jAcl2DbManager::addRight($group, 'auth.users.change.password');
            jAcl2DbManager::addRight($group, 'auth.user.change.password');
        }
    }

    protected function updatePasswordField($daoSel, $daoProfile)
    {
        $userDao = jDao::get($daoSel, $daoProfile);
        $tableAlias = $userDao->getPrimaryTable();
        $table = $userDao->getTables()[$tableAlias]['realname'];
        $passwordField = $userDao->getProperties()['password']['fieldName'];

        $cnx = jDb::getConnection($daoProfile);
        $cnx->exec('UPDATE '.$cnx->encloseName($table).
            ' SET '.$cnx->encloseName($passwordField).' = '.$cnx->quote('!!multiauth:ldap:multiauth_ldap!!').' '.
            ' WHERE '.$cnx->encloseName($passwordField).' = '.$cnx->quote('!!ldapdao password!!'));
    }

    protected function getAuthConf(IniModifierInterface $configIni)
    {
        $authconfig = $configIni->getValue('auth', 'coordplugins');
        $confPath = jApp::appSystemPath($authconfig);
        $confIni = parse_ini_file($confPath, true);
        return jAuth::loadConfig($confIni);
    }
}
