<?php
include(__DIR__ . '/init.php');

echo 'bml.innerHTML=\'<p style="float:right;margin:0 0 0 10px;padding:0;line-height:27px;font-size:14px;"><a href="javascript:;" onclick="var bml=document.getElementById(\\\'spbkmk\\\');if(bml.style.maxHeight==\\\'56px\\\'){bml.style.maxHeight=\\\'none\\\';this.innerHTML=\\\'Collapse\\\';}else{bml.style.maxHeight=\\\'56px\\\';this.innerHTML=\\\'Expand\\\';}">Expand</a> / <a href="javascript:;" onclick="var bml=document.getElementById(\\\'spbkmk\\\');bml.parentNode.removeChild(bml);">Close</a></p>\';';

if (!$auth) {
  echo 'bml.innerHTML+=\'<p style="margin:0;padding:0;line-height:27px;font-size:14px;text-align:center;">Please <a href="'.$site_url.'" target="_blank">log in</a></p>\';';
  exit;
} else {
  if (isset($_GET['entry']) && $_GET['entry']) {
    if (preg_match('/<a (?:[^>]*\s)*href\s*=\s*(?:"|\')([^"\']*)(?:"|\')[^>]*>([^<]+)<\/a>/i', urldecode($_GET['entry']), $matches)) {
      $entry = add_bookmark($matches[1], (isset($_GET['folder']) ? $_GET['folder'] : '_0'), 'url', $bookmark_json, (isset($matches[2]) ? preg_replace('/<[^>]+>/', '', $matches[2]) : null));
    }
  }
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
      echo 'bml.innerHTML+=\'<a style="margin:0 20px 0 0;padding:0;line-height:27px;font-size:14px;text-decoration:none;color:#000;display:inline-block;" href="'.str_replace(array('\\', '"', '\''), array('\\\\', '\\"', '\\\''), $entry['url']).'" title="'.str_replace(array('\\', '"', '\''), array('\\\\', '\\"', '\\\''), htmlentities($entry['name']).'&#013;'.htmlentities($entry['url'])).'">&raquo;&nbsp;'.str_replace(array('\\', '\''), array('\\\\', '\\\''), ($len = mb_strlen($entry['name'], 'utf-8') > 20 ? htmlentities(mb_substr($entry['name'], 0, 17)).'...' : $entry['name'])).'</a>\';';
  }
}

echo 'bml.innerHTML=\'<div style="margin:0 10px;" id="spbkmk-wrap" ondragover="event.preventDefault();" ondrop="if(event.stopPropagation()){event.stopPropagation();}var str=event.dataTransfer.getData(\\\'text/html\\\');this.parentNode.innerHTML=\\\'\\\';var script=document.createElement(\\\'script\\\');script.src=\\\''.$site_url.'bookmarkbar.php?'.(isset($_GET['folder']) ? 'folder='.$_GET['folder'].'&' : '').'entry=\\\'+encodeURIComponent(str);bml.appendChild(script);">\'+bml.innerHTML+\'</div>\';';
?>
