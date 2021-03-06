<?php
require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/../functions.php');
                    
//Output any connection error
if ($mysqli->connect_error) {
    die('Error : ('. $mysqli->connect_errno .') '. $mysqli->connect_error);
}

if(!file_exists(__DIR__.'/msg.php') and file_exists(__DIR__.'/msg_example.php')) {
    copy(__DIR__."/msg_example.php",__DIR__."/msg.php");
}

$id = mysqli_real_escape_string($mysqli, $_GET["id"]);
                    
$query = "SELECT * FROM ".$tbl." WHERE id = $id";
$result = $mysqli->query($query);

$row = $result->fetch_array();

if(isset($_POST["submit"]) and $_POST["user"]) {
    
    function generateRandomString($length = 10) {
        //return substr(str_shuffle(str_repeat(implode('', range('!','z')), $length)), 0, $length);
        return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
    }
	
	// give costum passwort or generate automaticle
	if(isset($_POST["pass"])) {
		$passwd = mysqli_real_escape_string($mysqli, $_POST["pass"]);
	} else {
		$passwd = generateRandomString(8);
	}
    
    $newUser = mysqli_real_escape_string($mysqli, $_POST["user"]);
    if(isset($_POST["email"])) {
        $newMail = mysqli_real_escape_string($mysqli, $_POST["email"]);
    }
    $newAdd = $_POST["user"];
    $ItemDesc = $newAdd;
    
    $OldUser = $row["TelegramUser"]; // old Username delete
    $InputChannel = array($row["channels"]);
    
    $getInfo	= callAPI('GET', $apiServer."getfullInfo/?id=".$ItemDesc, false);
    $getUserId	= json_decode($getInfo, true);
    $userid		= $getUserId["response"]["InputPeer"]["user_id"];
    $date		= $row["endtime"];
    
    if($userid) {
        $useridnow = ", userid = '$userid'";
    } else {
        $useridnow = ", userid = NULL";
    }
    
    mysqli_query($mysqli, "UPDATE ".$tbl." SET TelegramUser = '".$newUser."'".$useridnow.", pass = '".$passwd."' WHERE id = ".$row["id"]);
    
    if($use_map == "PMSF") {
        $statement = "insert";
        $hashedPwd = password_hash($passwd, PASSWORD_DEFAULT);
        
        $loginName	= $_POST["email"];
        mysqli_query($mysqli, "UPDATE users SET user = '".$newMail."', password = NULL, temp_password = '".$hashedPwd."', session_id = NULL WHERE user = '".$loginName."' ");
    } elseif($use_map == "Rocketmap") {
        $statement = "insert";
        $loginName	= $newAdd;
                        
        include("../Htpasswd.php");
    
        $htpasswd = new Htpasswd('../.htpasswd');
        $htpasswd->deleteUser($OldUser);
        $htpasswd->addUser($newAdd, $passwd);
    }
    
    include_once("msg.php");
                        
    if($botSend == '1') {
        if($use_map == "PMSF" or $use_map == "Rocketmap") {
            $botMessage = $UserMsg;
        } else {
            $botMessage = $UserMsgShort;
        }
        $sendMessage = callAPI('GET', $apiServer."sendMessage/?data[peer]=$userid&data[message]=$botMessage&data[parse_mode]=html", false);
    }
    
    include_once("_add_user.php");
                    
    if($mailSend == '1') {
        
        $empfaenger	= $row["buyerEmail"];
        require_once('../mailer/class.phpmailer.php');

        $mail             = new PHPMailer();
        $mail->CharSet	  = 'ISO-8859-1';
        
        ob_start();
        include("../mail.php");
        $body = ob_get_contents();
        ob_end_clean();

        $mail->IsSMTP(); // telling the class to use SMTP
        $mail->Host       = $mailHost; // SMTP server
        $mail->Port       = $smtpPort;                    // set the SMTP port for the GMAIL server
        $mail->SMTPSecure = $smtpSecure;
        $mail->SMTPDebug  = 0;                     // enables SMTP debug information (for testing)
                                           // 1 = errors and messages
                                           // 2 = messages only
        $mail->SMTPAuth   = true;                  // enable SMTP authentication
        $mail->Username   = $smtpUser; // SMTP account username
        $mail->Password   = $smtpPass;        // SMTP account password
        
        $mail->SetFrom($mailSender, $WebsiteTitle);
        $mail->AddReplyTo($mailSender, $WebsiteTitle);
        
        $mail->Subject    = $mailSubject;
        $mail->AltBody    = strip_tags($body); // optional, comment out and test
        $mail->MsgHTML($body);
        $mail->AddAddress($empfaenger, $WebsiteTitle);

        $mail->Send();
    }
    
    $query = "SELECT * FROM ".$tbl." WHERE id = $id";
    $result = $mysqli->query($query);
    $row = $result->fetch_array();
                
    $userSave = "<h3 style=\"background:#333333; color:#00CC00; padding:5px; text-align:center\">Benutzer ge&auml;ndert zu ".$newAdd."</h3>";
} elseif(isset($_POST["submit2"]) and $_POST["itemprice"]) {
    
    $query2 = "SELECT SUM(item_price) as total, SUM(abo_days) as abo, COUNT(id) as menge FROM products";
    $result2 = $mysqli->query($query2);
    $row2 = $result2->fetch_array();
    $schnitt = $row2["total"]/$row2["abo"];	// durchschnittlicher preis pro tag

    $sumBar = mysqli_real_escape_string($mysqli, $_POST["itemprice"]);
    $sumBar = str_replace(",",".", $sumBar);
    
    $days_to_end = $_POST["itemprice"]/$schnitt;
    $days_to_end = ceil($days_to_end);
        
    $amountInsert = $row["Amount"];
    $amountInsert+=$sumBar;
    
    $userid = $row["userid"];
    
    $date = date('Y-m-d H:i:s', strtotime($row["endtime"]. " + {$days_to_end} days"));
    
    if($use_map == "PMSF") {
        $datum = new DateTime($date);
        $datum = $datum->getTimestamp();
        $expire_timestamp = $datum;
        
        $check_user = $mysqli->query("SELECT id FROM users WHERE user = '".$row["buyerEmail"]."' ");
        if($check_user->num_rows != 0) {
            $update_user = $check_user->fetch_array();
            mysqli_query($mysqli, "UPDATE users SET expire_timestamp = '".$expire_timestamp."' WHERE id = ".$update_user["id"]);
        }
    }
    
    mysqli_query($mysqli, "UPDATE ".$tbl." SET Amount = $amountInsert, TransID = NULL, paydate = now(), endtime = DATE_ADD(endtime,INTERVAL $days_to_end DAY), info = NULL WHERE id = ".$row["id"]);
    
    if(!isset($loginName))	{ $loginName	= ''; }
    if(!isset($passwd))		{ $passwd		= ''; }
    
    include_once("msg.php");
    
    if($botSend == '1') {
        $botMessage = $extendUserMsg;
        $sendMessage = callAPI('GET', $apiServer."sendMessage/?data[peer]=$userid&data[message]=$botMessage&data[parse_mode]=html", false);
    }
                    
    if($mailSend == '1') {
        
        $statement = "update";
        $empfaenger	= $row["buyerEmail"];
        require_once('../mailer/class.phpmailer.php');

        $mail             = new PHPMailer();
        $mail->CharSet	  = 'ISO-8859-1';
        
        ob_start();
        include("../mail.php");
        $body = ob_get_contents();
        ob_end_clean();

        $mail->IsSMTP(); // telling the class to use SMTP
        $mail->Host       = $mailHost; // SMTP server
        $mail->Port       = $smtpPort;                    // set the SMTP port for the GMAIL server
        $mail->SMTPSecure = $smtpSecure;
        $mail->SMTPDebug  = 0;                     // enables SMTP debug information (for testing)
                                           // 1 = errors and messages
                                           // 2 = messages only
        $mail->SMTPAuth   = true;                  // enable SMTP authentication
        $mail->Username   = $smtpUser; // SMTP account username
        $mail->Password   = $smtpPass;        // SMTP account password
        
        $mail->SetFrom($mailSender, $WebsiteTitle);
        $mail->AddReplyTo($mailSender, $WebsiteTitle);
        
        $mail->Subject    = $mailSubject;
        $mail->AltBody    = strip_tags($body); // optional, comment out and test
        $mail->MsgHTML($body);
        $mail->AddAddress($empfaenger, $WebsiteTitle);

        $mail->Send();
    }
    
    $userSave = "<h3 style=\"background:#333333; color:#00CC00; padding:5px; text-align:center\">Abo verl&auml;ngert auf ".$date."</h3>";
    
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
<title><?=$WebsiteTitle ?> - ADMIN EDIT</title>
</head>

<body>
<main role="main" class="container">
<?php include "nav.php"; ?>
<div class="jumbotron">
<?php
if(isset($_POST["submit"]) or isset($_POST["submit2"])) { echo $userSave; }
if(isset($_GET["delete"])) {	
    echo "<div align='center'><h2>M&ouml;chtest du den Benutzer</h2><h1 style='font-style:italic'><a href='#'>".$row["TelegramUser"]."</a></h1><h2>unwiderruflich l&ouml;schen?</h2>";
    ?>
    <form method="post" action="index.php">
        <a class="btn btn-sm btn-outline-secondary" href="_edit_user.php?id=<?=$id?>" role="button">abbrechen</a>
        <input type="hidden" name="username" value="<?=$row["TelegramUser"]?>" />
        <input type="hidden" name="email" value="<?=$row["buyerEmail"]?>" />
        <input type="hidden" name="deleteUser" value="<?=$row["id"]?>" />
        <input type="submit" class="btn btn-sm btn-outline-secondary" value="Benutzer l&ouml;schen" />
    </form>
    <?php
    echo "</div>";
} else { ?>
<a class="btn btn-sm btn-outline-secondary" style="margin-bottom:20px" href="<?=dirname($_SERVER["SCRIPT_NAME"])?>" role="button">zur&uuml;ck</a>
<a class="btn btn-sm btn-outline-secondary" style="margin-bottom:20px" href="?id=<?=$id?>&delete=<?=$id?>" role="button">Benutzer l&ouml;schen</a>
<h1>Benutzer umbenennen</h1>
<form name="one" method="post" action=""> 
  <table class="table">
    <tr>
      <th width="50%" scope="col">Aktueller @Username</th>
      <th scope="col"><?=$row["TelegramUser"] ?></th>
    </tr>
    <tr>
      <th scope="col">Passwort</th>
      <th scope="col"><input type="text" name="pass" class="form-control" autocomplete="off" placeholder="leave blank to generate a password" value="<?=$row["pass"] ?>" /></th>
    </tr>
    <tr>
      <th scope="col"><b>Neuer @Username</b></th>
      <th scope="col"><input type="text" name="user" class="form-control" autocomplete="off" required /></th>
    </tr>
    <?php if($use_map == "PMSF") { ?>
    <tr>
      <th scope="col">Neue Emailadresse</th>
      <th scope="col"><input type="text" name="email" class="form-control" autocomplete="off" required /></th>
    </tr>
    <?php } ?>
    <tr>
      <th scope="col">&nbsp;</th>
      <th scope="col"><input class="btn btn-sm btn-outline-secondary" type="submit" name="submit" value="User &auml;ndern!" /></th>
    </tr>
  </table>
</form>
<h1>Abo verl&auml;ngern</h1>
<form name="two" method="post" action=""> 
  <table class="table">
    <tr>
      <th width="50%" scope="col">Abonnent</th>
      <th scope="col"><?=$row["TelegramUser"] ?></th>
    </tr>
    <tr>
      <th scope="col">Bar erhalten</th>
      <th scope="col"><input type="text" name="itemprice" class="form-control" placeholder="&euro;" required /></th>
    </tr>
    <tr>
      <th scope="col">&nbsp;</th>
      <th scope="col"><input class="btn btn-sm btn-outline-secondary" type="submit" name="submit2" value="Abo verl&auml;ngern!" /></th>
    </tr>
  </table>
</form>
<?php
}
?>
</div>
</main>
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
</body>
</html>
