<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP Email Verification</title>
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
		Your email address with SWAMP has recently been changed.
		<br />
		<br />
		<br />
		If you have any questions please contact the SWAMP staff at: {{ config('mail.contact.address') }} .
		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
