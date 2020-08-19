Changes
=======

next
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

