Configuration of the ldap plugin
================================

If you used the ldapdao module, you already know how to configure the ldap
plugin for multiauth: they have almost the same configuration parameters.

General configuration properties
---------------------------------

First you should setup the module into the authentication system, by modifying
the `auth.coord.ini.php` file.

If you have installed multiauth into [Lizmap Web Client](https://www.lizmap.com/),
you don't have to create or modify any `auth.coord.ini.php` file. The multiauth
module is pre-configured, with an ldap profile named `lizmapldap`, you have to fill 
with correct values for the connection. Go the next section. 

In other applications, you should create a section into `auth.coord.ini.php`. Its name should
be set into the `providers` parameter of multiauth.

In this section, only one parameter is needed: `ldapprofile`. It
indicates the name of the profile to access the ldap.

Here is an example into `auth.coord.ini.php`:

```
[multiauth]
providers[]=ldap:multiauth_ldap

[multiauth_ldap]
; profile to use for ldap
ldapprofile = "myldap"

```


Connection configuration
------------------------

You should create an ldap profile, with the name indicated into the `ldapprofile`
parameter. The ldap profile should be into the `var/config/profiles.ini.php` file.

If you have installed multiauth into Lizmap, the ldap profile is named `lizmapldap`,
otherwise you should use a name. For example, if the profile is named `myldap` 
(like in the following example), then you should set `ldapprofile=myldap` into `authldap.coord.ini.php`.

Example of a profile for the ldap connection:

```
[ldap:myldap]
hostname=localhost
port=389
tlsMode=starttls  ; empty, "starttls" or "ldaps" (ldaps by default if port 636)
adminUserDn="cn=admin,ou=admins,dc=acme"
adminPassword="Sup3rP4ssw0rd"
searchUserBaseDN="dc=XY,dc=fr"
searchUserFilter="(&(objectClass=posixAccount)(uid=%%LOGIN%%))"
bindUserDN="uid=%?%,ou=users,dc=XY,dc=fr"
searchAttributes="uid:login,givenName:firstname,sn:lastname,mail:email"
searchGroupFilter="(&(objectClass=posixGroup)(cn=XYZ*)(memberUid=%%LOGIN%%))"
searchGroupProperty="cn"
searchGroupBaseDN=""
```

First, you should set `hostname`, `port`, which are the name and the port of
the ldap server.

You must also indicate how the connexion should be made. You should indicate 
if you are using the ldap protocol without encryption (`tlsmode=`), the ldap 
protocol with STARTTLS, (`tlsMode=starttls`) or if you are using the ldaps 
protocol (`tlsMode=ldaps`).

If you use the standard port 389, you have the choice between no encryption (empty value)
or `starttls`, for the `tlsMode` setting.

If you are using the port 636, it automatically uses the ldaps protocol. In this
case, setting `tlsMode` to `ldaps` is optional.

Since ldaps is deprecated in OpenLdap, the best solution is to use the port 389
with `tlsMode=starttls`.

**Warning**: without encryption, passwords of the admin user and of users are sent in clear
text over the network. Use it only on a trusted network (or a local connection).

When TLS is used, the certificate of the server is always verified, whatever the
`TLS_REQCERT` value into `ldap.conf` or the `LDAPTLS_REQCERT` environment variable.
TLS 1.2 is the minimum version of the protocol.


Other connection parameters:

- `networkTimeout`: timeout in seconds to connect to the server (10 by default).
- `timeLimit`: time limit in seconds for search operations (10 by default).


Using client certificates and custom CA certificates
----------------------------------------------------

If the server is using a custom or self-signed CA certificate, indicate the path to this certificate
into the configuration parameter `clientTLSCACertificateFile`.

If the server requires a client certificate, indicate the path to the client certificate
into the configuration parameter `clientTLSCertificateFile` and the path to the client key
into the configuration parameter `clientTLSKeyFile`.

**Warning**: don't use these parameters on a PHP server which serves different PHP applications, because these
openldap settings are global to the PHP process and can affect other PHP applications running on the same server. For the same reason, you cannot use several instances of the Ldap class with different client certificates, during the same http request. All connections will use the same client certificate. This is not a limitation of our Ldap library, but of PHP and OpenLdap.

If you are using PHP-FPM, you can configure a pool dedicated to your application, so processes are not shared
with other applications.


Configuration properties for the admin
--------------------------------------

The plugin needs to query the directory with a user having some rights allowing 
to search a user, to get his attributes, his groups etc... He's called the "admin" 
in the plugin.

You must indicate in `adminUserDn` and `adminPassword`, the DN (Distinguished Name) 
and the password of this user. 



Configuration properties for user data
--------------------------------------

To verify password, or to register the user into the Jelix application the
first time he authenticates himself, the plugin needs some data about the user.

You should indicate to it which ldap attributes it can retrieve, and which
database fields that will receive the ldap attributes values.

You indicate such information into the `searchAttributes` property. It is a 
pair of names, `<ldap attribute>:<table field>`, separated by a comma.

In this example, `searchAttributes="uid:login,givenName,sn:lastname,mail:email,dn:"`:

- the value of the `uid` ldap attribute will be stored into the `login` field 
- the value of the `sn` ldap attribute will be stored into the `lastname` field
- the value of the `givenName` ldap attribute will be stored into a field that
  have the same name, as there is no field name nor `:`.
- there will not be mapping for the `dn` property. There is a `:` without field name.
  It will be readed from ldap, and can be used into the `bindUserDN` DN template.
  (see below).

The possible list of possible fields is indicated into the dao file, whose name
is indicated into the `dao` configuration property.


Configuration properties for authentication
-------------------------------------------

Before to try to authenticate the user against the ldap, the plugin retrieves
user properties. It uses two configuration parameters :  `searchUserBaseDN`, `searchUserFilter`
and `searchAttributes`.

The `searchUserBaseDN` parameter indicates the path of the LDAP subtree to search in.
Example: `'searchUserBaseDN' => "ou=ADAM users,o=Microsoft,c=US"`.

The `searchUserFilter` should contain the ldap query, and a `%%LOGIN%%` placeholder
that will be replaced by the login given by the user.

Example: `searchUserFilter="(&(objectClass=posixAccount)(uid=%%LOGIN%%))"`

Note that you can indicate several search filters and several base DN, if you have
complex ldap structure. Use `[]` to indicate an item list:

```ini
searchUserBaseDN[]="ou=ADAM users,o=Microsoft,c=US"
searchUserBaseDN[]="ou=FOO users,o=Microsoft,c=US"

searchUserFilter[]="(&(objectClass=posixAccount)(uid=%%LOGIN%%))"
searchUserFilter[]="(&(objectClass=posixAccount)(cn=%%LOGIN%%))"
```


You can have only one `searchUserBaseDN` value and several `searchUserFilter` values. The same
base DN will be used for each filter.

You can have several `searchUserBaseDN` values and only one `searchUserFilter` value. The search will
be done for each base DN, but with the same query.

And you can have several `searchUserBaseDN` values and several `searchUserFilter` values.
In this case each filter should correspond to each base DN. You must then have same count
of `searchUserBaseDN` values and `searchUserFilter` values.

In the previous example, a first search will be made with the filter `(&(objectClass=posixAccount)(uid=%%LOGIN%%))`
will be used with the base DN `ou=ADAM users,o=Microsoft,c=US`. And if the user is not
found, a second search will be made with the filter `(&(objectClass=posixAccount)(cn=%%LOGIN%%))`
and the base DN `ou=FOO users,o=Microsoft,c=US`.

If there is a result, the DN of the found entry will be used to "bind" to the ldap
server with the password given by the user.
If several entries match the filter for a same base DN, the login is considered as
ambiguous and an exception is thrown.

Instead of the DN of the entry, you can indicate into `bindAttribute` an ldap attribute
of the entry that contains the DN to use for the bind. If this attribute is missing or empty,
the authentication fails.

The login given to methods of the library is escaped before being inserted into filters, so
characters like `*` or `(` are not interpreted. Logins having control characters, are refused. Spaces at the beginning
or at the end of the login are removed before the search.

Note that comparisons in ldap are often case-insensitive. It
may authenticate the user `john` when searching `JoHN`. 
Your application should use the login returned into
the `UserLdapAttributes` object, not the login given by the user.


Configuration properties for user rights
----------------------------------------

If you have configured groups rights into your application, and if these
groups match your ldap groups, you can indicate to the plugin to automatically
put the user into the application groups, according to the user ldap groups.

This feature is disabled by default. To enable it, set `featureGroupLoad` to `true`,
and fill the `searchGroupFilter`, `searchGroupProperty` and `searchGroupBaseDN`
parameters. These three parameters are required when `featureGroupLoad` is `true`:
an exception is thrown if one of them is empty. So set `featureGroupLoad` to `true`.

You should then indicate into `searchGroupFilter` the ldap query that will
retrieve the groups of the user.

Example: `searchGroupFilter="(&(objectClass=posixGroup)(member=%%USERDN%%))"`

`%%USERDN%%` is replaced by the user dn.`%%LOGIN%%` is replaced by the login.  
You can also use any ldap attributes you indicate into `searchAttributes`, 
between `%%`. Example: `searchGroupFilter="(&(objectClass=posixGroup)(member=%%givenName%%))"`

Warning : setting `searchGroupFilter` will remove the user from any other
application groups that don't match the ldap group, except default groups
of user into the application, when `searchGroupKeepUserInDefaultGroups` is set 
to `on`. If you don't want a groups synchronization, leave `searchGroupFilter` empty.

With `searchGroupProperty`, you must indicate the ldap attribute that
contains the group name. Ex: `searchGroupProperty="cn"`.

You may also indicate the base DN for the search, into `searchGroupBaseDN`. Example:
`searchGroupBaseDN="ou=Groups,dc=Acme,dc=pt"`.

Passwords
---------

With `passwordLdapHashAlgo`, you indicate how passwords are stored when you authorize the
application to change the password. Set `featureChangePassword`

- `EXOP`: the password is sent to the server with the "Password Modify Extended Operation"
  (RFC 3062). The server hashes the password with its own configuration and applies its
  password policy. It is the recommended value if your server supports it (OpenLdap does,
  Active Directory doesn't).
- `AD`: for Microsoft Active Directory. The password is stored into the `unicodePwd`
  attribute, encoded as expected by Active Directory, and the server applies its password
  policy. An encrypted connection is required (`tlsMode` should be `starttls` or `ldaps`).
  See the Active Directory example below.
- `CRYPT`: the password is hashed with SHA-512 crypt (`{CRYPT}$6$...`).
- `SSHA`: salted SHA-1. The salt length is given by `passwordLdapSaltLength` (8 bytes minimum).
- `SHA`, `MD5`, `SMD5`: weak algorithms, kept for compatibility. A warning is logged when they are used.
- empty value (default): the password is not hashed by the library. Use it only if the
  server hashes passwords itself (with the `ppolicy_hash_cleartext` option of OpenLdap for example).


Example of configuration for an OpenLdap server
-----------------------------------------------

A typical OpenLdap directory stores users as `inetOrgPerson` entries, identified
by their `uid` attribute, and groups as `groupOfNames` entries, listing the DN of
their members into the `member` attribute.

```ini
hostname=ldap.example.com
port=389
tlsMode=starttls
; if the server certificate is signed by a private CA
clientTLSCACertificateFile=/etc/ssl/certs/example-CA.crt

adminUserDn=cn=admin,dc=example,dc=com
adminPassword=Sup3rP4ssw0rd

; users
searchUserBaseDN="ou=people,dc=example,dc=com"
searchUserFilter="(&(objectClass=inetOrgPerson)(uid=%%LOGIN%%))"
searchAttributes="uid:login,givenName:firstname,sn:lastname,displayName:realname,mail:email"
; the DN of the found entry is used to verify the password
bindAttribute=dn

; groups
featureGroupLoad=on
searchGroupBaseDN="ou=groups,dc=example,dc=com"
searchGroupFilter="(&(objectClass=groupOfNames)(member=%%USERDN%%))"
searchGroupProperty=cn

; management of user accounts
featureChangePassword=on
featureCreateUser=on
featureUpdateUser=on
featureDeleteUser=on
newUserDN="uid=%%LOGIN%%,ou=people,dc=example,dc=com"
newUserLdapAttributes="objectClass:inetOrgPerson,uid:%%LOGIN%%,userPassword:%%PASSWORD%%,cn:%%REALNAME%%,sn:%%REALNAME%%"
passwordLdapHashAlgo=EXOP

```

If your groups are `posixGroup` entries, they list logins instead of DNs into
the `memberUid` attribute. Use then this filter:
`searchGroupFilter="(&(objectClass=posixGroup)(memberUid=%%LOGIN%%))"`.

The admin user must have the rights to read users and groups and, if you enable
modification features, to write into `ou=people`. Remove the `feature*` parameters
if you only need authentication.

Example of configuration for a Microsoft Active Directory server
----------------------------------------------------------------

In Active Directory, users are `user` entries, and the login is usually stored into
the `sAMAccountName` attribute. Users are often spread into several organizational
units, so the base DN is generally the root of the domain. Groups are `group` entries,
listing the DN of their members into the `member` attribute.

```ini
hostname=dc01.corp.example.com
port=636
tlsMode=ldaps
; Active Directory certificates are often signed by the CA of the company
clientTLSCACertificateFile=/etc/ssl/certs/corp-example-CA.crt

; a service account having read access to the directory
adminUserDn="CN=svc-ldap,OU=Service Accounts,DC=corp,DC=example,DC=com"
adminPassword="Sup3rP4ssw0rd"

; users
searchUserBaseDN="DC=corp,DC=example,DC=com"
; exclude computer accounts and disabled accounts
searchUserFilter="(&(objectCategory=person)(objectClass=user)(sAMAccountName=%%LOGIN%%)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))"
searchAttributes="sAMAccountName:login,givenName:firstname,sn:lastname,displayName:realname,mail:email,userPrincipalName:"
; the DN of the found entry is used to verify the password
bindAttribute=dn

; groups
featureGroupLoad=on
searchGroupBaseDN="DC=corp,DC=example,DC=com"
searchGroupFilter="(&(objectClass=group)(member=%%USERDN%%))"
searchGroupProperty="cn"

```

Notes:

- Active Directory accepts STARTTLS on the port 389 too, if it has a certificate:
  `port=389`, `tlsMode=starttls`.
- To find also groups containing the user indirectly (nested groups), you can use
  the matching rule `LDAP_MATCHING_RULE_IN_CHAIN`:
  `searchGroupFilter="(&(objectClass=group)(member:1.2.840.113556.1.4.1941:=%%USERDN%%))"`.
  This kind of search may be slow on large directories.
- Active Directory accepts also the `userPrincipalName` (`john@corp.example.com`) to
  bind a user. You could set `bindAttribute=userPrincipalName`, but using the
  DN is the most reliable.
- Searching from the root of the domain on the port 389 or 636 may return "referrals"
  to other partitions of the directory. If you have performance issues, set a more
  specific `searchUserBaseDN`, or use the Global Catalog (port 3268, or 3269 with ldaps).

Active Directory does not support the "Password Modify Extended Operation", and stores
passwords into the `unicodePwd` attribute. To change passwords
set `passwordLdapHashAlgo` to `AD`, and enable features you need:

```
; ... same parameters as above
passwordLdapHashAlgo=AD',
featureChangePassword=on
```

- The service account must have the "Reset Password" right.
- the password change with the service account: the password history
  of the domain is not checked by Active Directory.
