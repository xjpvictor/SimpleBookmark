<?php
function addhttp($url, $check_js = 1) {
  if (!($js = preg_match("/^javascript:.*$/i", $url)) && !preg_match("~^(?:f|ht)tps?://~i", $url)) {
    $url = "http://" . $url;
  }
  return ($check_js && $js ? true : $url);
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

function output_bookmarks_recursive($bookmarks, $allow_edit, $deduplicate, $check_url, $cache_prefix, $update_status, $bookmark_json, $output = array('url' => '', 'folder' => '', 'urls' =>array(), 'preload' => ''), $level = '') {
  global $site_url, $sync_file_prefix;

  if (!$bookmarks)
    return false;

  foreach ($bookmarks as $bookmark_id => $entry) {
    if (isset($entry['id'])) {
      if ($entry['type'] == 'url') {
        if (!isset($entry['hash']))
          $entry['hash'] = sha1($entry['url']);
        if ($deduplicate && is_array($deduplicate) && isset($deduplicate[$entry['hash']]))
          $entry = delete_bookmark(($level ? $level : 0).'_'.$entry['id'], 0, $bookmark_json, $cache_prefix, $update_status);
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
<p class="entry url'.(!$allow_edit ? ' sync' : '').'" style="display:block;"'.($allow_edit ? ' id="entry-'.$level.'_'.$entry['id'].'"' : '').' data-id="'.$level.'_'.$entry['id'].'">
<span class="target touchOver'.(!$allow_edit ? ' noedit' : '').'"'.($allow_edit ? ' id="target-'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'"' : '').'>
<span class="move'.(!$allow_edit ? ' noedit' : '').'"'.($allow_edit ? ' id="move-'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'" draggable="true"' : '').'></span>
<span class="border touchOver" data-id="'.$level.'_'.$entry['id'].'">
<a class="url touchOver'.($allow_edit ? ' search'.(!$check_url && isset($entry['not_found']) && $entry['not_found'] ? ' not-found' : '').(isset($entry['hide_not_found']) && $entry['hide_not_found'] ? '' : ' show-not-found') : '').'" href="'.$entry['url'].'"'.($allow_edit ? ' id="'.$level.'_'.$entry['id'].'" data-id="'.$level.'_'.$entry['id'].'"' : '').' data-type="url" '.($allow_edit ? 'data-level="'.$level.'_" ' : '').'title="'.htmlentities($entry['url']).'">
'.(((isset($entry['meta']['preview']) && ($r = $entry['meta']['preview'])) || ((!$check_url || !isset($entry['meta']['preview'])) && in_array(strtolower(substr(($r = (!$allow_edit && isset($entry['original_url']) && $entry['original_url'] ? $entry['original_url'] : $entry['url'])), strrpos($r, '.')+1)), array('jpg', 'jpeg', 'png', 'gif')))) && ($output['preload'] = $output['preload'] . '<link rel="preload" as="image" href="index.php?action=preview&id='.($allow_edit ? '' : $sync_file_prefix).$entry['id'].'&url='.urlencode($r).'&site='.urlencode($entry['url']).'" />'."\n") ? '<img class="preview" id="preview-'.$level.'_'.$entry['id'].'" src="index.php?action=preview&id='.($allow_edit ? '' : $sync_file_prefix).$entry['id'].'&url='.urlencode($r).'&site='.urlencode($entry['url']).'" onerror="this.style.display=\'none\';" />' : '').'
<span class="touchOver" data-id="'.$level.'_'.$entry['id'].'" id="title-'.$level.'_'.$entry['id'].'">'.$entry['name'].'</span></a>
'.($allow_edit ? (isset($entry['meta']['offline']) && $entry['meta']['offline'] ? '<a class="offline" href="index.php?action=view&id='.$entry['id'].'-'.$entry['meta']['offline'].'" target="_blank">Cache</a>' : '').(addhttp($entry['url']) !== true ? '<a class="qr" href="javascript:var qr=document.getElementById(\'qrcode\');qr.innerHTML=\'\';new QRCode(qr,{text:\''.str_replace('\'', '\\\'', $entry['url']).'\',width:100,height:100});"><img src="lib/mobile.png" alt="QR Code" title="QR Code" /></a>' : '').'<a class="edit" href="javascript:;" onclick="toggleShow(\'entry-'.$level.'_'.$entry['id'].'\');toggleShow(\'editform-'.$level.'_'.$entry['id'].'\')">Edit</a>' : '<a class="delete noedit" onclick="return confirm(\'Permanently delete this bookmark?\');" href="index.php?mode=sync&action=delete&id='.$level.'_'.$entry['id'].'">Delete</a>').'
'.(!$allow_edit ? '<select name="d" onchange="if(this.selectedIndex)this.form.submit();"><option value="-1" selected disabled style="display:none;">Save</option>##FOLDERLIST##</select>' : '').'
</span>
</span>
</p>
'.(!$allow_edit ? '</form>' : '').'
'.($allow_edit ? '<form class="editform" id="editform-'.$level.'_'.$entry['id'].'" action="index.php?action=edit&id='.$level.'_'.$entry['id'].'" method="post">
<input name="n" type="text" required value="'.htmlentities($entry['name']).'"><br/>
<input name="u" type="text" required value="'.htmlentities($entry['url']).'"><br/>
<input type="hidden" name="ou" value="'.htmlentities($entry['url']).'">
<select name="l">##FOLDERLIST-'.($level ? $level : '_0').'##</select>
<input type="hidden" name="h" value="0">
<span class="ignore"><label><input class="cache" type="checkbox" name="h" value="1"'.(isset($entry['hide_not_found']) && $entry['hide_not_found'] ? ' checked' : '').'>Mark page as accessible</label></span><br/>
<p id="last-access-'.($level.'_'.$entry['id']).'" class="last-access">'.'<span id="last-access-message-'.($level.'_'.$entry['id']).'" data-content="Page Not Available.">'.(($n = (!$check_url && isset($entry['meta']['not_found']) && $entry['meta']['not_found'])) ? 'Page Not Available.' : '').'</span><span class="last-access-code" id="last-access-code-'.($level.'_'.$entry['id']).'"></span>'.(addhttp($entry['url']) !== true ? '<a href="index.php?action=checkurl&id='.$level.'_'.$entry['id'].'&url='.urlencode($entry['url']).'" class="url-checker">Check Page</a>' : '').'<span class="last-access-time" id="last-access-time-'.($level.'_'.$entry['id']).'">'.($n && isset($entry['meta']['last_access']) && $entry['meta']['last_access'] ? date('Y-m-d, G:i', $entry['meta']['last_access']) : '').'</span>'.'</p>'.'
<input type="submit" value="Update">
<a class="cancel" href="javascript:;" onclick="toggleShow(\'entry-'.$level.'_'.$entry['id'].'\');toggleShow(\'editform-'.$level.'_'.$entry['id'].'\')">Cancel</a>
<a class="delete" onclick="return confirm(\'Permanently delete this bookmark?\');" href="index.php?action=delete&id='.$level.'_'.$entry['id'].'">Delete</a>
'.(($s = '<a class="save" id="save-'.$level.'_'.$entry['id'].'" href="index.php?action=save&level='.$level.'&id='.$entry['id'].'&url='.urlencode($entry['url'])) && isset($entry['meta']['offline']) && $entry['meta']['offline'] ? $s.'&delete='.$entry['meta']['offline'].'">Delete Cache</a>' : (!isset($entry['meta']['downloadable']) || $entry['meta']['downloadable'] ? $s.'">Enable Cache</a>' : '')).'
<br/>
</form>' : '')."\n";
          if ($check_url && addhttp($entry['url']) !== true)
            $output['urls'][(isset($entry['meta']['not_found']) ? $entry['meta']['not_found'] : 0)]['_'.$entry['id']] = array('meta' => array_merge(array('not_found' => 0, 'http_code' => 200, 'last_access' => 0, 'offline' => '', 'downloadable' => 1, 'preview' => 0), (isset($entry['meta']) ? $entry['meta'] : array())), 'id' => $level.'_'.$entry['id'], 'url' => $entry['url'], 'index' => '_'.$entry['id']);
        }
      } elseif ($entry['type'] == 'folder') {
        $output['url'] .= '<!-- folder '.$entry['id'].' in '.($level ? $level : '0').' -->
<div class="folder"'.($allow_edit ? ' id="'.$level.'_'.$entry['id'].'"' : '').'>
<span class="entry'.(!$allow_edit ? ' sync' : '').'"'.($allow_edit ? ' id="entry-'.$level.'_'.$entry['id'].'"' : '').' style="display:block;" data-id="'.$level.'_'.$entry['id'].'">
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
<span'.($allow_edit ? ' class="folder-wrap" id="folder-wrap-'.$level.'_'.$entry['id'].'"' : '').' style="display:block;">'."\n";
        if ($allow_edit)
          $output['folder'] .= '<option value="'.$level.'_'.$entry['id'].'">'.$entry['name'].'</option>'."\n";
        if (isset($entry['entries']) && !empty($entry['entries']))
          $output = output_bookmarks_recursive($entry['entries'], $allow_edit, $deduplicate, $check_url, $cache_prefix, $update_status, $bookmark_json, $output, $level.'_'.$entry['id']);
        $output['url'] .= '</span><span class="target touchOver'.(!$allow_edit ? ' noedit' : '').'"'.($allow_edit ? ' id="target-'.$level.'_'.$entry['id'].'_0" data-id="'.$level.'_'.$entry['id'].'_0"' : '').'>&nbsp;</span></div>'."\n";
      }
    }
  }
  return $output;
}

function output_bookmarks($bookmarks, $bookmark_json, $allow_edit = 1, $deduplicate = 1, $check_url = 0, $cache_prefix = '', $update_status = 1) {
  global $cache_file_urllist;

  $output = output_bookmarks_recursive($bookmarks, $allow_edit, $deduplicate, $check_url, $cache_prefix, $update_status, $bookmark_json);

  $output['folder'] = '<option value="_0">My Bookmarks</option>'."\n".$output['folder'];
  $output['url'] = (isset($output['url']) && $output['url'] ? preg_replace_callback('/##FOLDERLIST-([_0-9]+)##/i', function ($match) use ($output) {
    return str_replace('value="'.$match[1].'"', 'value="'.$match[1].'" selected', $output['folder']);
  }, $output['url']) : '');

  if ($check_url)
    file_put_contents($cache_file_urllist, json_encode($output['urls']));

  return $output;
}

function add_bookmark($url, $folder, $type, $bookmark_json, $name = null, $redirect = 0, $update_status = 1, $reverse_order = 0) {
  global $site_url;

  if ($update_status)
    $bookmark = edit_bookmark_status($bookmark_json);

  if (($bookmark = (!isset($bookmark) || !$bookmark ? parse_bookmark_json($bookmark_json) : $bookmark)))
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
        preg_match('/<title(?:\s+[^>]*)?>((?:(?!<\/title>).)*.)<\/title>/si', $body, $title);
        $name = trim((isset($title[1]) ? toutf8(strip_tags($title[1])) : ''));
      }
      if (!isset($name) || !$name) {
        if ($header['downloadable'])
          $name = substr($url, (($pos = strrpos($url, '/')) ? $pos + 1 : 0), 140);
        else
          $name = substr($url, 0, 140);
      }
    }
  }

  $hash = sha1($url);

  $new = array('_'.$id => array('id' => $id, 'type' => $type, 'name' => ($type == 'url' ? $name : $url), 'date_added' => time(), 'hash' => $hash));
  if ($type == 'url') {
    $new['_'.$id]['url'] = $url;
    $new['_'.$id]['meta'] = array(
      'not_found' => (substr($header['http_code'], 0, 1) != 2 ? 1 : 0),
      'http_code' => $header['http_code'],
      'last_access' => time(),
      'offline' => '',
      'downloadable' => $header['downloadable'],
      'preview' => $header['preview'],
    );
    if ($redirect) {
      $new['_'.$id]['url'] = $site_url.'index.php?action=redirect&u='.urlencode($url).'&id=_'.$id;
      $new['_'.$id]['original_url'] = $url;
    } else
      $new['_'.$id]['hide_not_found'] = 0;
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
        if ($file = download_item($id, $url)) {
          $new['_'.$id]['meta']['offline'] = $file['file_name'];
          $data = $new['_'.$id];
        }
      }
    }

    $levels = array_reverse(explode('_', substr($folder, 1)));
    foreach ($levels as $level) {
      if ($level)
        $new = array('_'.$level => array('entries' => $new));
    }
  }

  if (isset($bookmark[0]['entries']) && is_array($bookmark[0]['entries'])) {
    if ($reverse_order)
      $bookmark[0]['entries'] = array_merge_recursive($new, $bookmark[0]['entries']);
    else
      $bookmark[0]['entries'] = array_merge_recursive($bookmark[0]['entries'], $new);
  } else
    $bookmark[0]['entries'] = $new;

  file_put_contents($bookmark_json, json_encode($bookmark), LOCK_EX);
  if ($type == 'folder')
    move_bookmark($data, ($folder !== '_0' ? $folder : '').'_-1', $bookmark_json, $update_status);

  return $data;
}

function update_bookmark_recursive($bookmark, $levels, $index, $callback_function, $callback_parameters) {
  if (empty($levels) || (isset($levels[$index]) && !$levels[$index]) || $index == count($levels)) {
    $data = call_user_func($callback_function, $bookmark, $callback_parameters);
  } else {
    $data = update_bookmark_recursive($bookmark['entries']['_'.$levels[$index]], $levels, $index + 1, $callback_function, $callback_parameters);
    $bookmark['entries']['_'.$levels[$index]] = $data[0];
    $data[0] = $bookmark;
  }
  return $data;
}

function delete_bookmark($id, $delete_contents, $bookmark_json, $cache_prefix = '', $update_status = 1) {
  global $content_dir, $cache_dir, $preview_filename_prefix;

  if ($update_status)
    $bookmark = edit_bookmark_status($bookmark_json);

  $bookmark = (!isset($bookmark) || !$bookmark ? parse_bookmark_json($bookmark_json) : $bookmark);
  $levels = explode('_', substr($id, 1));
  $id = array_pop($levels);
  $data = update_bookmark_recursive($bookmark[0], $levels, 0, 'delete_bookmark_callback', array($id, $delete_contents));
  $bookmark[0] = $data[0];
  if (isset($data[2]) && !empty($data[2]))
    $bookmark[0]['entries'] = array_merge($bookmark[0]['entries'], $data[2]);
  file_put_contents($bookmark_json, json_encode($bookmark), LOCK_EX);
  $files = glob($content_dir.$cache_prefix.$data[1]['id'].'-*', GLOB_NOSORT);
  if ($files) {
    foreach ($files as $file) {
      if (file_exists($file))
        unlink($file);
    }
  }
  $files = glob($cache_dir.$preview_filename_prefix.$cache_prefix.$data[1]['id'].'-*', GLOB_NOSORT);
  if ($files) {
    foreach ($files as $file) {
      if (file_exists($file))
        unlink($file);
    }
  }
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

function edit_bookmark($id, $update, $bookmark_json, $update_status = 1) {
  if (!$id)
    return false;

  if ($update_status)
    $bookmark = edit_bookmark_status($bookmark_json);

  $bookmark = (!isset($bookmark) || !$bookmark ? parse_bookmark_json($bookmark_json) : $bookmark);
  if (!$bookmark)
    return false;

  $levels = explode('_', substr($id, 1));
  $id = array_pop($levels);
  $data = update_bookmark_recursive($bookmark[0], $levels, 0, 'edit_bookmark_callback', array($id, $update));
  $bookmark[0] = $data[0];

  file_put_contents($bookmark_json, json_encode($bookmark), LOCK_EX);

  return $data[1];
}

function edit_bookmark_callback($bookmark, $parameters) {
  $id = $parameters[0];
  $update = $parameters[1];
  if (isset($bookmark['entries']['_'.$id]))
    $bookmark['entries']['_'.$id] = array_replace_recursive($bookmark['entries']['_'.$id], $update);
  return (array($bookmark, (isset($bookmark['entries']['_'.$id]) ? $bookmark['entries']['_'.$id] : false)));
}

function edit_bookmark_status($bookmark_json) {
  global $check_url, $cache_file_urlstatus;

  if ($check_url && file_exists($cache_file_urlstatus)) {
    $urlstatus = json_decode(file_get_contents($cache_file_urlstatus), 1);
    unlink($cache_file_urlstatus);
    $urls = array_merge((isset($urlstatus[1]) ? $urlstatus[1] : array()), (isset($urlstatus[0]) ? $urlstatus[0] : array()));
    if (!$urls)
      return false;

    $bookmark = parse_bookmark_json($bookmark_json);
    if (!$bookmark)
      return false;

    $data = array();
    foreach ($urls as $index => $url) {
      $levels = explode('_', substr($url['id'], 1));
      $id = array_pop($levels);
      $levels = array_reverse($levels);
      $entry = array('_'.$id => array('meta' => $url['meta']));
      foreach ($levels as $level) {
        $entry = array('_'.$level => array('entries' => $entry));
      }
      $data = array_replace_recursive($data, $entry);
    }

    $bookmark[0]['entries'] = array_replace_recursive($bookmark[0]['entries'], $data);
    file_put_contents($bookmark_json, json_encode($bookmark), LOCK_EX);

    return $bookmark;
  }
  return false;
}

function move_bookmark($entry, $destination, $bookmark_json, $update_status = 1) {
  if ($update_status)
    $bookmark = edit_bookmark_status($bookmark_json);

  $bookmark = (!isset($bookmark) || !$bookmark ? parse_bookmark_json($bookmark_json) : $bookmark);
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

function sort_bookmark($id, $sort, $recursive = 0, $bookmark_json, $update_status = 1) {
  if ($update_status)
    $bookmark = edit_bookmark_status($bookmark_json);

  $bookmark = (!isset($bookmark) || !$bookmark ? parse_bookmark_json($bookmark_json) : $bookmark);
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
        $data[1]['entries']['_'.$entry['id']] = sort_bookmark($id_s.'_'.$entry['id'], $sort, 1, $bookmark_json, $update_status);
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

function get_url_response($url, $nobody = 0, $return_value = '') {
  global $curl_ua;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HEADER, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_NOBODY, $nobody);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'User-Agent: '.$curl_ua,
  ));
  $response = curl_exec($ch);
  $header = array(
    'header_size' => curl_getinfo($ch, CURLINFO_HEADER_SIZE),
    'http_code' => (($hc = curl_getinfo($ch, CURLINFO_HTTP_CODE)) ? $hc : 404),
    'content_type' => (($ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE)) ? strtolower((($pos = strpos($ct, ';')) ? substr($ct, 0, $pos) : $ct)) : '')
  );
  $header['header'] = substr($response, 0, $header['header_size']);
  $body = (!$nobody ? substr($response, $header['header_size']) : '');
  curl_close($ch);

  if ($return_value)
    return (isset($header[$return_value]) ? $header[$return_value] : false);

  $header['downloadable'] = 0;
  $header['preview'] = 0;

  if ($header['http_code'] !== 200 || (!$nobody && !$body))
    return array('header' => $header, 'body' => $body);

  $header = parse_header($header, $body, $url);

  return array('header' => $header, 'body' => $body);
}

function parse_header($header, $body, $url) {
  global $lib_dir;

  if (!function_exists('url_to_absolute'))
    include($lib_dir.'url_to_absolute.php');

  if ($header['content_type'] && substr($header['content_type'], 0, 6) == 'image/') {
    $header['downloadable'] = 1;
    $header['preview'] = $url;
  } elseif ($header['content_type'] && $header['content_type'] == 'text/html') {
    $header['downloadable'] = 1;
    if ($body && (preg_match('/<meta\s+([^>]*\s+)?property\s*=\s*("|\')og:image("|\')\s+([^>]*\s+)?content\s*=\s*("|\')([^"\']+)("|\')(\s+[^>]*)?>/si', $body, $matches) || preg_match('/<meta\s+([^>]*\s+)?name\s*=\s*("|\')twitter:image("|\')\s+([^>]*\s+)?content\s*=\s*("|\')([^"\']+)("|\')(\s+[^>]*)?>/si', $body, $matches) || preg_match('/<link\s+([^>]*\s+)?rel\s*=\s*("|\')image_src("|\')\s+([^>]*\s+)?href\s*=\s*("|\')([^"\']+)("|\')(\s+[^>]*)?>/si', $body, $matches))) {
      $header['preview'] = trim((isset($matches[6]) ? $matches[6] : $header['preview']));
      $domain = substr($url, 0, (($pos = strpos($url, '/', (strpos($url, 'http://') !== false || strpos($url, 'https://') !== false ? 9 : 0))) !== false ? $pos : strlen($url)));
      $header['preview'] = preg_replace(array('/^\/\//', '/^\//'), array('', $domain.'/'), url_to_absolute(substr($url, 0, strrpos($url, '/')+1), $header['preview']));
    }
  } elseif ($header['content_type'] && (substr($header['content_type'], 0, 5) == 'text/' || $header['content_type'] == 'application/pdf' || $header['content_type'] == 'application/xhtml+xml' || $header['content_type'] == 'application/xml'))
    $header['downloadable'] = 1;

  return $header;
}

function download_item($id, $url) {
  global $content_dir, $cache_dir, $lib_dir, $curl_ua;

  // Update header
  $response = get_url_response($url, 1);
  $header = $response['header'];

  if ($header['http_code'] !== 200)
    return false;

  if (!$header['downloadable'])
    return array('file_name' => '', 'header' => $header);

  $fp = fopen(($file = $cache_dir.time()), 'w+');

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_FILE, $fp);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'User-Agent: '.$curl_ua,
  ));
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
    if (!function_exists('url_to_absolute'))
      include($lib_dir.'url_to_absolute.php');
    $Readability = new Readability($body, 'utf8');
    $ReadabilityData = $Readability->getContent();
    $body = '<h1>'.$ReadabilityData['title'].'</h1>'.$ReadabilityData['content'];
    $title = $ReadabilityData['title'];

    $body = preg_replace(array('/<(!DOCTYPE |\/)?html[^>]*>/i', '/<\/?body[^>]*>/i', '/<head[^>]*>.*<\/head>/i', '/<\/?head[^>]*>/i', '/<title[^>]*>.*<\/title>/i', '/<\/?title[^>]*>/i', '/<meta[^>]*>/i', '/<link[^>]*>/i', '/<!--[^>]*-->/i'), '', $body); //remove head element
    $body = escattr($body); // remove js

    $body = preg_replace_callback('/<img ((?:[^>]*\s)*)src\s*=\s*("|\')([^"\']+)("|\')(\s+[^>]*)?(\/\s*)?>/i', function ($matches) use ($id, $url) {
      $base_url = substr($url, 0, strrpos($url, '/') + 1);
      $domain = substr($url, 0, (($pos = strpos($url, '/', (strpos($url, 'http://') !== false || strpos($url, 'https://') !== false ? 9 : 0))) !== false ? $pos : strlen($url)));
      $image_url = preg_replace(array('/^\/\//', '/^\//'), array('', $domain.'/'), url_to_absolute($base_url, $matches[3]));
      $image = download_item($id, $image_url);

      return ($image && $image['file_name'] ? '<img '.$matches[1].'src='.$matches[2].'index.php?action=view&id='.$id.'-'.$image['file_name'].$matches[4].(isset($matches[5]) ? $matches[5] : '').(isset($matches[6]) ? $matches[6] : '/').'>' : '');
    }, $body); // download images and modify img src

    $body = preg_replace(array('/<a [^>]*href=\'\'[^>]*>[^<]*<\/a>/i', '/<a [^>]*href=""[^>]*>[^<]*<\/a>/i', '/<a( [^>]*)?>[\r\n\s]*<\/a>/i', '/<td( [^>]*)?>[\r\n\s]*<\/td>/i', '/<tr( [^>]*)?>[\r\n\s]*<\/tr>/i', '/<table( [^>]*)?>[\r\n\s]*<\/table>/i'), '', $body); //remove white spaces
    $body = preg_replace(array('/\s*[\r\n]+/'), "\n", $body); //remove white spaces

    $body = '<!DOCTYPE html><html><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no" /><title>'.(isset($title) && $title ? $title : '').'</title><link rel="profile" href="http://gmpg.org/xfn/11" /><link rel="shortcut icon" href="favicon.ico" />
<style type="text/css" media="all">
<!--
html, body, div, span, h1, p, a, img, b, u, i, ol, ul, li, table, tr, td, input{font-family:"Lucida Sans Unicode","Lucida Grande","Noto Sans",Helvetica,Arial,sans-serif !important;font-size:14px;line-height:23px;}
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

function imagecreatefrombmp($p_sFile) {
  $file = fopen($p_sFile, "rb");
  $read = fread($file, 10);
  while (!feof($file) && ($read<>""))
    $read .= fread($file, 1024);
  $temp = unpack("H*", $read);
  $hex = $temp[1];
  $header = substr($hex, 0, 108);
  if (substr($header, 0, 4) == "424d") {
    $header_parts = str_split($header, 2);
    $width = hexdec($header_parts[19].$header_parts[18]);
    $height = hexdec($header_parts[23].$header_parts[22]);
    unset($header_parts);
  }
  $x = 0;
  $y = 1;
  $image = imagecreatetruecolor($width, $height);
  imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
  $body = substr($hex, 108);
  $body_size = (strlen($body) / 2);
  $header_size = ($width * $height);
  $usePadding = ($body_size > ($header_size * 3) + 4);
  for ($i = 0; $i < $body_size; $i += 3) {
    if ($x >= $width) {
      if ($usePadding)
        $i += $width % 4;
      $x = 0;
      $y++;
      if ($y > $height)
        break;
    }
    $i_pos = $i * 2;
    $r = hexdec($body[$i_pos + 4].$body[$i_pos + 5]);
    $g = hexdec($body[$i_pos + 2].$body[$i_pos + 3]);
    $b = hexdec($body[$i_pos].$body[$i_pos + 1]);
    $color = imagecolorallocate($image, $r, $g, $b);
    imagesetpixel($image, $x, $height - $y, $color);
    $x++;
  }
  unset($body);
  return $image;
}

function createthumbnail($source_file, $height) {
  $size = getimagesize($source_file);
  $w = $size[0];
  $h = $size[1];
  $type=$size['mime'];
  $stype = explode("/", $type);
  $stype = $stype[count($stype)-1];
  switch($stype) {
  case 'gif':
    $simg = imagecreatefromgif($source_file);
    break;
  case 'jpeg':
    $simg = imagecreatefromjpeg($source_file);
    break;
  case 'tiff':
    break;
  case 'png':
    $simg = imagecreatefrompng($source_file);
    break;
  case 'bmp':
    $simg = imagecreatefrombmp($source_file);
    break;
  case 'x-ms-bmp':
    $simg = imagecreatefrombmp($source_file);
    break;
  case 'vnd.wap.wbmp':
    $simg = imagecreatefromwbmp($source_file);
    break;
  }
  if (!isset($simg) || !$simg)
    return false;

  $width = $height; // Width of dest image
  $wm = $w / $width; // Width magnification
  $hm = $h / $height; // Height magnification
  $m = max(1, $hm, $wm); // Magnification
  $adjusted_w = $w / $m; // New width of src image
  $adjusted_h = $h / $m; // New height of src image
  $half_width = $width / 2;
  $half_height = $height / 2;
  $half_w = $adjusted_w / 2;
  $half_h = $adjusted_h / 2;
  $int_w = $half_width - $half_w;
  $int_h = $half_height - $half_h;

  $dimg = imagecreatetruecolor($width, $height);
  imagefill($dimg, 0, 0, imagecolorallocate($dimg, 255, 255, 255));
  imagecopyresampled($dimg, $simg, $int_w, $int_h, 0, 0, $adjusted_w, $adjusted_h, $w, $h);

  if ($dimg) {
    imagejpeg($dimg, $source_file, 90);
    return $source_file;
  } else
    return false;
}
?>
