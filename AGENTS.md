# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Jelix framework package (`jelix/multiauth-module`) providing a Jelix module:

- **`multiauth/`**: a `jAuth` driver plugin that authenticates users against several
  login/password providers (ldap, database accounts, another database table), one after
  the other. It replaces the older `ldapdao` module and is compatible with `jauth`,
  `jauthdb`, `jauthdb_admin` and `jcommunity`.

It is a standard Jelix module (`module.xml`, `install/`, `events.xml`, `locales/`, ...).

## Running tests

Tests require a full Jelix application (`tests/testapp/`) plus an OpenLDAP server, run
via Docker Compose from `tests/`:

```bash
cd tests
./run-docker build      # first time only
./run-docker            # start containers (app at http://localhost:8842 or http://multiauth.local:8842)
./appctl ldapreset              # (re)populate the test ldap directory
./appctl install-module-local   # configure+install the multiauth module into the test app
```

Other useful `./appctl <cmd>` commands (run inside the php container): `reset`,
`clean_tmp`, `composer-install`, `composer-update`, `shell` / `shellroot`,
`ldapusers` (list ldap test users), `ldapreset` (restore default ldap users).

Test accounts: `admin`/`admin` (db account), `john`/`passjohn` and `jane`/`passjane`
(ldap users, in ldap groups `group1`/`group2`).

Run the PHPUnit suite:

```bash
./appctl unit-tests
```

Tests live in `tests/unittests/ldap/` (config: `tests/unittests/phpunit.xml`,
bootstrap: `tests/unittests/bootstrap.php`, which boots `tests/testapp/application.init.php`
and requires the provider plugin files directly — no autoloading of the module itself).
To run a single test file or method, use phpunit's normal selectors against that config,
e.g. (from inside the php container, where the testapp/ldap server are reachable):

```bash
vendor/bin/phpunit -c tests/unittests/phpunit.xml tests/unittests/ldap/multiauth_pluginTest.php
vendor/bin/phpunit -c tests/unittests/phpunit.xml --filter testSomething
```

The three ldap test classes (`multiauth_pluginTest.php`, `multiauth_plugin_starttls_Test.php`,
`multiauth_plugin_ldaps_Test.php`) share logic via `multiauth_plugin_trait.php` and only
differ in the ldap port/TLS mode they target — relevant when adding ldap connection tests.

`tests/` is excluded from the distributed package (`.gitattributes` has `tests export-ignore`).

## Architecture

### multiauth: driver + provider plugins

- `multiauth/plugins/auth/multiauth/multiauth.auth.php` defines `multiauthAuthDriver`
  (extends `jAuthDriverBase`, implements `jIAuthDriver2`) — the actual `jAuth` driver
  registered via `driver=multiauth` in `auth.coord.ini.php`. It owns the user DAO
  (configured via `dao`/`profile`) and orchestrates a list of **providers**.
- Each provider is a small plugin under `multiauth/plugins/multiauth/<name>/<name>.multiauth.php`,
  loaded dynamically via `jApp::loadPlugin($provider, 'multiauth', '.multiauth.php', $provider.'Provider', $config)`.
  Built-in providers: `ldap`, `dbaccounts`, `dbdao` (see `README.md` for their configuration).
- Providers implement `Jelix\MultiAuth\ProviderPluginInterface` (`multiauth/lib/ProviderPluginInterface.php`)
  and normally extend `Jelix\MultiAuth\ProviderAbstract` (`multiauth/lib/ProviderAbstract.php`),
  which handles password hashing/verification (modern `password_hash`/`password_verify` API,
  with fallback to a legacy `password_crypt_function` for migrated databases).
- The `providers` list in config (`providers[]=ldap:multiauth_ldap`, etc.) is `<plugin name>:<config section>`;
  without a `:`, the provider needs no dedicated config section (e.g. `dbaccounts`). The same
  plugin can be listed multiple times with different config sections (e.g. two ldap servers).
- At most one provider may use `FEATURE_USE_MULTIAUTH_TABLE` (i.e. rely on the shared account
  table's `password` column) — `multiauthAuthDriver` enforces this and calls it the
  "db account provider" (`dbAccountProvider`).
- **Provider tracking convention**: once a user successfully authenticates through a
  non-db provider, the user's `password` DB field is overwritten with the marker
  `!!multiauth:<providerKey>!!` so future logins know which provider to re-check against
  (see `verifyPassword()`, `canChangePassword()`, `changePassword()`, `getProviderForLogin()`
  in `multiauth.auth.php`). A legacy marker `!!ldapdao password!!` is recognized for
  accounts migrated from the old `ldapdao` module and is mapped to the first configured
  `ldap` provider.
- Configuration is read from `auth.coord.ini.php` (section `multiauth` + one section per
  provider), and can be overridden per-application-instance from `localconfig.ini.php`
  (same section names) without touching `auth.coord.ini.php` — merging happens in
  `multiauthAuthDriver::__construct()`.

### Module install scripts

The module ships `install/install.php` plus `install/configure.php`
(interactive configurator, e.g. `multiauthModuleConfigurator`).

## Versioning

Three places must stay in sync when releasing: `VERSION`, the `<version>` in
`multiauth/module.xml`, and `CHANGELOG.md`.
