<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP App Password Created</title>
	<style>
		body {
			font-family: sans-serif;
		}
	</style>
</head>
<body>
	<div>
		{{ $user->getFullName() }},
		<br />
		<br />

		A new SWAMP app password was recently created. If this action was not undertaken by you or by your request, please contact SWAMP security immediately.

		<br />
		<br />

		You may contact SWAMP security directly at {{ config('mail.security.address') }} or visit the security contact page for more options: <a href="{{ $url }}/#contact/security">Contact SWAMP Security</a>

		<br/>
		<br/>

		Thank you,
		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
