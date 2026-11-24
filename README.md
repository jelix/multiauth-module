This is a module for Jelix, providing a plugin for jAuth allowing to authenticate
against several login/password authentication providers.

Installation
============

This module is for Jelix 1.8.28 and higher. It replaces the ldapdao module,
and it is compatible with jauth, jauthdb, jauthdb_admin and jcommunity modules.

Install files with Jelix 1.8+
-----------------------------

You should use Composer to install the module. Run this commands in a shell, in the directory of your application
where there is a composer.json file:

```
composer require "jelix/multiauth-module"
```

You have then to launch the configurator for your application to enable the module.

If you are a developper and want to bundle multiauth with your application you develop:

```bash
php yourapp/dev.php module:configure multiauth
php yourapp/install/installer.php
```

If you are an administrator and want to use multiauth into the application you installed:

```bash
php yourapp/configure.php multiauth
php yourapp/install/installer.php
```

Configuration
=============

The configurator of the module create automatically configuration parameters, 

- configuration parameters for jAuth in the `app/system/*/auth.coord.ini.php` or `var/config/*/auth.coord.ini.php` (depending if the module multiauth is bundled with the application or not).
 Below, this file is referenced as `auth.coord.ini.php`.
- configuration parameters for MultiAuth in `app/system/mainconfig.ini.php` or `var/config/localconfig.ini.php` (depending if the module multiauth is bundled with the application or not).
  Below, this file is referenced as 'application configuration'.
- configuration parameters for the ldap connector in `var/config/profiles.ini.php`

Enabling the multiauth driver
-----------------------------

In the application configuration, you may have :

```ini
[coordplugin_auth]
driver=multiauth
```

It activate the driver of multiauth for jAuth.

Into `auth.coord.ini.php`
------------------------------

In the configuration file `auth.coord.ini.php`, you should have a section `multiauth`
with parameters for jAuth and authdb_admin.

```ini
[multiauth]
; name of the dao to get user data
dao = "jauthdb~jelixuser"

; profile to use for jDb 
profile = "jauth"

; name of the form for the jauthdb_admin module
form = "jauthdb_admin~jelixuser"

; path of the directory where to store files uploaded by the form (jauthdb_admin module)
; should be related to the var directory of the application
uploadsDirectory= ""

; required. Internal use for jAuth. Don't touch it.
compatiblewithdb = on

; you should set it to allow password storage migration, if you have an old
; users table.
; @deprecated
password_crypt_function = sha1
```


Into the application configuration (`mainconfig.ini.php` or `localconfig.ini.php`)
----------------------------------------------------------------------------------

You should have this kind of parameters :

```ini
[multiauth]
; list of authentication providers
providers[]=ldap:multiauth_ldap
providers[]=dbdao:centraldb
providers[]=dbaccounts

; if set to on, when a user login successfully, an account will be created automatically
automaticAccountCreation = on

```

This section can contain any parameters of the `multiauth` section from `auth.coord.ini.php`,
to redefine them without modifying `auth.coord.ini.php`. You can put this section into `localconfig.ini.php`
to redefine parameters either from `auth.coord.ini.php`, or `mainconfig.ini.php`.


The list of providers `providers` is the list of plugin that will be used, one after an
other, to try to authenticate the user by his login/password.

Three providers are provided with the module:

- ldap: to authenticate the user against an ldap. 
- dbaccounts: check the given login/password with the login/password stored into the
  account table (the table used by the dao indicated into the dao configuration
  parameter). 
- dbdao: check the given login/password with the login/password stored into
  a table that is not the account table.

For the `providers` configuration parameter, each item is `<plugin name>:<configuration section>`.
So, in the example above, the configuration for the ldap provider is into
the `multiauth_ldap` section. You should then have :

```ini
[multiauth_ldap]
; profile to use for ldap
ldapprofile = "multiauthldap"
```

For `dbaccounts`, no configuration section indicated, as it is not configurable.


Configuring ldap provider
-------------------------

See LDAP.md to know how to fill a configuration for the ldap plugin.

Configuring dbaccounts provider
--------------------------------

The `dbaccounts` plugin does not need configuration, this is why there is
no a section name.


Configuring dbdao provider
--------------------------

The `dbdao` plugin needs a simple configuration section containing a `dao` and
a `profile` parameter, needed to access to the table containing login/password.
Warning: it must not be the same dao/profile used by the multiauth plugin !
Else you could have some security issue.

With the example above having `providers[]=dbdao:centraldb`, you should set a section `centraldb`:

```ini
[centraldb]
; dao declaring the mapping to the authentication table. It should have a
; "password" and a "login" properties.
dao="main~central_auth_db"
; profile for jDb to access to the database containing the authentication table
profile="centraldb" 
```

Using the same provider multiple time
-------------------------------------

You can use a provider several times. For example, you may want to
use two different ldap to authenticate your users:

```ini
providers[]=ldap:ldapserver1
providers[]=ldap:ldapserver2
```

Obviously you will have two different sections to configure the ldap provider :

```ini
[ldapserver1]
ldapprofile = "ldapserver1"
[ldapserver2]
ldapprofile = "ldapserver2"
```

Note: if you are using the ldapadmin module to have an interface to configure the ldap connection,
ldapadmin only support one ldap configuration. It will configure only the first one. 
