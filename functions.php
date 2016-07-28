<?php
function toutf8($str) {
  if (!$str)
    return $str;
  if (mb_detect_encoding($str, 'ascii, utf-8'))
    return $str;
  elseif ($encode = mb_detect_encoding($str, 'gbk, gb2312, gb18030, big5, big5-hkscs, iso-8859-1, iso-8859-2, iso-8859-3, iso-8859-4, iso-8859-5, iso-8859-6, iso-8859-7, iso-8859-8, iso-8859-9, iso-8859-10, iso-8859-11, iso-8859-13, iso-8859-14, iso-8859-15, iso-8859-16, utf-16, utf-32, windows-1250, windows-1251, windows-1252, windows-1253, windows-1254, windows-1255, windows-1256, windows-1257, windows-1258, euc-jp, euc-kr, euc-tw, hz-gb-2312, ibm866, iso-2022-cn, iso-2022-jp, iso-2022-jp-1, iso-2022-kr, koi8-r, koi8-u, shift-jis, us-ascii, viscii'))
    return mb_convert_encoding($str, 'UTF-8', $encode);
  else
    return '';
}

function auth($expire = null) {
  if (isset($expire))
    session_set_cookie_params($expire, '/', '', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 1 : 0), 1);
  session_name('_spbkmk_bookmark_');
  session_save_path(__DIR__ . '/session');
  if (session_status() !== PHP_SESSION_ACTIVE)
    session_start();

  if (session_status() === PHP_SESSION_ACTIVE && (!isset($_SESSION['robot']) || $_SESSION['robot'] !== 0)) {
    if (isset($_COOKIE['_spbkmk_bookmark_notRobot']) && $_COOKIE['_spbkmk_bookmark_notRobot'] == 1)
      $_SESSION['robot'] = 0;
    else {
      session_destroy();
      return false;
    }
  }

  if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== 1)
    return false;
  else
    return true;
}

function parse_bookmark_json($bookmark_json) {
  $bookmarks = json_decode(file_get_contents($bookmark_json), 1);
  return $bookmarks;
}

function output_bookmarks_recursive($bookmarks, $output = array('url' => '', 'folder' => ''), $level = '') {
  global $site_url;

  foreach ($bookmarks as $bookmark_id => $entry) {
    if (isset($entry['id'])) {
      if ($entry['type'] == 'url') {
        if (!isset($entry['name']) || $entry['name'] == '')
          $entry['name'] = $entry['url'];
        $output['url'] .= '<!-- url '.$entry['id'].' in '.$level.' -->
<p class="entry url" style="display:block;" id="entry-'.$level.'_'.$entry['id'].'">
<span class="target" id="target-'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'">
<span class="move" id="move-'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'" draggable="true"></span>
<span class="border">
<a class="url search" href="'.$entry['url'].'" id="'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'" data-type="url" title="'.htmlentities($entry['url']).'"><span id="title-'.$level.'_'.$entry['id'].'">'.$entry['name'].'</span></a>
<a class="edit" href="javascript:;" onclick="toggleShow(\'entry-'.$level.'_'.$entry['id'].'\');toggleShow(\'editform-'.$level.'_'.$entry['id'].'\')">Edit</a>
</span>
</span>
</p>
<form class="editform" id="editform-'.$level.'_'.$entry['id'].'" action="index.php?action=edit&id='.$level.'_'.$entry['id'].'" method="post">
<input name="n" type="text" required value="'.htmlentities($entry['name']).'"><br/>
<input name="u" type="text" required value="'.htmlentities($entry['url']).'"><br/>
<select name="l">##FOLDERLIST-'.($level ? $level : '_0').'##</select><br/><br/>
<input type="submit" value="Update">
<a class="cancel" href="javascript:;" onclick="toggleShow(\'entry-'.$level.'_'.$entry['id'].'\');toggleShow(\'editform-'.$level.'_'.$entry['id'].'\')">Cancel</a>
<a class="delete" onclick="return confirm(\'Permanently delete this bookmark?\');" href="index.php?action=delete&id='.$level.'_'.$entry['id'].'">Delete</a><br/>
</form>'."\n";
      } elseif ($entry['type'] == 'folder') {
        $output['url'] .= '<!-- folder '.$entry['id'].' in '.$level.' -->
<div class="folder" id="'.$level.'_'.$entry['id'].'">
<span class="entry" id="entry-'.$level.'_'.$entry['id'].'" style="display:block;">
<h3 class="folder_title" style="display:block;">
<span class="target" id="target-'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'">
<span onclick="document.getElementById(\'search\').value=\'\';searchStrFunction();location.href=\'index.php#'.$level.'_'.$entry['id'].'\';" class="folder_title_name" id="title-'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'" data-type="folder">&raquo;&nbsp;'.$entry['name'].'</span>
<span class="move" id="move-'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'" draggable="true" onclick="toggleShow(\'folder-wrap-'.$level.'_'.$entry['id'].'\');"></span>
<a class="edit" href="javascript:;" onclick="toggleShow(\'entry-'.$level.'_'.$entry['id'].'\');toggleShow(\'editform-'.$level.'_'.$entry['id'].'\')">Edit</a>
<a class="bookmarklet" href="javascript:var url=\''.$site_url.'\';var x=document.createElement(\'SCRIPT\');x.type=\'text/javascript\';x.src=url+\'bookmarklet.php?d='.$level.'_'.$entry['id'].'\';document.getElementsByTagName(\'head\')[0].appendChild(x);void(0);" onclick="if(event.preventDefault){event.preventDefault();}if(event.stopPropagation){event.stopPropagation();}return false;" title="Drag to add bookmarklet">Add to '.htmlentities($entry['name']).'</a>
<a class="bookmarklet" href="javascript:if(document.getElementById(\'spbkmk\')){document.getElementById(\'spbkmk\').parentNode.removeChild(document.getElementById(\'spbkmk\'));}var bml=document.createElement(\'div\');bml.id=\'spbkmk\';bml.style.setProperty(\'position\',\'fixed\',\'important\');bml.style.setProperty(\'z-index\',2147483640,\'important\');bml.style.setProperty(\'top\',0,\'important\');bml.style.setProperty(\'left\',0,\'important\');bml.style.setProperty(\'right\',0,\'important\');bml.style.setProperty(\'text-align\',\'left\',\'important\');bml.style.setProperty(\'background-color\',\'#fff\',\'important\');bml.style.setProperty(\'min-height\',\'28px\',\'important\');bml.style.setProperty(\'max-height\',\'56px\',\'important\');bml.style.setProperty(\'overflow\',\'hidden\',\'important\');bml.style.setProperty(\'border-bottom-width\',\'1px\',\'important\');bml.style.setProperty(\'border-bottom-style\',\'solid\',\'important\');bml.style.setProperty(\'border-bottom-color\',\'#666\',\'important\');document.body.appendChild(bml);var script=document.createElement(\'script\');script.src=\''.$site_url.'bookmarkbar.php?folder='.$level.'_'.$entry['id'].'\';bml.appendChild(script);" onclick="if(event.preventDefault){event.preventDefault();}if(event.stopPropagation){event.stopPropagation();}return false;" title="Drag to add bookmarklet">Open '.htmlentities($entry['name']).' as bookmarkbar</a>
<a class="bookmarklet" href="javascript:window.open(\''.$site_url.'#'.$level.'_'.$entry['id'].'\', \'spbkmk\', \'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=600,height=800\');" onclick="if(event.preventDefault){event.preventDefault();}if(event.stopPropagation){event.stopPropagation();}return false;" title="Drag to add bookmarklet">Open '.htmlentities($entry['name']).' in new window</a>
</span>
</h3>
</span>
<form class="editform editfolder" id="editform-'.$level.'_'.$entry['id'].'" action="index.php?action=edit&id='.$level.'_'.$entry['id'].'" method="post">
<input name="n" type="text" required value="'.htmlentities($entry['name']).'"><br/>
<select name="l">##FOLDERLIST-'.($level ? $level : '_0').'##</select><br/><br/>
<input type="submit" value="Update">
<a class="cancel" href="javascript:;" onclick="toggleShow(\'entry-'.$level.'_'.$entry['id'].'\');toggleShow(\'editform-'.$level.'_'.$entry['id'].'\')">Cancel</a>
<a class="delete" onclick="return confirm(\'Permanently delete this folder? Note: bookmarks in this folder will NOT be deleted.\');" href="index.php?action=delete&id='.$level.'_'.$entry['id'].'">Delete</a>
<a class="delete" onclick="return confirm(\'Permanently delete this folder? Note: all items in this folder WILL be deleted.\');" href="index.php?action=delete&id='.$level.'_'.$entry['id'].'&items=1">Delete all</a>
<p class="sort">Sort folder by:
<a class="sort-name" href="index.php?action=sort&id='.$level.'_'.$entry['id'].'&sort=name">Name</a>,
<a class="sort-date" href="index.php?action=sort&id='.$level.'_'.$entry['id'].'&sort=date_added">Date</a> or
<a class="sort-reverse" href="index.php?action=sort&id='.$level.'_'.$entry['id'].'&sort=0">Reverse order</a>
</p>
</form>
<span id="folder-wrap-'.$level.'_'.$entry['id'].'" style="display:block;">'."\n";
        $output['folder'] .= '<option value="'.$level.'_'.$entry['id'].'">'.$entry['name'].'</option>'."\n";
        if (isset($entry['entries']) && !empty($entry['entries']))
          $output = output_bookmarks_recursive($entry['entries'], $output, $level.'_'.$entry['id'], $entry['name']);
        $output['url'] .= '</span><span class="target" id="target-'.$level.'_'.$entry['id'].'_0" data-id="'.$level.'_'.$entry['id'].'_0">&nbsp;</span></div>'."\n";
      }
    }
  }
  return $output;
}

function output_bookmarks($bookmarks) {
  $output = output_bookmarks_recursive($bookmarks);

  $output['folder'] = '<option value="_0">My Bookmarks</option>'."\n".$output['folder'];
  $output['url'] = preg_replace_callback('/##FOLDERLIST-([_0-9]+)##/i', function ($match) use ($output) {
    return str_replace('value="'.$match[1].'"', 'value="'.$match[1].'" selected', $output['folder']);
  }, $output['url']);

  return $output;
}

function add_bookmark($url, $folder, $type, $bookmark_json, $name = null) {
  $bookmark = parse_bookmark_json($bookmark_json);
  $id = (++$bookmark[1]);
  if ($type == 'url' && !isset($name)) {
    $name = substr($url, 0, 140);
    //$str = @file_get_contents($url);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $str = curl_exec($ch);
    curl_close($ch);
    if (strlen($str)) {
      preg_match('/\<title\>(.*)\<\/title\>/i', $str, $title);
      $name = (isset($title[1]) ? toutf8($title[1]) : '');
    }
  }
  $new = array('_'.$id => array('id' => $id, 'type' => $type, 'name' => ($type == 'url' ? $name : $url), 'date_added' => (isset($time) ? $time : time())));
  if ($type == 'url')
    $new['_'.$id]['url'] = $url;
  $data = $new['_'.$id];
  if ($folder) {
    $levels = array_reverse(explode('_', substr($folder, 1)));
    foreach ($levels as $level) {
      if ($level)
        $new = array('_'.$level => array('entries' => $new));
    }
  }

  if (isset($bookmark[0]['entries']) && is_array($bookmark[0]['entries']))
    $bookmark[0]['entries'] = array_merge_recursive($bookmark[0]['entries'], $new);
  else
    $bookmark[0]['entries'] = $new;

  file_put_contents($bookmark_json, json_encode($bookmark), LOCK_EX);
  if ($type == 'folder')
    move_bookmark($data, ($folder !== '_0' ? $folder : '').'_-1', $bookmark_json);
  return $data;
}

function update_bookmark_recursive($bookmark, $levels, $index, $callback_function, $callback_parameters) {
  if (empty($levels) || $index == count($levels)) {
    $data = call_user_func($callback_function, $bookmark, $callback_parameters);
  } else {
    $data = update_bookmark_recursive($bookmark['entries']['_'.$levels[$index]], $levels, $index + 1, $callback_function, $callback_parameters);
    $bookmark['entries']['_'.$levels[$index]] = $data[0];
    $data[0] = $bookmark;
  }
  return $data;
}

function delete_bookmark($id, $delete_contents, $bookmark_json) {
  $bookmark = parse_bookmark_json($bookmark_json);
  $levels = explode('_', substr($id, 1));
  $id = array_pop($levels);
  $data = update_bookmark_recursive($bookmark[0], $levels, 0, 'delete_bookmark_callback', array($id, $delete_contents));
  $bookmark[0] = $data[0];
  if (isset($data[2]) && !empty($data[2]))
    $bookmark[0]['entries'] = array_merge($bookmark[0]['entries'], $data[2]);
  file_put_contents($bookmark_json, json_encode($bookmark), LOCK_EX);
  return $data[1];
}

function delete_bookmark_callback($bookmark, $parameters) {
  $id = $parameters[0];
  $entry = $bookmark['entries']['_'.$id];
  unset($bookmark['entries']['_'.$id]);
  if ($entry['type'] == 'folder' && isset($parameters[1]) && !$parameters[1] && isset($entry['entries']) && !empty($entry['entries']))
    $contents = $entry['entries'];
  return ((isset($contents) ? array($bookmark, $entry, $contents) : array($bookmark, $entry)));
}

function update_bookmark($id, $update, $bookmark_json) {
  $bookmark = parse_bookmark_json($bookmark_json);
  $levels = explode('_', substr($id, 1));
  $id = array_pop($levels);
  $data = update_bookmark_recursive($bookmark[0], $levels, 0, 'update_bookmark_callback', array($id, $update));
  $bookmark[0] = $data[0];
  file_put_contents($bookmark_json, json_encode($bookmark), LOCK_EX);
  return $data[1];
}

function update_bookmark_callback($bookmark, $parameters) {
  $id = $parameters[0];
  $update = $parameters[1];
  foreach ($update as $key => $value) {
    $bookmark['entries']['_'.$id][$key] = $value;
  }
  return (array($bookmark, $bookmark['entries']['_'.$id]));
}

function move_bookmark($entry, $destination, $bookmark_json) {
  $bookmark = parse_bookmark_json($bookmark_json);
  $levels = explode('_', substr($destination, 1));
  $position = '_'.array_pop($levels);
  $data = update_bookmark_recursive($bookmark[0], $levels, 0, 'move_bookmark_callback', array($entry, $position));
  $bookmark[0] = $data[0];
  file_put_contents($bookmark_json, json_encode($bookmark), LOCK_EX);
  return $data[1];
}

function move_bookmark_callback($bookmark, $parameters) {
  $entry = $parameters[0];
  $position = $parameters[1];
  $id = $entry['id'];
  if ($position == '_0') {
    $bookmark['entries']['_'.$id] = $entry;
  } elseif ($position == '_-1') {
    $bookmark['entries'] = array_merge(array('_'.$id => $entry), $bookmark['entries']);
  } else {
    $position = array_search($position, array_keys($bookmark['entries']));
    $bookmark['entries'] = array_merge(array_slice($bookmark['entries'], 0, $position), array('_'.$id => $entry), array_slice($bookmark['entries'], $position));
  }
  return (array($bookmark, $entry));
}

function sort_bookmark($id, $sort, $recursive = 0, $bookmark_json) {
  $bookmark = parse_bookmark_json($bookmark_json);
  $id_s = $id;
  $levels = explode('_', substr($id, 1));
  $id = array_pop($levels);
  $data = update_bookmark_recursive($bookmark[0], $levels, 0, 'sort_bookmark_callback', array($id, $sort));
  $bookmark[0] = $data[0];
  file_put_contents($bookmark_json, json_encode($bookmark), LOCK_EX);
  if ($recursive == 1) {
    if ($id == 0) {
      $entries = $data[1];
      $id_s = '';
    } else
      $entries = $data[1]['entries'];
    foreach ($entries as $entry) {
      if ($entry['type'] == 'folder') {
        $data[1]['entries']['_'.$entry['id']] = sort_bookmark($id_s.'_'.$entry['id'], $sort, 1, $bookmark_json);
      }
    }
  }
  return $data[1];
}

function sort_bookmark_callback($bookmark, $parameters) {
  $id = $parameters[0];
  $sort = $parameters[1];
  if ($sort) {
    $seq = array();
    $type = array();
    if ($id == 0)
      $entries = $bookmark['entries'];
    else
      $entries = $bookmark['entries']['_'.$id]['entries'];
    foreach ($entries as $entry) {
      $seq[] = strtolower($entry[$sort]);
      $type[] = $entry['type'];
    }
    if ($sort == 'name')
      array_multisort($type, SORT_ASC, SORT_STRING, $seq, SORT_ASC, SORT_NATURAL, ($id == 0 ? $bookmark['entries'] : $bookmark['entries']['_'.$id]['entries']));
    elseif ($sort == 'date_added')
      array_multisort($type, SORT_DESC, SORT_STRING, $seq, SORT_DESC, ($id == 0 ? $bookmark['entries'] : $bookmark['entries']['_'.$id]['entries']));
  } else {
    if ($id == 0)
      $bookmark['entries'] = array_reverse($bookmark['entries']);
    else
      $bookmark['entries']['_'.$id]['entries'] = array_reverse($bookmark['entries']['_'.$id]['entries']);
  }
  return (array($bookmark, ($id == 0 ? $bookmark['entries'] : $bookmark['entries']['_'.$id])));
}
?>
