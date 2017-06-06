Dear {{ $user->getFullName() }},

You have requested to reset your password.  To do so, paste the following URL into your web browser: 
{{ $password_reset_url }} .

If you did not request a password change or have other questions, please contact the SWAMP staff at: 
{{ Config::get('mail.contact.address') }} .

-The Software Assurance Marketplace (SWAMP)

