<?php

// 需要先用 python virtualenv 建立有 streamlink 環境
// 作法：
//     > virtualenv streamlink
//     > source streamlink/bin/activate
//     > pip install streamlink

// 把這個 script 放 crontab 每小時執行一次
// 0 * * * * username php /foo/bar/crawl-video.php

$channels = array(
    'ctitv' => 'https://www.youtube.com/watch?v=wUPPkSANpyo',
    'ebc' => 'https://www.youtube.com/watch?v=dxpWqjvEKaM',
    'formosa' => 'https://www.youtube.com/watch?v=XxJKnDLYZz4',
    'set' => 'https://www.youtube.com/watch?v=4ZVUmEUFwaY',
    'tvbs' => 'https://www.youtube.com/watch?v=Hu1FkdAOws0',
);

if (!in_array(date('H'), array('11', '12', '17', '18', '19'))) {
    if (date('H') == 14 or date('H') == 21) {
        chdir(__DIR__);
        system("php count-fullscreen.php");
    }
    if (date('H') == 20) {
        chdir(__DIR__);
        system("php get-youtube-video.php");
        system("php youtube-crop.php");
    }
    exit;
}
foreach ($channels as $id => $url) {
    $pid = pcntl_fork();

    if ($pid) {
        continue;
    }
    system(sprintf("bash --init-file %s -c %s",
        __DIR__ . '/streamlink/bin/activate',
        escapeshellarg(sprintf("%s --quiet --force %s 240p --hls-duration 01:05:00 -o %s",
        __DIR__ . '/streamlink/bin/streamlink',
        escapeshellarg($url),
        __DIR__ . '/videos/' . $id . '/' . date('YmdH') . '.ts'))
    ));
    exit;
}

chdir(__DIR__);
system("php get-youtube-video.php");
system("php youtube-crop.php");
