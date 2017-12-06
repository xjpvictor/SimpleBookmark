<?php
include(__DIR__ . '/init.php');

// Process action
if ($auth) {
  if (isset($_GET['action'])) {
    switch ($_GET['action']) {
    case 'logout':
      session_destroy();
      break;
    case 'add':
      if (isset($_POST['u']) && $_POST['u']) {
        if ($_POST['t'] == 'sync') {
          parse_str(parse_url($_POST['u'], PHP_URL_QUERY), $u);
          $_POST['u'] = $u['u'];
          if (isset($u['id']) && $u['id'])
            delete_bookmark($u['id'], 0, $sync_json, $sync_file_prefix, 0);
        }

        $entry = add_bookmark($_POST['u'], $_POST['d'], ($_POST['t'] == 'sync' ? 'url' : $_POST['t']), $bookmark_json, (isset($_POST['n']) && $_POST['n'] ? $_POST['n'] : null));

        if ($_POST['t'] == 'sync')
          $anchor = 'sync';
        else
          $anchor = ($_POST['d'] == '_0' ? '' : $_POST['d']).'_'.$entry['id'];
      }
      break;
    case 'delete':
      if (isset($_GET['id']) && $_GET['id']) {
        if (isset($_GET['mode']) && $_GET['mode'] == 'sync') {
          $entry = delete_bookmark($_GET['id'], 0, $sync_json, $sync_file_prefix, 0);
        } else {
          $entry = delete_bookmark($_GET['id'], (isset($_GET['items']) ? $_GET['items'] : 0), $bookmark_json);
          $anchor = substr($_GET['id'], 0, strrpos($_GET['id'], '_'));
        }
      }
      break;
    case 'redirect':
      if (isset($_GET['u']) && $_GET['u']) {
        if (isset($_GET['id']) && $_GET['id']) {
          $url = urldecode($_GET['u']);
          $entry = delete_bookmark($_GET['id'], 0, $sync_json, $sync_file_prefix, 0);
          header('Location: '.$url);
          exit;
        }
      }
      break;
    case 'view':
      if (isset($_GET['id']) && $_GET['id'] && file_exists($content_dir.$_GET['id'])) {
        header('Content-Type: '.mime_content_type($content_dir.$_GET['id']));
        readfile($content_dir.$_GET['id']);
        exit;
      }
      break;
    case 'save':
      if (isset($_GET['id']) && $_GET['id']) {
        if (isset($_GET['delete']) && $_GET['delete']) {
          $files = glob($content_dir.$_GET['id'].'-*', GLOB_NOSORT);
          if ($files) {
            foreach ($files as $file) {
              if (file_exists($file))
                unlink($file);
            }
          }
          $entry = edit_bookmark($_GET['level'].'_'.$_GET['id'], array('meta' => array('offline' => '')), $bookmark_json);
        } else {
          if (isset($_GET['url']) && ($url = urldecode($_GET['url']))) {
            if (($file = download_item($_GET['id'], $url)))
              $entry = edit_bookmark($_GET['level'].'_'.$_GET['id'], array('meta' => array('offline' => $file['file_name'], 'downloadable' => $file['downloadable'], 'preview' => $file['preview'])), $bookmark_json);
            else
              $entry = edit_bookmark($_GET['level'].'_'.$_GET['id'], array('meta' => array('downloadable' => 0, 'preview' => 0)), $bookmark_json);
          }
        }
        $anchor = $_GET['level'].'_'.$_GET['id'];
      }
      break;
    case 'edit':
      if (isset($_GET['id']) && $_GET['id']) {
        $update = array('name' => $_POST['n']);
        if (isset($_POST['u'])) {
          $update['url'] = $_POST['u'];
          if (isset($_POST['ou']) && $_POST['ou'] !== $_POST['u'])
            $update['meta']['last_access'] = 0;
        }
        if (isset($_POST['c']))
          $update['cache'] = $_POST['c'];
        if (isset($_POST['h']))
          $update['hide_not_found'] = $_POST['h'];
        if (($entry = edit_bookmark($_GET['id'], $update, $bookmark_json))) {
          $l = ($_POST['l'] == '_0' ? '' : $_POST['l']);
          if (isset($_POST['l']) && $l.'_'.$entry['id'] !== $_GET['id']) {
            $entry = delete_bookmark($_GET['id'], 1, $bookmark_json);
            move_bookmark($entry, $l.'_0', $bookmark_json);
          }
          $anchor = $l.'_'.$entry['id'];
        }
      }
      break;
    case 'move':
      if (isset($_GET['id']) && $_GET['id']) {
        $entry = delete_bookmark($_GET['id'], 1, $bookmark_json);
        move_bookmark($entry, $_GET['position'], $bookmark_json);
        $anchor = substr($_GET['position'], 0, strrpos($_GET['position'], '_')).'_'.$entry['id'];
      }
      break;
    case 'sort':
      if (isset($_GET['id']) && $_GET['id']) {
        $entry = sort_bookmark($_GET['id'], $_GET['sort'], (isset($_GET['recursive']) ? $_GET['recursive'] : 0), $bookmark_json);
        $anchor = ($_GET['id'] == '_0' ? '' : $_GET['id']);
      }
      break;
    case 'export':
      header('Content-Type: text/plain');
      readfile($bookmark_json);
      exit;
    case 'preview':
      if (isset($_GET['id']) && $_GET['id'] && isset($_GET['url']) && ($url = urldecode($_GET['url']))) {
        ob_end_clean();
        ob_start();
        header('Content-Type: image/jpeg');
        header('Cache-Control: max-age='.$preview_file_life.', public');
        header("Pragma: cache");
        header('Expires: '.gmdate('D, d M Y H:i:s', time() + $preview_file_life).' GMT');
        $preview_file = $cache_dir . $preview_filename_prefix . $_GET['id'] . '-' . sha1($url);
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', (file_exists($preview_file) ? filemtime($preview_file) : time())).' GMT');
        if (file_exists($preview_file)) {
          if (filesize($preview_file)) {
            header('HTTP/1.1 200 Ok');
            readfile($preview_file);
          } else
            http_response_code(404);
        } else
          http_response_code(404);

        $size=ob_get_length();
        header("Content-Length: $size");
        header("Connection: close");
        ob_end_flush();
        flush();
        if (function_exists('fastcgi_finish_request'))
          fastcgi_finish_request();
        if (session_id())
          session_write_close();

        if (!file_exists($preview_file)) {
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $url);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_HEADER, 1);
          curl_setopt($ch, CURLOPT_TIMEOUT, 5);
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'User-Agent: '.$curl_ua,
          ));
          $response = curl_exec($ch);
          $header = array(
            'header_size' => curl_getinfo($ch, CURLINFO_HEADER_SIZE),
            'http_code' => (($hc = curl_getinfo($ch, CURLINFO_HTTP_CODE)) ? $hc : 404),
            'content_type' => (($ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE)) ? strtolower($ct) : '')
          );
          $header['header'] = substr($response, 0, $header['header_size']);
          $body = substr($response, $header['header_size']);
          curl_close($ch);

          if ($header['http_code'] == 200 && substr($header['content_type'], 0, 6) == 'image/' && $body) {
            file_put_contents($preview_file, $body);
            if (extension_loaded('gd')) {
              createthumbnail($preview_file, $preview_height);
              readfile($preview_file);
            } else
              echo $body;
          } else {
            touch($preview_file);
            http_response_code(404);
          }
        }
      }
      exit;
    case 'checkurl':
      if (isset($_GET['id']) && $_GET['id']) {
        if (isset($_GET['url']) && ($url = urldecode($_GET['url']))) {
          $response = get_url_response($url, 1);
          $entry = edit_bookmark($_GET['id'], array('meta' => array('not_found' => (substr($response['header']['http_code'], 0, 1) != 2 ? 1 : 0), 'http_code' => $response['header']['http_code'], 'last_access' => time(), 'downloadable' => $response['header']['downloadable'], 'preview' => $response['header']['preview'])), $bookmark_json);
        }
        $anchor = $_GET['id'];
      }
      break;
    }
    header('Location: index.php'.(isset($anchor) && $anchor ? '#entry-'.$anchor : ''));
    exit;
  } elseif (isset($_GET['u'])) {
    $url = addhttp(urldecode(substr($_SERVER['QUERY_STRING'], 2)));
    if ($url !== true) {
      $entry = add_bookmark($url, '_0', 'url', $sync_json, null, 1, 0, 1);
      echo '<html><body><script>if (window.confirm("URL synced to '.htmlentities($site_name).'. Redirect to '.htmlentities($site_name).'?")) {window.location="'.$site_url.'";} else {window.location="'.$url.'";}</script></body></html>';
    } else
      echo '<html><body><script>alert("Only urls will be synced");</script></body></html>';
    //header('Location: '.$url);
    exit;
  }
}

if ($auth)
  ob_start();

?>

<?php // Head ?>
<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<title><?php echo htmlentities($site_name); ?></title>
<meta name="description" content="<?php echo htmlentities($site_name); ?>" />
<link rel="profile" href="http://gmpg.org/xfn/11" />
<link rel="shortcut icon" href="favicon.ico" />
<link rel="apple-touch-icon" href="webapp-icon.png" />
<link rel="icon" href="webapp-icon.png" />
<link rel="mask-icon" href="website-icon.svg" color="#FF0000" />

<meta name="apple-mobile-web-app-title" content="Bookmarks" />
<meta name="application-name" content="Bookmarks" />

<style type="text/css" media="all">
<!--
html, body, div, span, h1, p, a, img, b, u, i, ol, ul, li, table, tr, td, input{font-family:"Lucida Sans Unicode","Lucida Grande","Noto Sans",Helvetica,Arial,sans-serif !important;font-size:14px;line-height:23px;}
html,body{max-width:100%;overflow-x:hidden;}
h3,.folder_title_name{font-size:16px;margin:0 0 1em;font-weight:bold;}
a {color:#0000cc;text-decoration:none;}
a:visited{color:#0000cc;}
a:hover{color:red;}
input[type="submit"]:hover,label:hover{cursor:pointer;}
input[type="radio"],input[type="checkbox"]{margin:0 5px 2px 0;vertical-align:middle;}
input[type="submit"],input[type="text"],input[type="password"]{appearance:none;-moz-appearance:none;-webkit-appearance:none;border-radius:0;-moz-border-radius:0;-webkit-border-radius:0;}
input[type="text"],input[type="password"]{border:1px solid #999;padding:0 3px;margin:0;font-size:14px;line-height:2em;}
input[type="submit"]{border:1px solid #4285f1;margin:0;padding:1px 8px;background:#4285f1;color:#fff;}
input[type="submit"]:hover{background:#d42;border-color:#d42;}
select{height:2em;}
body,#main{padding:0;margin:0;}
#wrap{padding:0 10px;}
#logout{float:right;padding-right:10px;}
#main,#addform{min-width:350px;}
#addform{position:fixed;top:0;left:0;z-index:10;width:100%;background:white;box-shadow:0 2px 2px #000;padding:0px;margin:0;}
#addform form{padding:10px 10px;}
#addform form input[type="text"]{width:50%;margin-right:5px;}
#addform form input[type="submit"]{margin-right:5px;}
#addform form input[type="radio"]{margin-left:8px;}
#addform form select{margin-left:5px;}
#advance{display:inline-block;padding:0;margin:0;}
#content{padding:60px 10px 10px;}
.entry:before{display:block;content:" ";margin-top:-70px;height:70px;visibility:hidden;}
a.edit,a.delete,a.save,a.cancel,a.offline,a.qr{margin-left:5px;font-weight:normal;}
a.edit,a.save,a.cancel{color:#666;}
.qr img{vertical-align:middle;height:15px;opacity:.6;margin-bottom:4px;}
#qrcode{position:fixed;top:0;left:0;bottom:0;right:0;z-index:9998;background:rgba(255,255,255,.8);opacity:1;-webkit-transition:all ease-in-out .3s;}
#qrcode:empty{pointer-events:none;opacity:0;}
#qrcode img{position:fixed;top:50%;left:50%;margin-top:-50px;margin-left:-50px;width:100px;height:100px;}
a.delete{color:red;}
a.delete.noedit{color:#666;}
a.offline{background:#4caf50;color:#fff;padding:2px 5px 0;border-radius:2px;display:inline-block;font-size:0.9em;line-height:21px;}
a.bookmarklet,a.bookmarklet:visited,a.bookmarklet:hover{padding:3px 7px;margin:10px 0 0 10px;color:#666;text-decoration:none;background-color:#eee;border-radius:3px;border:none;font-size:0.9em;cursor:move;font-weight:normal;display:inline-block;}
#addform-more a.bookmarklet{margin-left:0;margin-bottom:10px;}
#addform form #addform-url-title input[type="text"]{width:40%;}
h2.cat{margin:20px 10px 0px 0;display:inline-block;}
#sync h2.cat{margin:20px 0 10px;}
#sync{margin:0 0 10px;padding:0 0 10px;border-bottom:1px solid #000;}
#sync a.bookmarklet{margin-top:17px;vertical-align:top;}
form.save select{margin-left:5px;}
form.save select:hover{cursor:pointer;}
form.save select:not(:focus){border:none;font-size:14px;color:#4caf50;background:transparent;-webkit-appearance:none;padding:0;}
.folder{border-bottom:1px solid #444;margin-top:1em;}
.folder-wrap{margin-bottom:23px;}
.folder .folder{margin-left:10px;}
.folder_title{position:relative;}
.folder_title_name{cursor:pointer;}
.folder_title span.cache{background:#4caf50;color:#fff;padding:3px 7px;margin-left:10px;border-radius:2px;display:inline-block;font-size:0.9em;font-weight:normal;}
.url .border{padding-left:8px;border-left:3px solid #666;display:block;}
.target{position:relative;z-index:5;display:block;}
.url .target{margin-left:3px;}
.folder>.target,#bookmarks>.target{margin-top:-23px;margin-bottom:-1px;height:23px;}
.move{position:absolute;bottom:0;z-index:8;width:9px;height:100%;background:transparent;}
.folder_title .move{left:-1px;}
.url .move{left:-3px;}
.move:hover{cursor:move;}
.move.noedit:hover{cursor:default;}
.editform{display:none;margin:20px 10px 20px;}
.folder>.editform{margin-top:16px;}
.folder>.editfolder{margin-top:20px;}
.editform input[type="text"]{width:50%;margin:0 0 10px;padding:2px 4px;}
.editform input[type="submit"]{margin:0 5px 0 0;padding:4px 5px 5px;}
.editform select{margin-right:15px;}
p.sort{margin-top:8px;padding-bottom:8px;border-bottom:1px solid #999;}
.hide{display:none !important;}
.preview{vertical-align:middle;margin-right:5px;}
a.url.not-found.show-not-found{color:#fff;background:#d42;padding-left:5px;padding-right:5px;}
.not-found .preview{display:none;}
.last-access{display:block;color:#999;font-size:.9em;margin:8px 0;min-height:1px;}
.last-access span{color:#999;font-size:1em;margin-right:8px;}
a.url-checker{color:#4caf50;font-size:1em;margin-right:8px;}
.last-access span:empty{display:none;}
.last-access-time:not(:empty):before{content:'Last checked at ';}
.last-access-code:before{content:'(';}
.last-access-code:after{content:')';}
#rightbottom{position:fixed;right:0px;bottom:0px;z-index:999;width:30px;background-color:transparent;height:60px;color:#fff;padding:0;margin:0;}
#totop,#tobottom{width:30px;height:30px;font-size:20px;line-height:30px;color:#000;text-align:center;padding:0;margin:0;display:inline-block;}
#totop:hover,#tobottom:hover{color:#444;}
#foot{padding:0 10px 20px;}
#foot p{color:#666;}
#foot a{color:#666;}
#foot a:hover{color:#ca2017;}
.move.drag,.move.touch{background:#fff;opacity:.9;width:auto;padding:3px 10px;border:1px solid #eee;border-radius:2px;}
.move.touch{position:absolute;z-index:9999;left:0;right:0;display:block;}
.target.drag,#bookmarks>.target.drag{padding-top:15px;border-top:2px dashed #999;margin-top:0;}
.target.touch{z-index:9999;}
#search-noresult{font-weight:bold;}
#lock{position:fixed;top:0;right:0;bottom:0;left:0;z-index:9999;background:#fff;padding:20px;}
.import-form{display:inline-block;}
.file-button-wrap{width:200px;height:39px;padding:0;margin:1em 1em 0 0;box-shadow:0 0 1px #333;background:#eee;position:relative;}
.file-button-wrap:hover{background:#ddd;}
.file-button{width:100%;height:1.3em;line-height:1.3em;padding:10px 0;margin:0 auto;text-align:center;border:none;background-color:transparent;display:inline-block;cursor:pointer;float:right;}
.file-button-hide-wrap{position:absolute;opacity:0;top:0;right:0;width:100%;height:100%;border:none;margin:0;padding:0;z-index:2;}
.file-button-hide{width:100%;height:100%;border:none;margin:0;padding:0;}
@media screen and (max-width:800px){
#addform form input[type="text"]{width:80%;}
#addform form #addform-url-title input[type="text"]{width:60%;}
.editform input[type="text"]{width:80%;}
#search{margin-bottom:6px;}
#advance{margin-top:8px;}
#content{padding-top:90px;}
.entry:before{margin-top:-105px;height:105px;}
}
-->
</style>
</head>
<body>
<div id="main" style="display:none;">

<?php
if (!$auth) {
  // Show login
  echo '<div id="wrap">
<p>Please log in</p><br/>
<form method="post" action="index.php?action=login">
<input required name="p" type="password" autofocus>
<input class="compose" type="submit" value="Log in" >
<br/><br/><label><input name="r" type="checkbox" value="1"> Remember me</label>
</form>
</div><br/>';
} else {
  $cache = true;
  //$cache = 0;

  $cache_file_folderlist = $cache_dir.'folders.html';
  if ($cache && file_exists($cache_file_folderlist) && filemtime($cache_file_folderlist) >= filemtime($bookmark_json)) {
    $folders = file_get_contents($cache_file_folderlist);
  } else {
    $bookmarks = parse_bookmark_json($bookmark_json);
    $output = output_bookmarks($bookmarks[0]['entries'], $bookmark_json, 1, 1, $check_url);
    $folders = $output['folder'];
    file_put_contents($cache_file_folderlist, $folders);
  }

  $cache_file_synclist = $cache_dir.'sync.html';
  if ($cache && file_exists($cache_file_synclist) && file_exists($sync_json) && filemtime($cache_file_synclist) >= filemtime($sync_json))
    $sync_output = file_get_contents($cache_file_synclist);
  else {
    $sync = (file_exists($sync_json) ? parse_bookmark_json($sync_json) : false);
    $output_sync = ($sync !== false ? output_bookmarks($sync[0]['entries'], $sync_json, 0, 1, 0, $sync_file_prefix, 0) : '');
    if ($output_sync && $output_sync['url'])
      $sync_output = $output_sync['url'];
    else
      $sync_output = '';
    file_put_contents($cache_file_synclist, $sync_output);
  }
  $sync_output = str_replace('##FOLDERLIST##', $folders, $sync_output);

  $cache_file = $cache_dir.'index.html';
  if ($cache && file_exists($cache_file) && filemtime($cache_file) >= filemtime($bookmark_json)) {
    ob_end_clean();
    echo str_replace(array('##LOCKDOWN##', '##SYNCLIST##'), array((isset($passcode) && $passcode !== '' ? 1 : 0), $sync_output), file_get_contents($cache_file));
    exit;
  }

  // Parse bookmark json
  if (!isset($output)) {
    $bookmarks = parse_bookmark_json($bookmark_json);
    $output = output_bookmarks($bookmarks[0]['entries'], $bookmark_json, 1, 1, $check_url);
  }

  // Show add bookmark box
  echo '<div id="addform">'."\n";
  echo '<p id="logout"><a href="index.php?action=logout">Log out</a></p>';
  echo '<form action="index.php?action=add" method="post">'."\n";
  echo '<input type="text" required name="u" id="search" onkeyup="getStr(event);">'."\n";
  echo '<input type="submit" value="Add">'."\n";
  echo '<p id="advance"><a href="javascript:;" onclick="toggleShow(\'addform-more\');">Advance</a></p>'."\n";
  echo '<div id="addform-more" style="display:none;">'."\n";
  echo '<p>Type: <label><input required name="t" type="radio" value="url" checked onclick="document.getElementById(\'addform-url-title\').style.display=\'block\';">url</label>'."\n";
  echo '<label><input required name="t" type="radio" value="folder" onclick="document.getElementById(\'addform-url-title\').style.display=\'none\';">folder</label></p>'."\n";
  echo '<p id="addform-url-title">URL Title (Optional): <input type="text" name="n"></p>'."\n";
  echo '<p>Location: <select required name="d">'."\n";
  echo $output['folder'];
  echo '</select></p>'."\n";
  echo '<p class="sort-top" id="sort-_0">Sort all bookmarks by:
<a class="sort-name" href="index.php?action=sort&id=_0&sort=name&recursive=1">Name</a>,
<a class="sort-date" href="index.php?action=sort&id=_0&sort=date_added&recursive=1">Date</a> or
<a class="sort-reverse" href="index.php?action=sort&id=_0&sort=0&recursive=0">Reverse order</a>
</p>'."\n";
  echo '<p><a class="bookmarklet" href="javascript:var url=\''.$site_url.'\';var x=document.createElement(\'SCRIPT\');x.type=\'text/javascript\';x.src=url+\'bookmarklet.php?d=_\';document.getElementsByTagName(\'head\')[0].appendChild(x);void(0)" onclick="if(event.preventDefault){event.preventDefault();}if(event.stopPropagation){event.stopPropagation();}return false;" title="Drag to add bookmarklet">Save bookmark to '.htmlentities($site_name).'</a>&nbsp;&nbsp;';
  echo '<a class="bookmarklet" href="javascript:if(document.getElementById(\'spbkmk\')){document.getElementById(\'spbkmk\').parentNode.removeChild(document.getElementById(\'spbkmk\'));}var bml=document.createElement(\'div\');bml.id=\'spbkmk\';bml.style.setProperty(\'position\',\'fixed\',\'important\');bml.style.setProperty(\'z-index\',2147483640,\'important\');bml.style.setProperty(\'top\',0,\'important\');bml.style.setProperty(\'left\',0,\'important\');bml.style.setProperty(\'right\',0,\'important\');bml.style.setProperty(\'text-align\',\'left\',\'important\');bml.style.setProperty(\'background-color\',\'#fff\',\'important\');bml.style.setProperty(\'min-height\',\'28px\',\'important\');bml.style.setProperty(\'max-height\',\'56px\',\'important\');bml.style.setProperty(\'overflow\',\'hidden\',\'important\');bml.style.setProperty(\'border-bottom-width\',\'1px\',\'important\');bml.style.setProperty(\'border-bottom-style\',\'solid\',\'important\');bml.style.setProperty(\'border-bottom-color\',\'#666\',\'important\');document.body.appendChild(bml);var script=document.createElement(\'script\');script.src=\''.$site_url.'bookmarkbar.php\';bml.appendChild(script);" onclick="if(event.preventDefault){event.preventDefault();}if(event.stopPropagation){event.stopPropagation();}return false;" title="Drag to add bookmarklet">Open bookmarks in '.htmlentities($site_name).'</a></p>';
  echo '<p><a href="index.php?action=export">Export Bookmarks</a></p>';
  echo '</div>'."\n";
  echo '</form>'."\n";
  echo '</div>'."\n";

  // Show bookmarks
  echo '<div id="content"><div id="folder-wrap">'."\n";
  echo '<span class="entry sync" id="entry-sync"></span><div id="sync"><h2 class="cat">URL Sync</h2>'."\n";
  echo '<a class="bookmarklet" href="javascript:var url=\''.$site_url.'?u=\'+encodeURIComponent(window.location);window.location=url;" onclick="if(event.preventDefault){event.preventDefault();}if(event.stopPropagation){event.stopPropagation();}return false;" title="Drag to add bookmarklet">Sync URL to '.htmlentities($site_name).'</a>';
  echo '<a class="bookmarklet" href="javascript:if(document.getElementById(\'spbkmk\')){document.getElementById(\'spbkmk\').parentNode.removeChild(document.getElementById(\'spbkmk\'));}var bml=document.createElement(\'div\');bml.id=\'spbkmk\';bml.style.setProperty(\'position\',\'fixed\',\'important\');bml.style.setProperty(\'z-index\',2147483640,\'important\');bml.style.setProperty(\'top\',0,\'important\');bml.style.setProperty(\'left\',0,\'important\');bml.style.setProperty(\'right\',0,\'important\');bml.style.setProperty(\'text-align\',\'left\',\'important\');bml.style.setProperty(\'background-color\',\'#fff\',\'important\');bml.style.setProperty(\'min-height\',\'28px\',\'important\');bml.style.setProperty(\'max-height\',\'56px\',\'important\');bml.style.setProperty(\'overflow\',\'hidden\',\'important\');bml.style.setProperty(\'border-bottom-width\',\'1px\',\'important\');bml.style.setProperty(\'border-bottom-style\',\'solid\',\'important\');bml.style.setProperty(\'border-bottom-color\',\'#666\',\'important\');document.body.appendChild(bml);var script=document.createElement(\'script\');script.src=\''.$site_url.'bookmarkbar.php?folder=sync\';bml.appendChild(script);" onclick="if(event.preventDefault){event.preventDefault();}if(event.stopPropagation){event.stopPropagation();}return false;" title="Drag to add bookmarklet">View Sync URL</a>';
  echo (isset($cache) && $cache ? '##SYNCLIST##' : $sync_output);
  echo '</div>'."\n";

  echo '<div id="bookmarks"><h2 class="cat" id="mybookmarks">My Bookmarks</h2>'."\n";
  echo $output['url'];
  echo '<span class="target touchOver" id="target-_0" data-id="_0">&nbsp;</span>'."\n";
  echo '</div>'."\n";
  echo '</div></div>'."\n";
}
?>

<?php // Foot ?>
<?php if ($auth) { ?>
<div id="rightbottom"><a href="javascript:;" onclick="window.scrollTo(0,0);return false;" id="totop" title="Go to top">&#x25B2</a><a href="javascript:;" onclick="window.scrollTo(0, document.body.scrollHeight);return false;" id="tobottom" title="Go to bottom">&#x25BC</a></div>
<?php } ?>
</div> <!-- End of main -->
<div id="lock" style="display:none;">
<p>Enter Pass code:</p>
<input id="passcode" type="password" tabindex="1" autofocus onKeypress="if((window.event ? event.keyCode : (event.which ? event.which : false))=='13'){var elem=document.getElementById('passcode');lockUnlock(elem.value);elem.value='';}">
<input type="submit" value="Unlock" tabindex="2" onClick="var elem=document.getElementById('passcode');lockUnlock(elem.value);elem.value='';">
</div>
<div id="foot">
<script>
function getCookie(name) {
  var value = "; " + document.cookie;
  var parts = value.split("; " + name + "=");
  if (parts.length == 2) return parts.pop().split(";").shift();
  else return '';
}
function setLockCookie() {
  n = Date.now();
  d = new Date();
  d.setTime(n+31536000000);
  document.cookie = "_spbkmk_bookmark_lock="+n+";expires="+d.toGMTString()+";path=/";
}
function lockDown() {
  t = getCookie('_spbkmk_bookmark_lock');
  if (t && Date.now() - t >= 600000) {
    document.getElementById('lock').style.display='block';
    window.removeEventListener("scroll", setLockCookie);
    window.removeEventListener("mousemove", setLockCookie);
    window.removeEventListener("mousedown", setLockCookie);
    window.removeEventListener("keypress", setLockCookie);
    document.title = 'Locked | <?php echo str_replace('\'', '\\\'', htmlentities($site_name)); ?>';
    document.getElementById('passcode').focus();
    return true;
  } else {
    setTimeout("lockDown()", 60000);
    return false;
  }
}
function lockUnlock(p) {
  var xhr = new XMLHttpRequest();
  xhr.open("POST", 'passcode.php', true);
  xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
  xhr.onreadystatechange = function() {
    if (xhr.readyState == 4) {
      if (xhr.status == 200) {
        document.getElementById('lock').style.display='none';
        document.title = '<?php echo str_replace('\'', '\\\'', htmlentities($site_name)); ?>';
        setLockCookie();
        window.addEventListener('scroll', setLockCookie);
        window.addEventListener('mousemove', setLockCookie);
        window.addEventListener('mousedown', setLockCookie);
        window.addEventListener('keypress', setLockCookie);
        lockDown();
      }
      if (document.getElementById('lock_s'))
        document.head.removeChild(document.getElementById('lock_s'));
    }
  }
  xhr.send('p='+p);
}
if (<?php echo (isset($cache) && $cache ? '##LOCKDOWN##' : (isset($passcode) && $passcode !== '' ? 1 : 0)); ?>) {
  if (!lockDown()) {
    setTimeout(function() {
      window.addEventListener("scroll", setLockCookie);
      window.addEventListener("mousemove", setLockCookie);
      window.addEventListener("mousedown", setLockCookie);
      window.addEventListener("keypress", setLockCookie);
    }, 1000);
  }
}
document.getElementById('main').style.display='block';
function notRobot() {
  document.cookie = "_spbkmk_bookmark_notRobot=1;path=/";
  window.removeEventListener("scroll", notRobot);
  window.removeEventListener("mousemove", notRobot);
  window.removeEventListener("mousedown", notRobot);
  window.removeEventListener("keydown", notRobot);
}
window.addEventListener("scroll", notRobot);
window.addEventListener("mousemove", notRobot);
window.addEventListener("mousedown", notRobot);
window.addEventListener("keydown", notRobot);

function toggleShow(id) {
  var bb = document.getElementById(id);
  if (bb.style.display == "block") {
    bb.style.display = "none";
  } else {
    bb.style.display = "block";
  }
}
var moveId;
function removeClassDrag(ele) {
  ele.classList.remove('drag');
}
// Touch to Drag
var touchScrollTimer, touchOverElem = false;
function handleTouchStart(e) {
  if (e.preventDefault) {
    e.preventDefault();
  }
  var elem = e.target;
  var id = elem.getAttribute('data-id');
  var parentElem = document.getElementById('target-' + id);
  elem.classList.add('touch');
  elem.style.top = e.touches[0].pageY - parentElem.offsetTop + 'px';
  parentElem.classList.add('touch');
  elem.innerHTML = document.getElementById('title-' + id).innerHTML;
}
function handleTouchMove(e) {
  var elem = e.target;
  var id = elem.getAttribute('data-id');
  var parentElem = document.getElementById('target-' + id);
  elem.style.top = e.touches[0].pageY - parentElem.offsetTop + 'px';
  if (e.touches[0].clientY >= window.innerHeight - 30) {
    clearInterval(touchScrollTimer);
    touchScrollTimer = setInterval(function(){window.scrollBy(0, 5);}, 5);
  } else if (e.touches[0].clientY <= 30) {
    clearInterval(touchScrollTimer);
    touchScrollTimer = setInterval(function(){window.scrollBy(0, -5);}, 5);
  } else {
    clearInterval(touchScrollTimer);
  }
  var targetElem = document.elementFromPoint(e.touches[0].clientX, e.touches[0].clientY - 1);
  if (targetElem.classList.contains('touchOver')) {
    if (targetElem.getAttribute('data-id') !== id) {
      var targetParent = document.getElementById('target-' + targetElem.getAttribute('data-id'));
    } else {
      var targetParent = false;
    }
  } else {
    var targetParent = false;
  }
  if (touchOverElem !== targetParent) {
    if (touchOverElem)
      touchOverElem.classList.remove('drag');
    touchOverElem = targetParent;
    if (touchOverElem) {
      touchOverElem.classList.add('drag');
    }
  }
}
function handleTouchEnd(e) {
  clearInterval(touchScrollTimer);
  var elem = e.target;
  var id = elem.getAttribute('data-id');
  var parentElem = document.getElementById('target-' + id);
  elem.classList.remove('touch');
  elem.style.top = '0px';
  parentElem.classList.remove('touch');
  elem.innerHTML = '';
  if (touchOverElem) {
    touchOverElem.classList.remove('drag');
    touchOverElem = false;
  }
  var targetElem = document.elementFromPoint(e.changedTouches[0].clientX, e.changedTouches[0].clientY);
  if (targetElem.classList.contains('touchOver')) {
    if (targetElem.getAttribute('data-id') !== id) {
      window.location = '<?php echo $site_url; ?>index.php?action=move&id=' + id + '&position=' + targetElem.getAttribute('data-id');
    }
  }
}
// Drag and Drop
var dragEnterId = 0;
function handleDragStart(e) {
  id = this.getAttribute('data-id');
  moveId = id;
  e.dataTransfer.setData('text/html', id);
  this.classList.add('drag');
  this.innerHTML = document.getElementById('title-' + id).innerHTML;
}
function handleDragEnd(e) {
  removeClassDrag(this);
  this.innerHTML = '';
  if (dragEnterId)
    removeClassDrag(document.getElementById('target-'+dragEnterId));
}
function handleDragEnter(e) {
  var i=this.getAttribute('data-id');
  if (i !== moveId) {
    this.classList.add('drag');
    if (dragEnterId && i !== dragEnterId) {
      removeClassDrag(document.getElementById('target-'+dragEnterId));
    }
    dragEnterId = i;
  }
}
function handleDragOver(e) {
  if (e.preventDefault) {
    e.preventDefault();
  }
  e.dataTransfer.dropEffect = 'move';
  return false;
}
function handleDrop(e) {
  if (e.stopPropagation) {
    e.stopPropagation();
  }
  sour = e.dataTransfer.getData('text/html');
  dest = this.getAttribute('data-id');
  if (dest != sour && dest.indexOf(sour + '_') !== 0) {
    window.location = '<?php echo $site_url; ?>index.php?action=move&id=' + sour + '&position=' + dest;
  } else {
    removeClassDrag(this);
  }
  return false;
}
var targets=document.getElementsByClassName('target');
for (i = 0;i < targets.length;i++) {
  var target=targets[i];
  if (!target.classList.contains('noedit')) {
    target.addEventListener('dragover', handleDragOver, false);
    target.addEventListener('dragenter', handleDragEnter, false);
    target.addEventListener('drop', handleDrop, false);
  }
  var id=target.getAttribute('data-id');
  var move = document.getElementById('move-'+id);
  if (typeof move !== 'undefined' && move !== null) {
    move.addEventListener('dragstart', handleDragStart, false);
    move.addEventListener('dragend', handleDragEnd, false);
    move.addEventListener('touchstart', handleTouchStart, false);
    move.addEventListener('touchmove', handleTouchMove, false);
    move.addEventListener('touchend', handleTouchEnd, false);
  }
};
var dragScrollTimer, dragScroll = 0;
function dragScrollFunc() {
  if (!dragScroll) {
    dragScrollTimer = setInterval(function(){window.scrollBy(0, -5);}, 5);
    dragScroll = 1;
  }
}
document.getElementById('addform').addEventListener('dragover', dragScrollFunc, false);
document.getElementById('addform').addEventListener('dragleave', function(){clearInterval(dragScrollTimer);dragScroll = 0;}, false);
document.getElementById('addform').addEventListener('drop', function(){clearInterval(dragScrollTimer);dragScroll = 0;}, false);
// Search
var searchTimeout;
function getStr(event) {
  clearTimeout(searchTimeout);
  if (event.keyCode === 27) {
    document.getElementById('search').value='';
    document.getElementById('search').blur();
    searchStr();
  } else if (event.keyCode === 13) {
    if (event.preventDefault) {
      event.preventDefault();
    }
    if (event.stopPropagation) {
      event.stopPropagation();
    }
    return false;
  } else {
    searchStr();
  //  var entries=document.getElementsByClassName('entry');
  //  for(i=0;i<entries.length;i++) {
  //    var id=entries[i].getAttribute('data-id');
  //    if(!entries[i].classList.contains('sync')) {
  //      document.getElementById('entry-' + id).style.display='block';
  //      document.getElementById('editform-' + id).style.display='none';
  //    }
  //  }
  }
}
function searchStrFunction() {
  var str=document.getElementById('search').value;
  var searchtext=(str ? str.toLowerCase() : '');
  var showFolders='', level='';

  if (!searchtext) {
    document.getElementById('sync').style.display='block';
    document.getElementById('entry-sync').style.display='block';
    document.getElementById('mybookmarks').style.display='block';
    document.getElementById('target-_0').style.display='block';
    var entries=document.getElementsByClassName('hide');
    for(i=entries.length-1;i>=0;i--) {
      entries[i].classList.remove('hide');
    }
  } else {
    document.getElementById('sync').style.display='none';
    document.getElementById('entry-sync').style.display='none';
    document.getElementById('mybookmarks').style.display='none';
    document.getElementById('target-_0').style.display='none';
    var links=document.getElementsByClassName('search');
    for(i=0;i<links.length;i++) {
      var link=links[i];
      var id=link.getAttribute('data-id');
      var type=link.getAttribute('data-type');
      var elem=document.getElementById('entry-' + id);
      var text=document.getElementById('title-' + id).innerHTML.toLowerCase();
      if (type=='url') {
        var href=link.getAttribute('href').toLowerCase();
        if (href.indexOf(searchtext)=='-1' && text.indexOf(searchtext)=='-1') {
          elem.classList.add('hide');
        } else {
          elem.classList.remove('hide');
          var parentId=link.getAttribute('data-level');
          if(level !== parentId){
            showFolders+=','+parentId;
            level=parentId;
          }
        }
      } else {
        if (text.indexOf(searchtext)=='-1') {
          document.getElementById(id).classList.add('hide');
        } else {
          document.getElementById(id).classList.remove('hide');
          showFolders+=','+id+'_';
        }
      }
    }
    var titles=document.getElementsByClassName('folder_title_name');
    for(i=0;i<titles.length;i++) {
      var id=titles[i].getAttribute('data-id');
      if (showFolders.indexOf(','+id+'_')=='-1')
        document.getElementById(id).classList.add('hide');
      else
        document.getElementById(id).classList.remove('hide');
    }
  }

  if (document.getElementById('folder-wrap').offsetHeight == 0 && !document.getElementById('search-noresult')) {
    document.getElementById('content').innerHTML+='<p id="search-noresult">No bookmark found</p>';
  } else if (document.getElementById('folder-wrap').offsetHeight != 0 && (elemns=document.getElementById('search-noresult'))) {
    elemns.parentNode.removeChild(elemns);
  }
}
function searchStr() {
  searchTimeout=setTimeout(searchStrFunction, 500);
}
</script>
<?php
if (file_exists($f = $data_dir . 'foot.php'))
  include($f);

if ($auth) {
  echo '<form class="import-form" id="chrome-upload" method="POST" action="utils/import_chrome.php" enctype="multipart/form-data">
<div class="file-button-wrap"">
<span class="file-button">Import Chrome Bookmarks</span>
<span class="file-button-hide-wrap">
<input type="file" name="f" class="file-button-hide" accept="application/enex+xml" onchange="document.getElementById(\'chrome-upload\').submit();">
</span>
</div>
</form>';
  echo '<form class="import-form" id="firefox-upload" method="POST" action="utils/import_firefox.php" enctype="multipart/form-data">
<div class="file-button-wrap"">
<span class="file-button">Import Firefox Bookmarks</span>
<span class="file-button-hide-wrap">
<input type="file" name="f" class="file-button-hide" accept="application/enex+xml" onchange="document.getElementById(\'firefox-upload\').submit();">
</span>
</div>
</form>';
}
?>
<p id="copy">&copy; <?php echo date("Y"); ?> <a href="index.php"><?php echo htmlentities($site_name); ?></a>. Powered by <a href="https://github.com/xjpvictor/SimpleBookmark" target="_blank">SimpleBookmark</a>.</p>
</div>
<img src='parsemail.php' alt='Parse mail' style="position:fixed;left:-3px;top:-50px;width:0;height:0;border:none;"/>

<?php if ($check_url) : ?>
<script>
function addURLChecker(c) {
  var urlchecker = document.getElementById("urlchecker");
  if (typeof urlchecker !== "undefined" && urlchecker !== null) {
    urlchecker.parentNode.removeChild(urlchecker);
  }
  var d = document, s = d.createElement('script');
  s.src = "checkurls.php?c="+c;
  s.async = true;
  s.id = "urlchecker";
  d.body.appendChild(s);
}
setTimeout(addURLChecker, 3, 1);
</script>
<?php endif; ?>

<script>
document.addEventListener('gesturestart', function (e) {
  e.preventDefault();
});
</script>
<div id="qrcode" onclick="this.innerHTML='';"></div>
<script src="lib/qrcode.js"></script>
<!-- Cache generated at <?php echo date("r"); ?> -->
</body>
</html>

<?php
if (isset($cache) && $cache) {
  $html = ob_get_contents();
  ob_clean();
  file_put_contents($cache_file, $html);
  echo str_replace(array('##LOCKDOWN##', '##SYNCLIST##'), array((isset($passcode) && $passcode !== '' ? 1 : 0), $sync_output), $html);
  if (isset($_SESSION['lock']))
    unset($_SESSION['lock']);
  ob_end_flush();
}
?>
