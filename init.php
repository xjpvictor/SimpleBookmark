<?php
if ((isset($_SERVER["HTTP_X_PURPOSE"]) && strtolower($_SERVER["HTTP_X_PURPOSE"]) == "preview") ||  (isset($_SERVER["HTTP_X_MOZ"]) && strtolower($_SERVER["HTTP_X_MOZ"]) == "prefetch")) {
  http_response_code(404);
  exit;
}

if (!file_exists(($config_file = ($data_dir = __DIR__ . '/data/').'config.php')))
  exit('Please update "config.php" file according to "config.php-dist"');
if (!function_exists('password_hash'))
  exit('Please update your php version >= 5.5.3');

$curl_ua = 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36';
$bookmark_json = $data_dir.'bookmarks.json';
$sync_json = $data_dir.'sync.json';
$content_dir = $data_dir.'contents/';
$lib_dir = __DIR__.'/lib/';
$cache_dir = __DIR__ . '/cache/';
$preview_filename_prefix = 'preview-';
$preview_file_life = 30*86400;
$preview_height = '20';
$sync_file_prefix = 'sync-';
$cache_file_urllist = $cache_dir.'urls.json';
$cache_file_urlstatus = $cache_dir.'urlstatus.json';
$lock_file_urlstatus = $cache_dir.'urlstatus.lock';
$cache_file_urlstatus_output = $cache_dir.'urlstatus.js';

if (@filemtime($config_file) && function_exists('opcache_invalidate'))
  opcache_invalidate($config_file,true);
include($config_file);

include(__DIR__ . '/functions.php');

$site_name = ($site_name ? $site_name : 'My Bookmarks');
$site_url = ($site_url ? (stripos($site_url, 'http://') === false && stripos($site_url, 'https://') === false ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https://' : 'http://') : '').$site_url.(substr($site_url, -1) !== '/' ? '/' : '') : (isset($_SERVER['SERVER_NAME']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['SERVER_NAME'].'/' : ''));

$check_url = (!isset($check_url) ? 5 : $check_url);

$cost = 12; //Need to reset password if change this
if ($password && !preg_match('/\$2y\$'.$cost.'\$[\.\/0-9a-zA-Z]{'.(60-5-strlen($cost)).'}/', $password))
  file_put_contents($config_file, str_replace('$password = \''.$password.'\'', '$password = \''.($password = password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost])).'\'', file_get_contents($config_file)), LOCK_EX);

if (isset($passcode) && $passcode && !preg_match('/\$2y\$'.$cost.'\$[\.\/0-9a-zA-Z]{'.(60-5-strlen($cost)).'}/', $passcode))
  file_put_contents($config_file, str_replace('$passcode = \''.$passcode.'\'', '$passcode = \''.($passcode = password_hash($passcode, PASSWORD_BCRYPT, ['cost' => $cost])).'\'', file_get_contents($config_file)), LOCK_EX);

// Authentication
if (isset($_POST['p']) && password_verify($_POST['p'], $password)) {
  auth((isset($_POST['r']) && $_POST['r'] ? 31536000 : 0));
  $auth = true;
  session_regenerate_id(true);
  $_SESSION['auth'] = 1;
  if (isset($_GET['action']) && $_GET['action'] == 'login') {
    setcookie('_spbkmk_bookmark_lock', microtime(), time() + 31536000, '/', '', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 1 : 0), 1);
    header('Location: index.php');
    exit;
  }
} else
  $auth = auth();

?>
