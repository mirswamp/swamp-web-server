<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP User Account Disabled</title>
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
		You have requested to disable your SWAMP account. You can re-enable your account or request to delete your account by sending a message to {{ config('mail.contact.address') }} . If you request to delete your account, it will be deleted within 30 business days.
		<br />
		<br />
		If you have any questions please contact the SWAMP staff at: {{ config('mail.contact.address') }} .
		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>