<?php
/**
 * @package    jelix
 * @subpackage auth_driver
 * @author     Laurent Jouanneau
 * @copyright  2019 Laurent Jouanneau
 * @license   MIT
 */

class multiauthListener extends jEventListener
{

    /**
     * @param jEvent $event
     */
    public function onjauthdbAdminGetViewInfo($event)
    {
        if (jAcl2::check("auth.users.create")) {
            $login = $event->form->getData('login');
            $password = $event->form->getData('password');

            $driver = jAuth::getDriver();
            if (get_class($driver) != 'multiauthAuthDriver') {
                return;
            }
            /** @var \Jelix\MultiAuth\ProviderPluginInterface $provider */
            $provider = $driver->getProviderForLogin($login, $password);
            if ($provider) {
                $text = jLocale::get('multiauth~multiauth.user.provider.use', $provider->getLabel());
                $event->add('<p>'.htmlspecialchars($text).'</p>');
            }
        }
    }

    protected function isResetPasswordEnabled() {
        if (jApp::isModuleEnabled('jcommunity') && class_exists("\Jelix\JCommunity\Config")) {
            $config = new \Jelix\JCommunity\Config();
            return $config->isResetAdminPasswordEnabledForAdmin();
        }
        return false;
    }

    /**
     * @param jFormsBase $form
     */
    protected function prepareUserForm($form, $createForm = false)
    {
        /** @var multiauthAuthDriver $authDriver */
        $authDriver = jAuth::getDriver();
        if (!$createForm) {
            $login = $form->getData('login');
            $password = $form->getData('password');
            /** @var \Jelix\MultiAuth\ProviderPluginInterface $provider */
            $userProvider = $authDriver->getProviderForLogin($login, $password);
        } else {
            $userProvider = $authDriver->getDbAccountProvider();
        }

        $isResetPasswordEnabled = $this->isResetPasswordEnabled();

        $choice = new jFormsControlChoice('auth_provider');
        $choice->label = jLocale::get('multiauth~multiauth.choice.provider.label');

        $providers = $authDriver->getProviders();

        foreach ($providers as $pName => $provider) {
            $choice->createItem($pName, $provider->getLabel());

            // display password field only if:
            // - the provider allow to setup a password
            // - and the password should be setup directly (no mail to reset the password)
            // - and this is a form to create a user or if the provider to choice
            //   is not the current provider used for the user (in this case,
            //   the password should be changed in an other way)
            if ($provider->getFeature() & \Jelix\MultiAuth\ProviderPluginInterface::FEATURE_CHANGE_PASSWORD
                &&
                !$isResetPasswordEnabled
                &&
                ($createForm || (
                    $userProvider && $provider->getRegisterKey() != $userProvider->getRegisterKey()
                ))
            ) {
                $id = str_replace(':', '_', $pName);
                $ctrl= new jFormsControlSecret('password_'.$id);
                $ctrl->datatype->addFacet('maxLength', 50);
                $ctrl->label=jLocale::get('multiauth~multiauth.choice.provider.password.label');
                $ctrl->required = true;
                $ctrl2 = new jFormsControlSecretConfirm('password_'.$id.'_confirm');
                $ctrl2->primarySecret = 'password_'.$id;
                $ctrl2->label = jLocale::get('multiauth~multiauth.choice.provider.password.confirm.label');
                $ctrl2->required = true;
                $choice->addChildControl($ctrl, $pName);
                $choice->addChildControl($ctrl2, $pName);
            }
            if ($userProvider && $provider->getRegisterKey() == $userProvider->getRegisterKey()) {
                $choice->defaultValue = $pName;
            }
        }

        $form->addControlBefore($choice, 'password');
        $form->getControl('password')->deactivate(true);
        $form->getControl('password_confirm')->deactivate(true);
    }


    /**
     * @param jEvent $event
     */
    public function onjauthdbAdminPrepareCreate($event)
    {
        $this->prepareUserForm($event->form, true);
    }

    /**
     * @param jEvent $event
     */
    public function onjauthdbAdminEditCreate($event)
    {
        $this->prepareUserForm($event->form, true);
    }

    /**
     * @param jEvent $event
     */
    public function onjauthdbAdminBeforeCheckCreateForm($event)
    {
        $this->prepareUserForm($event->form, true);
    }

    /**
     * @param jEvent $event
     */
    public function onjauthdbAdminCheckCreateForm($event)
    {
        /** @var multiauthAuthDriver $authDriver */
        $authDriver = jAuth::getDriver();
        if (get_class($authDriver) != 'multiauthAuthDriver') {
            return;
        }

        if ($this->isResetPasswordEnabled()) {
            return;
        }
        /** @var jFormsBase $form */
        $form = $event->form;
        $form->getControl('password')->deactivate(false);

        $providerKey = $form->getData('auth_provider');

        $provider = $authDriver->getProviders()[$providerKey];
        if ($provider->getFeature() & \Jelix\MultiAuth\ProviderPluginInterface::FEATURE_CHANGE_PASSWORD) {
            $id = str_replace(':', '_', $providerKey);
            $newPassword = $form->getData('password_'.$id);
            if ($newPassword !== null && trim($newPassword) !== '') {
                $form->setData('password', $newPassword);
            } else {
                $form->setErrorOn('password_'.$id, jLocale::get('multiauth~multiauth.message.bad.password'));
                $event->add(array('check'=>false));
                return;
            }
        } else {
            if ($provider->userExists($form->getData('login'))) {
                $form->setData('password', '!!multiauth:'.$providerKey.'!!');
            } else {
                $event->add(array('check'=>false));
                $form->setErrorOn('auth_provider', jLocale::get('multiauth~multiauth.choice.provider.inexistant.user'));
                return;
            }
        }

        $event->add(array('check'=>true));
    }

    /**
     * @param jEvent $event
     */
    public function onjauthdbAdminPrepareUpdate($event)
    {
        if (!$event->himself && jAcl2::check("auth.users.create")) {
            $this->prepareUserForm($event->form, false);
        }
    }

    /**
     * @param jEvent $event
     */
    public function onjauthdbAdminEditUpdate($event)
    {
        if (!$event->himself && jAcl2::check("auth.users.create")) {
            $this->prepareUserForm($event->form, false);
        }
    }


    /**
     * @param jEvent $event
     */
    public function onjauthdbAdminBeforeCheckUpdateForm($event)
    {
        if (!$event->himself && jAcl2::check("auth.users.create")) {
            $this->prepareUserForm($event->form, false);
        }
    }

    /**
     * @param jEvent $event
     */
    public function onjauthdbAdminCheckUpdateForm($event)
    {
        if (!$event->himself && jAcl2::check("auth.users.create")) {
            /** @var multiauthAuthDriver $authDriver */
            $authDriver = jAuth::getDriver();
            if (get_class($authDriver) != 'multiauthAuthDriver') {
                return;
            }

            /** @var jFormsBase $form */
            $form = $event->form;
            $form->getControl('password')->deactivate(false);

            $currentProvider = $authDriver->getProviderForLogin($form->getData('login'));

            $providerKey = $form->getData('auth_provider');

            if ($providerKey == $currentProvider->getRegisterKey()) {
                // the provider did not changed
                $event->add(array('check'=>true));
                return;
            }

            $provider = $authDriver->getProviders()[$providerKey];

            if (!$provider->userExists($form->getData('login'))) {
                $event->add(array('check'=>false));
                $form->setErrorOn('auth_provider', jLocale::get('multiauth~multiauth.choice.provider.inexistant.user'));
                return;
            }

            if ($provider->getFeature() & \Jelix\MultiAuth\ProviderPluginInterface::FEATURE_CHANGE_PASSWORD) {

                if ($this->isResetPasswordEnabled()) {
                    $pwd = $form->getData('password');
                    if (strpos($pwd, '!!multiauth:') === 0 || $pwd == '!!ldapdao password!!') {
                        $newPassword = \jAuth::getRandomPassword();
                        $form->setData('password', $newPassword);
                        $provider->changePassword($form->getData('login'), $newPassword);
                        jMessage::add(jLocale::get('multiauth~multiauth.account.update.reset.password'), 'warning');
                    }
                }
                else {
                    $id = str_replace(':', '_', $providerKey);
                    $newPassword = $form->getData('password_'.$id);
                    if ($newPassword !== null && trim($newPassword) !== '') {
                        $form->setData('password', $newPassword);
                        $provider->changePassword($form->getData('login'), $newPassword);
                    } else {
                        $form->setErrorOn('password_'.$id, jLocale::get('multiauth~multiauth.message.bad.password'));
                        $event->add(array('check'=>false));
                        return;
                    }
                }
            } else {
                $form->setData('password', '!!multiauth:'.$providerKey.'!!');
                $authDriver->updateProviderInAccount($form->getData('login'), $providerKey);
            }
        }
        $event->add(array('check'=>true));
    }
}
