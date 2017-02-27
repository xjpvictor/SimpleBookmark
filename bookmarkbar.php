<?php
header('Content-Type: text/javascript');
include(__DIR__ . '/init.php');

echo 'bml.innerHTML=\'<p style="font-family:\\\'Lucida Sans Unicode\\\',\\\'Lucida Grande\\\',\\\'Noto Sans\\\',FreeSans,Helvetica,Arial,sans-serif !important;float:right !important;margin:0 0 0 10px !important;padding:0 !important;line-height:27px !important;font-size:14px !important;position:inherit !important;"><a style="font-family:\\\'Lucida Sans Unicode\\\',\\\'Lucida Grande\\\',\\\'Noto Sans\\\',FreeSans,Helvetica,Arial,sans-serif !important;margin:0 !important;padding:0 !important;line-height:27px !important;font-size:14px !important;text-decoration:none !important;color:#00c !important;display:inline-block !important;border:none !important;position:inherit !important;" href="javascript:;" onclick="var bml=document.getElementById(\\\'spbkmk\\\');if(bml.style.maxHeight==\\\'56px\\\'){bml.style.setProperty(\\\'max-height\\\',\\\'none\\\',\\\'important\\\');this.innerHTML=\\\'Collapse\\\';}else{bml.style.setProperty(\\\'max-height\\\',\\\'56px\\\',\\\'important\\\');this.innerHTML=\\\'Expand\\\';}">Expand</a> / <a style="font-family:\\\'Lucida Sans Unicode\\\',\\\'Lucida Grande\\\',\\\'Noto Sans\\\',FreeSans,Helvetica,Arial,sans-serif !important;margin:0 !important;padding:0 !important;line-height:27px !important;font-size:14px !important;text-decoration:none !important;color:#00c !important;display:inline-block !important;border:none !important;position:inherit !important;" href="javascript:;" onclick="var bml=document.getElementById(\\\'spbkmk\\\');bml.parentNode.removeChild(bml);">Close</a></p>\';';

if (!$auth) {
  echo 'bml.innerHTML=\'<div style="margin:0 10px !important;">\'+bml.innerHTML+\'<p style="margin:0 !important;padding:0 !important;line-height:27px !important;font-size:14px !important;text-align:center !important;">Please <a href="'.$site_url.'" target="_blank">log in</a></p></div>\';';
  exit;
}

if (isset($_GET['entry']) && $_GET['entry']) {
  if (preg_match('/<a (?:[^>]*\s)*href\s*=\s*(?:"|\')([^"\']*)(?:"|\')[^>]*>([^<]+)<\/a>/i', urldecode($_GET['entry']), $matches)) {
    $entry = add_bookmark($matches[1], (isset($_GET['folder']) ? $_GET['folder'] : '_0'), 'url', $bookmark_json, (isset($matches[2]) ? preg_replace('/<[^>]+>/', '', $matches[2]) : null));
  }
}

$cache_file = $cache_dir.'bookmarkbar'.(isset($_GET['folder']) ? $_GET['folder'] : '_0').'.html';
if (file_exists($cache_file) && filemtime($cache_file) >= filemtime($bookmark_json)) {
  echo (file_get_contents($cache_file));
  exit;
}

ob_start();

$bookmarks = parse_bookmark_json($bookmark_json);
$bookmarks = $bookmarks[0]['entries'];
if (isset($_GET['folder'])) {
  $levels = explode('_', substr($_GET['folder'], 1));
  foreach ($levels as $level) {
    $bookmarks = $bookmarks['_'.$level]['entries'];
  }
}
// output bookmarks
foreach ($bookmarks as $entry) {
  if ($entry['type'] == 'url')
    echo 'bml.innerHTML+=\'<a style="font-family:\\\'Lucida Sans Unicode\\\',\\\'Lucida Grande\\\',\\\'Noto Sans\\\',FreeSans,Helvetica,Arial,sans-serif !important;font-family:\\\'Lucida Sans Unicode\\\',\\\'Lucida Grande\\\',\\\'Noto Sans\\\',FreeSans,Helvetica,Arial,sans-serif !important;margin:0 20px 0 0 !important;padding:0 !important;line-height:27px !important;font-size:14px !important;text-decoration:none !important;color:#000 !important;display:inline-block !important;border:none !important;position:inherit !important;" href="'.str_replace(array('\\', '"', '\''), array('\\\\', '\\"', '\\\''), $entry['url']).'" title="'.str_replace(array('\\', '"', '\''), array('\\\\', '\\"', '\\\''), htmlentities($entry['name']).'&#013;'.htmlentities($entry['url'])).'">&raquo;&nbsp;'.str_replace(array('\\', '\''), array('\\\\', '\\\''), ($len = mb_strlen($entry['name'], 'utf-8') > 20 ? htmlentities(mb_substr($entry['name'], 0, 17)).'...' : $entry['name'])).'</a>\';';
}

echo 'bml.innerHTML=\'<div style="font-family:\\\'Lucida Sans Unicode\\\',\\\'Lucida Grande\\\',\\\'Noto Sans\\\',FreeSans,Helvetica,Arial,\\\'hiragino sans gb\\\',\\\'stheiti\\\',sans-serif !important;padding:0 !important;line-height:27px !important;margin:0 10px !important;font-size:14px !important;border:none !important;" id="spbkmk-wrap" ondragover="event.preventDefault();" ondrop="if(event.stopPropagation()){event.stopPropagation();}var str=event.dataTransfer.getData(\\\'text/html\\\');this.parentNode.innerHTML=\\\'\\\';var script=document.createElement(\\\'script\\\');script.src=\\\''.$site_url.'bookmarkbar.php?'.(isset($_GET['folder']) ? 'folder='.$_GET['folder'].'&' : '').'entry=\\\'+encodeURIComponent(str);bml.appendChild(script);">\'+bml.innerHTML+\'</div>\';';

$output = ob_get_contents();
ob_clean();
file_put_contents($cache_file, $output);
echo $output;
ob_end_flush();
?>
