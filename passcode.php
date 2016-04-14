<?php
include(__DIR__ . '/init.php');

if (isset($passcode) && $passcode) {
  if (isset($_GET['p']) && password_verify($_GET['p'], $passcode)) {
    echo "document.getElementById('lock').style.display='none';document.title = '".str_replace('\'', '\\\'', htmlentities($site_name))."';setLockCookie();window.addEventListener('scroll', setLockCookie);window.addEventListener('mousemove', setLockCookie);window.addEventListener('keypress', setLockCookie);lockDown();document.body.removeChild(document.getElementById('lock_s'));";
    exit;
  } elseif (isset($_GET['p'])) {
    echo "document.body.removeChild(document.getElementById('lock_s'));";
    exit;
  }
}

header("HTTP/1.1 401 Unauthorized");
exit;
