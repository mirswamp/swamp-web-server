<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP User Account Deleted</title>
	<style>
		body {
			font-family: sans-serif;
		}
	</style>
</head>
<body>
	<div>
		Dear {{ $user->getFullName() }},
		<br />
		<br />
		You have requested your SWAMP account to be deleted. Note that SWAMP user accounts are never actually deleted. Instead your account has been disabled. You can re-enable your account by contacting {{ config('mail.contact.address') }} .

		<br />
		<br />
		If you have any questions please contact the SWAMP staff at: {{ config('mail.contact.address') }} .

		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
