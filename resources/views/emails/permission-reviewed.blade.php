<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP Permission Request</title>
	<style>
		body {
			font-family: sans-serif;
		}
	</style>
</head>
<body>
	<div>
		{{ $user->getFullName() }},

		<br/>
		<br/>
		Your permissions have recently been reviewed by a SWAMP Administrator. The Administrator left the following comment:
		<br/>
		<br/>

		{{ $comment }}

		<br/>
		<br/>

		If you have any questions please contact the SWAMP staff at: {{ Config::get('mail.contact.address') }} .
		
		<br/>
		<br/>

		You may view the status of your current permissions <a href="{{ $url }}/#my-account/permissions">here</a>.

		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
