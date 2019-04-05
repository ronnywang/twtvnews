<?php


if (!file_exists('ytcropmerges')) {
    mkdir('ytcropmerges');
}

$fp = fopen('skip-crop.csv', 'r');
fgetcsv($fp);
$skip_crop = array();
while ($rows = fgetcsv($fp)) {
    $skip_crop[$rows[0] . '&' . $rows[1]] = true;
}
fclose($fp);

$channels = array(
    'ctitv' => array('crop' => '325x24+100+193'),
    'ebc' => array('crop' => '426x24+0+192'),
    'formosa' => array('crop' => '328x24+98+192'),
    'set' => array('crop' => '334x27+92+188'),
    'tvbs' => array('crop' => '292x23+106+191'),
);

foreach ($channels as $channel => $channel_data) {
    if (!file_exists("ytcropmerges/{$channel}")) {
        mkdir("ytcropmerges/{$channel}");
    }

    for ($t_date = strtotime('-1 day'); true; $t_date -= 86400) {
        $date = date('Ymd', $t_date);
        if (!file_exists("youtubes/{$channel}/{$date}/list.csv")) {
            break;
        }

        if (file_exists("ytcropmerges/{$channel}/{$date}.csv")) {
            continue;
        }
        error_log("merging {$channel} {$date}");

        $merge_files = new StdClass;
        $fp = fopen("youtubes/{$channel}/{$date}/list.csv", 'r');
        $news_list = array();
        while ($line = fgets($fp)) {
            list($id, $title) = explode(',', trim($line), 2);
            $path = "{$channel}/{$date}/crop-{$id}";
            if (!file_exists("ytcrops/" . $path)) {
                continue;
            }
            $news = new StdClass;
            $news->id = $id;
            $news->title = $title;
            $news->path = $path;

            $diff_fp = fopen("ytcrops/{$path}/diff.csv", "r");
            $groups = array();
            $group = null;
            while ($file_diff = fgets($diff_fp)) {
                list($channel, $ts_file, $file2, $file1, $diff) = explode(',', trim($file_diff));
                $timecode = intval(explode('.', $file2)[0]);
                $png_time = $timecode - 1;
                if (is_null($group)) {
                    $group = new StdClass;
                    $group->files = array($file1);
                    $group->start = $png_time;
                    $group->end = $png_time;
                    $group->diff = array(0);
                }

                if ($diff > 1000) {
                    $groups[] = $group;
                    $group = new StdClass;
                    $group->files = array($file2);
                    $group->start = $png_time;
                    $group->end = $png_time;
                    $group->diff = array($diff);
                } else {
                    $group->files[] = $file2;
                    $group->diff[] = $diff;
                    $group->end = $png_time;
                }
            }
            $groups[] = $group;

            foreach ($groups as $idx => $group) {
                $group->screens = array_unique(array(
                    $group->files[0],
                    $group->files[floor(count($group->files) / 2)],
                    $group->files[count($group->files) - 1],
                ));
                $groups[$idx] = $group;
            }

            $news->groups = $groups;
            $news_list[] = $news;
            fclose($diff_fp);
        }
        fclose($fp);

        // 先把所有大標題合成一張大圖，再用以圖找圖來比對
        $fp = fopen("ytcropmerges/{$channel}/{$date}.csv", "w");
        $files = array();
        $merge_files = array();
        foreach ($news_list as $news) {
            foreach ($news->groups as $group) {
                if (count($group->files) < 8) {
                    continue;
                }
                if (array_key_exists($news->id . '&' . $group->start, $skip_crop)) {
                    continue;
                }
                $files[] = 'ytcrops/' . $news->path . '/' . $group->files[0];

                fputcsv($fp, array(
                    $group->files[0],
                    $news->id,
                    $group->start,
                ));
            }
        }

        $size = count($files);
        $target_merge_file = "ytcropmerges/{$channel}/{$date}.png";
        system("montage " . implode(' ', $files) . " -geometry 325x24+0+0 -tile 1x{$size} " . $target_merge_file);
        fclose($fp);

        $date = date('Ymd', strtotime('-1 day', $date));
    }
}

