<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP Ownership Approved</title>
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
		Your assessment 
		@if ($package && $package['name'])
		of {{ $package['name'] }} version {{ $package['version_string'] }} 
		@endif
		@if ($tool && $tool['name'])
		using {{ $tool['name'] }} version {{ $tool['version_string'] }} 
		@endif
		@if ($platform && $platform['name'])
		on {{ $platform['name'] }} version {{ $platform['version_string'] }} 
		@endif
		completed
		@if ($completionDate)
		at {{ $completionDate }}
		@endif
		@if ($status)
		with a status of '{{ $status }}'.
		@endif
		
		<br />
		<br />
		If you have any questions please contact the SWAMP staff at: {{ config('mail.contact.address') }} .

		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
