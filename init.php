<?php
if (!file_exists(($config_file = ($data_dir = __DIR__ . '/data/').'config.php')))
  exit('Please update "config.php" file according to "config.php-dist"');
if (!function_exists('password_hash'))
  exit('Please update your php version >= 5.5.3');

$bookmark_json = $data_dir.'bookmarks.json';
$cache_dir = __DIR__ . '/cache/';

if (@filemtime($config_file) && function_exists('opcache_invalidate'))
  opcache_invalidate($config_file,true);
include($config_file);

include(__DIR__ . '/functions.php');

$site_name = ($site_name ? $site_name : 'My Bookmarks');
$site_url = ($site_url ? (stripos($site_url, 'http://') === false && stripos($site_url, 'https://') === false ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https://' : 'http://') : '').$site_url.(substr($site_url, -1) !== '/' ? '/' : '') : (isset($_SERVER['SERVER_NAME']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['SERVER_NAME'].'/' : ''));

$cost = 12; //Need to reset password if change this
if ($password && !preg_match('/\$2y\$'.$cost.'\$[\.\/0-9a-zA-Z]{'.(60-5-strlen($cost)).'}/', $password))
  file_put_contents($config_file, str_replace('$password = \''.$password.'\'', '$password = \''.($password = password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost])).'\'', file_get_contents($config_file)), LOCK_EX);

$auth = auth($_SERVER['REMOTE_ADDR']);

// Authentication
if (isset($_POST['p']) && password_verify($_POST['p'], $password)) {
  $auth = true;
  session_regenerate_id(true);
  $_SESSION['time'] = time();
  $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
}

if (!isset($parsemail) && $auth === false) {
  session_destroy();
}
?>
