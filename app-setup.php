<?php
include 'PhishTank.php';

$phishtank = new PhishTank();
if(isset($_POST['action']) && $_POST['action'] == 'Submit App Details') {
	$phishtank->setup('application',array('app_key' => $_POST['app_key'], 'shared_secret' => $_POST['shared_secret']));
	if($phishtank->verifyApp()) {
		header('Location: frob-setup.php');
		exit();
	}
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
if($phishtank->isConfigured('application')) {
	?>
<p>PhishTank's application parameters are already configured.</p>
<dl>
	<dt>Application Key</dt>
		<dd><?php echo $phishtank->app_key; ?></dd>
	<dt>Shared Secret</dt>
		<dd><?php echo $phishtank->shared_secret; ?></dd>
</dl>
	<?php if($phishtank->verifyApp()) { ?>
		<p>You can continue on to obtaining a frob for personal use of the application or if you want to use a different application key, you can fill in the details below.</p>
<a href="frob-setup.php">Move on to Frob Setup</a>
	<?php
	}else {?>
		<p>PhishTank is returning errors for your app key and shared secret.  Please make sure they are correct.</p>
<?php
	}
}

?>
<h2>Phishtank Application Configuration</h2>

<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="goodform">
<div>
	<label>Application Key</label>
	<input type="text" name="app_key" id="app_key" />
</div>
<div>
	<label>Shared Secret</label>
	<input type="text" name="shared_secret" id="shared_secret" />
</div>
<div class="goodform-submit">
	<input type="submit" name="action" value="Submit App Details" />
</div>
</form>
</body>
</html>