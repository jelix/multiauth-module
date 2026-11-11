Changes
=======

next
-----

Compatibility PHP 8.2 minimum.

Drop support of Jelix 1.7 and Lizmap 3.5 or lower.

**Improve the ldap connector**

It has been rewritten, is more secured and brings some new features:

- possibility to use a client TLS certificate. Warning: it can be used only on a PHP server serving
  a single application, since the certificate is persistent in a process (this is a limitation of the openldap library)
- possibility to enable the change of the password


**Removed configuration parameters for ldap**

- `bindUserDn` has been removed. You must fill  `searchGroupBaseDN` and you may
  have to fill the new parameter `bindAttribute`, if needed.

**New configuration parameters for ldap**

- `bindAttribute`: indicate the ldap attribute containing the DN of the user. (by default: `dn`)
- `networkTimeout`: timeout in seconds to connect to the server (10 by default).
- `timeLimit`: time limit in seconds for search operations (10 by default).
- `clientTLSCACertificateFile`: path to a custom or self-signed CA certificate
- `clientTLSCertificateFile`: path to the client Certificate
- `clientTLSKeyFile`: path to the private key for the client Certificate
- `featureGroupLoad` to enable/disable the synchronization of rights
- `featureChangePassword` to enable/disable the possibility for the user to change his password
- `passwordLdapHashAlgo`: the hash algorithm to use to send the new password to Ldap.

How to migrate:

The configuration is automatically migrated. However, if the authentication does
not work, check if `searchUserBaseDN` and `bindAttribute` are set correctly.


v1.2.2
------

- Adds the `getDao()` method on the authentication plugin for applications that are using Jelix 1.8.3.

v1.2.1
------

Fix installation

v1.2.0
------

Compatibility with PHP 8.1 and Jelix 1.8

v1.1.4
-----

- more log for ldap connection in case of problems.

v1.1.3
------

- Fix support of ldapdao password field in case the migration did not occured

v1.1.2
------

- Fix support of reset password by mail during user update/creation, when using the jCommunity module
- More verbosity in logs when something fails during ldap authentication
- Fix the support of the port for the connection to a ldap server.

v1.1.1
------

- fix an issue when login with ldap and having ldap login not in the same case
  as the login given by the user.
- installer for Jelix 1.7+

v1.1.0
------

- Ldap: improve support of ldaps/starttls
- Improvement: Allow to use localconfig.ini.php to configure the multiauth plugin
- portuguese translation
- Fix the check of the password to get the provider name
- Fix Jelix listeners: be sure we get the multiauth driver
- Fix Exception messages


v1.0.0
-------

Initial version

