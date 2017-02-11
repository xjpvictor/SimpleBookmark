<?php
function get_http_code($entries, $timestamp) {
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
      curl_multi_add_handle($mh, $ch[$index]);
    }
  }

  $running = null;
  do {
    curl_multi_exec($mh, $running);
  } while ($running);

  foreach ($ch as $index => $c) {
    $response = curl_multi_getcontent($c);
    $header = array(
      'header_size' => curl_getinfo($c, CURLINFO_HEADER_SIZE),
      'http_code' => curl_getinfo($c, CURLINFO_HTTP_CODE),
      'content_type' => (($ct = curl_getinfo($c, CURLINFO_CONTENT_TYPE)) ? strtolower((($pos = strpos($ct, ';')) ? substr($ct, 0, $pos) : $ct)) : '')
    );
    $header['header'] = substr($response, 0, $header['header_size']);
    $body = substr($response, $header['header_size']);

    $header['downloadable'] = 0;
    $header['preview'] = 0;

    if ($header['http_code'] == 200 && $body)
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

header('Content-Type: text/javascript');
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header('Expires: '.gmdate('D, d M Y H:i:s', $timestamp).' GMT');

if (file_exists($lock_file_urlstatus) && $timestamp - filemtime($lock_file_urlstatus) <= 3600)
  exit;

touch($lock_file_urlstatus);

if (!file_exists($cache_file_urllist) && !file_exists($cache_file_urlstatus))
  exit;

$files = array();
if (file_exists($cache_file_urllist))
  $files[filemtime($cache_file_urllist)] = $cache_file_urllist;
if (file_exists($cache_file_urlstatus))
  $files[filemtime($cache_file_urlstatus)] = $cache_file_urlstatus;
ksort($files, SORT_NUMERIC);
$file = array_pop($files);
$urls = json_decode(file_get_contents($file), 1);

if (!$urls)
  exit;

if (isset($urls[1]) && $urls[1])
  array_multisort(array_column($urls[1], 'time', 'index'), SORT_ASC, SORT_NUMERIC, $urls[1]);
if (isset($urls[0]) && $urls[0])
  array_multisort(array_column($urls[0], 'time', 'index'), SORT_ASC, SORT_NUMERIC, $urls[0]);
$urls_to_update = array_merge((isset($urls[1]) && $urls[1] ? $urls[1] : array()), (isset($urls[0]) && $urls[0] ? array_slice($urls[0], 0, $check_url, 1) : array()));

$responses = get_http_code($urls_to_update, $timestamp);
$urls_output = array();

foreach ($responses as $index => $response) {
  $entry = $urls_to_update[$index];
  $entry['meta']['last_access'] = $timestamp;
  $entry['time'] = $timestamp;
  unset($urls[$entry['meta']['not_found']][$index]);

  $not_found = (substr($response['http_code'], 0, 1) != 2 ? 1 : 0);
  if ($entry['meta']['not_found'] != $not_found) {
    $entry['meta']['not_found'] = $not_found;
    $entry['meta']['downloadable'] = $response['downloadable'];
    $entry['meta']['preview'] = $response['preview'];
    $urls_output[] = array(0 => $not_found, 'id' => $entry['id']);
  }
  $urls[$not_found][$index] = $entry;
}

if ($urls_output)
  file_put_contents($cache_file_urlstatus, json_encode($urls), LOCK_EX);

echo (!$urls_output ? '' : '
var urls = '.json_encode(array_values($urls_output)).';
for (var i = 0, len = urls.length; i < len; i++) {
  var urlEntry = urls[i];
  var id = urlEntry["id"];
  var elem = document.getElementById(id);
  if (typeof elem !== "undefined" && elem !== null) {
    if (urlEntry[0]) {
      elem.classList.add("not-found");
      document.getElementById("last-access-message-"+id).innerHTML = "Page Not Found.";
      document.getElementById("last-access-time-"+id).innerHTML = "'.date('Y-m-d, G:i', $timestamp).'";
    } else {
      elem.classList.remove("not-found");
      document.getElementById("last-access-message-"+id).innerHTML = "";
      document.getElementById("last-access-time-"+id).innerHTML = "";
    }
  }
}
');

if (file_exists($lock_file_urlstatus));
  unlink($lock_file_urlstatus);

exit;
