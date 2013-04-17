<?php

/* not ready for use, there is a bug */

if (php_sapi_name() != 'cli') {
	print "This program must be called from the command line.\n";
	exit(1);
}

if (!file_exists('wp-config.php')) {
	print "Cannot find wp-config.php\n";
	exit(1);
}

include('wp-config.php');

print "\nCurrent Table Prefix: ".$table_prefix."\n";

if ($table_prefix=='wp_') {
	$new_table_prefix = readline("Enter new table prefix (letters, numbers, underscore):");

	if ($new_table_prefix!='') {
		if ($new_table_prefix==$table_prefix) {
			print "Table prefix unchanged. Exiting.\n";
			exit(1);
		}
	} else {
		print "Table prefix is empty string. Exiting.\n";
		exit(1);
	}
}

$mysqli=false;
if (!function_exists(mysql_connect)) {
	if (function_exists(mysqli_connect)) {
		print "\nmysql_connect does not exist, but mysqli_connect does, switching to mysqli mode.\n";
		$mysqli=true;
	}
}

/* get list of tables */
$tables=array();
switch ($mysqli) {
	case true:
		$conn=mysqli_connect(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);
		$sql = "SHOW TABLES FROM `".DB_NAME."`";
		$res = mysqli_query($conn,$sql) or die($sql);
		while ($row = mysqli_fetch_array($res)) {
			if (substr($row[0],0,strlen($table_prefix))==$table_prefix) {
				$tables[]=$row[0].' TO '.$new_table_prefix.substr($row[0],strlen($table_prefix),strlen($row[0])-strlen($table_prefix));
			}
		}
		mysqli_free_result($res);
		break;
	case false: /* fall through */
	default:
		$conn=mysql_connect(DB_HOST,DB_USER,DB_PASSWORD);
		$db=mysql_select_db(DB_NAME);
		$sql = "SHOW TABLES FROM `".DB_NAME."`";
		$res = mysql_query($sql,$conn);
		if (substr($row[0],0,strlen($table_prefix))==$table_prefix) {
			$tables[]=$row[0].' TO '.$new_table_prefix.substr($row[0],strlen($table_prefix),strlen($row[0])-strlen($table_prefix));
		}
		mysql_free_result($res);
		break;
}

if (count($tables)<1) {
	print "Error - could not find wp tables. Exiting.\n";
	exit(1);
}

print "\nRenaming wp tables:\n";
foreach ($tables as $v) {
	print "From ".$v."\n";
}

$ok = readline("\nIs this OK? Type 'yes' to commit changes: ");
if ($ok!='yes') {
	print "You did not type 'yes'. No changes made. Exiting.\n";
	exit(1);
}

/* rename tables all at once. if something fails, all will be reverted per doc. */

$sql = "RENAME TABLE ".join(',',$tables);
print "\nExecuting: ".$sql."\n\n";

switch ($mysqli) {
	case true:
		mysqli_query($conn,$sql) or die($sql);
		break;
	case false: /* fall through */
	default:
		mysql_query($sql,$conn) or die($sql);
		break;
}

/* Save new table prefix in wp-config.php */
$f=join('',file('wp-config.php'));
$f=str_replace("'".$table_prefix."'","'".$new_table_prefix."'",$f);
$fp=fopen('wp-config.php','w');
fwrite($fp,$f);
fclose($fp);

/* check for admin user */

$change_admin = false;
$sql = "SELECT `ID` FROM `".$new_table_prefix."users` WHERE user_login='admin'";

switch ($mysqli) {
	case true:
		$res = mysqli_query($conn,$sql);
		if (mysqli_num_rows($res)>0) {
			$change_admin = true;
		}
		mysqli_free_result($res);
		break;
	case false: /* fall through */
		$res = mysql_query($sql,$conn);
		if (mysql_num_rows($res)>0) {
			$change_admin = true;
		}
		mysql_free_result($res);
		break;
}

if ($change_admin) {
	$newadmin = readline("The admin username exists. Please enter a new username for the admin user. Refer to wordpress documentation regarding acceptable usernames. Alpha no spaces < 60 chars should work.:");
	if ($newadmin!='') {
		$sql = "UPDATE `".$new_table_prefix."users` SET user_login='".addslashes($newadmin)."' WHERE user_login='admin'";
		switch ($mysqli) {
			case true:
				mysqli_query($conn,$sql) or die($sql);
				break;
			case false: /* fall through */
			default: 
				mysql_query($sql,$conn) or die ($sql);
				break;
		}
		print "\nUser 'admin' updated to '".$newadmin."'. Please make a note of it.\n";
	}
}

	
	


$uid = uniqid();

print "\nMoving wp-config.php to wp-config-".$uid.".php...\n";
system('mv wp-config.php wp-config-'.$uid.'.php');
if (!file_exists('wp-config-'.$uid.'.php')) {
	print "Oops. Could not create wp-config-".$uid.".php. Exiting.\n";
	exit(1);
}
if (file_exists('wp-config.php')) {
	print "Oops. wp-config.php still exists.\n";
	exit(1);
}

$t=`find . | grep -v cfixer.php`;
$x=explode("\n",$t);
foreach ($x as $v) {
	if (strlen($v)>4) {
		if (substr($v,strlen($v)-4,4)=='.php') {
			$f = join('',file($v));
			if (strstr($f,'wp-config.php')) {
				$f=str_replace('wp-config.php','wp-config-'.$uid.'.php',$f);
				$fp=fopen($v,'w');
				fwrite($fp,$f);
				fclose($fp);
				print $v." updated.\n";
			}
		}
	}
}


print "\nMoving wp-login.php to wp-login-".$uid.".php...\n";
system('mv wp-login.php wp-login-'.$uid.'.php');
if (!file_exists('wp-login-'.$uid.'.php')) {
        print "Oops. Could not create wp-login-".$uid.".php. Exiting.\n";
        exit(1);
}
if (file_exists('wp-login.php')) {
        print "Oops. wp-login.php still exists.\n";
        exit(1);
}
$t=`find . | grep -v cfixer.php`;
$x=explode("\n",$t);
foreach ($x as $v) {
        if (strlen($v)>4) {
                if (substr($v,strlen($v)-4,4)=='.php') {
                        $f = join('',file($v));
                        if (strstr($f,'wp-login.php')) {
                                $f=str_replace('wp-login.php','wp-login-'.$uid.'.php',$f);
                                $fp=fopen($v,'w');
                                fwrite($fp,$f);
                                fclose($fp);
                                print $v." updated.\n";
                        }
                }
        }
}


print "\nMoving wp-includes to wp-includes-".$uid."...\n";
$t=`find . | grep -v cfixer.php`;
$x=explode("\n",$t);

foreach ($x as $v) {
	if (strlen($v)>4) {
		if (substr($v,strlen($v)-4,4)=='.php') {
			$f = join('',file($v));
			if (strstr($f,'wp-includes')) {
				$f=str_replace('wp-includes','wp-includes-'.$uid,$f);
				$fp=fopen($v,'w');
				fwrite($fp,$f);
				fclose($fp);
				print $v." updated.\n";
			}
		}
	}
}
system('mv wp-includes wp-includes-'.$uid);

print "\nMoving wp-admin to wp-admin-".$uid."...\n";
$t=`find . | grep -v cfixer.php`;
$x=explode("\n",$t);

foreach ($x as $v) {
	if (strlen($v)>4) {
	        if (substr($v,strlen($v)-4,4)=='.php') {
	                $f = join('',file($v));
	                if (strstr($f,'wp-admin')) {
	                        $f=str_replace('wp-admin/','wp-admin-'.$uid.'/',$f);
	                        $fp=fopen($v,'w');
	                        fwrite($fp,$f);
	                        fclose($fp);
	                        print $v." updated.\n";
	                }
	        }
	}
}
system('mv wp-admin wp-admin-'.$uid);

/* get site url so we can output the new admin link */

$sql = "SELECT option_value FROM ".$new_table_prefix."options WHERE option_name='siteurl'";
switch ($mysqli) {
	case true:
		$res=mysqli_query($conn,$sql);
		$row=mysqli_fetch_array($res);
		$url = stripslashes($row['option_value']);
		mysqli_free_result($res);
		break;
	case false: /* fall through */
	default:
		$res=mysql_query($sql,$conn);
		$row=mysql_fetch_array($res);
		$url=stripslashes($row['option_value']);
		mysql_free_result($res);
		break;
}

	
print "\n\nAll done. Make sure to keep a record of your wp-admin location,\n";
print $url."/wp-admin-".$uid."/\n\n";
exit(0);

