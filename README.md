# PHP OAuth Authorization Server

This project aims at providing a stand-alone OAuth v2 Authorization Server that
is easy to integrate with your existing REST services, written in any language, 
without requiring extensive changes.

# Features
* PDO (database abstraction layer for various databases) storage backend for
  OAuth tokens
* OAuth v2 (authorization code and implicit grant) support
* SAML authentication support ([simpleSAMLphp](http://www.simplesamlphp.org)) 
* [BrowserID](http://browserid.org) authentication support using 
([php-browserid](https://github.com/fkooman/php-browserid/))

# Screenshots

Below are some screenshots of the OAuth consent dialog, the first one is the default view, the second is the view when one clicks the "Details" button.

![oauth_consent_simple](https://github.com/fkooman/php-oauth/raw/master/docs/oauth_consent_simple.png)

![oauth_consent_advanced](https://github.com/fkooman/php-oauth/raw/master/docs/oauth_consent_advanced.png)

# Requirements
The installation requirements on Fedora/CentOS can be installed like this:

    $ su -c 'yum install git php-pdo php httpd'

On Debian/Ubuntu:

    $ sudo apt-get install git sqlite3 php5 php5-sqlite

# Installation
*NOTE*: in the `chown` line you need to use your own user account name!

    $ cd /var/www/html
    $ su -c 'mkdir php-oauth'
    $ su -c 'chown fkooman:fkooman php-oauth'
    $ git clone git://github.com/fkooman/php-oauth.git
    $ cd php-oauth

Now you can create the default configuration files, the paths will be 
automatically set, permissions set and a sample Apache configuration file will 
be generated and shown on the screen (see below for more information on
Apache configuration).

    $ docs/configure.sh

Next make sure to configure the database settings in `config/oauth.ini`, and 
possibly other settings. If you want to keep using SQlite you are good to go 
without fiddling with the database settings. Now to initialize the database,
i.e. to install the tables, run:

    $ php docs/initOAuthDatabase.php

It is also possible to already preregister some clients which makes sense if 
you want to use the management clients discussed below. The sample registrations
are listed in `docs/registration.json`. By default they point to 
`http://localhost`, but if you run this software on a "real" domain you need to
modify the `docs/registration.json` file to point to your domain name and 
full path where the management clients will be installed.

To modify the domain of where the clients will be located in one go, you can
run the following command:

    $ sed 's|http://localhost|https://www.example.org|g' docs/registration.json > docs/myregistration.json

You can still modify the `docs/myregistration.json` by hand if you desire, and 
then load them in the database:

    $ php docs/registerClients.php docs/myregistration.json

This should take care of the initial setup and you can now move to the 
management clients, see below.

*NOTE*: On Ubuntu (Debian) you would typically install in `/var/www/php-oauth` and not 
in `/var/www/html/php-oauth` and you use `sudo` instead of `su -c`.

# Management Clients
There are two reference management clients available:

* [Manage Applications](https://github.com/fkooman/html-manage-applications/). 
* [Manage Authorizations](https://github.com/fkooman/html-manage-authorizations/). 

These clients are written in HTML, CSS and JavaScript only and can be hosted on 
any (static) web server. See the accompanying READMEs for more information. If 
you followed the client registration in the previous section they should start
working immediately if you install the applications at the correct URL. Do not
forget to enable the management API, see below in the section on Entitlements.

# SELinux
The install script already takes care of setting the file permissions of the
`data/` directory to allow Apache to write to the directory. If you want to use
the BrowserID authentication plugin you also need to give Apache permission to 
access the network. These permissions can be given by using `setsebool` as root:

    $ sudo setsebool -P httpd_can_network_connect=on

If you want the logger to send out email, you need the following as well:

    $ sudo setsebool -P httpd_can_sendmail=on

This is only for Red Hat based Linux distributions like RHEL, CentOS and 
Fedora.

If you want the labeling of the `data/` directory to survive file system 
relabling you have to update the policy as well.

FIXME: add how to update the policy...

# Apache
There is an example configuration file in `docs/apache.conf`. 

On Red Hat based distributions the file can be placed in 
`/etc/httpd/conf.d/php-oauth.conf`. On Debian based distributions the file can
be placed in `/etc/apache2/conf.d/php-oauth`. Be sure to modify it to suit your 
environment and do not forget to restart Apache. 

The `docs/configure.sh` script from the previous section outputs a config for 
your system which replaces the `/PATH/TO/APP` with the actual install directory.

# Authentication
There are thee plugins provided to authenticate users:

* DummyResourceOwner - Static account configured in `config/oauth.ini`
* SspResourceOwner - simpleSAMLphp plugin for SAML authentication
* BrowserIDResourceOwner - BrowserID / Mozilla Persona plugin

You can configure which plugin to use by modifying the `authenticationMechanism`
setting.

## Entitlements
A more complex part of the authentication and authorization is the use of 
entitlements. This is a bit similar to scope in OAuth, only entitlements are 
for resource owner, while scope is only for OAuth clients.

The entitlements are for example used by the `php-oauth` API. It is possible to 
write a client application that uses the `php-oauth` API to manage OAuth client 
registrations. The problem now is how to decide who is allowed to manage 
OAuth client registrations. Clearly not all users who can successfully 
authenticate, but only a subset. The way now to determine who gets to do what
is accomplished through entitlements. 

In the `[Api]` section the management API can be enabled:

    [Api]
    enableApi = TRUE

In particular, the authenticated user (resouce owner) needs to have the 
`applications` entitlement in order to be able to modify application 
registrations.

In the authentication modules one can then specify what particular resource 
owner will get this entitlement. For instance in the `DummyResourceOwner` 
section:

    ; Dummy Configuration
    [DummyResourceOwner]
    resourceOwnerId = "fkooman"
    resourceOwnerEntitlement["applications"] = "fkooman"

Here you can see that the resource owner `fkooman` will be granted the 
`applications` entitlement. As there is only one account in the 
`DummyResourceOwner` configuration it is quite boring.

Now, for the `SspResourceOwner` configuration it is a little bit more complex, 
all non relevant configuration was stripped:

    [SspResourceOwner]
    entitlementAttributeName = "urn:mace:dir:attribute-def:eduPersonEntitlement"
    entitlementValueMapping["applications"] = "urn:vnd:oauth2:applications"

This means that the entitlement is determined by looking at the SAML attribute 
`urn:mace:dir:attribute-def:eduPersonEntitlement` provided as part
of the SAML assertion. If (one of the values) of this attribute contains
`urn:vnd:oauth2:applications` that particular user will be granted the
`applications` entitlement. The SAML IdP will have to set this entitlement for
users that are allowed to perform OAuth client registrations. This is 
convenient as you no longer need to modify the configuration of `php-oauth` to
add a new "administrator", but can just add the entitlement to the user in the
IdP user directory.

See the 
[eduPersonEntitlementInLDAP](https://github.com/fkooman/php-oauth/blob/master/docs/eduPersonEntitlementInLDAP.md) 
document for more information about adding this entitlement to some directory
servers.

See
[API documentation](https://github.com/fkooman/php-oauth/blob/master/docs/API.md) 
for more information about the exposed API so you can write your own client 
interfacing with it.

## simpleSAMLphp
In the configuration file `config/oauth.ini` various aspects can be configured. 
To configure the SAML integration, make sure the following settings are
at least correct. See above for the entitlement configuration.

    authenticationMechanism = "SspResourceOwner"

    ; simpleSAMLphp configuration
    [SspResourceOwner]
    sspPath = "/var/simplesamlphp"
    authSource = "default-sp"

    ; by default we use the (persistent) NameID value received from the SAML 
    ; assertion as the user identifier (RECOMMENDED)
    useNameID = TRUE

    ; you can also use an attribute as a unique identifier for a user instead 
    ; of the NameID, but set 'useNameID' to FALSE then!
    ;resourceOwnerIdAttributeName = "uid"
    ;resourceOwnerIdAttributeName = "urn:mace:dir:attribute-def:uid"

