# CILogon Provider for the OAuth 2.0 Client

[![Build Status](https://travis-ci.org/cilogon/oauth2-cilogon.svg?branch=master)](https://travis-ci.org/cilogon/oauth2-cilogon)
[![Coverage Status](https://coveralls.io/repos/github/cilogon/oauth2-cilogon/badge.svg?branch=master)](https://coveralls.io/github/cilogon/oauth2-cilogon?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/cilogon/oauth2-cilogon/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/cilogon/oauth2-cilogon/?branch=master)

This package provides CILogon OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

This package is compliant with [PSR-1][], [PSR-2][] and [PSR-4][]. If you notice compliance oversights, please send
a patch via pull request.

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md

## Requirements

The following versions of PHP are supported.

* PHP 5.6
* PHP 7.0
* PHP 7.1
* HHVM

## Installation

To install, use composer:

```
composer require cilogon/oauth2-cilogon
```

## Usage

### Authorization Code Flow

```php
$provider = new League\OAuth2\Client\Provider\CILogon([
    'clientId'     => '{cilogon-client-id}',
    'clientSecret' => '{cilogon-client-secret}',
    'redirectUri'  => 'https://example.com/callback-url',
]);

if (!empty($_GET['error'])) {

    // Got an error, probably user denied access
    exit('Got error: ' . $_GET['error'] . 
         'Description: ' . $GET['error_description']);

} elseif (empty($_GET['code'])) {

    // If we don't have an authorization code then get one with all 
    // possible CILogon-specific scopes.
    $authUrl = $provider->getAuthorizationUrl([
        'scope' => ['openid','email','profile','org.cilogon.userinfo']
    ]);
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: '.$authUrl);
    exit;

} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    // Check given state against previously stored one to mitigate CSRF attack
    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    try {
        // Try to get an access token using the authorization code grant
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        // Print out the access token, which can be used in 
        // authenticated requests against the service provider's API.
        echo '<xmp>' . "\n";
        echo 'Token                  : ' . $token->getToken() . "\n";
        $expires = $token->getExpires();
        if (!is_null($expires)) {
            echo 'Expires                : ' . $token->getExpires();
            echo ($token->hasExpired() ? ' (expired)' : ' (active)') . "\n";
        }
        echo '</xmp>' . "\n";

        // Using the access token, get the user's details
        $user = $provider->getResourceOwner($token);

        echo '<xmp>' . "\n";
        echo 'User ID                : ' . $user->getId() . "\n";
        echo 'First name             : ' . $user->getGivenName() . "\n";
        echo 'Last name              : ' . $user->getFamilyName() . "\n";
        echo 'Full name              : ' . $user->getName() . "\n";
        echo 'Email                  : ' . $user->getEmail() . "\n";
        echo 'eduPersonPrincipalName : ' . $user->getEPPN() . "\n";
        echo 'eduPersonTargetedId    : ' . $user->getEPTID() . "\n";
        echo 'IdP entityId           : ' . $user->getIdP() . "\n";
        echo 'IdP Display Name       : ' . $user->getIdPName() . "\n";
        echo 'Org Unit               : ' . $user->getOU() . "\n";
        echo 'Affiliation            : ' . $user->getAffiliation() . "\n";
        echo '</xmp>';

    } catch (Exception $e) {

        // Failed to get access token or user details
        exit('Something went wrong: ' . $e->getMessage());

    }
}
```

### Managing Scopes

When creating your CILogon authorization URL, you can specify the state and scopes your application may authorize.

```php
$options = [
    'state' => 'OPTIONAL_CUSTOM_CONFIGURED_STATE',
    'scope' => ['openid','email','profile','org.cilogon.userinfo']
];

$authorizationUrl = $provider->getAuthorizationUrl($options);
```

If neither are defined, the provider will utilize internal defaults.

At the time of authoring this documentation, the following scopes are available.

- openid - Required/Default - gives the CILogon-specific identifier of the user 
- email - gives the user's email address
- profile - gives the user's name (given, family, and display, if available)
- org.cilogon.userinfo - gives Identity Provider SAML attributes, e.g.,  ePPN (eduPersonPrincipalName), ePTID (eduPersonTargetedID), eduPersonScopedAffiliation, ou (organizationalUnitName)

Two additional [CILogon-specific options](http://www.cilogon.org/oidc) are available.

- selected\_idp - the SAML entityId of the user's pre-selected Identity Provider. If given, CILogon UI will present the user with this IdP and ask for consent for release of information. See [https://cilogon.org/include/idplist.xml](https://cilogon.org/include/idplist.xml) for the list of Identity Providers supported by CILogon (those desginated as \<Whitelisted\>).
- skin - a pre-defined custom CILogon interface skin to change the look of the CILogon site. Contact [help@cilogon.org](mailto:help@cilogon.org) to reqeust a custom skin.

Example:

```php
$options = [
    'scope' => ['openid','email','profile','org.cilogon.userinfo'],
    'selected_idp' => 'urn:mace:incommon:uiuc.edu', // UIUC
    'skin' => 'globusonline' // For globus.org
];

$authorizationUrl = $provider->getAuthorizationUrl($options);
```

### Refreshing a Token

```php
$refreshtoken = $token->getRefreshToken();
$token = $provider->getAccessToken('refresh_token', [
    'refresh_token' => $refreshtoken,
]);
```

## License

The University of Illinois/NCSA Open Source License (NCSA). Please see [License File](https://github.com/cilogon/oauth2-cilogon/blob/master/LICENSE) for more information.
