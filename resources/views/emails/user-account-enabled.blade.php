<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP User Account Enabled</title>
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
		Your account has been enabled by a SWAMP administrator!

		<br />
		<br />
		If you have any questions please contact the SWAMP staff at: security@continuousassurance.org

		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
