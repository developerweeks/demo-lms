# Login.gov & Drupal

The login_gov module proivdes a plugin for 
[Open ID Connect](https://drupal.org/project/openid_connect) that integrates 
with the GSA's Login.gov Identity Provider service to provide single sign-on 
(SSO) service to a Drupal website. 

# Setting Up

For the sake of clarity, this README will refer to the module using the module's
machine name "login_gov" and the GSA's service as "Login.gov".

Before starting, you'll need to set up an account in Login.gov's 
[Identty Sandbox](https://dashboard.int.identitysandbox.gov/). Anyone with an
active email ending in .gov or .mil can set up a sandbox account and test the
integration with Login.gov. Before you can go live with your application, the 
agency will need to set up an IAA with Login.gov naming your site.  See 
[Getting started with Login.gov](https://login.gov/partners/get-started/) for
more details.

Many agencies set up a proxy identity provider (IdP) with names like 
sso.agency.gov or login.agency.gov so their IAA has only one named site, the 
proxy IdP. The proxy often routes agency users to their internal PIV solution 
and non-agency users to Login.gov. In these cases, your site will use their 
SAML or OpenID connector setup instead of this module. Check with your project's 
COR or ISSO for guidance.

## Patches

The module needs a patch to the 3.x version of OpenID Connect from 
[issue #3342661.](https://www.drupal.org/project/openid_connect/issues/3342661#comment-14927801)
Adding the following to the "patches" section of `composer.json` will apply it:

```json
"drupal/openid_connect": {
    "d.o #3342661: Allow plugins to override the getUrlOptions() method": "https://www.drupal.org/files/issues/2023-02-17/openid_connect.url_options_alter.3342661-2.patch"
}
```

## Public/Private Keys

The login_gov module requires a private key to sign the JWT payloads sent to the
Login.gov servers and a public cert that is uploaded to the application profile 
on Login.gov. Check out developers.login.gov for more details about 
[Setting up a Sandbox Account](https://developers.login.gov/oidc/getting-started/#set-up-a-sandbox-account).

As noted in the "Setting up a Sandbox Account" linked above, you'll need to 
create a new private key and public key/cert. The following OpenSSL command is
a quick way to create a 2048-bit RSA self-signed key pair valid for one year.

```shell
openssl req -nodes -x509 -days 365 -newkey rsa:2048 -keyout private.pem -out public.crt
```

Because you're uploading the public key directly to Login.gov, there is no need
to get an SSL cert signed by a third-party provider. The cert will be linked to
your site and only your site. As with any SSL cert, keeping the private key 
secure is critical to maintaining the security of your site.

## Installing the Private Key

The login_gov module uses the [Key](https://drupal.org/project/key) and the 
[Key Asymmetric](https://drupal.org/project/key_asymmetric) modules to manage 
the private key.

After installing, go to `admin/config/system/keys` , click "+ Add Key", give it 
a name, and use the Key Type "Private key". Under "Provider settings", choose 
how you will deploy the private key in your environments. Discuss with your 
hosting provider, ops team and/or security team how to deploy it best. 

* Using "Configuration" stores it in the Drupal database and would be exported 
by `drush cex`, which risks the private secret finding its way to your project's
git repository.

* Using "File" puts it somewhere on the server. You'll need access to the server
to upload it outside of the "docroot".

* Using "Environment" means the site will read it out of the web server's or PHP
container's environment variables. Depending on your hosting provider, this is 
often uploaded through the hosting provider's management portal.

## Setting up the Client

Once you've got the key set up, you can create the OpenID Client in the OpenID
Connect config section: `admin/config/people/openid-connect`.

* Name - Used by OpenID Connect in the "Log in with @name" button.

* Client ID - This value is provided by Login.gov. In your application's config
page, they called it "Issuer."

* Sandbox Mode - Check this if you're running in a Login.gov developer sandbox 
and uncheck it for a production application.

* Authentication Assurance Level - Check all that your application will accept.
This affects how well Login.gov vets users before returning them to your site
and what fields are available to your site.  More details are on the
[Attributes](https://developers.login.gov/attributes/) page in the Login.gov
documentation.

* Verified Within - How recently should someone have verified or reverified
their identiy with Login.gov. See the 
[OpenID Authorization](https://developers.login.gov/oidc/authorization/) section 
on developers.login.gov for more details.

* User Fields - what fields to request from Login.gov. The users will be told
that your site is asking for these fields the first time they login and anytime
your site changes what is requested.

* Force Reauthorization - If a user has already authenticated to Login.gov, they
will not need to re-authenticate when they try to log into your site. Enabling
this feature will require them to re-authenticate every time.  **NOTE: Enabling 
this requires permission from Login.gov.**

* Key from Key - Choose the key you set up during "Installing the Private Key"
above.

* Redirect URL - Login.gov will only accept requests with the redirect URL set 
to a known value. In your application's settings on Login.gov, there is a field 
to note all the valid URLs. Ensure this URL is in the list of known valid 
Rediect URLs.

# Disclaimers

This module was written by [John Franklin](https://www.drupal.org/user/683430) 
at Bixal Solutions and is not maintained nor affiliated with GSA, the Login.gov 
service, nor any federal agency.