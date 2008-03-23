<?php
include('PhishTank.php');
include('PhishTank_Url.php');

$phishtank = new PhishTank();
if (!$phishtank->isConfigured('user')) {
    header('Location: user-setup.php');
    exit();
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>PhishTank Examples</title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <style type="text/css" media="screen,projection,print">
        @import 'goodform.css';
    </style>
</head>
<body>
<?php
if(isset($_POST['action'])) {
    switch ($_POST['action']) {
    case 'Check Url (API)':
        $url = filter_input(INPUT_POST,'url', FILTER_VALIDATE_URL);
        if ($url) {
            $phishtank = new PhishTank();
            $phishtankurl = $phishtank->checkUrl($url);
            ?>
            <table>
            <?php   displayPhishHeader();
                    displayPhishRow($phishtankurl); ?>
            </table>
            <?php
        } else {
            echo '<p>Not a valid url</p>';
        }
        break;
    case 'Check Url (Simple)':
        $url = filter_input(INPUT_POST,'url', FILTER_VALIDATE_URL);
        if ($url) {
            $phishtankurl = PhishTank::simpleCheckUrl($url);
            ?>
            <table>
            <?php   displayPhishHeader();
                    displayPhishRow($phishtankurl); ?>
            </table>
            <?php
        } else {
            echo '<p>Not a valid url</p>';
        }
        break;
    case 'Check Email':
        $phishtank = new PhishTank();
        $check = $phishtank->checkEmail($_POST['email']); ?>
        <table>
        <?php
            displayPhishHeader();
            foreach ($check['urls'] as $phishtankurl) {
                displayPhishRow($phishtankurl);
            } ?>
        </table>
	<?php
        break;
    case 'Submit Url':
        $url = filter_input(INPUT_POST,'url', FILTER_VALIDATE_URL);
        if ($url) {
            $phishtank = new PhishTank();
            $phishtankurl = $phishtank->submitUrl($url);
            ?>
            <table>
            <?php
                displayPhishHeader();
                displayPhishRow($phishtankurl);
                ?>
            </table>
        <?php
        } else {
            echo '<p>Not a valid url';
        }
        break;
    case 'Submit Email':
        $phishtank = new PhishTank();
        $check = $phishtank->submitEmail($_POST['email']);
        if ($check['inDatabase']) {
            echo '<p>All urls in email were already in database</p>';
        } else {
            echo '<p>Email added to database</p>';
        }
        ?>
        <table>
            <?php
            displayPhishHeader();
            foreach ($check['urls'] as $phishtankurl) { 
                displayPhishRow($phishtankurl);
            } ?>
        </table>
    <?php
        break;
    case 'Check Urls (API)':
        ?>
        <table>
            <?php
            displayPhishHeader();
            foreach ($_POST['urls'] as $url) {
                if ($url != '') {
                    $response = PhishTank::simple_check_url($url);
                    $phishtankurl = new PhishTank_Url($response->results->url0);
                    displayPhishRow($phishtankurl);
                }	
            }
            ?>
        </table>
        <?php
        break;
    case 'Check Urls (Simple)':
        $phishtank = new PhishTank();
        ?>
        <table>
            <?php
            displayPhishHeader();
            foreach ($_POST['urls'] as $url) {
                if ($url != '') {
                    $response = $phishtank->check_url($url);
                    $phishtankurl = new PhishTank_Url($response->results->url0); 
                    displayPhishRow($phishtankurl);
                }	
            } ?>
        </table>
        <?php
        break;
    case 'Check Emails':
        $phishtank = new PhishTank();
        foreach ($_POST['emails'] as $email) {
            if ($email != '') {
                $response = $phishtank->check_email($email); ?>
                <table>
                    <?php
                    displayPhishHeader();
                    foreach ($response->results->children() AS $url) {
                        $phishtankurl = new PhishTank_Url($url);
                        displayPhishRow($phishtankurl);
                    } ?>
                </table>
            <?php
            }
        } 
        break;
    default:
        echo 'Unknown Request';
    }
}
?>
<h1>Example Methods</h1>

<h2>Check Single Url</h2>

<h3>Check using full API</h3>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="goodform">
<div>
    <label for="url-api">Url: </label>
    <input type="text" id="url-api" name="url" />
</div>
<div class="goodform-submit">
    <input type="submit" name="action" value="Check Url (API)" />
</div>
</form>

<h3>Check using simple API</h3>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="goodform">
<div>
    <label for="url-siimple">Url: </label>
    <input type="text" id="url-simple" name="url" />
</div>
<div class="goodform-submit">
    <input type="submit" name="action" value="Check Url (Simple)" />
</div>
</form>

<h2>Check Email</h2>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="goodform">
<div>
    <label for="email">Email: </label>
    <textarea id="email" name="email"></textarea>
</div>
<div class="goodform-submit">
    <input type="submit" name="action" value="Check Email" />
</div>
</form>

<h2>Submit Single Url</h2>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="goodform">
<div>
    <label for="url-submit">Url: </label>
    <input type="text" id="url-submit" name="url" />
</div>
<div class="goodform-submit">
    <input type="submit" name="action" value="Submit Url" />
</div>
</form>

<h2>Submit Email</h2>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="goodform">
<div>
    <label for="email-submit">Email: </label>
    <textarea id="email-submit" name="email"></textarea>
</div>
<div class="goodform-submit">
    <input type="submit" name="action" value="Submit Email" />
</div>
</form>

<h2>Check Multiple Urls</h2>
<h3>Check using full API</h3>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="goodform">
<?php for ($i =1; $i<=5; ++$i) {?>
    <div>
        <label for="url-api<?php echo $i; ?>">Url <?php echo $i; ?></label>
        <input type="text" id="url-api<?php echo $i; ?>" name="urls[]" />
    </div>
<?php } ?>
<div class="goodform-submit">
    <input type="submit" name="action" value="Check Urls (API)" />
</div>
</form>

<h3>Check using simple API</h3>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="goodform">
<?php for ($i =1; $i<=5; ++$i) {?>
    <div>
        <label for="url-simple<?php echo $i; ?>">Url <?php echo $i; ?></label>
        <input type="text" id="url-simple<?php echo $i; ?>" name="urls[]" />
    </div>
<?php } ?>
<div class="goodform-submit">
    <input type="submit" name="action" value="Check Urls (Simple)" />
</div>
</form>

<h2>Check Multiple Emails</h2>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="goodform">
<?php for ($i =1; $i<=5; ++$i) {?>
    <div>
        <label for="email<?php echo $i; ?>">Email <?php echo $i; ?></label>
        <textarea id="email<?php echo $i; ?>" name="emails[]"></textarea>
    </div>
<?php } ?>
<div class="goodform-submit">
    <input type="submit" name="action" value="Check Emails" />
</div>
</form>

</body>
</html>
<?php
/**************************************************************/
function displayPhishHeader()
{   ?>
    <tr>
        <th>URL</th>
        <th>In Database</th>
        <th>Phish Id</th>
        <th>Phish Detail Page</th>
        <th>Verified</th>
        <th>Valid</th>
    </tr>
<?php
}

function displayPhishRow($phishtankurl)
{   ?>
    <tr>
        <td><?php echo $phishtankurl->url; ?></td>
        <td><?php echo $phishtankurl->in_database; ?></td>
        <td><?php echo $phishtankurl->phish_id; ?></td>
        <td><a href="<?php echo $phishtankurl->phish_detail_page; ?>">Page</a></td>
        <td><?php echo $phishtankurl->verified; ?></td>
        <td><?php echo $phishtankurl->valid; ?></td>
        <td><?php echo $phishtankurl->submitted_at; ?></td>
    </tr>
<?php
}
?>