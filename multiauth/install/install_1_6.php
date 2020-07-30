<?php
/**
 * @author    Laurent Jouanneau
 * @copyright   2019-2020 Laurent Jouanneau
 */


class multiauthModuleInstaller extends jInstallerModule
{
    public function install()
    {
        $authConfig = $this->getAuthConf();

        if ($this->firstExec('acl2')) {
            // in case we had ldapdao, let's update some things
            $this->restoreRights();
            if (array_key_exists('ldapdao', $authConfig) &&
                array_key_exists('dao', $authConfig['ldapdao']) &&
                $authConfig['ldapdao']['dao'] !== ''
            ) {
                if (array_key_exists('profile', $authConfig['ldapdao'])) {
                    $profile = $authConfig['ldapdao']['profile'];
                }
                else {
                    $profile = '';
                }

                $this->updatePasswordField($authConfig['ldapdao']['dao'], $profile);
            }
        }

        $appConfig = $this->getAppConfig();
        $appConfig->setValue('driver', 'multiauth', 'coordplugin_auth');
        $appConfig->save();

        if (!$this->getParameter('noconfigfile')) {
            $this->copyFile('auth_multi.coord.ini.php', 'config:auth_multi.coord.ini.php', false);
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

        try {
            $cnx = jDb::getConnection($daoProfile);
            $cnx->exec('UPDATE '.$cnx->encloseName($table).
                ' SET '.$cnx->encloseName($passwordField).' = '.$cnx->quote('!!multiauth:ldap:multiauth_ldap!!').' '.
                ' WHERE '.$cnx->encloseName($passwordField).' = '.$cnx->quote('!!ldapdao password!!'));

        }
        catch (Exception $e) {
            echo "\nWARNING: user table not updated to migrate from ldapdao to multiauth, because of this error:\n";
            echo $e->getMessage();
            echo "\n";
        }
    }

    protected function getAppConfig() {
        if ($this->getParameter('localconfig')) {
            $appConfig = $this->entryPoint->localConfigIni->getMaster();
        } else {
            $appConfig = $this->entryPoint->configIni->getMaster();
        }
        return $appConfig;
    }

    protected function getAuthConf()
    {
        $authconfig = $this->config->getValue('auth', 'coordplugins');
        $confPath = jApp::configPath($authconfig);
        $confIni = parse_ini_file($confPath, true);
        return jAuth::loadConfig($confIni);
    }
}
