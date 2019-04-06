var videos = {};

$(function(){
        // 先把前兩張圖都打開
        var loadmore = function(){
            $('.group:visible:not(.done):lt(4)').each(function(){
                $('.lazyload', this).each(function(){
                    var lazyload_dom = $(this);
                    lazyload_dom.html($('<img>').attr('src', lazyload_dom.data('img')));
                    lazyload_dom.append($('<img>').attr('src', lazyload_dom.data('crop')));
                    lazyload_dom.removeClass('lazyload');
                });
            });
        };

        $('tbody').on('click', '.youtube-video', function(e){
            e.preventDefault();

            var edit_tr_dom = $(this).parents('tr');
            var record = $(this).data('youtube-record');
            $('input[name="title"]', edit_tr_dom).val(record['標題'] + '(' + record['影片ID'] + ')');
        });

        $('tbody').on('click', 'button.button-split', function(e){
            e.preventDefault();
            var button_dom = $(this);
            var action = ['split', button_dom.data('origin-start'), button_dom.data('new-start')];

            var edit_tr_dom = $(this).parents('tr');
            edit_tr_dom.remove();

            action_logs.push(action);
            do_action(action);
            $('textarea').val(JSON.stringify(action_logs));
        });

        $('tbody').on('keyup', 'input[name="title"]', function(e){
                var text = $(this).val();
                var records = youtube_records.filter(function(r) {
                    if (match_date.indexOf(r['日期']) < 0) {
                        return false;
                    }
                    return (r['標題'].indexOf(text) >= 0);
                });
                var edit_tr_dom = $(this).parents('tr');
                $('.dropdown-menu', edit_tr_dom).html('');
                records.map(function(r){
                    var a_dom = $('<a></a>');
                    a_dom.addClass('dropdown-item');
                    a_dom.addClass('youtube-video');
                    a_dom.data('youtube-record', r);
                    a_dom.text(r['標題']);
                    $('.dropdown-menu', edit_tr_dom).append(a_dom);
                });
                $(this).dropdown();
        });

        $('tbody').on('submit', '.group-form', function(e){
            e.preventDefault();
            var edit_tr_dom = $(this).parents('tr');
            var tr_dom = edit_tr_dom.prev();

            var title = $('input[name="title"]', edit_tr_dom).val();
            if (tr_dom.data('title') != title) {
                var action = ['set-title', tr_dom.data('start'), title];
                action_logs.push(action);
                do_action(action);
                $('textarea').val(JSON.stringify(action_logs));
            }
            edit_tr_dom.remove();
        });

        $('tbody').on('click', 'tr.section', function(e){
            e.preventDefault();
            var tr_dom = $(this);
            var edit_tr_dom = $('<tr></tr>');
            var data = tr_dom.data('members');
            var td_dom = $('<td></td>').attr('colspan', 5);

            var id = 'input-' + (new Date()).getTime();
            var form_dom = $('<form class="group-form dropdown">'
                    + '標題:<input type="text" name="title" size="80" class="dropdown-toggle" data-toggle="dropdown" id="' + id + '">'
                    + '<div class="dropdown-menu" aria-labelledby="' + id + '"></div>'
                    + '<button type="submit">修改</button></form>'
                    );
            $('input[name="title"]', form_dom).val(tr_dom.data('title'));

            td_dom.append(form_dom);

            data.map(function(i, idx){
                var div_dom = $('<div></div>');
                var record = videos[i];
                if (idx) {
                    div_dom.append($('<button></button>').text('從這拆開').addClass('button-split').data('origin-start', data[0]).data('new-start', i));
                }
                div_dom.append($('<div></div>').append($('<img>').attr('src', record['img-file'])).append($('<img>').attr('src', record['crop-file'])));
                td_dom.append(div_dom);
            });
            edit_tr_dom.append(td_dom);
            edit_tr_dom.insertAfter(tr_dom);
        });

        var youtube_records = [];
        var youtube_map = {};
        var match_date = [];

        $.get('https://raw.githubusercontent.com/ronnywang/twtvnews/master/youtube.csv', function(content){
            youtube_records = $.csv.toObjects(content);
            youtube_records = youtube_records.map(function(a, idx) {
                a['images'] = [];
                youtube_map[a['影片ID']] = idx;
                return a;
            });

            var matches = document.location.href.match('([^/]*)/([0-9]{10,10})');
            if (matches) {
                var channel = matches[1];
                var time = matches[2];
                var date = new Date(time.substr(0, 4) + '/' + time.substr(4, 2) + '/' + time.substr(6, 2));
                for (var i = 0; i < 2; i ++) {
                    var yyyymmdd = date.getFullYear() + ('00' + (1 + date.getMonth())).substr(-2) + ('00' + date.getDate()).substr(-2);

                    match_date.push(yyyymmdd);

                    $.get('https://raw.githubusercontent.com/ronnywang/twtvnews-youtube-titles/gh-pages/' + channel + '/' + yyyymmdd + '.csv', function(content){
                        var terms = $.csv.toArrays(content);
                        terms.map(function(term){
                            if (youtube_records[youtube_map[term[1]]]) {
                                youtube_records[youtube_map[term[1]]]['images'].push(parseInt(term[3]));
                            }
                        });
                    }, 'text');

                    date.setTime(date.getTime() - 86400 * 1000);
                }
            }
        }, 'text');

        $.get('data.json', function(records){
                for (var i = 0; i < records.length; i ++) {
                    var record = records[i];
                    var div_dom = $('<div></div>').addClass('group');

                    videos[parseInt(record.start)] = record;
                    div_dom.data('start', record.start);
                    div_dom.data('end', record.end);
                    div_dom.data('youtube-id', record['youtube-id']);
                    div_dom.data('youtube-title', record['youtube-title']);

                    div_dom.append($('<h1></h1>').text(get_time_string(record.start) + ' - ' + get_time_string(record.end)));
                    if (record['youtube-id']) {
                        div_dom.append($('<h2></h2>').text(record['youtube-date'] + '. ' + record['youtube-title'] + '(' + record['youtube-id'] + ')'));
                    }

                    if (!i) {
                        div_dom.append($('<div></div>').append($('<img>').attr('src', record['img-file'])).append($('<img>').attr('src', record['crop-file'])));
                    } else {
                        div_dom.append($('<div></div>').addClass('lazyload').data('img', record['img-file']).data('crop', record['crop-file']));
                    }

                    $('#video').append(div_dom);
                }
                loadmore();
        });

        var get_time_string = function(n){
            return ('00' + Math.floor(n / 60)).substr(-2) + ':' 
                 + ('00' + Math.floor(n % 60)).substr(-2);
        };
        var action_logs = [];
        var do_action = function(action){
            var group_dom = $('.group:not(.done)').eq(0);
            var start = parseInt(group_dom.data('start'));
            var end = parseInt(group_dom.data('end'));
            var youtube_id = group_dom.data('youtube-id');
            var youtube_title = group_dom.data('youtube-title');
            var action_params = [];

            if (typeof(action) === 'object') {
                action_params = action.slice(1);
                action = action[0];
            }

            if (action == 'btn-right') { // 正確
                var tr_dom = $('<tr></tr>').addClass('section');
                tr_dom.attr('id', 'group-' + start);
                tr_dom.data('start', start)
                    .data('end', end)
                    .data('youtube-id', youtube_id)
                    .data('youtube-title', youtube_title)
                    .data('type', 'news')
                    .data('members', [start])
                    .data('title', youtube_title + '(' + youtube_id + ')')
                ;
                tr_dom.append($('<td></td>').text(get_time_string(start)));
                tr_dom.append($('<td></td>').text(get_time_string(end)));
                tr_dom.append($('<td></td>').text(end - start + 1));
                tr_dom.append($('<td></td>').text('新聞段落'));
                tr_dom.append($('<td></td>').text(youtube_title + '(' + youtube_id + ')'));
                $('tbody').append(tr_dom);
            } else if (action == 'btn-like-prev') {
                var last_tr_dom = $('tbody tr:last');
                var members = last_tr_dom.data('members');
                members.push(start);
                last_tr_dom.data('members', members);

                start = last_tr_dom.data('start');
                last_tr_dom.data('end', end);
                if (!last_tr_dom.data('youtube-id') && youtube_id) {
                    last_tr_dom.data('youtube-id', youtube_id);
                    last_tr_dom.data('youtube-title', youtube_title);
                    $('td', last_tr_dom).eq(4).text(youtube_title + '(' + youtube_id + ')');
                    last_tr_dom.data('title', youtube_title + '(' + youtube_id + ')');
                }
                $('td', last_tr_dom).eq(1).text(get_time_string(end));
                $('td', last_tr_dom).eq(2).text(end - start + 1);
            } else if (action == 'btn-ad') {
                var tr_dom = $('<tr></tr>').addClass('section');
                tr_dom.attr('id', 'group-' + start);
                tr_dom.data('start', start)
                    .data('end', end)
                    .data('type', 'ad')
                    .data('members', [])
                ;
                tr_dom.append($('<td></td>').text(get_time_string(start)));
                tr_dom.append($('<td></td>').text(get_time_string(end)));
                tr_dom.append($('<td></td>').text(end - start + 1));
                tr_dom.append($('<td></td>').text('廣告'));
                tr_dom.append($('<td></td>').text(''));
                $('tbody').append(tr_dom);
            } else if (action == 'btn-section-nexton') {
                var tr_dom = $('<tr></tr>').addClass('section');
                tr_dom.attr('id', 'group-' + start);
                tr_dom.data('start', start)
                    .data('end', end)
                    .data('type', 'section-nexton')
                    .data('members', [start])
                ;
                tr_dom.append($('<td></td>').text(get_time_string(start)));
                tr_dom.append($('<td></td>').text(get_time_string(end)));
                tr_dom.append($('<td></td>').text(end - start + 1));
                tr_dom.append($('<td></td>').text('開播預告'));
                tr_dom.append($('<td></td>').text(''));
                $('tbody').append(tr_dom);
            } else if (action == 'btn-section-start') {
                var tr_dom = $('<tr></tr>').addClass('section');
                tr_dom.attr('id', 'group-' + start);
                tr_dom.data('start', start)
                    .data('end', end)
                    .data('type', 'section-start')
                    .data('members', [start])
                ;
                tr_dom.append($('<td></td>').text(get_time_string(start)));
                tr_dom.append($('<td></td>').text(get_time_string(end)));
                tr_dom.append($('<td></td>').text(end - start + 1));
                tr_dom.append($('<td></td>').text('開播畫面'));
                tr_dom.append($('<td></td>').text(''));
                $('tbody').append(tr_dom);
            } else if (action == 'split') {
                var origin_start = action_params[0];
                var new_start = action_params[1];
                var old_tr_dom = $('tbody #group-' + origin_start);
                var members = old_tr_dom.data('members');
                var start = origin_start;
                var end = old_tr_dom.data('end');
                old_tr_dom.data('end', new_start - 1);
                old_tr_dom.data('members', members.filter(function(s) { return s < new_start; }));
                $('td', old_tr_dom).eq(1).text(get_time_string(new_start - 1));
                $('td', old_tr_dom).eq(2).text(new_start - origin_start);

                var tr_dom = $('<tr></tr>').addClass('section');
                tr_dom.attr('id', 'group-' + new_start);
                tr_dom.data('start', new_start)
                    .data('end', end)
                    .data('type', old_tr_dom.data('type'))
                    .data('members', members.filter(function(s) { return s >= new_start; }))
                ;
                tr_dom.append($('<td></td>').text(get_time_string(new_start)));
                tr_dom.append($('<td></td>').text(get_time_string(end)));
                tr_dom.append($('<td></td>').text(end - new_start + 1));
                tr_dom.append($('<td></td>').text('新聞段落'));
                tr_dom.append($('<td></td>').text(''));
                tr_dom.insertAfter(old_tr_dom);
            } else if (action == 'set-title') {
                var start = action_params[0];
                var title = action_params[1];
                var tr_dom = $('tbody #group-' + start);
                $('td', tr_dom).eq(4).text(title);
                tr_dom.data('title', title);
                return;
            } else {
                alert('還沒實作這動作');
                return;
            }

            group_dom.addClass('done').hide();
        }


        var replay_actions = function(action_logs){
            $('tbody').html('');
            $('.group').removeClass('done').show();
            action_logs.map(do_action);
        };

        $('#action-button button').click(function(e){
            e.preventDefault();
            var button_dom = $(this);
            var action = button_dom.attr('id');
            if (action == 'btn-undo') {
                action_logs.pop();
                replay_actions(action_logs);
            } else {
                action_logs.push(action);
                do_action(action);
            }
            loadmore();
            $('textarea').val(JSON.stringify(action_logs));
        });

        $('#load-config').submit(function(e){
            e.preventDefault();
            action_logs = JSON.parse($('textarea', this).val());
            replay_actions(action_logs);
            loadmore();
        });
});

