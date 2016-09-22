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
		Your account has been disabled by a SWAMP Administrator!

		<br />
		<br />
		If you have any questions please contact the SWAMP staff at: security@continuousassurance.org

		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
