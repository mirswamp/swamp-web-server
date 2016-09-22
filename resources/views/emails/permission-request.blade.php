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
		Administrator,
		<br />
		<br />

		@if ( sizeof( $new_permissions ) > 0 )
		{{ $user->getFullName() }} has requested the following new permissions:
		<ul>
		@foreach ($new_permissions as $np)
    		<li>{{ $np }}</li>
		@endforeach
		</ul>
		<br />
		<br />
		@endif

		@if ( sizeof( $updated_permissions ) > 0 )
		{{ $user->getFullName() }} has requested to renew the following permissions:
		<ul>
		@foreach ($updated_permissions as $up)
    		<li>{{ $up }}</li>
		@endforeach
		</ul>
		<br />
		<br />
		@endif

		@if ( $meta_information )
		The use provided the following pieces of meta information:
			<br/><br/>
			@foreach ( $meta_information as $key => $value )
				<b>{{ $key }}:</b> {{ $value }} <br/><br/>
			@endforeach
			<br/><br/>
		@endif

		@if ( $comment )
		The user left the following justification:
		<br/><br/>
		{{ $comment }}
		</br></br>
		@endif

		<br/><br/>
		To administer {{ $user->getFullName() }}'s permissions, please click the following link:
		<a href="{{ $url }}/#accounts/{{ $user->user_uid }}/permissions">User Permissions</a>
		<br /><br />

		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
