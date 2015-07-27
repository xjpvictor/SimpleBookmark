<?php
// Draw iframe
if (!isset($_GET['u'])) {
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
  iframe.style.position = "fixed";
  iframe.style.left = "50%";
  iframe.style.top = "10px";
  iframe.style.marginLeft = "-150px";
  iframe.style.zIndex = 100000;
  iframe.style.border = "none";
  document.body.appendChild(iframe);

  window.addEventListener("message", function(e) {
    if (e.data == "spb_close") {
      document.body.removeChild(document.getElementById("spb_iframe"));
    }
  });
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
html, body, div, span, h1, p, a, input, textarea{font-family:"Lucida Sans Unicode","Lucida Grande","wenquanyi micro hei","droid sans fallback",FreeSans,Helvetica,Arial,"hiragino sans gb","stheiti","microsoft yahei",\5FAE\8F6F\96C5\9ED1,sans-serif !important;font-size:14px;line-height:1.8em;}
html,body{background:transparent;}
body{padding:0;margin:0;}
#wrap{background-color:#000;border:1px solid red;border-radius:3px;margin:6px 6px 0 0;}
#wrap form{text-align:center;}
#wrap input[type="password"]{width:195px;height:20px;margin:4px 5px 4px 0;}
#wrap select{width:140px;font-size:14px;padding:3px 0;}
#wrap input[type="submit"]{width:70px;font-size:14px;}
#wrap input[type="submit"]:hover{cursor:pointer;}
#wrap p{color:#fff;font-size:16px;text-align:center;margin:4px 0;}
#cancel{position:absolute;top:0px;right:0px;background:white;color:black;font-size:13px;font-weight:bold;text-align:center;line-height:1em;margin:0;padding:0;width:13px;height:13px;border-radius:13px;border:2px solid #000;}
#cancel:hover{cursor:pointer;}
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
    $bookmarks = parse_bookmark_json($bookmark_json);
    $output = output_bookmarks($bookmarks[0]['entries']);
    echo '<form method="post" action="'.$url.'">';
    echo '<p>Add to: <select required name="d">'."\n";
    echo '<option value="_0">Bookmarks</option>'."\n";
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
