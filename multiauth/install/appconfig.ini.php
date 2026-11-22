;<?php die(''); ?>
;for security reasons , don't remove or modify the first line

[coordplugin_auth]
driver=multiauth

[multiauth]
; list of authentication providers
providers[]=ldap:multiauth_ldap
providers[]=dbaccounts

; when a user login successfully, an account will be created automatically
automaticAccountCreation = on

;------- parameters for the multiauth ldap plugin

[multiauth_ldap]
; profile to use for ldap
ldapprofile = "multiauthldap"
