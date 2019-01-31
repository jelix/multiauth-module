This is a module for Jelix, providing a plugin for jAuth allowing to authenticate
against several login/password authentication providers.

Installation
============

This module is for Jelix 1.6.21 and higher. It replaces the ldapdao module,
and it is compatible with jauth, jauthdb, jauthdb_admin and jcommunity modules.

Install files with Jelix 1.7
----------------------------
You should use Composer to install the module. Run this commands in a shell:
                                               
```
composer require "jelix/multiauth-module"
```

Launch the configurator for your application to enable the module

```bash
php yourapp/dev.php module:configure multiauth

php yourapp/install/installer.php

```

Install files with Jelix 1.6.21
-------------------------------

Copy the `multiauth` directory into the modules/ directory of your application.

Next you must say to Jelix that you want to use the module. Declare
it into the mainconfig.ini.php file (into yourapp/var/config/).

In the `[modules]` section, add:

```ini
multiauth.access=1
```

Following modules are required: jacl2, jauth, jauthdb. In this same section 
verify that they are activated:

```ini
jacl2.access=1
jauth.access=2
jauthdb.access=1
```

If you are using the jCommunity module, you should not activate jauthdb, so keep
`jauthdb.access=0`.


In the command line, launch:

```
php yourapp/install/installer.php
```

Configuration
=============

using the `auth.coord.ini.php`
------------------------------

You must modify the configuration file `auth.coord.ini.php`.
 
First, set `driver=multiauth`.

Then you should add a section `multiauth`:

```ini
[multiauth]
; name of the dao to get user data
dao = "jauthdb~jelixuser"

; profile to use for jDb 
profile = "jauth"

; list of authentication providers
providers[]=ldap:multiauth_ldap
providers[]=dbdao:centraldb
providers[]=dbaccounts

; name of the form for the jauthdb_admin module
form = "jauthdb_admin~jelixuser"

; path of the directory where to store files uploaded by the form (jauthdb_admin module)
; should be related to the var directory of the application
uploadsDirectory= ""

; if set to on, when a user login successfully, an account will be created automatically
automaticAccountCreation = on

; required. Internal use for jAuth. Don't touch it.
compatiblewithdb = on

; you should set it to allow password storage migration, if you have an old
; users table.
; @deprecated
password_crypt_function = sha1

```

The list of providers is the list of plugin that will be used, one after an
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


using the `localconfig.ini.php`
-------------------------------

You may want to change some values of the configuration from `auth.coord.ini.php`,
in a specific instance of your application. The multiauth plugin is able to
load its configuration from the `localconfig.ini.php` in addition from,
`auth.coord.ini.php`, so you want have to modify `auth.coord.ini.php`.

In your `localconfig.ini.php`, create a section `multiauth`. It can contains
all parameters that you can set into the `multiauth` of `auth.coord.ini.php`.
The parameters from `localconfig.ini.php` overwrites parameters from `auth.coord.ini.php`.

Same behavior for provider configuration section. 

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

