<?php

$data = json_decode(file_get_contents(__DIR__ . '/bookmark.json'), 1);
$data = $data['roots']['other']['children'];

$id = 0;

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

$bookmarks = toarray($data, $id);
$bookmarks[0] = array('entries' => $bookmarks[0]);

file_put_contents(__DIR__ . '/bookmarks.json', json_encode($bookmarks));

?>
