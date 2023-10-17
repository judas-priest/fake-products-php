<?php
/*function removeBB2($text)
{
    $text = '<b>Dima</b>,<u>cool</u>';

    $find = [
        '~(.)|\<(b)\>(.*?)\</b\>~s',
        '~(.)|\<(i)\>(.*?)\</i\>~s',
        '~(.)|\<(u)\>(.*?)\</u\>~s'
    ];
    $replace = [
        '$1',
        '$1',
        '$1'
    ];
    $attachment = [];
    $result = [];
    if (preg_match_all($exp, $text, $out, PREG_OFFSET_CAPTURE)) {
    }
    preg_replace_callback($find, function ($matches) use (&$attachment, &$result) {
        $offset = 1;
        $length = strlen($matches['0']);
        $attachment[] = ['type' => $matches['1'], 'offset' => $offset, 'length' => $length];
        $result[] = $matches['0'];
        return;
    }, $text);

    print_r($attachment);
}
*/

function makeReadableSize($bytes)
{
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), [0, 0, 2, 2, 3][$i]) . [' B', ' kB', ' MB', ' GB', ' TB'][$i];
}
function ExistsURL(string $url)
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_NOBODY, true);
    if (curl_exec($curl) !== false) {
        return curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 404 ? true : false;
    } else {
        return false;
    }
}


function GetLinkPreview(string $url, int $full = 0)
{
    $fileRegex = '~.*\/(\w*\.(mp(3|4)|wav|exe|zip|rar|apk|bin|tar(|\.gz)))(\?.*)?$~';
    $youtubeRegex = '~http(?:s?):\/\/(?:www\.)?youtu(?:be\.com\/watch\?v=|\.be\/)([\w\-\_]*)(&(amp;)?‌​[\w\?‌​=]*)?~';
    $newUrl = preg_replace('/^(https?:\/\/)?(www\.)?/', '', $url);
    preg_match('/(?!(w+)\.)\S*(?:\w+\.)+\w+/', $newUrl, $hostname);
    // if (preg_match($youtubeRegex, $newUrl)) {
    //     echo 'dima';
    // }
    $curl = curl_init($newUrl);
    $options = [
        CURLOPT_USERAGENT => 'DuckDuckBot/1.0; (+http://duckduckgo.com/duckduckbot.html)',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => function (
            $DownloadSize,
            $Downloaded,
            $UploadSize,
            $Uploaded
        ) {
            return ($Downloaded > (1048576)) ? true : false;
        }
    ];
    curl_setopt_array($curl, $options);
    $fp = curl_exec($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    $protocol = strtolower($info['scheme']) . '://';
    preg_match('~^.*\/(\w*)~', $info['content_type'], $mime);
    if ((curl_errno($curl) === 42 || curl_errno($curl) === 28 && $info['http_code'] === 200) && preg_match($fileRegex, $newUrl, $file)) {
        $json = [];
        $json['type'] = 'file-link';
        $json['url'] = $protocol . $newUrl;
        $json['title'] = $file['1'];
        $json['extension'] = $file['2'];
        $json['fileSize'] = $info['download_content_length'];
        $json['timestamp'] = time();
        return $json;
    }
    if ($fp) {
        switch ($mime['1']) {
            case 'gif':
            case 'png':
            case 'svg+xml':
            case 'tiff':
            case 'webp':
            case 'heif':
            case 'heic':
            case 'jpeg':
                $json = [];
                $json['type'] = 'image';
                $json['url'] = $protocol . $newUrl;
                return $json;
                break;
            case 'html':
                $fp = mb_convert_encoding($fp, 'HTML-ENTITIES', 'UTF-8');
                $doc = new DomDocument();
                @$doc->loadHTML($fp);
                $xpath = new DOMXPath($doc);
                $query = '//*/meta[starts-with(@property, \'og:\')] | //*/meta[starts-with(@name, \'twitter:\')]';
                $metas = $xpath->query($query);
                $rmetas = [];
                foreach ($metas as $meta) {
                    $property          = $meta->getAttribute('property');
                    $name          = $meta->getAttribute('name');
                    $content           = $meta->getAttribute('content');
                    $property          = preg_replace('~og:~s', '', $property);
                    $name          = preg_replace('~twitter:~s', '', $name);
                    $rmetas[$property] = $content;
                    $rmetas[$name] = $content;
                }
                $title = $xpath->query('//title')->item(0)->textContent ?? $url;
                $description = $xpath->query('//meta[@name="description"]/@content')->item(0)->value ?? null;
                $favicon = $xpath->query('//head//link[@rel="icon"]/attribute::href | //head//link[@rel="shortcut icon"]/attribute::href')->item(0)->value ?? null;
                $json = [];
                $json['type'] = 'link';
                $json['url'] = '';
                $json['title'] = '';
                $json['description'] = '';
                isset($title) ? $json['html-title'] = trim(mbCutString($title, 70)) : '';
                isset($description) ? $json['html-description'] = $description : '';
                isset($rmetas['title']) ? $json['meta-title'] = $rmetas['title'] : '';
                isset($rmetas['description']) ? $json['meta-description'] =  $rmetas['description'] : '';

                if (isset($rmetas['image'])) $json['meta-image'] = $rmetas['image']; // && ExistsURL($rmetas['image'])

                isset($favicon) ? $json['favicon'] = $favicon : '';
                $json['timestamp'] = time();
                foreach ($json as &$object) {
                    $object = preg_replace('/www\./', '', $object);
                    if (preg_match('/^(?!(\.\.))(\W*)?\w*?\.\w*?\.\w*\/.*/', $object, $match)) {
                        $object = preg_replace('/^\/\/?/', '', $object);
                        $object = $protocol . $object;
                    } else {
                        $object = preg_replace('/^\/|^..\//', $protocol . $hostname['0'] . '/', $object);
                    }
                    if (preg_match('/^(?!(\w*\.))\w*\/.*$/', $object, $match)) $object = $protocol . $hostname['0'] . '/' . $object;
                }
                $json['title'] = $json['meta-title'] ?? $json['html-title'];
                $json['description'] = $json['meta-description'] ?? $json['html-description'] ?? null;
                $json['url'] = $protocol . $newUrl;
                if ($full === 1) {
                    return $json;
                } else {
                    $result['type'] = $json['type'];
                    $result['url'] = $json['url'];
                    $result['title'] = $json['title'] ?? null;
                    $result['description'] = $json['description'] ?? null;
                    $result['image'] = $json['meta-image'] ?? null;
                    $result['favicon'] = $json['favicon'] ?? null;
                    $result['timestamp'] = $json['timestamp'];
                    return $result;
                }
                break;
        }
    } /*else if (!$fp) {
        $getContent = "https://api.linkpreview.net?key=b658e12c3c4a3e5dd323c3161214f9b1&fields=url,title,description,image,icon&q=$newUrl";
        $settings = stream_context_create(['http' => ['timeout' => 20,]]);
        if (false !== ($result = @file_get_contents($getContent, false, $settings))) {
            $result = json_decode($result, true);
            $result['type'] = 'link';
            $result['timestamp'] = time();
            return $result;
        }
    } */
    return ['type' => 'link', 'timestamp' => time(), 'message' => $url . ' is not available!', 'error' =>  $info['http_code']];
}

function mbCutString(string $str, $length, $postfix = '...', $encoding = 'UTF-8')
{
    if (mb_strlen($str, $encoding) <= $length) {
        return $str;
    }
    $tmp = mb_substr($str, 0, $length, $encoding);
    return mb_substr($tmp, 0, mb_strripos($tmp, ' ', 0, $encoding), $encoding) . $postfix;
}

function mb_preg_match_all($ps_pattern, $ps_subject, &$pa_matches, $pn_flags = PREG_PATTERN_ORDER, $pn_offset = 0, $ps_encoding = NULL)
{
    // WARNING! - All this function does is to correct offsets, nothing else:
    //
    if (is_null($ps_encoding))
        $ps_encoding = mb_internal_encoding();

    $pn_offset = strlen(mb_substr($ps_subject, 0, $pn_offset, $ps_encoding));
    $ret = preg_match_all($ps_pattern, $ps_subject, $pa_matches, $pn_flags, $pn_offset);

    if ($ret && ($pn_flags & PREG_OFFSET_CAPTURE))
        foreach ($pa_matches as &$ha_match)
            foreach ($ha_match as &$ha_match)
                $ha_match[1] = mb_strlen(substr($ps_subject, 0, $ha_match[1]), $ps_encoding);
    //
    // (code is independent of PREG_PATTER_ORDER / PREG_SET_ORDER)

    return $ret;
}


function utf8_substr_replace(string $str, $repl, $start, $length = null)
{
    preg_match_all('/./us', $str, $ar);
    preg_match_all('/./us', $repl, $rar);

    $length = is_int($length) ? $length : mb_strlen($str);

    array_splice($ar[0], $start, $length, $rar[0]);

    return implode($ar[0]);
}
function str_replace_once(string $search, string $replace, string $text)
{
    $pos = mb_strpos($text, $search);
    return $pos !== false ? utf8_substr_replace($text, $replace, $pos, mb_strlen($search)) : $text;
}

/**
 * @param mixed $string The input string.
 * @param mixed $replacement The replacement string.
 * @param mixed $start If start is positive, the replacing will begin at the start'th offset into string.  If start is negative, the replacing will begin at the start'th character from the end of string.
 * @param mixed $length If given and is positive, it represents the length of the portion of string which is to be replaced. If it is negative, it represents the number of characters from the end of string at which to stop replacing. If it is not given, then it will default to strlen( string ); i.e. end the replacing at the end of string. Of course, if length is zero then this function will have the effect of inserting replacement into string at the given start offset.
 * @return string The result string is returned. If string is an array then array is returned.
 */
function mb_substr_replace(string $string, $replacement, $start, $length = NULL)
{
    if (is_array($string)) {
        $num = count($string);
        // $replacement
        $replacement = is_array($replacement) ? array_slice($replacement, 0, $num) : array_pad(array($replacement), $num, $replacement);
        // $start
        if (is_array($start)) {
            $start = array_slice($start, 0, $num);
            foreach ($start as $key => $value)
                $start[$key] = is_int($value) ? $value : 0;
        } else {
            $start = array_pad(array($start), $num, $start);
        }
        // $length
        if (!isset($length)) {
            $length = array_fill(0, $num, 0);
        } elseif (is_array($length)) {
            $length = array_slice($length, 0, $num);
            foreach ($length as $key => $value)
                $length[$key] = isset($value) ? (is_int($value) ? $value : $num) : 0;
        } else {
            $length = array_pad(array($length), $num, $length);
        }
        // Recursive call
        return array_map(__FUNCTION__, $string, $replacement, $start, $length);
    }
    preg_match_all('/./us', (string)$string, $smatches);
    preg_match_all('/./us', (string)$replacement, $rmatches);
    if ($length === NULL) $length = mb_strlen($string);
    array_splice($smatches[0], $start, $length, $rmatches[0]);
    return join($smatches[0]);
}
function monthes($month)
{
    $monthes = [
        null,
        'Января',
        'Февраля',
        'Марта',
        'Апреля',
        'Мая',
        'Июня',
        'Июля',
        'Августа',
        'Сентября',
        'Октября',
        'Ноября',
        'Декабря',
    ];
    if ($month === 'm') $m = date('m');
    elseif ($month == null) $m = date('m');
    else $m = $month;

    $month = $monthes[$m];
    return $month;
}
/*
type:
0 - other
1 - comment
2 - like
 */

function birthday($birthday)
{
    $birthday = date('d.m.Y', strtotime($birthday));
    $arr      = explode('.', $birthday);
    $date     = mktime(0, 0, 0, $arr[1], $arr[0], date('Y'));
    if ($date < time()) {
        $date = mktime(0, 0, 0, $arr[1], $arr[0], date('Y') + 1);
    }
    $birthday = intval(($date - time()) / 86400);
    return $birthday;
}
function downcounter($date)
{
    $check_time = strtotime($date) - time();
    if ($check_time <= 0) {
        return false;
    }
    $days    = floor($check_time / 86400);
    $hours   = floor(($check_time % 86400) / 3600);
    $minutes = floor(($check_time % 3600) / 60);
    $seconds = $check_time % 60;
    $str     = '';
    if ($days > 0) {
        $str .= declension($days, [
            'день',
            'дня',
            'дней',
        ]) . ' ';
    }
    if ($hours > 0) {
        $str .= declension($hours, [
            'час',
            'часа',
            'часов',
        ]) . ' ';
    }
    if ($minutes > 0) {
        $str .= declension($minutes, [
            'минута',
            'минуты',
            'минут',
        ]) . ' ';
    }
    if ($seconds > 0) {
        $str .= declension($seconds, [
            'секунда',
            'секунды',
            'секунд',
        ]);
    }
    return $str;
}
function declension($digit, $expr, $onlyword = false)
{
    if (!is_array($expr)) {
        $expr = array_filter(explode(' ', $expr));
    }
    if (empty($expr[2])) {
        $expr[2] = $expr[1];
    }
    $i = preg_replace('/[^0-9]+/s', '', $digit) % 100;
    if ($onlyword) {
        $digit = '';
    }
    if ($i >= 5 && $i <= 20) {
        $res = $digit . ' ' . $expr[2];
    } else {
        $i %= 10;
        if ($i == 1) {
            $res = $digit . ' ' . $expr[0];
        } elseif ($i >= 2 && $i <= 4) {
            $res = $digit . ' ' . $expr[1];
        } else {
            $res = $digit . ' ' . $expr[2];
        }
    }
    return trim($res);
}
function online($LastVisit)
{
    $today    = date('d.m.Y H:i');
    $now      = strtotime($today);
    $LastDate = strtotime($LastVisit);
    if (substr($today, 0, 8) == substr($LastVisit, 0, 8)) {
        if ($LastDate == $now || ($now - $LastDate) <= 180) {
            $online = 1;
            return boolval($online);
        } else {
            $online = 0;
            return boolval($online);
        }
    }
    $online = false;
    return boolval($online);
}
function timeName($time)
{
    $today = date('d.m.Y H:i');
    if (substr($today, 0, 10) == substr($time, 0, 10)) {
        $time = 'Сегодня' . substr($time, 11);
    } elseif (substr(date('d.m.Y H:i', strtotime('-1 days')), 0, 8) == substr($time, 0, 8)) {
        $time = 'Вчера' . substr($time, 11);
    } elseif (substr(date('d.m.Y H:i', strtotime('-2 days')), 0, 8) == substr($time, 0, 8)) {
        $time = 'Позавчера' . substr($time, 11);
    }
    return $time;
}
function onlineIndicator($lastTime)
{
    if (online($lastTime)) {
        return ' userOnline';
    } else {
        return '';
    }
}

function strpos_all(string $haystack, string $needle)
{
    $offset = 0;
    $allpos = [];
    while (($pos = mb_strpos($haystack, $needle, $offset)) !== FALSE) {
        $offset   = $pos + 1;
        $allpos[] = $pos;
    }
    return $allpos;
}


function tagsParser($item, $offset, $length, $entry)
{
    switch ($item) { //$item['1']['0']
        case 'b':
            $style = 'bold';
            $type = 'styled';
            break;
        case 'u':
            $style = 'underline';
            $type = 'styled';
            break;
        case 'i':
            $style = 'italic';
            $type = 'styled';
            break;
        case 's':
        case 'strike':
            $style = 'strike';
            $type = 'styled';
            break;
        case 'img':
            $type = 'image';
            break;
        case 'url':
        case 'a':
            $type = 'link';
            break;
        case 'sticker':
            $type = 'sticker';
            break;
    }
    $arr = ['offset' => $offset, 'length' => $length, 'type' => $type];


    switch ($type) {
        case 'styled':
            $arr['style'] = $style;
            $result = $arr;
            break;
        case 'image':
            $arr['url'] = $entry;
            $result = $arr;
            break;
        case 'link':
            require_once $_SERVER['DOCUMENT_ROOT'] . '/v2/func.php';
            $arr = GetLinkPreview($entry); //GetLinkPreview($item['2']['0']);
            $arr['offset'] = $offset;
            $arr['length'] = $length;
            $result = $arr;
            //$result[] = ['type' => 'link', 'url' => $item['0']['0'], 'title' => $title, 'favicon' => $favicon, 'timestamp' => '0', 'offset' => $offset, 'length' => mb_strlen($item['0']['0'])];
            break;
        case 'sticker':
            preg_match_all('~(\w*)\s(\d*)~', $entry, $matches);
            $arr['pack'] = $matches[1][0];
            $arr['sticker'] = $matches[2][0];
            $arr['url'] = getenv('HOST_BRB_CONTENT') . "/stickers/{$arr['pack']}/{$arr['sticker']}.png";
            $result = $arr;
            break;
    }
    return $result;
}
function tags(string $text, string $exp, string $exp2, string $exp3)
{
    $attachments = [];
    $result = [];
    $string = $text;
    $text = tagsRegexMaxon($text, $attachments, $exp, $exp2, $exp3);

    while (preg_match_all($exp2, $string, $dd)) {
        $string = preg_replace($exp2, '$2', $string);
    }

    foreach ($attachments as $item) {
        $result[] = tagsParser($item['tag'], $item['offset'], $item['length'], $item['entry']);
    }
    return ['text' => $string ?? null, 'result' => $result ?? null];
}

function removeTags(string $string, string $exp2)
{
    return preg_replace($exp2, '$2', $string);
}
function tagsRegexMaxon(string $substring, array &$attachments, string $exp, string $exp2, string $exp3, int $offset = 0)
{
    mb_preg_match_all(
        $exp,
        $substring,
        $matches,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    $offset_local = $offset;
    foreach ($matches as $match) {
        $entry = removeTags($match[2][0], $exp2);
        $attachments[] =
            [
                'entry' => $entry,
                'tag' => $match[1][0],
                'offset' => $match[0][1] + $offset_local,
                'length' => mb_strlen($match[2][0])
            ];
        // If string has inner tags
        if (preg_match($exp3, $match[2][0])) {
            tagsRegexMaxon($match[2][0], $attachments, $exp, $exp2, $exp3, $match[0][1] + $offset_local);
        } else {
            $offset_local -= (mb_strlen($match[0][0]) - mb_strlen($entry));
            continue;
        }
        $offset_local -= (mb_strlen($match[0][0]) - mb_strlen($entry));
    }
}
/*
        function tagsRegex(string $text, string $exp, string $exp2)
        {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/v2/func.php';

            $result = [];
            $string = $text;
            while (preg_match_all($exp2, $string, $dd)) {
                $string = preg_replace($exp2, '$2', $text);
                //exit($string);
            }
            while (preg_match_all($exp, $text, $out, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {

                $text = preg_replace($exp, '$2', $text);

                //$offs = 0;
                //exit($text);
                //$text = $out['0']['2']['0'];
                //print_r($out);
                //$ai = 0;
                //print_r($out);
                //exit();
                foreach ($out as $item) {
                    //print_r($item);
                    //print_r($string."\n");
                    //$offset = ($item['0']['1'] -  $offs) <= 0 ? 0 : ($item['0']['1'] - $offs);
                    if (isset($item['1']['0'])) {
                        $in = false;
                        while (preg_match($exp, $item['2']['0'], $out2)) {
                            $in = true;
                            $item['2']['0'] = preg_replace($exp, '$2', $item['2']['0']);
                        }
                        //exit(print_r($item));
                        //print_r($string);
                        //print_r($item['2']['0']);

                        //$length = mb_strlen($item['0']['0']) - (3 + 4);
                        //$offs += 3 + 4; //old version


                        $length = mb_strlen($item['2']['0']);
                        $offset = mb_strpos($string, $item['2']['0']);
                        if (!$in) {
                            $sub_str = str_repeat('🜏', $length);
                            //$string = str_replace_once($item['2']['0'], $sub_str, $string);
                            $string = mb_substr_replace($string, $sub_str, $offset, $length);
                        }
                        //echo "$string $offset \n";
                        //print_r($offset.'.');
                        //print_r($item['2']['0']."\n");
                        //print_r("$string\n");


                        //echo $offset . ' ' . $item['2']['0'];
                        //$offs += 5 + (mb_strlen($item['1']['0']) * 2);
                        //echo "$ai. ".$item['2']['0']."\n";
                        //$ai +=1;

                        //exit();



                        $result[] = tagsParser($item, $offset, $length);
                    }
                }
            }


            return ['text' => $text ?? null, 'result' => $result ?? null];
        }
*/
//require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bb.php';
function fcknParse(string $text, string $parseMode = 'text')
{
    $text = strip_tags($text, ['<br>', '<b>', '<u>', '<i>', '<s>', '<strike>', '<a>', '<img>', '<sticker>']);
    $result = '';
    switch ($parseMode) {
        case 'html':
            $expBR = '~\<br\>+~';
            $expIMG = '~\<img.+src\="(.*?)"\/?>~';
            $expURL = '~<a\s+(?:[^>]*?\s+)?href="(.*?)".*<\/a>~';
            $expSTK = '~\<sticker.*\="(\w*)">(\d*)</sticker>~';
            $text = preg_replace($expBR, "\n", $text);
            $text = preg_replace($expIMG, '<img>$1</img>', $text);
            $text = preg_replace($expURL, '<a>$1</a>', $text);
            $text = preg_replace($expSTK, '<sticker>$1 $2</sticker>', $text);
            $exp = '~\<([a-zA-Z]*)\>(.*?)\</\1\>~';
            $exp2 = '~\<\/?(\w+)[^\]*]~';
            $exp3 = '~\<[a-zA-Z]*\>~';
            $arr = tags($text, $exp, $exp2, $exp3);
            $text = $arr['text'] ?? $text;
            $result = $arr['result'] ?? null;
            break;
        case 'bb':
            $text = htmlspecialchars($text);
            $exp = '~\[([a-zA-Z]*)\](.*?)\[/\1\]~'; //'~\[([a-zA-Z]*).?(\d)?\](.*?)\[/\1\]~';
            $exp2 = '~\[\/?(\w+)[^\]]*]~';
            $exp3 = '~\[[a-zA-Z]*\]~';
            $arr = tags($text, $exp, $exp2, $exp3);
            $text = $arr['text'] ?? $text;
            $result = $arr['result'] ?? null;
            break;
        case 'md':
            $text = htmlspecialchars($text);
            $exp = '~\[([a-zA-Z]*)\](.*?)\[/\1\]~';
            $exp2 = '~\[\/?(\w+)[^\]]*]~';
            $exp3 = '~\[[a-zA-Z]*\]~';
            $expB = '~\*\*(.*)\*\*~';
            $expI = '~\*(.+?)\*~';
            $expU = '~_(.+?)_~';
            $expS = '!\~\~(.*)\~\~!';
            $expSTK = '~\$\[(\w*)\]\((\d*)\)~';
            $expIMG = '~\!\[(.*?)\]\((.*?)\)~';
            $expURL = '~\[(.*?)\]\((.*?)\)~'; //'~(?:__|([*#]))|\[(.*?)\]\((.*?)\)~'
            $text = preg_replace($expB, '[b]$1[/b]', $text);
            $text = preg_replace($expI, '[i]$1[/i]', $text);
            $text = preg_replace($expU, '[u]$1[/u]', $text);
            $text = preg_replace($expS, '[s]$1[/s]', $text);
            $text = preg_replace($expSTK, '[sticker]$1 $2[/sticker]', $text);
            $text = preg_replace($expIMG, '[img]$2[/img]', $text);
            $text = preg_replace($expURL, '[a]$2[/a]', $text);
            $arr = tags($text, $exp, $exp2, $exp3);
            $text = $arr['text'] ?? $text;
            $result = $arr['result'] ?? null;
            break;
        default:
            $text = htmlspecialchars($text);
            //$exp = '~\https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,}~';
            break;
    }
    return [$text, $result];
}
function avatare($img)
{
    //$img   = $_SERVER['DOCUMENT_ROOT'] . '/avatars/' . $avatar;
    $newName = time() . '.png';
    $image = new Imagick($img);
    $image->setImageFormat('png');
    $file = getenv('DIR_BRB_CONTENT') . "/avatars/$newName";
    file_put_contents($file, $image, FILE_APPEND);
    $image->adaptiveResizeImage(128, 128, true);
    $file = getenv('DIR_BRB_CONTENT') . "/avatars/min/$newName";
    file_put_contents($file, $image, FILE_APPEND);
    $image->adaptiveResizeImage(256, 256, true);
    $file = getenv('DIR_BRB_CONTENT') . "/avatars/mid/$newName";
    file_put_contents($file, $image, FILE_APPEND);
    unlink($img);
    return $newName;
}
