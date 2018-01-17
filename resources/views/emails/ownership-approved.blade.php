<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP Ownership Approved</title>
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
		You have been approved to create and own new SWAMP projects!

		<br />
		<br />
		If you have any questions please contact the SWAMP staff at: {{ Config::get('mail.contact.address') }} .

		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
