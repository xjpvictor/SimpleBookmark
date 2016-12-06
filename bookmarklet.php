<?php
// Draw iframe
if (!isset($_GET['u'])) {
  header('Content-Type: text/javascript');
  echo 'if (!window.location.origin) {
  window.location.origin = window.location.protocol+"//"+window.location.hostname+(window.location.port?":"+window.location.port:"");
}
if (!document.getElementById("spb_iframe") && window.location.origin) {
  var iframe = document.createElement("iframe");
  iframe.frameBorder = 0;
  iframe.width = "310px";
  iframe.height = "50px";
  iframe.id = "spb_iframe";
  iframe.src = x.src + "&u=" + encodeURIComponent(document.location.href) + "&n=" + encodeURIComponent(document.title) + "&href=" + encodeURIComponent(window.location.origin);
  iframe.style.setProperty("position", "fixed", "important");
  iframe.style.setProperty("left", "50%", "important");
  iframe.style.setProperty("top", "10px", "important");
  iframe.style.setProperty("margin-left", "-150px", "important");
  iframe.style.setProperty("z-index", 2147483647, "important");
  iframe.style.setProperty("border", "none", "important");
  document.body.appendChild(iframe);

  function closeFrame(e) {
    if (e.data == "spb_close") {
      document.body.removeChild(document.getElementById("spb_iframe"));
      window.removeEventListener("message", closeFrame);
    }
  }

  window.addEventListener("message", closeFrame);
}';
  exit;
}

include(__DIR__ . '/init.php');
?>

<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0" />
<link rel="profile" href="http://gmpg.org/xfn/11" />
<style type="text/css" media="all">
html, body, div, span, h1, p, a, input, textarea{font-family:"Lucida Sans Unicode","Lucida Grande","wenquanyi micro hei","droid sans fallback",FreeSans,Helvetica,Arial,"hiragino sans gb","stheiti","microsoft yahei",\5FAE\8F6F\96C5\9ED1,sans-serif !important;font-size:14px !important;line-height:1.8em !important;}
html,body{background:transparent !important;}
body{padding:0 !important;margin:0 !important;}
#wrap{background-color:#000 !important;border:1px solid red !important;border-radius:3px !important;margin:6px 6px 0 0 !important;}
#wrap form{text-align:center !important;}
#wrap input[type="password"]{width:195px !important;height:20px !important;margin:4px 5px 4px 0 !important;}
#wrap select{width:140px !important;font-size:14px !important;padding:3px 0 !important;}
#wrap input[type="submit"]{width:70px !important;font-size:14px !important;}
#wrap input[type="submit"]:hover{cursor:pointer !important;}
#wrap p{color:#fff !important;font-size:16px !important;text-align:center !important;margin:4px 0 !important;}
#cancel{position:absolute !important;top:0px !important;right:0px !important;background:white !important;color:black !important;font-size:13px !important;font-weight:bold !important;text-align:center !important;line-height:1em !important;margin:0 !important;padding:0 !important;width:13px !important;height:13px !important;border-radius:13px !important;border:2px solid #000 !important;}
#cancel:hover{cursor:pointer !important;}
</style>
</head>
<body>
<div id="wrap">
<div id="cancel" onclick="window.top.postMessage('spb_close', '<?php echo urldecode($_GET['href']); ?>');" title="Cancel">
x
</div>
<?php
if ($auth && ((isset($_GET['d']) && $_GET['d'] !== '_') || isset($_POST['d']))) {
  if (add_bookmark(urldecode($_GET['u']), (isset($_POST['d']) ? $_POST['d'] : $_GET['d']), 'url', $bookmark_json, (isset($_GET['n']) && $_GET['n'] ? urldecode($_GET['n']) : null))) {
    echo '<p>Bookmark added</p>';
  } else
    echo '<p>Error</p>';
  echo '<script>
var stoptime=3;
setTimeout("spbclose()",stoptime*1000);
function spbclose(){window.top.postMessage(\'spb_close\', \''.urldecode($_GET['href']).'\');}
</script>';
} else {
  $url = 'bookmarklet.php?';
  $i = 0;
  foreach ($_GET as $key => $value) {
    $url .= ($i ? '&' : '').$key.'='.urlencode($value);
    $i++;
  }
  if (!$auth) {
    echo '<form method="post" action="'.$url.'">';
    echo '<input required name="p" type="password" autofocus><input type="submit" value="Log in">';
  } else {
    $cache_file = $cache_dir.'bookmarklet.html';
    if (file_exists($cache_file) && filemtime($cache_file) >= filemtime($bookmark_json)) {
      echo (file_get_contents($cache_file));
      exit;
    }

    $cache = true;
    ob_start();

    $bookmarks = parse_bookmark_json($bookmark_json);
    $output = output_bookmarks($bookmarks[0]['entries'], $bookmark_json);
    echo '<form method="post" action="'.$url.'">';
    echo '<p>Add to: <select required name="d">'."\n";
    echo $output['folder'];
    echo '</select>'."\n";
    echo '<input type="submit" value="Add"></p>';
  }
  echo '</form>';
}
?>
</div>
</body>
</html>

<?php
if (isset($cache) && $cache) {
  $output = ob_get_contents();
  ob_clean();
  file_put_contents($cache_file, $output);
  echo $output;
  ob_end_flush();
}
?>
