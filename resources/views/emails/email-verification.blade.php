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
		Your email address with SWAMP has recently been requested to change to this address.
		<br />
		<br />
		To use this email address, click the following link: 
		<a href="{{ $verify_url }}/{{ $verification_key }}">{{ $verify_url }}/{{ $verification_key }}</a>
		<br />
		<br />
		If you have any questions please contact the SWAMP staff at: security@continuousassurance.org
		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
