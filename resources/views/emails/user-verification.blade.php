<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP User Verification</title>
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
		You have registered to join the SWAMP.

		To complete the registration process, click this link: 
		<a href="{{ $verify_url }}/{{ $verification_key }}">{{ $verify_url }}/{{ $verification_key }}</a>

		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
