<?php

// 產生 https://ronnywang.github.io/set-2019032318/ 和 https://ronnywang.github.io/ctitv-2019032318/
// 的程式
//
// 因為一小時 4000 張圖片總共會佔 900MB 以上
// 但是如果我用圖片合併（下面是 120 一張）
// 可以節省到只需要 400MB，方便展示使用
if ($_GET['channel']) {
    $channel = $_GET['channel'];
    $file = $_GET['file'];
} elseif (preg_match('#^/list-photo.php/([^/]+)/(.*)\.html$#', $_SERVER['REQUEST_URI'], $matches)) {
    $channel = $matches[1];
    $file = $matches[2];
} else {
    $channel = 'set';
    $file = '2019032318.ts';
}

$files = glob("screens/{$channel}/{$file}/*.png");
sort($files);
$minutes_files = array_chunk($files, 120);
$target = $_SERVER['argv'][1];
if (!is_dir($target)) {
    throw new Exception("is not dir");
}
$content = '<script
      src="https://code.jquery.com/jquery-3.3.1.min.js"
        integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
          crossorigin="anonymous"></script>';

foreach ($minutes_files as $idx => $minute_files) {
    $target_file = $target . "/tile-{$idx}.png";

    if (!$idx) {
        $content .= sprintf("<div><img src='%s'></div>", "tile-{$idx}.png");
    } else {
        $content .= sprintf("<div class='lazyload' data-img='%s' style='cursor:pointer'> load more </div>", "tile-{$idx}.png");
    }
   
    system("montage " . implode(' ', $minute_files) . " -geometry 426x240+0+0 -tile 3x40 {$target_file}");
}
ob_start();
?>
<script>
$('.lazyload').click(function(e){
    e.preventDefault();
    $(this).html($('<img>').attr('src', $(this).data('img')));
});
</script>
<?php
$content .= ob_get_clean();
file_put_contents($target . "/index.html", $content);
