Using LDAP for User Authentication and Attributes
-------------------------------------------------

In a typical SWAMP-In-A-Box installation, user accounts are stored in the
local (MySQL) database. In contrast on www.mir-swamp.org , user accounts
are stored in an LDAP server which is managed by the SWAMP codebase. In
this LDAP setup, SWAMP assumes that the LDAP is writable so that user
account information can be saved and updated. 

However, it is possible to configure your local SWAMP installation to use a
local LDAP or Active Directory server (assuming AD has been configured to
act as an LDAP server, as is the default). In this case, user accounts in
the LDAP/AD server are probably managed by an external process, i.e., not
by SWAMP. To handle this setup, SWAMP can treat the LDAP/AD server as
"read-only", meaning that LDAP/AD can be used to authenticate a user and
to provide user attributes, but SWAMP cannot update information in LDAP/AD.

There are several configuration options you can add to the ".env" file,
typically found in /var/www.swamp-web-server. First, the options are
described with their semantic meanings and default values. Then, several
examples are given.


-------------------------------------------------------
Configuration Options in /var/www/swamp-web-server/.env
-------------------------------------------------------

LDAP_ENABLED={false,true}
  Defaults to false. To use an LDAP/AD server for user authentication and
	attributes, this option must be set to true.

LDAP_READ_ONLY={false,true}
  Defaults to false. If set to false, the SWAMP codebase assumes SWAMP has
	total control over user attributes and LDAP entries. This option must be
	set to true if you are using a pre-existing LDAP/AD server.

LDAP_HOST=<URI of the LDAP/AD server>
  Defaults to blank. When LDAP_ENABLED=true, this option MUST be set to the
	protocol+host for the LDAP/AD server, e.g., ldaps://ldap.example.org .
	The specified LDAP server must support LDAP protocol 3.

LDAP_PORT=<port number for LDAP_HOST>
	Defaults to 636 (corresponding to ldaps://...). This is the port number
	for the configured LDAP_HOST.

LDAP_BASE_DN=<RDN search base for LDAP searches>
  Defaults to 'ou=people,o=SWAMP,dc=cosalab,dc=org'. You will probably need
	to configure this to the base DN for the LDAP directory for your users.
	See examples below.

LDAP_USER_RDN_ATTR=<Prefix RDN attribute for users>
	Defaults to 'swampUuid'. You will probably need to configure this to
	match how your LDAP 'dn' fields are formed. See examples below. 
	
LDAP_SWAMP_UID_ATTR=<unique attribute name for index into SWAMP database>
  Defaults to "swampUuid'. You probably need to configure this to some
	attribute which uniquely identifies a user in your LDAP server. Often,
	this will be the same as LDAP_USER_RDN_ATTR. See examples below.

LDAP_FIRSTNAME_ATTR=<key name of user 'first name' field>
  Defaults to 'givenName'. If your LDAP/AD server uses a different field
	for storing the user's first name, configure it here.

LDAP_LASTNAME_ATTR=<key name of user 'last name' field>
  Defaults to 'sn'. If your LDAP/AD server uses a different field
	for storing the user's last name, configure it here.

LDAP_FULLNAME_ATTR=<key name of user 'display name' field>
  Defaults to 'cn'. If your LDAP/AD server uses a different field
	for storing the user's full/display name, configure it here.

LDAP_PASSWORD_ATTR=<key name of user 'password' field>
  Defaults to 'userPassword'. If your LDAP/AD server uses a different field
	for storing the user's password, configure it here. NOTE that this is
	used ONLY when LDAP_READ_ONLY=false (meaning SWAMP can change a user's
	password).

LDAP_USERNAME_ATTR=<key name of user 'login name' field>
  Defaults to 'uid'. When a user is prompted to log in with username and
	password, this is the field that is checked for a matching username. If
	you wanted users to log in with their email address, for example, you
	would set LDAP_USERNAME_ATTR=mail.

LDAP_EMAIL_ATTR=<key name of user 'email address' field>
  Defaults to 'mail'. If your LDAP/AD server uses a different field
	for storing the user's email address, configure it here.

LDAP_ADDRESS_ATTR=<key name of user 'snailmail address' field>
	Defaults to 'postalAddress'. If your LDAP/AD server uses a different
	field for storing the user's postal address, configure it here.

LDAP_PHONE_ATTR=<key name of user 'telephone' field>
	Defaults to 'telephoneNumber'. If your LDAP/AD server uses a different
	field for storing the user's phone number, configure it here.

LDAP_WEB_USER=<full 'dn' of a credentialed user with global LDAP read access>
  Defaults to null. When this option set not set (or set to 'null'), it is
	assumed that the LDAP/AD server is configured for anonymous searches. If
	this is not the case, then you will need to configure an LDAP/AD user
	that has search access to find all users. (Adding this user to LDAP/AD is
	beyond the scope of this document). The full 'dn' of the user should be
	used here. See examples below.

LDAP_WEB_USER_PASSWORD=<password for LDAP_WEB_USER>
  Defaults to null. This option corresponds to the password necessary to
	authenticate the LDAP_WEB_USER. When not set (or set to null), it is
	assumed that no password is necessary.

LDAP_PASSWORD_SET_USER=<full 'dn' of a credentialed user with permission to
                        change another user's password>
	Defaults to null. This configuration option is necessary ONLY if
	LDAP_READ_ONLY=false. It corresponds to the full 'dn' of the user that
	has permission to change the password of any user in LDAP. 

LDAP_PASSWORD_SET_USER_PASSWORD=<password for LDAP_PASSWORD_SET_USER>
  Defaults to null. This option corresponds to the password necessary to
	authenticate the LDAP_PASSWORD_SET_USER. 


--------
Examples
--------

Below are some examples showing the output of a command line 'ldapsearch'
query and the corresponding .env confiuration.


Secure LDAP Server with Anonymous Read Access
---------------------------------------------

ldapsearch command
------------------

ldapsearch -LLL -x -H ldaps://ldap.ncsa.illinois.edu \
           -b "dc=ncsa,dc=illinois,dc=edu" "(sn=*fleury*)"

dn: uid=tfleury,ou=People,dc=ncsa,dc=illinois,dc=edu
cn: Terrence Fleury
givenName: Terrence
sn: Fleury
uid: tfleury
mail: tfleury@illinois.edu
employeeType: all_ncsa_employe
objectClass: top
objectClass: person
objectClass: organizationalPerson
objectClass: inetOrgPerson
objectClass: inetUser
objectClass: posixAccount
uidNumber: 28064
gidNumber: 202
homeDirectory: /afs/ncsa/.u7/tfleury
loginShell: /bin/csh
memberOf: cn=jira-users,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=grp_bw_ncsa_staf,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=org_all_groups,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=org_do,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=all_ncsa_employe,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=grp_jira_users,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=all_users,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=grp_bldg_ncsa,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=grp_bldg_both,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=org_cisr,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=org_ici,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=org_csd,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=prj_cerb_users,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=iam_sec_testing,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=lsst_users,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=lsst_security,ou=Groups,dc=ncsa,dc=illinois,dc=edu
memberOf: cn=ncsa-ca,ou=Groups,dc=ncsa,dc=illinois,dc=edu


Corresponding .env entry
------------------------

LDAP_ENABLED=true
LDAP_READ_ONLY=true
LDAP_HOST=ldaps://ldap.ncsa.illinois.edu
LDAP_BASE_DN=ou=People,dc=ncsa,dc=illinois,dc=edu
LDAP_USER_RDN_ATTR=uid
LDAP_SWAMP_UID_ATTR=uid


Notes:
-----
In the query response, you should see:
    dn: uid=tfleury,ou=People,dc=ncsa,dc=illinois,dc=edu
In this case, the LDAP_USER_RDN_ATTR is the key for "uid=tfleury" portion
of the dn, and the LDAP_BASE_DN is there rest of the dn string.

Since the 'uid' field is globally unique in the LDAP directory, we set that
for LDAP_SWAMP_UID_ATTR. 

We also want the user to enter 'tfleury' for username/password, so we use
the default value for LDAP_USERNAME_ATTR=uid .

Finally, we use the default value of LDAP_PORT=636 since we are connecting
with ldaps://.


Insecure Active Directory Server with Credentialed User
-------------------------------------------------------

ldapsearch command
------------------

ldapsearch -LLL -x -H ldap://128.104.100.232 \
           -b "dc=swamp,dc=ad" \
					 -D "ldapquery@swamp.ad" \
					 -W  "(sAMAccountName=*fleury*)"
Enter LDAP Password: <password entered>

dn: CN=Terry Fleury,CN=Users,DC=swamp,DC=ad
objectClass: top
objectClass: person
objectClass: organizationalPerson
objectClass: user
cn: Terry Fleury
sn: Fleury
telephoneNumber: +1 217 3330231
givenName: Terry
distinguishedName: CN=Terry Fleury,CN=Users,DC=swamp,DC=ad
instanceType: 4
whenCreated: 20161102135807.0Z
whenChanged: 20161103141526.0Z
displayName: Terry Fleury
uSNCreated: 65515
memberOf: CN=Domain Admins,CN=Users,DC=swamp,DC=ad
uSNChanged: 66272
streetAddress:: MTIwNSBXLiBDbGFyayBTdC4NClVyYmFuYSwgSUwgNjE4MjE=
name: Terry Fleury
objectGUID:: 4YwXKKIRxEOMD9BK4WaXGQ==
userAccountControl: 66048
badPwdCount: 0
codePage: 0
countryCode: 0
badPasswordTime: 131231177822233920
lastLogoff: 0
lastLogon: 131231177936769682
pwdLastSet: 131225686874147433
primaryGroupID: 513
objectSid:: AQUAAAAAAAUVAAAA7H5IDl2Zlbb2qCf1UgQAAA==
adminCount: 1
accountExpires: 9223372036854775807
logonCount: 1
sAMAccountName: tfleury
sAMAccountType: 805306368
userPrincipalName: tfleury@swamp.ad
objectCategory: CN=Person,CN=Schema,CN=Configuration,DC=swamp,DC=ad
dSCorePropagationData: 20161102144813.0Z
dSCorePropagationData: 16010101000000.0Z
lastLogonTimestamp: 131226561268705498
mail: tfleury@illinois.edu


Corresponding .env entry
------------------------

LDAP_ENABLED=true
LDAP_READ_ONLY=true
LDAP_HOST=ldap://128.104.100.232
LDAP_PORT=389
LDAP_WEB_USER=ldapquery@swamp.ad
LDAP_WEB_USER_PASSWORD=<password here>
LDAP_BASE_DN=cn=Users,dc=swamp,dc=ad
LDAP_USER_RDN_ATTR=cn
LDAP_SWAMP_UID_ATTR=userPrincipalName
LDAP_USERNAME_ATTR=sAMAccountName
LDAP_ADDRESS_ATTR=streetAddress

Notes:
-----
In the query response, you should see:
    dn: CN=Terry Fleury,CN=Users,DC=swamp,DC=ad
In this case, the LDAP_USER_RDN_ATTR is the key for "cn=Terry Fleury" portion
of the dn, and the LDAP_BASE_DN is there rest of the dn string.

The user ldapquery@swamp.ad was configured in the AD server to have read
access for the other users in the server. This was an out-of-band step.

We need a unique AD identifier to store in the MySQL database. In this case
we configure LDAP_SWAMP_UID_ATTR=userPrincipalName , but any other unique
identifier could be used.

We want the user to enter 'tfleury' for username/password, so we use
LDAP_USERNAME_ATTR=sAMAccountName .

Since LDAP_HOST is using ldap:// , we configure LDAP_PORT=389 (insecure).
Note that is a bad idea to use an insecure LDAP protocol since user
passwords would be transmitted in the clear.

Finally, the postal address key is 'streetAddress', so we configure
LDAP_ADDRESS_ATTR=streetAddress. The other user attributes are default
values and thus don't need special configuration.


--------------------
Other Considerations
--------------------

SWAMP Admin User
----------------
When SWAMP-In-A-Box is installed, a default SWAMP admin user is set up.
When LDAP is configured for user authentication and attributes, this
default SWAMP admin user can no longer be accessed. Instead, you will need
to give one of the (previously logged in) LDAP users admin rights. This can
be done with a mysql command as follows.

Notes: 
-----
PROJECT_DB_* config options can be found in /var/www/swamp-web-server/.env. 
SWAMP_UID corresponds to the unique identifier for the admin user to be. It
is the value which corresponds to the LDAP_SWAMP_UID_ATTR key for the user.

Command
-------
export PROJECT_DB_HOST=localhost
export PROJECT_DB_PORT=3306
export PROJECT_DB_DATABASE=project
export PROJECT_DB_USERNAME=web
export SWAMP_UID=<unique SWAMP_UID of new admin user>
mysql -h $PROJECT_DB_HOST -P $PROJECT_DB_PORT -u $PROJECT_DB_USERNAME -p \
      -e "USE $PROJECT_DB_DATABASE; UPDATE user_account SET admin_flag=1 WHERE user_uid='$SWAMP_UID';"


LDAP Size
---------
If your LDAP/AD server has several thousand users, your SWAMP admin user
many not be able to manage users. This is dependent on how the limits on the
LDAP/AD server are configured. If the server limits the number of records
which can be returned on a search, SWAMP may receive only a subset of users
when asking for all users. This in turn affects the "Review Accounts" page
by showing only a subset of SWAMP users.
