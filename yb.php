<?php

require_once __DIR__ . '/yb_helpers.php';

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    echo 'config.php does not exists. Use config.dist.php to create your.' . PHP_EOL;
    return;
}

$config = include __DIR__ . '/config.php';
if (!isset($config['channels']) || empty($config['channels'])) {
    echo 'Channel list is required.' . PHP_EOL;
    return;
}

if (!file_exists($config['storageDir'])) {
    mkdir($config['storageDir']);
}

foreach ($config['channels'] as $category => $chanel) {
    $url = null;
    $nextPageToken = null;
    while (true) {
        if (!$url) {
            $url = 'https://www.googleapis.com/youtube/v3/search?key=' . $config['youtubeApiKey'] . '&channelId=' . $chanel . '&part=snippet,id&order=date&maxResults=50';
        } else {
            $url = 'https://www.googleapis.com/youtube/v3/search?key=' . $config['youtubeApiKey'] . '&channelId=' . $chanel . '&part=snippet,id&order=date&maxResults=50&pageToken=' . $nextPageToken;
        }

        $response = file_get_contents($url);
        if (empty($response)) {
            echo 'Wrong api key and/or invalid channel. Exiting...' . PHP_EOL;
            exit;
        }
        $search = json_decode($response, true);
        foreach ($search['items'] as $item) {
            if (!isset($item['id']['videoId'])) {
                echo 'Not a video' . PHP_EOL;
                continue;
            }
            $id = $item['id']['videoId'];
            $date = strtotime($item['snippet']['publishedAt']);
            importyt($config, $category, $id, $date);
        }

        if (!isset($search['nextPageToken'])) {
            echo 'No more' . PHP_EOL;
            break;
        }

        $nextPageToken = $search['nextPageToken'];

        sleep(1);
    }
}

function importyt(array $config, string $category, string $id, int $date)
{
    $vars = [];
    parse_str(urldecode(file_get_contents("https://youtube.com/get_video_info?video_id=$id")), $vars);

    $data = json_decode($vars['player_response'], true);

    $title = isset($data['videoDetails']['title']) ? $data['videoDetails']['title'] : null;
    $url = null;
    $backup = null;
    $backup2 = null;
    foreach ($data['streamingData']['formats'] as $format) {
        if ($format['height'] == $config['targetQuality']) {
            $url = $format;
        }
        if ($format['height'] <= 360) {
            $backup = $format;
        }
        if ($format['height'] <= 480) {
            $backup2 = $format;
        }
    }

    $format = $url;
    if ($format) {
        $format = $backup;
    }
    if ($format) {
        $format = $backup2;
    }

    $seeurl = isset($format['url']) ? $format['url'] : false;
    $mime = isset($format['mimeType']) ? $format['mimeType'] : false;

    if (!$seeurl) {
        echo 'no url skipping ' . $id . PHP_EOL;
        file_put_contents(__DIR__ . '/missed.txt', date('Y-m-d H:i:s') . ' ' . $id . ' ' . $title . PHP_EOL, FILE_APPEND | LOCK_EX);
    } else {
        echo 'Working on ' . date('Y-m-d', $date) . ' ' . $title . PHP_EOL;
        $dir = $config['storageDir'] . '/' . $category . '/';
        if (!file_exists($dir)) {
            mkdir($dir);
        }
        $type = mime2ext(explode(';', $mime)[0]);

        $file = $dir . sanitize_file_name($title) . '.' . $type;
        $fileNoTmp = $file;
        $file = $file . '.tmp';

        if (file_exists($fileNoTmp)) {
            echo 'File alredy exists ' . $fileNoTmp . PHP_EOL;
        } else {
            @unlink($file);

            $fp = fopen($file, 'w+');
            $ch = curl_init(urldecode($seeurl));
            curl_setopt($ch, CURLOPT_TIMEOUT, 50);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);

            rename($file, $fileNoTmp);
            touch($fileNoTmp, $date);

            echo 'Done' . PHP_EOL;
        }
    }
}
