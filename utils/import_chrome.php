<?php
include(__DIR__ . '/../init.php');

if (isset($_FILES['f']['tmp_name']) && $_FILES['f']['tmp_name']) {
  $data = json_decode(file_get_contents($_FILES['f']['tmp_name']), 1);
  $data = $data['roots']['other']['children'];
  if (is_uploaded_file($_FILES['f']['tmp_name']))
    unlink($_FILES['f']['tmp_name']);
  $_FILES = array();
} else {
  header('Location: '.$site_url);
  exit;
}

if (file_exists($bookmark_json)) {
  $bookmarks = parse_bookmark_json($bookmark_json);
  $id = (++$bookmark[1]);
} else {
  $id = 0;
}

function toarray($data, $id) {
  $bookmarks = array();
  foreach ($data as $entry) {
    $id++;
    $bookmarks['_'.$id] = array('id' => $id,
      'type' => $entry['type'],
      'name' => $entry['name'],
      'date_added' => round($entry['date_added'] / 10000000));

    if ($entry['type'] == 'url') {
      $bookmarks['_'.$id]['url'] = (isset($entry['url']) ? $entry['url'] : '');
    } elseif ($entry['type'] == 'folder' && isset($entry['children']) && !empty($entry['children'])) {
      $result = toarray($entry['children'], $id);
      $bookmarks['_'.$id]['entries'] = $result[0];
      $id = $result[1];
    }
  }

  return array($bookmarks, $id);
}

$bookmarks_import = toarray($data, $id);
if (isset($bookmarks)) {
  $bookmarks[0]['entries'] = array_merge($bookmarks[0]['entries'], $bookmarks_import[0]);
} else {
  $bookmarks = array(0 => array('entries' => $bookmarks_import[0]), 1 => $bookmarks_import[1]);
}

file_put_contents($bookmark_json, json_encode($bookmarks));

header('Location: '.$site_url);
?>
