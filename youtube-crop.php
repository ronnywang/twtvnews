<?php

// 這隻 script 處理幾個動作
// 1. 將 videos/ 下的影片一秒截一張圖存到 screens/ 下
// 2. 將 screens/ 下的完整截圖取出主標題的部份 存到 crops/ 下
// 3. 將主標題部份每秒做比對，成果存在 diffs/ 下
// 成果可見 https://ronnywang.github.io/twnews-demo/
include("config.php");

foreach ($channels as $channel_id => $channel_data) {
    $youtube_dir = __DIR__ . '/youtubes/' . $channel_id;
    $ytcrop_dir = __DIR__ . '/ytcrops/' . $channel_id;
    if (!file_exists($ytcrop_dir)) {
        mkdir($ytcrop_dir);
    }
    foreach (glob($youtube_dir .'/*') as $youtube_date_dir) {
        $date = basename($youtube_date_dir);
        $fp = fopen($youtube_date_dir . '/list.csv', 'r');
        while ($line = fgets($fp)) {
            list($id, $title) = explode(',', trim($line), 2);
            $ts_file = $youtube_date_dir . '/' . $id . '.ts';
            $ytcrop_date_dir = $ytcrop_dir . '/' . $date;
            $crop_dir = $ytcrop_date_dir . '/crop-' . $id;

            if (file_exists($crop_dir)) {
                continue;
            }
            if (!file_exists($ytcrop_date_dir)) {
                mkdir($ytcrop_date_dir);
            }

            $screen_dir = 'tmp-screens';
            system("rm -rf $screen_dir");
            mkdir($screen_dir);

            system(sprintf("ffmpeg -i %s -vf scale=426:240,fps=1 %s", escapeshellarg($ts_file), escapeshellarg($screen_dir . '/%010d.png')));

            $screen_files = glob($screen_dir . '/*.png');
            sort($screen_files);
            mkdir($crop_dir);
            foreach ($screen_files as $screen_file) {
                $screen_name = basename($screen_file);
                if (!file_exists($crop_dir . '/' . $screen_name)) {
                    $cmd = (sprintf("convert %s -crop %s %s", $screen_file, $channel_data['crop'], $crop_dir . '/' . $screen_name));
                    error_log($cmd);
                    system($cmd);
                }
            }
            system("rm -rf " . $screen_dir);

            // 處理字幕截圖
            $prev_file = null;

            $crop_files = glob($crop_dir . '/*.png');
            sort($crop_files);
            $output = fopen($crop_dir . '/diff.csv', 'w');
            foreach ($crop_files as $crop_file) {
                $crop_name = basename($crop_file);
                if (is_null($prev_file)) {
                    $prev_file = $crop_file;
                    continue;
                }

                $crop1 = $crop_dir . '/' . $crop_name;
                $crop2 = $crop_dir . '/' . basename($prev_file);
                $cmd = (sprintf("compare -metric AE -fuzz %s %s %s null: 2>&1", '10%', $crop1, $crop2));
                $ret = trim(`$cmd`);
                fputcsv($output, array($channel_id, basename($ts_file), $crop_name, basename($prev_file), $ret));

                $prev_file = $crop_file;
            }
            fclose($output);
        }
    }
}
