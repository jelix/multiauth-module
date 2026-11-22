;<?php die(''); ?>
;for security reasons , don't remove or modify the first line

[ldap:multiauthldap]
; address of the ldap server
hostname=
; port of the ldap server
port=389
; secure mode for TLS. Value is empty, "starttls" or "ldaps" (ldaps by default if port 636)
tlsMode=starttls

; DN of the user having right to search into the ldap. ex: "cn=admin,ou=admins,dc=acme"
adminUserDn=""

; password for the admin user
adminPassword=""


; base dn to search users. Used to search a user using the filter from searchUserFilter
; example for Active Directory: "ou=ADAM users,o=Microsoft,c=US", or "OU=Town,DC=my-town,DC=com"
searchUserBaseDN="dc=XY,dc=fr"
; it can be a list with several base DN
; searchUserBaseDN[]=...
; searchUserBaseDN[]=...

; filter to get user information, with the given login name
; example for Active Directory: "(sAMAccountName=%%LOGIN%%)"
searchUserFilter="(&(objectClass=posixAccount)(uid=%%LOGIN%%))"
; it can be a list:
;searchUserFilter[]=...
;searchUserFilter[]=...

; attributes to retrieve for a user
; for dao mapping: "ldap attribute:dao attribute"
; ex: "uid:login,givenName:firstname,mail:email" : uid goes into the login property,
; ldap attribute givenName goes to the property firstname etc..
; example for Active Directory: "cn,distinguishedName,name"
; or "sAMAccountName:login,givenName:firstname,sn:lastname,mail:email,distinguishedName,name,dn"
searchAttributes="uid:login,givenName:firstname,sn:lastname,mail:email"

; the ldap attribute containing the DN of the user
bindAttribute="dn"

; activate or deactivate the retrieval of user's groups
featureGroupLoad=off

; search ldap filter to retrieve groups of a user.
; The user will be assign to jAcl2 groups having the same name of ldap groups.
; Leave empty if you don't want this synchronisation between jAcl2 groups and
; ldap groups.
; !!! IMPORTANT !!! : if searchGroupFilter is not empty,
; the plugin will remove the user from all existing jelix groups
; (except default groups if searchGroupKeepUserInDefaultGroups is set to on)
; and only keep the relation between the user and the group retrieved from LDAP
;searchGroupFilter="(&(objectClass=posixGroup)(cn=XYZ*)(memberUid=%%LOGIN%%))"
searchGroupFilter=

; if set to on, users are set in default groups during groups synchronization
; default is on.
searchGroupKeepUserInDefaultGroups = on

; the property in the ldap entry corresponding to a group, that indicate the
; the group name
searchGroupProperty="cn"

; base dn to search groups. Used to search a group using the filter from searchGroupFilter
searchGroupBaseDN=""

