<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP Admin Invitation</title>
	<style>
		body {
			font-family: sans-serif;
		}
	</style>
</head>
<body>
	<div>
		Dear {{ $invitee_name }},
		<br />
		<br />
		You have been invited by {{ $inviter->getFullName() }} to become a SWAMP administrator.

		To accept or decline this invitation, click this link: 
		<a href="{{ $confirm_url }}/{{ $invitation->invitation_key }}">{{ $confirm_url }}/{{ $invitation->invitation_key }}</a>

		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
