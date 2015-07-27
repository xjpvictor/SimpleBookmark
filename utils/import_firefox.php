<?php

$data = json_decode(file_get_contents(__DIR__ . '/bookmark.json'), 1);
$data = $data['children'][2]['children'];

$id = 0;

function toarray($data, $id) {
  $bookmarks = array();
  foreach ($data as $entry) {
    $id++;
    $bookmarks['_'.$id] = array('id' => $id,
      'type' => ($entry['type'] == 'text/x-moz-place-container' ? 'folder' : 'url'),
      'name' => $entry['title'],
      'date_added' => round($entry['dateAdded'] / 10000000));

    if ($entry['type'] == 'text/x-moz-place') {
      $bookmarks['_'.$id]['url'] = (isset($entry['uri']) ? $entry['uri'] : '');
    } elseif ($entry['type'] == 'text/x-moz-place-container' && isset($entry['children']) && !empty($entry['children'])) {
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
