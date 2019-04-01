<?php


$channel = 'ctitv';
$date = $_GET['date'] ?: 20190327;

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
?>
<html>
<body>
<table border="1">
    <thead>
        <tr>
            <th>標題</th>
            <th>開始時間</th>
            <th>結束時間</th>
            <th>秒數</th>
            <th>圖片</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($news_list as $news) { ?>
    <?php foreach ($news->groups as $group) { ?>
    <?php if (count($group->files) < 8) { continue; } ?>
    <tr>
        <td><a href="https://youtu.be/<?= $news->id ?>"><?= htmlspecialchars($news->title) ?></a>(<?= $news->id ?>)</td>
        <td><?= sprintf("%02d:%02d", floor($group->start / 60), $group->start % 60) ?></td>
        <td><?= sprintf("%02d:%02d", floor($group->end / 60), $group->end % 60) ?></td>
        <td title="<?= implode("\n", $group->diffs) ?>"><?= $group->end - $group->start + 1 ?></td>
        <td>
            <?php foreach ($group->screens as $screen) { ?>
            <div><img src="ytcrops/<?= $news->path ?>/<?= $screen ?>"></div>
            <?php } ?>
        </td>
    </tr>
    <?php } ?>
    <?php } ?>
    </tbody>
</table>
</body>
</html>
