<?php
include(__DIR__ . '/init.php');

if (isset($passcode) && $passcode) {
  if (isset($_POST['p']) && password_verify($_POST['p'], $passcode)) {
    header("HTTP/1.1 200 OK");
    exit;
  } elseif (isset($_POST['p'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
  }
}

header("HTTP/1.1 401 Unauthorized");
exit;
