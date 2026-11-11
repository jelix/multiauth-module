# Installation of the module into Lizmap

Here instructions to install the module into [Lizmap Web Client](https://www.lizmap.com/).

Lizmap should be installed and should work, before installing the module.

This documentation is for Lizmap 3.6 or higher.

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
