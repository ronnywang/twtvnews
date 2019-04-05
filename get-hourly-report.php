<?php

$channels = array(
    'ctitv' => array('crop' => '325x24+100+193'),
    'ebc' => array('crop' => '426x24+0+192'),
    'formosa' => array('crop' => '328x24+98+192'),
    'set' => array('crop' => '334x27+92+188'),
    'tvbs' => array('crop' => '292x23+106+191'),
);
$with_screen = true;

$titles = array();
$fp = fopen('youtube.csv', 'r');
$columns = fgetcsv($fp);
while ($rows = fgetcsv($fp)) {
    $values = array_combine($columns, $rows);
    $titles[$values['影片ID']] = $values;
}
fclose($fp);

foreach (glob("youtubes/*/*/list.csv") as $list_file) {
    $fp = fopen($list_file, 'r');
    while ($rows = fgetcsv($fp)) {
        list($id, $title) = $rows;
        if (!array_key_exists($id, $titles)) {
            $titles[$id] = array(
                '標題' => $title,
                '日期' => explode('/', $list_file)[2],
            );
        }
    }
}

if (!file_exists('reports')) {
    mkdir('reports');
}
foreach ($channels as $channel => $channel_data) {
    if (!file_exists("reports/{$channel}")) {
        mkdir("reports/{$channel}");
    }

    foreach (glob("diffs/{$channel}/*.ts.csv") as $diff_file) {
        $yyyymmdd = explode('.', basename($diff_file))[0];
        if ($_SERVER['argv'][1] and $_SERVER['argv'][1] != "{$channel}/{$yyyymmdd}") {
            continue;
        }

        $match_file = "match-result/{$channel}/{$yyyymmdd}.ts.csv";
        if (!file_exists($match_file)) {
            continue;
        }
        $target = "reports/{$channel}/{$yyyymmdd}";
        if (file_exists($target . '/data.json')) {
            continue;
        }
        $screen_dir = "screens/{$channel}/{$yyyymmdd}.ts";
        if ($with_screen and !file_exists($screen_dir . '/0000000001.png')) {
            $ts_file = "videos/{$channel}/{$yyyymmdd}.ts";
            mkdir($screen_dir);
            error_log("取出 $ts_file 每秒截圖");
            system(sprintf("ffmpeg -i %s -vf fps=1 %s", escapeshellarg($ts_file), escapeshellarg($screen_dir . '/%010d.png')));
        }
        mkdir($target);

        $fp = fopen($diff_file, 'r');
        $diff = array();
        while ($row = fgetcsv($fp)) {
            $diff[$row[1].'-'.$row[2]] = $row[3];
        }
        fclose($fp);

        $fp = fopen($match_file, 'r');
        $prev_id = '';
        $files_yt_id = array();
        $files = array();
        while ($row = fgetcsv($fp)) {
            list($file, $id, $second) = $row;
            if (array_key_existS(4, $row)) {
                $score = $row[4];
            } else {
                $score = 0;
            }

            if ($id == 'same') {
                $id = $prev_id;
            } else {
                if ($id and $score > 0.2) {
                    $id = '';
                }
                $prev_id = $id;
            }
            $files_yt_id[basename($file)] = $id;
            $files[] = $file;
        }

        $get_time_from_filename = function($f){
            $n = explode('.', basename($f))[0];
            return sprintf("%02d:%02d", $n / 60, $n % 60);
        };

        $draw_group = function($group, $idx) use ($get_time_from_filename, $target, $titles, $diff, $files_yt_id, $with_screen, $channel_data){
            $record = array();
            $record['start'] = explode('.', basename($group->minute_files[0]))[0];
            $record['end'] = explode('.', basename($group->minute_files[count($group->minute_files) - 1]))[0];
            $record['youtube-id'] = $group->match_yt;
            $record['youtube-date'] = $group->match_yt ? $titles[$group->match_yt]['日期'] : '';
            $record['youtube-title'] = $group->match_yt ? $titles[$group->match_yt]['標題'] : '';

            // 計算 crop 區域最常出現的六個標題
            $crop_group = array();
            $prev_file = null;
            foreach ($group->minute_files as $file) {
                if (is_null($prev_file)) {
                    $crop_group[$file] = 1;
                    $prev_file = $file;
                } elseif ($diff[basename($file) . '-crop'] < 1000) {
                    $crop_group[$prev_file] ++;
                } else {
                    $crop_group[$file] = 1;
                    $prev_file = $file;
                }
            }

            if (count($group->minute_files) > 6) {
                $chunked_minute_files = array();
                for ($p = 0; $p < 6; $p ++) {
                    $pos = min(
                        floor($p * count($group->minute_files) / 5),
                        count($group->minute_files) - 1
                    );
                    $chunked_minute_files[] = $group->minute_files[$pos];
                }
            } else {
                $chunked_minute_files = $group->minute_files;
            }
            $cidx = 0;

            if (true) {
            //foreach (array_chunk($group->minute_files, 120) as $cidx => $chunked_minute_files) {
                $img_file = "tile-{$idx}-{$cidx}.png";
                $target_file = $target . "/" . $img_file;
                $crop_file = "crop-{$idx}-{$cidx}.png";
                $target_crop_file = "{$target}/{$crop_file}";
                $record['img-file'] = $img_file;
                $record['crop-file'] = $crop_file;

                $chunked_minute_files = array_map(function($f) use ($diff, $files_yt_id) {
                    $bf = basename($f);
                    if (array_key_exists($bf, $files_yt_id)) {
                        $t = $files_yt_id[$bf];
                    } else {
                        $t = '';
                    }
                    if ($diff[$bf . '-full']) {
                        $n = intval(explode('.', $bf)[0]);
                        return "\( {$f} -set label {$n}\ {$diff[$bf . '-full']}+{$diff[$bf . '-crop']}+{$t} \)";
                    } else {
                        return $f;
                    }
                }, $chunked_minute_files);
                $size = ceil(count($chunked_minute_files) / 3);
                if ($with_screen) {
                    $cmd = ("montage " . implode(' ', $chunked_minute_files) . " -resize 216x120 -geometry 216x120+0+0 -tile 3x{$size} {$target_file}");
                    error_log($cmd);
                    system($cmd);

                    arsort($crop_group);
                    $crop_group = array_slice($crop_group, 0, 6);
                    $size = count($crop_group);
                    $resize = explode('+', $channel_data['crop'])[0];
                    // -crop 325x24+100+193 -geometry 325x24 -tile 1x3
                    $cmd = ("montage " . implode(' ', array_keys($crop_group)) . " -crop {$channel_data['crop']} -geometry {$resize} -tile 1x{$size} {$target_crop_file}");
                    error_log($cmd);
                    system($cmd);
                }
                return $record;
            }
        };

        $group = new StdClass;
        $group->minute_files = array();
        $group->match_yt = null;
        $group->start = $group->end = null;

        $groups = array();
        foreach ($files as $file) {
            if ($id = $files_yt_id[basename($file)]) {
                if ($group->match_yt and $id != $group->match_yt) {
                    $groups[] = $group;
                    $group = new STdClass;
                    $group->minute_files = array($file);
                    $group->match_yt = $id;
                    $group->end = $group->start = intval(explode('.', basename($file))[0]);
                    continue;
                } else {
                    $group->match_yt = $id;
                }
            }

            if ($diff[basename($file) . '-full'] < 60000) {
                $group->minute_files[] = $file;
                $group->end = intval(explode('.', basename($file))[0]);
                if (is_null($group->start)) {
                    $group->start = $group->end;
                }
                continue;
            } elseif ($diff[basename($file) . '-crop'] < 1000) {
                $group->minute_files[] = $file;
                $group->end = intval(explode('.', basename($file))[0]);
                if (is_null($group->start)) {
                    $group->start = $group->end;
                }
                continue;
            }

            $group->minute_files[] = $file;
            $groups[] = $group;
            $group = new STdClass;
            $group->minute_files = array();
            $group->match_yt = null;
            $group->end = $group->start = intval(explode('.', basename($file))[0]);
        }
        $groups[] = $group;

        // 往回檢查是否有同一檔新聞
        $checking_groups = $groups;
        $result_groups = array();

        while (count($checking_groups)) {
            $checking_group = array_shift($checking_groups);
            error_log($checking_group->start);

            // 如果結果是空的直接塞
            if (!count($result_groups)) {
                $result_groups[] = $checking_group;
                continue;
            }

            $prev_result_group = $result_groups[count($result_groups) - 1];
            // 如果檢查的沒有 youtube id ，直接塞
            if (!$checking_group->match_yt) {
                $result_groups[] = $checking_group;
                continue;
            }

            // 往回追，看看往回 10 秒內有沒有同 ytid 的
            $sure_n = 0;
            for ($n = 0; $n < count($result_groups); $n ++) {
                $p = count($result_groups) - $n - 1;
                if ($checking_group->start - $result_groups[$p]->end > 10) {
                    break;
                }
                if ($result_groups[$p]->match_yt and $result_groups[$p]->match_yt != $checking_group->match_yt) {
                    break;
                } elseif ($result_groups[$p]->match_yt) {
                    $sure_n = $n + 1;
                }
            }

            // 有找到的話
            if ($sure_n) {
                error_log(sprintf("sure_n = %d, count(result_groups) = %d", $sure_n, count($result_groups)));
                $minute_files = array();
                $start = null;
                foreach (array_slice($result_groups, count($result_groups) - $sure_n, $sure_n) as $result_group) {
                    $minute_files = array_merge($minute_files, $result_group->minute_files);
                    if (is_null($start)) {
                        $start = $result_group->start;
                    }
                }
                $checking_group->minute_files = array_merge($minute_files, $checking_group->minute_files);
                $checking_group->start = $start;

                $result_groups = array_slice($result_groups, 0, count($result_groups) - $sure_n);
                error_log(sprintf("done, sure_n = %d, count(result_groups) = %d", $sure_n, count($result_groups)));
            }
            $result_groups[] = $checking_group;
        }

        copy('report.js', $target . '/report.js');
        copy('report-tmpl.html', $target . '/index.html');
        $data = array();
        foreach ($result_groups as $idx => $group) {
            $data[] = $draw_group($group, $idx);
            file_put_contents($target . "/data.json", json_encode($data));
        }
    }
}
