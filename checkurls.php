<?php
function get_http_code($entries, $timestamp) {
  global $curl_ua;

  $responses = array();
  $ch = array();

  $mh = curl_multi_init();

  foreach ($entries as $index => $entry) {
    if ($entry['meta']['not_found'] || $timestamp - $entry['meta']['last_access'] > 86400) {
      $ch[$index] = curl_init();
      curl_setopt($ch[$index], CURLOPT_URL, $entry['url']);
      curl_setopt($ch[$index], CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch[$index], CURLOPT_HEADER, 1);
      curl_setopt($ch[$index], CURLOPT_TIMEOUT, 30);
      curl_setopt($ch[$index], CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch[$index], CURLOPT_HTTPHEADER, array(
        'User-Agent: '.$curl_ua,
      ));
      curl_multi_add_handle($mh, $ch[$index]);
    }
  }

  $running = null;
  do {
    $mrc = curl_multi_exec($mh, $running);
  } while ($mrc == CURLM_CALL_MULTI_PERFORM);

  while ($running && $mrc == CURLM_OK) {
    if (curl_multi_select($mh) == -1) {
      usleep(1);
    }
    do {
      $mrc = curl_multi_exec($mh, $running);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);
  }

  foreach ($ch as $index => $c) {
    $response = curl_multi_getcontent($c);
    $header = array(
      'header_size' => curl_getinfo($c, CURLINFO_HEADER_SIZE),
      'http_code' => (($hc = curl_getinfo($c, CURLINFO_HTTP_CODE)) ? $hc : 404),
      'content_type' => (($ct = curl_getinfo($c, CURLINFO_CONTENT_TYPE)) ? strtolower((($pos = strpos($ct, ';')) ? substr($ct, 0, $pos) : $ct)) : '')
    );
    $header['header'] = substr($response, 0, $header['header_size']);
    $body = substr($response, $header['header_size']);

    $header['downloadable'] = 0;
    $header['preview'] = 0;

    if ($header['http_code'] == 200)
      $header = parse_header($header, $body, $entries[$index]['url']);

    $responses[$index] = $header;

    curl_multi_remove_handle($mh, $c);
  }

  curl_multi_close($mh);

  return $responses;
}

include(__DIR__ . '/init.php');

if (!$check_url)
  exit;

$timestamp = time();

if (!file_exists($cache_file_urllist))
  exit;

$file = $cache_file_urllist;
if (file_exists($cache_file_urlstatus) && filemtime($cache_file_urlstatus) > filemtime($cache_file_urllist))
  $file = $cache_file_urlstatus;
$urls = json_decode(file_get_contents($file), 1);

if (!$urls)
  exit;

header('HTTP/1.1 200 Ok');
header('Content-Type: text/javascript');
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header('Expires: '.gmdate('D, d M Y H:i:s', $timestamp).' GMT');

ob_end_clean();
ob_start();

if (isset($_GET['c']) && $_GET['c'] == 1) {
  if (file_exists($cache_file_urlstatus_output))
    echo file_get_contents($cache_file_urlstatus_output);
}
echo 'setTimeout(addURLChecker, 5000, 0);';

$size=ob_get_length();
header("Content-Length: $size");
header("Connection: close");
ob_end_flush();
flush();
if (function_exists('fastcgi_finish_request'))
  fastcgi_finish_request();
if (session_id())
  session_write_close();

if (file_exists($lock_file_urlstatus) && $timestamp - filemtime($lock_file_urlstatus) <= 3600)
  exit;

touch($lock_file_urlstatus);

$urls_to_update = array_merge((isset($urls[1]) && $urls[1] ? $urls[1] : array()), (isset($urls[0]) && $urls[0] ? array_slice($urls[0], rand(0, count($urls[0])-1), $check_url, 1) : array()));

if ($urls_to_update) {
  $responses = get_http_code($urls_to_update, $timestamp);

  if ($responses) {
    foreach ($responses as $index => $response) {
      $entry = $urls_to_update[$index];
      $entry['meta']['last_access'] = $timestamp;
      $entry['meta']['http_code'] = $response['http_code'];
      unset($urls[$entry['meta']['not_found']][$index]);
      if (isset($urls[($entry['meta']['not_found'] ? 0 : 1)][$index]))
        unset($urls[($entry['meta']['not_found'] ? 0 : 1)][$index]);

      $not_found = (substr($response['http_code'], 0, 1) != 2 ? 1 : 0);
      if ($entry['meta']['not_found'] != $not_found) {
        $entry['meta']['not_found'] = $not_found;
        $entry['meta']['downloadable'] = $response['downloadable'];
      }
      if ($response['preview'])
        $entry['meta']['preview'] = $response['preview'];
      $urls[$not_found][$index] = $entry;
    }

    if ($file == $cache_file_urllist || file_exists($cache_file_urlstatus))
      file_put_contents($cache_file_urlstatus, json_encode($urls), LOCK_EX);
  }
}

$output = (!isset($urls[1]) || !$urls[1] ? '' : '
var urls = '.json_encode(array_values($urls[1])).';
for (var i = 0, len = urls.length; i < len; i++) {
  var urlEntry = urls[i];
  var id = urlEntry["id"];
  var elem = document.getElementById(id);
  if (typeof elem !== "undefined" && elem !== null) {
    if (urlEntry["meta"]["not_found"]) {
      elem.classList.add("not-found");
      document.getElementById("last-access-message-"+id).innerHTML = document.getElementById("last-access-message-"+id).getAttribute("data-content");
      document.getElementById("last-access-code-"+id).innerHTML = urlEntry["meta"]["http_code"];
      document.getElementById("last-access-time-"+id).innerHTML = "'.date('Y-m-d, G:i', $timestamp).'";
    }
  }
}
');

file_put_contents($cache_file_urlstatus_output, $output);

if (file_exists($lock_file_urlstatus));
  unlink($lock_file_urlstatus);

exit;
