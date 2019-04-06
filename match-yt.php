<?php


include("config.php");

if (!file_exists('match-result')) {
    mkdir('match-result');
}

foreach ($channels as $channel => $channel_data) {
    if (!file_exists("match-result/{$channel}")) {
        mkdir("match-result/{$channel}");
    }
    $merge_files = new StdClass;

    for ($t_date = strtotime('-1 day'); true; $t_date -= 86400) {
        $date = date('Ymd', $t_date);
        $yesterday = date('Ymd', $t_date - 86400);
        foreach (array($date, $yesterday) as $d) {
            if (!property_exists($merge_files, $d)) {
                if (!file_exists("ytcropmerges/{$channel}/{$d}.csv")) {
                    break 2;
                }
                $merge_files->{$d} = array();
                $fp = fopen("ytcropmerges/{$channel}/{$d}.csv", 'r');
                while ($rows = fgetcsv($fp)) {
                    list($file, $video_id, $video_start) = $rows;
                    $merge_files->{$d}[] = array($file, $video_id, $video_start);
                }
            }
        }

        foreach (glob("videos/{$channel}/{$date}*.ts") as $ts_file) {
            $result_file = "match-result/{$channel}/" . basename($ts_file) . '.csv';
            if (file_exists($result_file)) {
                continue;
            }
            $result_tmp_file = 'tmp-result.csv';

            $output = fopen($result_tmp_file, 'w');
            error_log("檢查 $ts_file");

            $screen_dir = "screens/{$channel}/" . basename($ts_file);
            if (!file_exists($screen_dir)) {
                mkdir($screen_dir);
                error_log("取出 $ts_file 每秒截圖");
                system(sprintf("ffmpeg -i %s -vf fps=1 %s", escapeshellarg($ts_file), escapeshellarg($screen_dir . '/%010d.png')));
            }

            unlink('tmp-prev-crop.png');
            foreach (glob($screen_dir . "/*.png") as $screen_file) {
                error_log($screen_file);
                $cmd = (sprintf("convert %s -crop %s %s", $screen_file, $channel_data['crop'], 'tmp-crop.png'));
                system($cmd);

                if (file_exists('tmp-prev-crop.png')) {
                    // 先跟前一秒比，如果很接近就不用去找了
                    $cmd = (sprintf("compare -metric AE -fuzz %s %s %s null: 2>&1", '10%', 'tmp-crop.png', 'tmp-prev-crop.png'));
                    $ret = trim(`$cmd`);
                    if ($ret < 1000) {
                        fputcsv($output, array($screen_file, 'same', ''));
                        continue;
                    }
                }

                $notfound = true;
                foreach (array($date, $yesterday) as $d) {
                    $merge_file = "ytcropmerges/{$channel}/{$d}.png";
                    $cmd = (sprintf("compare -metric RMSE -subimage-search %s %s null: 2>&1", $merge_file, 'tmp-crop.png'));
                    error_log($cmd);
                    $ret = `$cmd`;
                    if (strpos($ret, 'images too dissimilar')) {
                        continue;
                    } elseif (preg_match('#([0-9.]+) \(([0-9.]+)\) @ 0,([0-9]+)#', $ret, $matches)) {
                        $pos = $matches[3] / 24;
                        if ($matches[2] > 0.2) {
                            continue;
                        }
                        fputcsv($output, array($screen_file, $merge_files->{$d}[$pos][1], $merge_files->{$d}[$pos][2], $matches[1], $matches[2]));
                        $notfound = false;
                        break;
                    } 
                }
                if ($notfound) {
                    fputcsv($output, array($screen_file, '', ''));
                }
                rename('tmp-crop.png', 'tmp-prev-crop.png');
            }
            system("rm -rf $screen_dir");
            fclose($output);
            rename($result_tmp_file, $result_file);
        }
    }
}
