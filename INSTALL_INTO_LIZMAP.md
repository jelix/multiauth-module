# Installation of the module into Lizmap

Here instructions to install the module into [Lizmap Web Client](https://www.lizmap.com/).

Lizmap should be installed and should work, before installing the module.

This documentation is for Lizmap 3.4 or higher.

It is recommended to install the module with [Composer](https://getcomposer.org), 
the package manager for PHP. But you can also install manually the sources.

## Automatic installation with Composer  Into Lizmap 3.6 or higher

* into `lizmap/my-packages`, create the file `composer.json` (if it doesn't exist)
  by copying the file `composer.json.dist`, and install the modules with Composer:

```bash
cp -n lizmap/my-packages/composer.json.dist lizmap/my-packages/composer.json
composer require --working-dir=lizmap/my-packages "jelix/multiauth-module"
```

* Then go into `lizmap/install/` and execute Lizmap install scripts :

```bash
php configurator.php multiauth
php installer.php
./clean_vartmp.sh
./set_rights.sh
```

## Manual installation without Composer Into Lizmap 3.6 or higher

* Get the last ZIP archive in the [release page](https://github.com/jelix/multiauth-module/releases) of
  the GitHub repository.
* Extract the archive and copy the `multiauth` directory in the Lizmap Web Client folder `lizmap/lizmap-modules/`
* enable the module with:

```bash
php lizmap/install/configurator.php multiauth
```

* Then execute Lizmap install scripts into `lizmap/install/` :

```bash
php lizmap/install/installer.php
./lizmap/install/clean_vartmp.sh
./lizmap/install/set_rights.sh
```


### Automatic installation with Composer into Lizmap 3.4 and 3.5

* into `lizmap/my-packages`, create the file `composer.json` (if it doesn't exist)
  by copying the file `composer.json.dist`, and install the modules with Composer:

```bash
cp -n lizmap/my-packages/composer.json.dist lizmap/my-packages/composer.json
composer require --working-dir=lizmap/my-packages "jelix/multiauth-module"
```

* Edit the config file `lizmap/var/config/localconfig.ini.php` and add or modify these values into
  the section `[modules]`:

```ini
ldapdao.access=0
multiauth.access=2
jauth.access=2
```


* Then go into `lizmap/install/` and execute Lizmap install scripts :

```bash
php installer.php
./clean_vartmp.sh
./set_rights.sh
```

You can configure the module. See the "Configuration" section of the README.md file.

### Manual installation without Composer Into Lizmap 3.4 or 3.5

* Get the last ZIP archive in the [release page](https://github.com/jelix/multiauth-module/releases) of
  the GitHub repository.
* Extract the archive and copy the `multiauth` directory in the Lizmap Web Client folder `lizmap/lizmap-modules/`
* Edit the config file `lizmap/var/config/localconfig.ini.php` and add or modify these values into
  the section `[modules]`:

```ini
ldapdao.access=0
multiauth.access=2
jauth.access=2
```

* Then execute Lizmap install scripts into `lizmap/install/` :

```bash
php lizmap/install/installer.php
./lizmap/install/clean_vartmp.sh
./lizmap/install/set_rights.sh
```
