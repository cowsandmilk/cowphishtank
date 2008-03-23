<?php
include 'PhishTank.php';

$phishtank = new PhishTank();

if(!$phishtank->isConfigured('frob')) {
	header('Location: frob-setup.php');
	exit();
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>PhishTank Setup</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<style type="text/css" media="screen,projection,print">
		@import 'goodform.css';
	</style>
</head>
<body>
<?php if($phishtank->setup('user')) { ?>
	<h2>Account Configured</h2>
	<p>You may proceed onto the example uses <a href="setup-examples.php">Examples</a></p>
	<?php
}else { ?>
<h2>Account Authorization Failed??</h2>

<p>You have two options.  You can either refresh the page to see if PhishTank now says you've been approvewd, or you may head back to the <a href="<?php echo $phishtank->authorization_url; ?>">authorization url</a></p>
<?php } ?>
</body>
</html>
