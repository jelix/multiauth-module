This is a module for Jelix, providing a plugin for jAuth allowing to authenticate
against several login/password authentication providers.

This module is for Jelix 1.6.21 and higher. 


Installation
============

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
php yourapp/cmd.php install
```

Configuration
=============

TODO
