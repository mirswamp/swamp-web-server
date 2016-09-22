<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP Username Request</title>
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
		You have recently requested a username reminder.  Your username is:  {{ $user->username }}
		<br />
		<br />
		If you did not request a username reminder or have other questions, please contact the SWAMP staff at: <a href="mailto:security@continuousassurance.org">security@continuousassurance.org</a>
		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
