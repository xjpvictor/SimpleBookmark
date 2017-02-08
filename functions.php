<?php
function addhttp($url) {
  if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
    $url = "http://" . $url;
  }
  return $url;
}
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

function escattr($match) {
  if (is_array($match))
    $match = '<'.$match[1].(isset($match[6]) ? $match[6] : '').'>';
  return preg_replace_callback('/<([^>]*\s+)?(on[^=]+|jsaction|data|data-[a-z]+|dynsrc|accesskey|tabindex|shape|srcset|alt|title)\s*=\s*(("[^"]*")|(\'[^\']*\'))?(\s+[^>]*\/?)?>/i', 'escattr', $match);
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
  $bookmarks = (file_exists($bookmark_json) ? json_decode(file_get_contents($bookmark_json), 1) : false);
  return $bookmarks;
}

function output_bookmarks_recursive($bookmarks, $allow_edit, $deduplicate, $bookmark_json, $output = array('url' => '', 'folder' => ''), $level = '') {
  global $site_url;

  foreach ($bookmarks as $bookmark_id => $entry) {
    if (isset($entry['id'])) {
      if ($entry['type'] == 'url') {
        if (!isset($entry['hash']))
          $entry['hash'] = sha1($entry['url']);
        if ($deduplicate && is_array($deduplicate) && isset($deduplicate[$entry['hash']]))
          $entry = delete_bookmark(($level ? $level : 0).'_'.$entry['id'], 0, $bookmark_json);
        else {
          if ($deduplicate) {
            if (is_array($deduplicate))
              $deduplicate[$entry['hash']] = 1;
            else
              $deduplicate = array($entry['hash'] => 1);
          }
          if (!isset($entry['name']) || $entry['name'] == '')
            $entry['name'] = $entry['url'];
          $output['url'] .= '<!-- url '.$entry['id'].' in '.($level ? $level : '0').' -->
'.(!$allow_edit ? '<form class="save" id="editform-'.$level.'_'.$entry['id'].'" action="index.php?action=add" method="post">
<input type="hidden" name="u" value="'.htmlentities($entry['url']).'">
<input type="hidden" name="t" value="sync">
<input type="hidden" name="n" value="'.($entry['name'] == $entry['url'] ? '' : htmlentities($entry['name'])).'">' : '').'
<p class="entry url" style="display:block;"'.($allow_edit ? ' id="entry-'.$level.'_'.$entry['id'].'"' : '').'>
<span class="target touchOver'.(!$allow_edit ? ' noedit' : '').'"'.($allow_edit ? ' id="target-'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'"' : '').'>
<span class="move'.(!$allow_edit ? ' noedit' : '').'"'.($allow_edit ? ' id="move-'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'" draggable="true"' : '').'></span>
<span class="border touchOver" data-id="'.$level.'_'.$entry['id'].'">
<a class="url touchOver'.($allow_edit ? ' search' : '').'" href="'.$entry['url'].'"'.($allow_edit ? ' id="'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'"' : '').' data-type="url" title="'.htmlentities($entry['url']).'"><span class="touchOver" data-id="'.$level.'_'.$entry['id'].'" id="title-'.$level.'_'.$entry['id'].'">'.$entry['name'].'</span></a>
'.($allow_edit ? (isset($entry['meta']['offline']) && $entry['meta']['offline'] && $entry['meta']['offline'] !== -1 && isset($entry['meta']['content_type']) && $entry['meta']['content_type'] ? '<a class="offline" href="index.php?action=view&id='.$entry['id'].'-'.$entry['meta']['offline'].'&type='.urlencode($entry['meta']['content_type']).'" target="_blank">Cache</a>' : '').'<a class="edit" href="javascript:;" onclick="toggleShow(\'entry-'.$level.'_'.$entry['id'].'\');toggleShow(\'editform-'.$level.'_'.$entry['id'].'\')">Edit</a>' : '<a class="delete noedit" onclick="return confirm(\'Permanently delete this bookmark?\');" href="index.php?mode=sync&action=delete&id='.$level.'_'.$entry['id'].'">Delete</a>').'
'.(!$allow_edit ? '<select name="d" onchange="if(this.selectedIndex)this.form.submit();"><option value="-1" selected disabled style="display:none;">Save</option>##FOLDERLIST##</select>' : '').'
</span>
</span>
</p>
'.(!$allow_edit ? '</form>' : '').'
'.($allow_edit ? '<form class="editform" id="editform-'.$level.'_'.$entry['id'].'" action="index.php?action=edit&id='.$level.'_'.$entry['id'].'" method="post">
<input name="n" type="text" required value="'.htmlentities($entry['name']).'"><br/>
<input name="u" type="text" required value="'.htmlentities($entry['url']).'"><br/>
<select name="l">##FOLDERLIST-'.($level ? $level : '_0').'##</select><br/><br/>
<input type="submit" value="Update">
<a class="cancel" href="javascript:;" onclick="toggleShow(\'entry-'.$level.'_'.$entry['id'].'\');toggleShow(\'editform-'.$level.'_'.$entry['id'].'\')">Cancel</a>
<a class="delete" onclick="return confirm(\'Permanently delete this bookmark?\');" href="index.php?action=delete&id='.$level.'_'.$entry['id'].'">Delete</a>
'.(!isset($entry['meta']['downloadable']) || $entry['meta']['downloadable'] ? '<a class="save" href="index.php?action=save&level='.$level.'&id='.$entry['id'].'&url='.urlencode($entry['url']).(isset($entry['meta']['offline']) && $entry['meta']['offline'] && $entry['meta']['offline'] !== -1 ? '&delete='.$entry['meta']['offline'].'">Delete Cache' : '">Enable Cache').'</a>' : '').'
<br/>
</form>' : '')."\n";
        }
      } elseif ($entry['type'] == 'folder') {
        $output['url'] .= '<!-- folder '.$entry['id'].' in '.($level ? $level : '0').' -->
<div class="folder"'.($allow_edit ? ' id="'.$level.'_'.$entry['id'].'"' : '').'>
<span class="entry"'.($allow_edit ? ' id="entry-'.$level.'_'.$entry['id'].'"' : '').' style="display:block;">
<h3 class="folder_title touchOver" style="display:block;" data-id="'.$level.'_'.$entry['id'].'">
<span class="target touchOver'.(!$allow_edit ? ' noedit' : '').'"'.($allow_edit ? ' id="target-'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'"' : '').'>
<span class="folder_title_name touchOver"'.($allow_edit ? ' onclick="document.getElementById(\'search\').value=\'\';searchStrFunction();location.href=\'index.php#'.$level.'_'.$entry['id'].'\';" id="title-'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'"' : '').' data-type="folder">&raquo;&nbsp;'.$entry['name'].'</span>
<span class="move"'.($allow_edit ? ' id="move-'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'" draggable="true" onclick="toggleShow(\'folder-wrap-'.$level.'_'.$entry['id'].'\');"' : '').'></span>
'.($allow_edit ? '<a class="edit" href="javascript:;" onclick="toggleShow(\'entry-'.$level.'_'.$entry['id'].'\');toggleShow(\'editform-'.$level.'_'.$entry['id'].'\')">Edit</a>
'.(isset($entry['cache']) && $entry['cache'] ? '<span class="cache">Cache enabled</span>' : '').'
<a class="bookmarklet" href="javascript:var url=\''.$site_url.'\';var x=document.createElement(\'SCRIPT\');x.type=\'text/javascript\';x.src=url+\'bookmarklet.php?d='.$level.'_'.$entry['id'].'\';document.getElementsByTagName(\'head\')[0].appendChild(x);void(0);" onclick="if(event.preventDefault){event.preventDefault();}if(event.stopPropagation){event.stopPropagation();}return false;" title="Drag to add bookmarklet">Add to '.htmlentities($entry['name']).'</a>
<a class="bookmarklet" href="javascript:if(document.getElementById(\'spbkmk\')){document.getElementById(\'spbkmk\').parentNode.removeChild(document.getElementById(\'spbkmk\'));}var bml=document.createElement(\'div\');bml.id=\'spbkmk\';bml.style.setProperty(\'position\',\'fixed\',\'important\');bml.style.setProperty(\'z-index\',2147483640,\'important\');bml.style.setProperty(\'top\',0,\'important\');bml.style.setProperty(\'left\',0,\'important\');bml.style.setProperty(\'right\',0,\'important\');bml.style.setProperty(\'text-align\',\'left\',\'important\');bml.style.setProperty(\'background-color\',\'#fff\',\'important\');bml.style.setProperty(\'min-height\',\'28px\',\'important\');bml.style.setProperty(\'max-height\',\'56px\',\'important\');bml.style.setProperty(\'overflow\',\'hidden\',\'important\');bml.style.setProperty(\'border-bottom-width\',\'1px\',\'important\');bml.style.setProperty(\'border-bottom-style\',\'solid\',\'important\');bml.style.setProperty(\'border-bottom-color\',\'#666\',\'important\');document.body.appendChild(bml);var script=document.createElement(\'script\');script.src=\''.$site_url.'bookmarkbar.php?folder='.$level.'_'.$entry['id'].'\';bml.appendChild(script);" onclick="if(event.preventDefault){event.preventDefault();}if(event.stopPropagation){event.stopPropagation();}return false;" title="Drag to add bookmarklet">Open '.htmlentities($entry['name']).' as bookmarkbar</a>
<a class="bookmarklet" href="javascript:window.open(\''.$site_url.'#'.$level.'_'.$entry['id'].'\', \'spbkmk\', \'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=600,height=800\');" onclick="if(event.preventDefault){event.preventDefault();}if(event.stopPropagation){event.stopPropagation();}return false;" title="Drag to add bookmarklet">Open '.htmlentities($entry['name']).' in new window</a>' : '').'
</span>
</h3>
</span>
'.($allow_edit ? '<form class="editform editfolder" id="editform-'.$level.'_'.$entry['id'].'" action="index.php?action=edit&id='.$level.'_'.$entry['id'].'" method="post">
<input name="n" type="text" required value="'.htmlentities($entry['name']).'"><br/>
<select name="l">##FOLDERLIST-'.($level ? $level : '_0').'##</select><br>
<input type="hidden" name="c" value="0">
<p><label><input class="cache" type="checkbox" name="c" value="1"'.(isset($entry['cache']) && $entry['cache'] ? ' checked' : '').'>Cache new bookmarks</label></p>
<input type="submit" value="Update">
<a class="cancel" href="javascript:;" onclick="toggleShow(\'entry-'.$level.'_'.$entry['id'].'\');toggleShow(\'editform-'.$level.'_'.$entry['id'].'\')">Cancel</a>
<a class="delete" onclick="return confirm(\'Permanently delete this folder? Note: bookmarks in this folder will NOT be deleted.\');" href="index.php?action=delete&id='.$level.'_'.$entry['id'].'">Delete</a>
<a class="delete" onclick="return confirm(\'Permanently delete this folder? Note: all items in this folder WILL be deleted.\');" href="index.php?action=delete&id='.$level.'_'.$entry['id'].'&items=1">Delete all</a>
<p class="sort">Sort folder by:
<a class="sort-name" href="index.php?action=sort&id='.$level.'_'.$entry['id'].'&sort=name">Name</a>,
<a class="sort-date" href="index.php?action=sort&id='.$level.'_'.$entry['id'].'&sort=date_added">Date</a> or
<a class="sort-reverse" href="index.php?action=sort&id='.$level.'_'.$entry['id'].'&sort=0">Reverse order</a>
</p>
</form>' : '').'
<span'.($allow_edit ? ' id="folder-wrap-'.$level.'_'.$entry['id'].'"' : '').' style="display:block;">'."\n";
        if ($allow_edit)
          $output['folder'] .= '<option value="'.$level.'_'.$entry['id'].'">'.$entry['name'].'</option>'."\n";
        if (isset($entry['entries']) && !empty($entry['entries']))
          $output = output_bookmarks_recursive($entry['entries'], $allow_edit, $deduplicate, $bookmark_json, $output, $level.'_'.$entry['id']);
        $output['url'] .= '</span><span class="target touchOver'.(!$allow_edit ? ' noedit' : '').'"'.($allow_edit ? ' id="target-'.$level.'_'.$entry['id'].'_0" data-id="'.$level.'_'.$entry['id'].'_0"' : '').'>&nbsp;</span></div>'."\n";
      }
    }
  }
  return $output;
}

function output_bookmarks($bookmarks, $bookmark_json, $allow_edit = 1, $deduplicate = 0) {
  $output = output_bookmarks_recursive($bookmarks, $allow_edit, $deduplicate, $bookmark_json);

  $output['folder'] = '<option value="_0">My Bookmarks</option>'."\n".$output['folder'];
  $output['url'] = preg_replace_callback('/##FOLDERLIST-([_0-9]+)##/i', function ($match) use ($output) {
    return str_replace('value="'.$match[1].'"', 'value="'.$match[1].'" selected', $output['folder']);
  }, $output['url']);

  return $output;
}

function add_bookmark($url, $folder, $type, $bookmark_json, $name = null, $redirect = 0) {
  global $site_url;

  if ($bookmark = parse_bookmark_json($bookmark_json))
    $id = (++$bookmark[1]);
  else
    $id = 1;
  if ($type == 'url') {
    //$str = @file_get_contents($url);
    $response = get_url_response($url);
    $header = $response['header'];
    $body = $response['body'];

    if (!isset($name) || !$name) {
      if ($header['http_code'] == 200 && strlen($body)) {
        preg_match('/\<title\>(.*)\<\/title\>/i', $body, $title);
        $name = (isset($title[1]) ? toutf8($title[1]) : '');
      }
      if (!isset($name) || !$name) {
        if ($header['downloadable'])
          $name = substr($url, strrpos($url, '/') + 1, 140);
        else
          $name = substr($url, 0, 140);
      }
    }
  }

  $hash = sha1($url);
  if ($type == 'url' && $redirect)
    $url = $site_url.'index.php?action=redirect&u='.urlencode($url).'&id=_'.$id;

  $new = array('_'.$id => array('id' => $id, 'type' => $type, 'name' => ($type == 'url' ? $name : $url), 'date_added' => time(), 'hash' => $hash));
  if ($type == 'url') {
    $new['_'.$id]['url'] = $url;
    if (!$redirect) {
      $new['_'.$id]['meta'] = array(
        'http_code' => $header['http_code'],
        'last_access' => time(),
        'content_type' => $header['content_type'],
        'offline' => '',
        'downloadable' => $header['downloadable']
      );
    }
  } else
    $new['_'.$id]['cache'] = 0;

  $data = $new['_'.$id];
  if ($folder) {
    if ($type == 'url' && !$redirect && $header['downloadable']) {
      $parent = $bookmark[0];
      $levels = explode('_', substr($folder, 1));
      foreach ($levels as $level) {
        if ($level)
          $parent = $parent['entries']['_'.$level];
      }
      if (isset($parent['cache']) && $parent['cache']) {
        $new['_'.$id]['meta']['offline'] = -1;
        $data['meta']['offline'] = -1;
      }
    }

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
  $bookmark['entries']['_'.$id] = array_replace_recursive($bookmark['entries']['_'.$id], $update);
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

function get_url_response($url, $nobody = 0) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HEADER, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_NOBODY, $nobody);
  $response = curl_exec($ch);
  $header = array(
    'header_size' => curl_getinfo($ch, CURLINFO_HEADER_SIZE),
    'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    'content_type' => (($ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE)) ? strtolower($ct) : '')
  );
  $header['header'] = substr($response, 0, $header['header_size']);
  $body = (!$nobody ? substr($response, $header['header_size']) : '');
  curl_close($ch);

  if ($header['content_type'] && (substr($header['content_type'], 0, 6) == 'image/' || substr($header['content_type'], 0, 5) == 'text/' || $header['content_type'] == 'application/pdf' || $header['content_type'] == 'application/xhtml+xml' || $header['content_type'] == 'application/xml'))
    $header['downloadable'] = 1;
  else
    $header['downloadable'] = 0;

  return array('header' => $header, 'body' => $body);
}

function download_item($id, $url) {
  global $content_dir, $cache_dir, $lib_dir;

  // Update header
  $response = get_url_response($url, 1);
  $header = $response['header'];

  if ($header['http_code'] !== 200)
    return false;

  if (!$header['downloadable'])
    return array('file_name' => '', 'header' => $header, 'downloadable' => 0);

  $fp = fopen(($file = $cache_dir.time()), 'w+');

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_FILE, $fp);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_exec($ch);
  curl_close($ch);
  fclose($fp);

  if ($header['content_type'] && substr($header['content_type'], 0, 9) == 'text/html' && filesize($file)) {
    // readability
    $body = toutf8(file_get_contents($file));
    $body = preg_replace(array('/<!--.*?-->/si'), '', $body); //remove comments
    $body = preg_replace(array('/<style.*?\/style>/si', '/<script.*?\/script>/si', '/<form.*?\/form>/si', '/<iframe.*?\/iframe>/si', '/<button.*?\/button>/si', '/<input [^>]*>/si', '/<textarea.*?\/textarea>/si', '/<noscript.*?\/noscript>/si', '/<select.*?\/select>/si', '/<option.*?\/option>/si', '/<object.*?\/object>/si', '/<applet.*?\/applet>/si', '/<basefont [^>]*>/si', '/<font.*?\/font>/si', '/<bgsound [^>]*>/si', '/<blink.*?\/blink>/si', '/<canvas.*?\/canvas>/si', '/<command.*?\/command>/si', '/<menu.*?\/menu>/si', '/<nav.*?\/nav>/si', '/<datalist.*?\/datalist>/si', '/<embed [^>]*>/si', '/<frame [^>]*>/si', '/<frameset.*?\/frameset>/si', '/<keygen [^>]*>/si', '/<label.*?\/label>/si', '/<marquee.*?\/marquee>/si', '/<ins.*?\/ins>/si'), '', $body); //remove element
    $body = preg_replace(array('/<([a-z]+\s+)([^>]*)on[a-z]+=\s*"[^">]+"(\s+[^>]*)?(\/\s*)?>/i', '/<([a-z]+\s+)([^>]*)style=\s*"[^">]+"(\s+[^>]*)?(\/\s*)?>/i', '/<([a-z]+\s+)([^>]*)class=\s*"[^">]+"(\s+[^>]*)?(\/\s*)?>/i', '/<([a-z]+\s+)([^>]*)id=\s*"[^">]+"(\s+[^>]*)?(\/\s*)?>/i', '/<([a-z]+\s+)([^>]*)align=\s*"[^">]+"(\s+[^>]*)?(\/\s*)?>/i', '/<([a-z]+\s+)([^>]*)border=\s*"[^">]+"(\s+[^>]*)?(\/\s*)?>/i', '/<([a-z]+\s+)([^>]*)margin=\s*"[^">]+"(\s+[^>]*)?(\/\s*)?>/i'), '<$1$2$3$4>', $body); //remove inline-js and style
    $body = preg_replace(array('/<([a-z]+\s+)([^>]*)on[a-z]+=\s*\'[^\'>]+\'(\s+[^>]*)?(\/\s*)?>/i', '/<([a-z]+\s+)([^>]*)style=\s*\'[^\'>]+\'(\s+[^>]*)?(\/\s*)?>/i', '/<([a-z]+\s+)([^>]*)class=\s*\'[^\'>]+\'(\s+[^>]*)?(\/\s*)?>/i', '/<([a-z]+\s+)([^>]*)id=\s*\'[^\'>]+\'(\s+[^>]*)?(\/\s*)?>/i', '/<([a-z]+\s+)([^>]*)align=\s*\'[^\'>]+\'(\s+[^>]*)?(\/\s*)?>/i', '/<([a-z]+\s+)([^>]*)border=\s*\'[^\'>]+\'(\s+[^>]*)?(\/\s*)?>/i', '/<([a-z]+\s+)([^>]*)margin=\s*\'[^\'>]+\'(\s+[^>]*)?(\/\s*)?>/i'), '<$1$2$3$4>', $body); //remove inline-js and style

    if (extension_loaded('tidy')) {
      $body_raw = $body;
      $tidy = new tidy;
      $body = $tidy->repairString($body);
      if (!$body || !($tidy->parseString($body)) || !($tidy->cleanRepair()))
        $body = $body_raw;
      else
        $body = $tidy;
    }

    require $lib_dir.'readability/config.inc.php';
    require $lib_dir.'readability/common.inc.php';
    require $lib_dir.'readability/Readability.inc.php';
    include($lib_dir.'url_to_absolute.php');
    $Readability = new Readability($body, 'utf8');
    $ReadabilityData = $Readability->getContent();
    $body = '<h1>'.$ReadabilityData['title'].'</h1>'.$ReadabilityData['content'];
    $title = $ReadabilityData['title'];

    $body = preg_replace(array('/<(!DOCTYPE |\/)?html[^>]*>/i', '/<\/?body[^>]*>/i', '/<head[^>]*>.*<\/head>/i', '/<\/?head[^>]*>/i', '/<title[^>]*>.*<\/title>/i', '/<\/?title[^>]*>/i', '/<meta[^>]*>/i', '/<link[^>]*>/i', '/<!--[^>]*-->/i'), '', $body); //remove head element
    $body = escattr($body); // remove js

    $body = preg_replace_callback('/<img ((?:[^>]*\s)*)src\s*=\s*("|\')([^"\']+)("|\')(\s+[^>]*)?(\/\s*)?>/i', function ($matches) use ($id) {
      $base_url = substr($matches[3], 0, strrpos($matches[3], '/') + 1);
      $image_url = url_to_absolute($base_url, $matches[3]);
      $image = download_item($id, $image_url);

      return ($image && $image['file_name'] ? '<img '.$matches[1].'src='.$matches[2].'index.php?action=view&id='.$id.'-'.$image['file_name'].'&type='.$image['header']['content_type'].$matches[4].(isset($matches[5]) ? $matches[5] : '').(isset($matches[6]) ? $matches[6] : '/').'>' : '');
    }, $body); // download images and modify img src

    $body = preg_replace(array('/<a [^>]*href=\'\'[^>]*>[^<]*<\/a>/i', '/<a [^>]*href=""[^>]*>[^<]*<\/a>/i', '/<a( [^>]*)?>[\r\n\s]*<\/a>/i', '/<td( [^>]*)?>[\r\n\s]*<\/td>/i', '/<tr( [^>]*)?>[\r\n\s]*<\/tr>/i', '/<table( [^>]*)?>[\r\n\s]*<\/table>/i'), '', $body); //remove white spaces
    $body = preg_replace(array('/\s*[\r\n]+/'), "\n", $body); //remove white spaces

    $body = '<!DOCTYPE html><html><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no" /><title>'.(isset($title) && $title ? $title : '').'</title><link rel="profile" href="http://gmpg.org/xfn/11" /><link rel="shortcut icon" href="/favicon.ico" />
<style type="text/css" media="all">
<!--
html, body, div, span, h1, p, a, img, b, u, i, ol, ul, li, table, tr, td, input{font-family:"Lucida Sans Unicode","Lucida Grande","wenquanyi micro hei","droid sans fallback",FreeSans,Helvetica,Arial,"hiragino sans gb","stheiti","microsoft yahei",\5FAE\8F6F\96C5\9ED1,sans-serif !important;font-size:14px;line-height:23px;}
html,body{max-width:100%;overflow-x:hidden;}
h1{font-size:2em;line-height:1.2em;}
#main{font-size:24px;line-height:1.8em;margin:10px auto 23px;width:90%;max-width:1000px;word-wrap:break-word;}
#main p,#main div,#main a,#main span{font-size:18px;line-height:1.8em;padding:.8em 0 0;margin:0;word-wrap:break-word;}
#main img{max-width:98%;}
-->
</style>
      </head><div id="main">'.$body.'</div></body></html>';

    file_put_contents($file, $body);
  }

  if (filesize($file)) {
    rename($file, $content_dir.$id.'-'.($file_name = sha1_file($file).'-'.filesize($file)));
    return array('file_name' => $file_name, 'header' => $header);
  } else {
    if (file_exists($file))
      unlink($file);
    return array('file_name' => '', 'header' => $header);
  }
}
?>
