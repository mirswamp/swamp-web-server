<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP Permission Granted</title>
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

		@if ( sizeof( $new_permissions ) > 0 )
		An administrator has given you the following {{ $status }} permissions:
		<ul>
		@foreach ($new_permissions as $np)
    		<li>{{ $np }}</li>
		@endforeach
		</ul>
		<br />
		<br />
		@endif

		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
