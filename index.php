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
        $entry = add_bookmark($_POST['u'], $_POST['d'], $_POST['t'], $bookmark_json, (isset($_POST['n']) && $_POST['n'] ? $_POST['n'] : null));
        $anchor = ($_POST['d'] == '_0' ? '' : $_POST['d']).'_'.$entry['id'];
      }
      break;
    case 'delete':
      if (isset($_GET['id']) && $_GET['id']) {
        if (isset($_GET['mode']) && $_GET['mode'] == 'sync') {
          $entry = delete_bookmark($_GET['id'], 0, $sync_json);
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
          $entry = delete_bookmark($_GET['id'], 0, $sync_json);
          header('Location: '.$url);
          exit;
        }
      }
      break;
    case 'edit':
      if (isset($_GET['id']) && $_GET['id']) {
        $update = array('name' => $_POST['n']);
        if (isset($_POST['u']))
          $update['url'] = $_POST['u'];
        $entry = update_bookmark($_GET['id'], $update, $bookmark_json);
        if (isset($_POST['l']) && $_POST['l'] !== substr($_GET['id'], 0, strrpos($_GET['id'], '_'))) {
          $entry = delete_bookmark($_GET['id'], 1, $bookmark_json);
          move_bookmark($entry, $_POST['l'].'_0', $bookmark_json);
        }
        $anchor = $_POST['l'].'_'.$entry['id'];
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
    }
    header('Location: index.php'.(isset($anchor) && $anchor ? '#entry-'.$anchor : ''));
    exit;
  } elseif (isset($_GET['u'])) {
    $url = addhttp(urldecode(substr($_SERVER['QUERY_STRING'], 2)));
    $entry = add_bookmark($url, '_0', 'url', $sync_json, null, 1);
    echo '<html><body><script>if (window.confirm("URL synced to '.htmlentities($site_name).'. Redirect to original url?")) {window.location="'.$url.'";} else {window.location="'.$site_url.'";}</script></body></html>';
    //header('Location: '.$url);
    exit;
  }
}
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
<style type="text/css" media="all">
<!--
html, body, div, span, h1, p, a, img, b, u, i, ol, ul, li, table, tr, td, input{font-family:"Lucida Sans Unicode","Lucida Grande","wenquanyi micro hei","droid sans fallback",FreeSans,Helvetica,Arial,"hiragino sans gb","stheiti","microsoft yahei",\5FAE\8F6F\96C5\9ED1,sans-serif !important;font-size:14px;line-height:23px;}
h3,.folder_title_name{font-size:16px;margin:0 0 1em;font-weight:bold;}
a {color:#0000cc;text-decoration:none;}
a:visited{color:#0000cc;}
a:hover{color:red;}
input[type="submit"]:hover{cursor:pointer;}
body,#main{padding:0;margin:0;}
#wrap{padding:0 10px;}
#logout{float:right;padding-right:10px;}
#main,#addform{min-width:350px;}
#addform{position:fixed;top:0;left:0;z-index:10;width:100%;background:white;box-shadow:0 2px 2px #000;padding:0px;margin:0;}
#addform form{padding:10px 10px;}
#addform form input[type="text"]{width:50%;margin-right:5px;}
#addform form input[type="submit"]{margin-right:5px;padding:4px 5px 5px;}
#advance{display:inline-block;padding:0;margin:0;}
#content{padding:60px 10px 10px;}
.entry:before{display:block;content:" ";margin-top:-70px;height:70px;visibility:hidden;}
a.edit,a.delete{margin-left:5px;font-weight:normal;}
a.edit{color:#666;}
a.delete{color:red;}
a.delete.noedit{color:#666;}
a.bookmarklet,a.bookmarklet:visited,a.bookmarklet:hover{padding:3px 7px;margin:10px 0 0 10px;color:#666;text-decoration:none;background-color:#eee;border-radius:3px;border:none;font-size:0.9em;cursor:move;font-weight:normal;display:inline-block;}
#addform-more a.bookmarklet{margin-left:0;margin-bottom:10px;}
#addform form #addform-url-title input[type="text"]{width:40%;}
h2.cat{margin:20px 10px 0px 0;display:inline-block;}
#sync h2.cat{margin:20px 0 10px;}
#sync{margin:0 0 10px;padding:0 0 10px;border-bottom:1px solid #000;}
#sync a.bookmarklet{margin-top:17px;vertical-align:top;}
.folder{border-bottom:1px solid #444;margin-top:1em;}
.folder .folder{margin-left:10px;}
.folder_title{position:relative;}
.folder_title_name{cursor:pointer;}
.url .border{padding-left:8px;border-left:3px solid #666;display:block;}
.target{position:relative;z-index:5;display:block;}
.url .target{margin-left:3px;}
.folder>.target{margin-top:-23px;margin-bottom:-1px;height:23px;}
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
p.sort{margin-top:8px;padding-bottom:8px;border-bottom:1px solid #999;}
.hide{display:none !important;}
#rightbottom{position:fixed;right:0px;bottom:0px;z-index:999;width:30px;background-color:transparent;height:60px;color:#fff;padding:0;margin:0;}
#totop,#tobottom{width:30px;height:30px;font-size:20px;line-height:30px;color:#000;text-align:center;padding:0;margin:0;display:inline-block;}
#totop:hover,#tobottom:hover{color:#444;}
#foot{padding:0 10px 20px;}
#foot p{color:#666;}
#foot a{color:#666;}
#foot a:hover{color:#ca2017;}
.move.drag{background:#fff;opacity:.9;width:auto;padding:3px 10px;border:1px solid #eee;border-radius:2px;}
.target.drag{padding-top:50px;}
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
<div id="main">

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

  $cache_file_synclist = $cache_dir.'sync.html';
  if ($cache && file_exists($cache_file_synclist) && file_exists($sync_json) && filemtime($cache_file_synclist) >= filemtime($sync_json))
    $sync_output = file_get_contents($cache_file_synclist);
  else {
    $sync = (file_exists($sync_json) ? parse_bookmark_json($sync_json) : false);
    $output_sync = ($sync !== false ? output_bookmarks($sync[0]['entries'], $sync_json, 0, 1) : '');
    if ($output_sync && $output_sync['url'])
      $sync_output = $output_sync['url'];
    else
      $sync_output = '';
    file_put_contents($cache_file_synclist, $sync_output);
  }

  $cache_file = $cache_dir.'index.html';
  if ($cache && file_exists($cache_file) && filemtime($cache_file) >= filemtime($bookmark_json)) {
    echo str_replace(array('##LOCKDOWN##', '##SYNCLIST##'), array((isset($passcode) && $passcode !== '' ? 1 : 0), $sync_output), file_get_contents($cache_file));
    exit;
  }

  ob_start();

  // Parse bookmark json
  $bookmarks = parse_bookmark_json($bookmark_json);
  $output = output_bookmarks($bookmarks[0]['entries'], $bookmark_json);

  // Show add bookmark box
  echo '<div id="addform">'."\n";
  echo '<p id="logout"><a href="index.php?action=logout">Log out</a></p>';
  echo '<form action="index.php?action=add" method="post">'."\n";
  echo '<input type="text" required name="u" id="search" onkeydown="getStr(event);" onkeyup="searchStr(event);">'."\n";
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
  echo '<div id="sync"><h2 class="cat">URL Sync</h2>'."\n";
  echo '<a class="bookmarklet" href="javascript:var url=\''.$site_url.'?u=\'+window.location;window.location=url;" onclick="if(event.preventDefault){event.preventDefault();}if(event.stopPropagation){event.stopPropagation();}return false;" title="Drag to add bookmarklet">Sync URL to '.htmlentities($site_name).'</a>';
  echo '##SYNCLIST##';
  echo '</div>'."\n";

  echo '<div id="bookmarks"><h2 class="cat">My Bookmarks</h2>'."\n";
  echo $output['url'];
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
<form method="POST" action="javascript:void(0);" onSubmit="var elem=document.getElementById('passcode');var script=document.createElement('script');script.id='lock_s';script.src='passcode.php?p='+elem.value;document.body.appendChild(script);elem.value='';">
<input id="passcode" type="password" autofocus>
<input type="submit" value="Unlock">
</form>
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
    window.removeEventListener("keydown", setLockCookie);
    document.title = 'Locked | <?php echo str_replace('\'', '\\\'', htmlentities($site_name)); ?>';
  } else
    setTimeout("lockDown()", 60000);
}
if (<?php echo (isset($cache) && $cache ? '##LOCKDOWN##' : (isset($passcode) && $passcode !== '' ? 1 : 0)); ?>) {
  lockDown();
  setTimeout(function() {
    window.addEventListener("scroll", setLockCookie);
    window.addEventListener("mousemove", setLockCookie);
    window.addEventListener("mousedown", setLockCookie);
    window.addEventListener("keydown", setLockCookie);
  }, 10000);
}
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
var eleCounts = {};
var moveId;
function handleDragStart(e) {
  id = this.getAttribute('data-id');
  moveId = id;
  e.dataTransfer.setData('text/html', id);
  this.classList.add('drag');
  this.innerHTML = document.getElementById('title-' + id).innerHTML;
}
function handleDragEnd(e) {
  this.classList.remove('drag');
  this.innerHTML = '';
}
function handleDragEnter(e) {
  var i=this.getAttribute('data-id');
  if (eleCounts[i]) {
    eleCounts[i]++;
  } else {
    eleCounts[i]=1;
  }
  if (i !== moveId) {
    this.classList.add('drag');
  }
}
function handleDragLeave(e) {
  var ele=this;
  var i=this.getAttribute('data-id');
  setTimeout(function() {
    if (eleCounts[i]) {
      eleCounts[i]--;
    } else {
      eleCounts[i]=0;
    }
    if (eleCounts[i] <= 0) {
      ele.classList.remove('drag');
    }
  }, 1);
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
    this.classList.remove('drag');
  }
  return false;
}
var targets=document.getElementsByClassName('target');
for (i = 0;i < targets.length;i++) {
  var target=targets[i];
  if (!target.classList.contains('noedit')) {
    target.addEventListener('dragover', handleDragOver, false);
    target.addEventListener('dragenter', handleDragEnter, false);
    target.addEventListener('dragleave', handleDragLeave, false);
    target.addEventListener('drop', handleDrop, false);
  }
};
var moves=document.getElementsByClassName('move');
for (i=0;i<moves.length;i++) {
  var move=moves[i];
  move.addEventListener('dragstart', handleDragStart, false);
  move.addEventListener('dragend', handleDragEnd, false);
};
function getStr(event) {
  if (event.keyCode === 27) {
    document.getElementById('search').blur();
  } else if (event.keyCode === 13) {
    if (event.preventDefault) {
      event.preventDefault();
    }
    if (event.stopPropagation) {
      event.stopPropagation();
    }
    return false;
  }
}
function searchStrFunction() {
  var searchtext=document.getElementById('search').value.toLowerCase();
  if (searchtext) {
    document.getElementById('sync').style.display='none';
  } else {
    document.getElementById('sync').style.display='block';
  }
  var links=document.getElementsByClassName('search');
  for(i=0;i<links.length;i++) {
    var link=links[i];
    var id=link.getAttribute('data-id');
    var type=link.getAttribute('data-type');
    var elem=document.getElementById('entry-' + id);
    var form=document.getElementById('editform-' + id);
    elem.style.display='block';
    form.style.display='none';
    var text=document.getElementById('title-' + id).innerHTML.toLowerCase();
    if (type=='url') {
      var href=link.getAttribute('href').toLowerCase();
      if (href.indexOf(searchtext)=='-1' && text.indexOf(searchtext)=='-1') {
        elem.classList.add('hide');
        var l=id;
        while ((l=l.substring(0, l.lastIndexOf('_')))) {
          document.getElementById('entry-' + l).style.display='block';
          document.getElementById('editform-' + l).style.display='none';
          if (document.getElementById('folder-wrap-' + l).offsetHeight == 0) {
            var t = document.getElementById('title-' + l).innerHTML.toLowerCase();
            if (t.indexOf(searchtext)=='-1') {
              document.getElementById(l).classList.add('hide');
            }
          }
        }
      } else {
        elem.classList.remove('hide');
        var l=id;
        while ((l=l.substring(0, l.lastIndexOf('_')))) {
          document.getElementById('entry-' + l).style.display='block';
          document.getElementById('editform-' + l).style.display='none';
          document.getElementById(l).classList.remove('hide');
        }
      }
    }
  }
  if (document.getElementById('folder-wrap').offsetHeight == 0 && !document.getElementById('search-noresult')) {
    document.getElementById('content').innerHTML+='<p id="search-noresult">No bookmark found</p>';
  } else if (document.getElementById('folder-wrap').offsetHeight != 0 && (elemns=document.getElementById('search-noresult'))) {
    elemns.parentNode.removeChild(elemns);
  }
}
var timeout=null;
function searchStr(event) {
  clearTimeout(timeout);
  timeout=setTimeout(searchStrFunction(), 100);
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
<img src='parsemail.php' alt='Parse mail' style="position:fixed;left:-3px;top:-20px;width:0;height:0;border:none;"/>
</body>
</html>

<?php
if (isset($cache) && $cache) {
  $output = ob_get_contents();
  ob_clean();
  file_put_contents($cache_file, $output);
  echo str_replace(array('##LOCKDOWN##', '##SYNCLIST##'), array((isset($passcode) && $passcode !== '' ? 1 : 0), $sync_output), $output);
  if (isset($_SESSION['lock']))
    unset($_SESSION['lock']);
  ob_end_flush();
}
?>
