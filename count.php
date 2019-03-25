<?php

// 這隻 script 處理幾個動作
// 1. 將 videos/ 下的影片一秒截一張圖存到 screens/ 下
// 2. 將 screens/ 下的完整截圖取出主標題的部份 存到 crops/ 下
// 3. 將主標題部份每秒做比對，成果存在 diffs/ 下
// 成果可見 https://ronnywang.github.io/twnews-demo/
$channels = array(
    'ctitv' => array('crop' => '325x25+100+190'),
    'ebc' => array('crop' => '426x24+0+192'),
    'formosa' => array('crop' => '328x24+98+192'),
    'set' => array('crop' => '334x28+92+188'),
    'tvbs' => array('crop' => '292x23+106+191'),
);

$output = fopen('php://output', 'w');

for ($d = strtotime('2019/3/14 0:0:0'); $d < time(); $d += 86400) {
    foreach (array(11, 12, 17, 18, 19) as $hour) {
        foreach ($channels as $channel_id => $channel_data) {
            $ts_file = __DIR__ . "/videos/{$channel_id}/" . date('Ymd', $d) . sprintf("%02d", $hour) . '.ts';
            if (!file_exists($ts_file)) {
                continue;
            }
            $diffs_file = __DIR__  . "/diffs/{$channel_id}/" . date('Ymd', $d) . sprintf("%02d", $hour) . '.csv';
            if (file_exists($diffs_file)) {
                continue;
            }
            error_log($ts_file);
            if (!file_exists(__DIR__ . "/crops/{$channel_id}")) {
                mkdir(__DIR__ . "/crops/{$channel_id}");
            }
            if (!file_exists(__DIR__ . "/screens/{$channel_id}")) {
                mkdir(__DIR__ . "/screens/{$channel_id}");
            }
            if (!file_exists(__DIR__ . "/diffs/{$channel_id}")) {
                mkdir(__DIR__ . "/diffs/{$channel_id}");
            }


            // 先每秒截一張圖
            $crop_dir = __DIR__ . "/crops/{$channel_id}/" . basename($ts_file) . '/';

            if (!file_exists($crop_dir)) {
                $screen_dir = __DIR__ . "/screens/{$channel_id}/" . basename($ts_file) . '/';
                mkdir($screen_dir);
                system(sprintf("ffmpeg -i %s -vf fps=1 %s", escapeshellarg($ts_file), escapeshellarg($screen_dir . '/%010d.png')));
                $screen_files = glob($screen_dir . '/*');
                sort($screen_files);
                mkdir($crop_dir);
                foreach ($screen_files as $screen_file) {
                    $screen_name = basename($screen_file);
                    if (!file_exists($crop_dir . '/' . $screen_name)) {
                        $cmd = (sprintf("convert %s -crop %s %s", $screen_file, $channel_data['crop'], $crop_dir . '/' . $screen_name));
                        system($cmd);
                    }

                }

                system("rm -rf $screen_dir");
            }

            // 處理字幕截圖
            $prev_file = null;

            $crop_files = glob($crop_dir . '/*');
            sort($crop_files);
            $output = fopen($diffs_file, 'w');
            foreach ($crop_files as $crop_file) {
                $crop_name = basename($crop_file);
                if (is_null($prev_file)) {
                    $prev_file = $crop_file;
                    continue;
                }

                $crop1 = $crop_dir . '/' . $crop_name;
                $crop2 = $crop_dir . '/' . basename($prev_file);
                $cmd = (sprintf("compare -metric AE -fuzz %s %s %s null: 2>&1", '20%', $crop1, $crop2));
                $ret = trim(`$cmd`);
                fputcsv($output, array($channel_id, basename($ts_file), $crop_name, basename($prev_file), $ret));

                $prev_file = $crop_file;
            }
            fclose($output);
        }
    }
}
