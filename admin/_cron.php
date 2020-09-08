<?php
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../Htpasswd.php';
                    
/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

$beginnScript = microtime(true);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=$WebsiteTitle?> - Cron</title>
<style type="text/css">
.admin {
background:#0099FF;
color:#FFFFFF;
padding:3px;
line-height:160%
}

.true {
background:#009900;
color:#FFFFFF;
padding:3px;
line-height:160%
}

.false {
background:#FF0000;
color:#FFFFFF;
padding:3px;
line-height:160%
}

.left {
background:#FF9900;
color:#000000;
padding:3px;
line-height:160%
}

.time {
background:#FFFF00;
color:#000000;
padding:3px;
line-height:160%
}

.iderror {
background:#333333;
color:#FF3300;
padding:3px;
line-height:160%;
font-weight:bolder
}

.stat {
background:#FF99CC;
color:#000000;
padding:3px;
line-height:160%;
font-weight:bolder;
font-style:oblique
}
</style>
</head>
<body>
<?php
require_once dirname(__FILE__) . '/../functions.php';

$sql_abos = $mysqli->query("SELECT * FROM ".$tbl);
while($get_user = $sql_abos->fetch_array()) {
    if($get_user["userid"] == NULL) {
        
        $from_username = $get_user["TelegramUser"];
        $getUserId = callAPI('GET', $apiServer."getInfo/?id=$from_username", false);
        
        $output = json_decode($getUserId, true);
        $user_id = $output["response"]["InputPeer"]["user_id"];
        
    
        mysqli_query($mysqli, "UPDATE ".$tbl." SET userid = $user_id WHERE id = ".$get_user["id"]." ");
        
        echo "set userid '".$user_id."' for ".$from_username."<br>";
    }
}


$timestamp = time();
$datum = date("Y-m-d H:i:s", $timestamp);
echo $datum;

echo "<br><br>";

$query = "SELECT * FROM channels";
$result = $mysqli->query($query);

if($result->num_rows) { 
    //echo "<h2>ergebnisse vorhanden</h2>";
    include(dirname(__FILE__) . "/_delete_user.php");
} else {
    echo "<h2>nix zu tun!</h2>";
}

// User löschen die in keinen Kanälen sind !!!
$non_exists_user = $mysqli->query("SELECT * FROM ".$tbl);
while($manual = $non_exists_user->fetch_array()) {
    if($manual["userid"]) {
        if($manual["endtime"] < date("Y-m-d H:i:s") ) {
            mysqli_query($mysqli, "DELETE FROM ".$tbl." WHERE id = ".$manual['id']." ");
            echo "<p>delete".$manual['TelegramUser'].'</p>';
            if($use_map == "PMSF") {
                mysqli_query($mysqli, "UPDATE users SET access_level = '0' WHERE user = '".$manual['buyerEmail']."' ");
            } elseif($use_map == "Rocketmap") {
                $htpasswd = new Htpasswd('../.htpasswd');
                $htpasswd->deleteUser($manual["TelegramUser"]);
            }
        }
    }
}

$dauerScript = microtime(true) - $beginnScript;
echo "<h4>Verarbeitung der Cron in ".sprintf('%.3f', $dauerScript)." Sek.</h4>";

$datei = fopen(dirname(__FILE__) . "/cron.txt","w");
fwrite($datei, date("d.m.Y - H:i:s"),100);
fclose($datei);
?>
</body>
</html>