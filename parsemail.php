<?php
function getimap($mail_server, $mail_port = '', $mail_service = 'imap', $mail_cert = '1', $mail_ssl = '0', $mail_tls = '0', $mail_folder = 'INBOX', $mail_user, $mail_pwd) {
  return imap_open('{'.$mail_server.($mail_port ? ':'.$mail_port : '').'/'.$mail_service.(!$mail_cert ? '/novalidate-cert' : '').($mail_ssl ? '/ssl' : '').($mail_tls == '1' ? '/tls' : '').($mail_tls == '-1' ? '/notls' : '').'}'.$mail_folder, $mail_user, $mail_pwd);
}
function getmails($conn) {
  $num = imap_num_msg($conn);
  $mail = array();
  for ($i = 1; $i <= $num; $i++) {
    $mail[$i - 1][] = parseheader($conn, $i);
    $mail[$i - 1][] = parsebody($conn, $i);
  }
  return $mail;
}
function parseheader($conn, $msgno) {
  $header = imap_headerinfo($conn, $msgno);
  $header_a = array();
  if (isset($header->to) && !empty($header->to)) {
    foreach ($header->to as $to)
      $header_a['to'][] = $to->mailbox.'@'.$to->host;
  }
  if (isset($header->from) && !empty($header->from)) {
    foreach ($header->from as $from)
      $header_a['from'][] = array('addr' => $from->mailbox.'@'.$from->host, 'person' => (isset($from->personal) && $from->personal ? $from->personal : ''));
  }
  if (isset($header->reply_to) && !empty($header->reply_to)) {
    foreach ($header->reply_to as $reply_to)
      $header_a['reply_to'][] = array('addr' => $reply_to->mailbox.'@'.$reply_to->host, 'person' => (isset($reply_to->personal) && $reply_to->personal ? $reply_to->personal : ''));
  }
  if (isset($header->cc) && !empty($header->cc)) {
    foreach ($header->cc as $cc)
      $header_a['cc'][] = array('addr' => $cc->mailbox.'@'.$cc->host, 'person' => (isset($cc->personal) && $cc->personal ? $cc->personal : ''));
  }
  if (isset($header->subject))
    $header_a['subject'] = imap_utf8($header->subject);
  else
    $header_a['subject'] = '';
  $header_a['recent'] = $header->Recent;
  $header_a['unseen'] = $header->Unseen;
  $header_a['flagged'] = $header->Flagged;
  $header_a['deleted'] = $header->Deleted;
  $header_a['draft'] = $header->Draft;
  $header_a['date'] = $header->udate;
  $header_a['msgno'] = $msgno;

  return $header_a;
}
function parsebody($conn, $msgno) {
  $body = imap_fetchstructure($conn, $msgno);
  $att = false;
  $attachment = array();
  $inline = array();
  $content = '';
  return parseparts($conn, $msgno, $body, $attachment, $inline, $content);
}
function parseparts($conn, $msgno, $body, $attachment, $inline, $content, $section = 0) {
  $att = false;
  if (isset($body->parts) && !empty($body->parts)) {
    $section = ($section ? $section . '.' : '');
    if (isset($body->subtype) && strtolower($body->subtype) == 'alternative') {
      $parts_a = parseparts($conn, $msgno, end($body->parts), $attachment, $inline, $content, $section . count($body->parts));
      $content = $parts_a['content'];
      $attachment = array_merge($attachment, $parts_a['attachment']);
      $inline = array_merge($inline, $parts_a['inline']);
    } else {
      foreach ($body->parts as $i => $part) {
        $parts_a = parseparts($conn, $msgno, $part, $attachment, $inline, $content, $section . ($i + 1));
        $content = $parts_a['content'];
        $attachment = array_merge($attachment, $parts_a['attachment']);
        $inline = array_merge($inline, $parts_a['inline']);
      }
    }
  } elseif (!isset($body->subtype) || (strtolower($body->subtype) !== 'pgp-signature' && strtolower($body->subtype) !== 'pkcs7-signature')) {
    if ($body->ifdparameters) {
      foreach ($body->dparameters as $para) {
        if (strtolower($para->attribute) == 'filename') {
          $att = imap_utf8($para->value);
        }
      }
    }
    if (!$att && $body->ifparameters) {
      foreach ($body->parameters as $para) {
        if (strtolower($para->attribute) == 'name') {
          $att = imap_utf8($para->value);
        }
      }
    }
    $data = preg_replace('/[\r\n]+$/', '', imap_fetchbody($conn, $msgno, (!$section ? '1' : $section)));
    $encode = (isset($body->encoding) ? $body->encoding : 0);
    if ($att) {
      if ($encode == 4)
        $data = base64_encode(quoted_printable_decode($data));
      elseif ($encode !== 3)
        $data = base64_encode($data);
      if (strtolower($body->disposition) == 'inline') {
        if (substr($body->id, 0, 1) == '<' && substr($body->id, -1) == '>')
          $n = substr($body->id, 1, -1);
        else
          $n = $body->id;
        $inline[$n] = $data;
      } else
        $attachment[$att] = $data;
    } else {
      if ($encode == 3)
        $data = base64_decode(strtr($data, ' ', '+'));
      elseif ($encode == 4)
        $data = quoted_printable_decode($data);
      if (strtolower($body->subtype) == 'html') {
        if (preg_match('/<body(\s+[^>]*)?>(.*)<\/body>/si', $data, $matches))
          $data = $matches[2];
        $content .= $data;
      } else
        $content .= htmlspecialchars($data);
    }
  }
  return array('content' => $content, 'attachment' => $attachment, 'inline' => $inline);
}

if (!function_exists('imap_open')) {
  exit('IMAP extension not installed');
}

include(__DIR__ . '/init.php');

if (!$mail_box || !$mail_pwd || !$mail_server || empty($allowed_mail)) {
  exit('Mail box not setup');
}

ob_end_clean();
ob_start();
header('HTTP/1.1 200 Ok');
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header('Expires: '.gmdate('D, d M Y H:i:s', time()).' GMT');
$size=ob_get_length();
header("Content-Length: $size");
header("Connection: close");
ob_end_flush();
flush();
if (function_exists('fastcgi_finish_request'))
  fastcgi_finish_request();
if (session_id())
  session_write_close();

$conn = getimap($mail_server, $mail_port, $mail_service, $mail_cert, $mail_ssl, $mail_tls, $mail_folder, $mail_box, $mail_pwd);
$mails = getmails($conn);

if ($mails) {
  foreach ($mails as $mail) {
    $header = $mail[0];
    $body = $mail[1]['content'];

    if (($header['recent'] == 'N' || $header['unseen'] == 'U') && $header['deleted'] !== 'D' && $header['draft'] !== 'X' && isset($header['from'][0]['addr']) && $header['from'][0]['addr'] && $allowed_mail && in_array($header['from'][0]['addr'], $allowed_mail)) {
      if ($body) {
        $folder = '_0';
        if ($header['subject']) {
          $bookmark = parse_bookmark_json($bookmark_json);
          $bookmark = $bookmark[0]['entries'];
          foreach ($bookmark as $entry) {
            if ($entry['type'] == 'folder' && strtolower($header['subject']) == strtolower($entry['name'])) {
              $folder = '_'.$entry['id'];
              break;
            }
          }
        }
        if (preg_match('/<a (?:[^>]*\s)*href\s*=\s*(?:"|\')([^"\']*)(?:"|\')[^>]*>([^<]+)<\/a>/i', $body, $matches))
          $body = $matches[1];
        $entry = add_bookmark($body, $folder, 'url', $bookmark_json);
      }
    }
    imap_delete($conn, $header['msgno']);
  }
}

imap_expunge($conn);
imap_close($conn);

exit;
