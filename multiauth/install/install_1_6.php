<?php
/**
* @author    Laurent Jouanneau
*/


class multiauthModuleInstaller extends jInstallerModule
{
    public function install()
    {
        $confIni = parse_ini_file($this->getAuthConfFile(), true);
        $authConfig = jAuth::loadConfig($confIni);

        if ($this->firstExec('acl2')) {
            if ($authConfig['driver'] == 'ldapdao') {
                // we had ldapdao, let's update some things
                $this->restoreRights();
                $this->updatePasswordField($authConfig['ldapdao']['dao'], $authConfig['ldapdao']['profile']);
            }
        }

        if ($this->getParameter('localconfig')) {
            $appConfig = $this->entryPoint->localConfigIni->getMaster();
        } else {
            $appConfig = $this->entryPoint->configIni->getMaster();
        }
        $appConfig->setValue('driver', 'multiauth', 'coordplugin_auth');
        $appConfig->save();

        if (!$this->getParameter('noconfigfile')) {
            $this->copyFile('auth_multi.coord.ini.php', 'config:auth_multi.coord.ini.php', false);
        }
    }

    protected function isJelix17()
    {
        return method_exists('jApp', 'appSystemPath');
    }

    protected function getAuthConfFile()
    {
        $authconfig = $this->config->getValue('auth', 'coordplugins');
        if ($this->isJelix17()) {
            $confPath = jApp::appSystemPath($authconfig);
        } else {
            $confPath = jApp::configPath($authconfig);
        }
        return $confPath;
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
}
