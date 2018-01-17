<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SWAMP Welcome</title>
	<style>
		body {
			font-family: sans-serif;
		}
	</style>
</head>
<body>
<div>
	<img id="logo"
src="{{ $logo }}" alt="logo" />
	<p>Greetings {{ $user->getFullName() }},</p>
	On behalf of the staff at the Software Assurance Marketplace: thank you for your interest in our services!
	<p>Please take a moment to view the <a
href="https://www.swampinabox.org/doc/SWAMP-User-Manual.pdf">SWAMP User Manual</a> and the <a
href='https://continuousassurance.org/video-tutorials/'>video tutorials</a> on our website; they provide valuable guidance for the initial orientation experience. </p>
	<p>
		<div style="float: left; width: 30%;">
			<p style="font-size:14px">&nbsp;</p>
			<p style="font-size:14px">Need Help?</p>
			<p style="font-size:14px">&nbsp;</p>
		</div>
		<div style="float: right; width: 70%;">
			<p style="font-size:14px">Failed Analysis?</p>
			<p style="font-size:14px">Broken Package?</p>
			<p style="font-size:14px">Confusing scheduler?</p>
		</div>
	</p>
	<p style="font-size:14px">We’re here to help with that!</p>
	We will gladly assist you for a productive and worthwhile experience, including analyzing failed assessment results for you, assisting with package content and package upload questions, and generally assuring that you are confident in your ability to use this service productively.
	<p>
		<p style="color:red;font-size:18px">Technical Support</p>
		The SWAMP offers 24/7 support 365 days a year.  Following is contact information for the SWAMP:
		<ul>
			<li>Dial: (317) 274-3942 (24/7/365)</li>
			<li>Email: <a href="mailto: support@continuousassurance.org">support@continuousassurance.org</a></li>
		</ul>
		To create a support ticket, go to
		<a href="https://ticket.continuousassurance.org">https://ticket.continuousassurance.org</a>
		or email
		<a href="mailto: support@continuousassurance.org">support@continuousassurance.org</a>
	</p>
	We look forward to ensuring your success here.
	<p>-The Software Assurance Marketplace (SWAMP)</p>
</div>
</body>
</html>
