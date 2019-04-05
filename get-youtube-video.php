<?php

$channels = array(
    'ctitv' => 'https://www.youtube.com/channel/UCpu3bemTQwAU8PqM4kJdoEQ/videos',
    'set' => 'https://www.youtube.com/user/setnews159/videos',
);

$crawl_video = function($channel, $date, $video_id, $title){
    $dir = __DIR__ . '/youtubes/' . $channel;
    $target = $dir . '/' . $date . '/' . $video_id . '.ts';
    if (!file_exists($dir  . '/' . $date)) {
        mkdir($dir . '/' . $date);
    }

    if (file_exists($target)) {
        return;
    }
    $url = 'https://www.youtube.com/watch?v=' . $video_id;
    error_log('loading ' . $video_id . ':' . $title );
    system(sprintf("bash --init-file %s -c %s",
        __DIR__ . '/streamlink/bin/activate',
        escapeshellarg(sprintf("%s --quiet --force %s 360p -o %s",
        __DIR__ . '/streamlink/bin/streamlink',
        escapeshellarg($url),
        $target))
    ));
    file_put_contents($dir . '/' . $date . '/list.csv', implode(',', array($video_id, $title)) . "\n", FILE_APPEND);
};

// 先讀舊記錄
$youtube_videos = array();

if (file_exists('youtube.csv')) {
    $fp = fopen('youtube.csv', 'r');
    $columns = fgetcsv($fp);
    while ($rows = fgetcsv($fp)) {
        $values = array_combine($columns, $rows);
        $youtube_videos[$values['影片ID']] = $values;
    }
    fclose($fp);
}

$output = fopen('youtube.csv', 'w');
$columns = array('頻道', '日期', '影片ID', '標題', '影片長度', '觀看人數', '網址');
fputcsv($output, $columns);
foreach ($channels as $channel => $url) {
    $dir = __DIR__ . '/youtubes/' . $channel;
    if (!file_exists($dir)) {
        mkdir($dir);
    }

    $content = file_get_contents($url);
    $content = str_replace('<head>', '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">', $content);
    for ($page  = 0; $page < 30; $page ++) {
        $doc = new DOMDocument;

        $doc->loadHTML($content);
        foreach ($doc->getElementsByTagName('a') as $a_dom) {
            if (preg_match('#^([0-9]+)中天新聞　*(.*)#u', $a_dom->nodeValue, $matches)) {
                $date = $matches[1];
                $title = $matches[2];
            } elseif (preg_match('#(.*[^0-9])([0-9]+)｜三立新聞台$#u', $a_dom->nodeValue, $matches)) {
                $date = $matches[2];
                $title = $matches[1];
            } else {
                continue;
            }


            if (!preg_match('#/watch\?v=([^&]*)$#', $a_dom->getAttribute('href'), $matches)) {
                continue;
            }
            $duration = '';
            $view = '';
            foreach ($a_dom->parentNode->parentNode->getElementsByTagName('span') as $span_dom) {
                if ($span_dom->getAttribute('class') == 'accessible-description') {
                    $duration = $span_dom->nodeValue;
                    break;
                }
            }
            foreach ($a_dom->parentNode->parentNode->parentNode->getElementsByTagName('ul') as $ul_dom) {
                if ($ul_dom->getAttribute('class') == 'yt-lockup-meta-info') {
                    $view = $ul_dom->getElementsByTagName('li')->item(0)->nodeValue;
                    break;
                }
            }
            $video_id = $matches[1];
            if (preg_match('#^- Duration: (\d+) seconds?.$#', trim($duration), $matches)) {
                $duration = $matches[1];
            } else if (preg_match('#^- Duration: (\d+) minutes?, (\d+) seconds?.$#', trim($duration), $matches)) {
                $duration = $matches[1] * 60 + $matches[2];
            } else if (preg_match('#^- Duration: (\d+) minutes?.$#', trim($duration), $matches)) {
                $duration = $matches[1] * 60;
            } else if (strpos($duration, 'hour')) {
                continue;
            } else {
                var_dump($duration);
                throw new Exception($duration);
            }
            if ($duration > 1000) {
                continue;
            }

            $crawl_video($channel, $date, $video_id, $title);

            $view = str_replace(',', '', trim($view));
            $view = str_replace(' views', '', trim($view));
            $youtube_videos[$video_id] = array_combine(
                $columns,
                array($channel, $date, $video_id, $title, $duration, $view, 'https://youtu.be/' . $video_id)
            );
        }

        $more_href = null;
        foreach ($doc->getElementsByTagName('button') as $button_dom) {
            if ($more_href = $button_dom->getAttribute('data-uix-load-more-href')) {
                break;
            }
        }
        if (!($more_href)) {
            print_r($obj);
            print_r($content);
            break;
        }
        error_log('more ' . $more_href);
        $content = file_get_contents('https://www.youtube.com' . $more_href);
        $obj = json_decode($content);
        if (!$obj->content_html) {
            print_r($obj);
            exit;
        }

        $content = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'
            . $obj->content_html
            . $obj->load_more_widget_html
            . '</body></html>';
    }
}

foreach ($youtube_videos as $id => $values) {
    fputcsv($output, array_values($values));
}
fclose($output);
