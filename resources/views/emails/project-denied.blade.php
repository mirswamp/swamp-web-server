<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP Project Denied</title>
	<style>
		body {
			font-family: sans-serif;
		}
	</style>
</head>
<body>
	<div>
		Dear {{ $project['owner'] }},
		<br />
		<br />
		Your project: {{ $project['full_name'] }} has been denied.

		<br />
		<br />
		If you believe this is in error or have other questions, please contact the SWAMP staff at: http://continuousassurance.org/contact/

		<br />
		<br />
		-The Software Assurance Marketplace (SWAMP)
	<div>
</body>
</html>
