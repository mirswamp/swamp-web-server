<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP Project Invitation</title>
	<style>
		body {
			font-family: sans-serif;
		}
	</style>
</head>
<body>
	<div>
		Dear {{ $invitation->invitee_name }},
		<br />
		<br />
		You have been invited by {{ $inviter->GetFullName() }} to join the project, {{ $project->full_name }}.

		To join this project, click this link: 
		<a href="{{ $confirm_url }}/{{ $invitation->invitation_key }}">{{ $confirm_url }}/{{ $invitation->invitation_key }}</a>

		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
