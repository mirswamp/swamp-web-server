<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP User Project Deactivated</title>
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
		Your project {{ $project['full_name'] }} has been deactivated!

		<br />
		<br />
		If you have any questions please contact the SWAMP staff at: {{ Config::get('mail.contact.address') }} .

		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
