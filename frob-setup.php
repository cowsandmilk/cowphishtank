<?php
include 'PhishTank.php';

$phishtank = new PhishTank();

if(isset($_POST['action']) && $_POST['action'] == 'Request API Key') {
	$phishtank->setup('frob');
	header('Location: '.$phishtank->authorization_url);
}
if(!$phishtank->isConfigured('application') || !$phishtank->verifyApp()) {
	header('Location: app-setup.php');
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
<?php


?>
<h2>Frob/Account Configuration</h2>
<p>To use PhishTank, you must have an account with PhishTank.  During this next step, you must log in with PhishTank, who will then provide an API key for the use of this application</p>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="goodform">
<div class="goodform-submit">
	<input type="submit" name="action" value="Request API Key" />
</div>
</form>
</body>
</html>