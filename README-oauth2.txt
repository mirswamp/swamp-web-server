The SWAMP can be configured to use external OAuth2 identity providers
(IdPs). Currently, the following external IdPs can be configured:

* GitHub  (https://developer.github.com/v3/oauth/)
* Google  (https://developers.google.com/identity/protocols/OAuth2)
* CILogon (https://cilogon.org/oauth2/register)

------
GitHub
------
Register a new OAuth Application at:
  https://developers.google.com/identity/protocols/OAuth2

Enter the following information:
  Application name: SWAMP
  Homepage URL: https://continuousassurance.org/
	Application description: <leave blank>
	Authorized callback URL: https://hostname/swamp-web-server/public/oauth2
  (Optional) Add an Application Logo on the summary screen. 
Click the green "Update applcation" button when finished.

Copy down the Client ID and Client Secret and enter these values in your
local .env file. E.g.:

GITHUB_ENABLED=true
GITHUB_CLIENT_ID=93jbv982398a9823
GITHUB_CLIENT_SECRET=ajl349blawej49o8bnlwe4jvaoe4jo9j


------
Google 
------
Go to Google API Console: https://console.developers.google.com/
Click on "Credentials" in the left column.
Click on the blue "Create credentials" button dropdown and select 
  'OAuth client ID'.
Click on the blue "Configure consent screen" button.
You will be shown the "OAuth consent screen" tab. Enter the following
information:
  Email address: Your email or Group
  Product name shown to users: SWAMP
  Homepage URL: https://continuousassurance.org/
  Product logo URL: https://www.mir-swamp.org/images/logos/swamp-icon-small.png
  Privacy policy: https://continuousassurance.org/swamp/SWAMP-Privacy-Policy.pdf
  Terms of service: https://www.mir-swamp.org/#policies/acceptable-use-policy
Click on the blue "Save" button.
You will be shown the "Credentials" tab.
Click the blue "Create credentials" button dropdown and select
  'OAuth client ID'.
Select the 'Web application' radio button.
  Name: SWAMP
  Authorized JavaScript origins:
    https://hostname/
  Authorized redirect URIs:
    https://hostname/swamp-web-server/public/oauth2
Click the blue "Create" button.

Copy down OAuth client ID and secret and enter these values in your local
.env file. E.g.:

GOOGLE_ENABLED=true
GOOGLE_CLIENT_ID=123456789-abcdefghij.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=ja90843hb98342hb9o8a4

LAST STEP: MUST ENABLE GOOGLE+ API
Click on "Library" in left column, find "Google+ API" under Social APIs.
Click "Enable" link at the top of the page.


-------
CILogon
-------
Go to https://cilogon.org/oauth2/register
Enter the following information:
  Client Name: SWAMP
  Contact email: Your email
  Home URL: https://continuousassurance.org/
  Uncheck Use Limited Proxy Certificates
  Callback URLs: https://hostname/swamp-web-server/public/oauth2
Click "submit" button.

Copy down client identifier and client secret and enter these values in
your local .env file. E.g.:

CILOGON_ENABLED=true
CILOGON_CLIENT_ID=myproxy:oa4mp,2012:/client_id/821hgv898h4t
CILOGON_CLIENT_SECRET=jogjqklgvhow34hto8ahliq3hpfaer8vhq348hr

WAIT for email approval from CILogon Administrator.

