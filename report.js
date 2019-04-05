$(function(){
        // 先把前兩張圖都打開
        var loadmore = function(){
            $('.group:not(.done):lt(4)').each(function(){
                $('.lazyload', this).each(function(){
                    var lazyload_dom = $(this);
                    lazyload_dom.html($('<img>').attr('src', lazyload_dom.data('img')));
                    lazyload_dom.append($('<img>').attr('src', lazyload_dom.data('crop')));
                    lazyload_dom.removeClass('lazyload');
                });
            });
        };
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

            if (action == 'btn-right') { // 正確
                var tr_dom = $('<tr></tr>');
                tr_dom.data('start', start)
                    .data('end', end)
                    .data('youtube-id', youtube_id)
                    .data('youtube-title', youtube_title)
                    .data('type', 'news')
                    .data('members', [])
                ;
                tr_dom.append($('<td></td>').text(get_time_string(start)));
                tr_dom.append($('<td></td>').text(get_time_string(end)));
                tr_dom.append($('<td></td>').text(end - start + 1));
                tr_dom.append($('<td></td>').text('新聞段落'));
                tr_dom.append($('<td></td>').text(youtube_id + ':' + youtube_title));
                $('tbody').append(tr_dom);
            } else if (action == 'btn-like-prev') {
                var last_tr_dom = $('tbody tr:last');
                start = last_tr_dom.data('start');
                last_tr_dom.data('end', end);
                if (!last_tr_dom.data('youtube-id') && youtube_id) {
                    last_tr_dom.data('youtube-id', youtube_id);
                    last_tr_dom.data('youtube-title', youtube_title);
                    $('td', last_tr_dom).eq(4).text(youtube_id + ':' + youtube_title);
                }
                $('td', last_tr_dom).eq(1).text(get_time_string(end));
                $('td', last_tr_dom).eq(2).text(end - start + 1);
            } else if (action == 'btn-ad') {
                var tr_dom = $('<tr></tr>');
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
                var tr_dom = $('<tr></tr>');
                tr_dom.data('start', start)
                    .data('end', end)
                    .data('type', 'section-nexton')
                    .data('members', [])
                ;
                tr_dom.append($('<td></td>').text(get_time_string(start)));
                tr_dom.append($('<td></td>').text(get_time_string(end)));
                tr_dom.append($('<td></td>').text(end - start + 1));
                tr_dom.append($('<td></td>').text('開播預告'));
                tr_dom.append($('<td></td>').text(''));
                $('tbody').append(tr_dom);
            } else if (action == 'btn-section-start') {
                var tr_dom = $('<tr></tr>');
                tr_dom.data('start', start)
                    .data('end', end)
                    .data('type', 'section-start')
                    .data('members', [])
                ;
                tr_dom.append($('<td></td>').text(get_time_string(start)));
                tr_dom.append($('<td></td>').text(get_time_string(end)));
                tr_dom.append($('<td></td>').text(end - start + 1));
                tr_dom.append($('<td></td>').text('開播畫面'));
                tr_dom.append($('<td></td>').text(''));
                $('tbody').append(tr_dom);
            } else {
                alert('還沒實作這動作');
                return;
            }

            group_dom.addClass('done').hide();
            loadmore();
        }

        loadmore();

        $('#action-button button').click(function(e){
            e.preventDefault();
            var button_dom = $(this);
            var action = button_dom.attr('id');
            if (action == 'btn-undo') {
                action_logs.pop();
                $('tbody').html('');
                $('.group').removeClass('done').show();
                action_logs.map(do_action);
            } else {
                action_logs.push(action);
                do_action(action);
            }
            $('textarea').val(JSON.stringify(action_logs));
        });

        $('#load-config').submit(function(e){
            e.preventDefault();
            action_logs = JSON.parse($('textarea', this).val());
            $('tbody').html('');
            $('.group').removeClass('done').show();
            action_logs.map(do_action);
            loadmore();
        });
});

