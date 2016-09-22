<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP Password Reset</title>
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
		You have requested to reset your password.  To do so, click this link: 
		<a href="{{ $password_reset_url }}">{{ $password_reset_url }}</a>
		<br />
		<br />
		If you did not request a password change or have other questions, please contact the SWAMP staff at: <a href="mailto:security@continuousassurance.org">security@continuousassurance.org</a>
		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
