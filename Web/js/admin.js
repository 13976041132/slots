/**
 * 管理后台JS
 */

$(document).ready(function () {
    $.ajaxSetup({
        cache: false //close ajax cache
    });
    $.datetimepicker.setLocale('ch');
    $(document).click(function () {
        $('.dropdown-menu').hide();
    });
    window.onpopstate = function () {
        initPage();
    };
    initNavigation();
    initDom($('body'));
    initPage();
});

// 初始化导航
function initNavigation() {
    $('.nav-menu-1>li').click(function () {
        var $li = $(this);
        var $opened = $li.siblings('.nav-menu-open');
        $opened.removeClass('nav-menu-open').find('ul').slideUp('fast');
        if ($li.find('.nav-menu-2').length) {
            $li.find('.nav-menu-2').slideToggle('fast');
            if ($li.hasClass('nav-menu-open')) {
                $li.removeClass('nav-menu-open');
            } else {
                $li.addClass('nav-menu-open');
            }
        }
    });
    $('.nav-menu-2>li').click(function (e) {
        e.stopPropagation();
    });
    $('.nav-body li').click(function () {
        var uri = $(this).data('uri');
        if (uri) loadPage(uri);
    });
}

// 激活菜单
// 根据当前显示页面uri匹配
function navActivate(uri) {
    var $menu_li_2 = null;
    $('.nav-body li').each(function () {
        var $li = $(this);
        var data_uri = $li.data('uri') || '';
        var data_pages = $li.data('pages') || '';
        if (data_uri && uri.indexOf(data_uri) !== -1) {
            $menu_li_2 = $li;
        } else if (data_pages) {
            var pages = data_pages.split(',');
            for (var i = 0; i < pages.length; i++) {
                if (uri.indexOf(pages[i]) !== -1) {
                    $menu_li_2 = $li;
                }
            }
        }
    });
    if (!$menu_li_2) return;
    var $menu_li_1 = $menu_li_2.parents('.nav-menu-1 li');
    if (!$menu_li_2.hasClass('nav-menu-active')) {
        $('.nav-menu-active').removeClass('nav-menu-active');
        $menu_li_2.addClass('nav-menu-active');
    }
    if (!$menu_li_1.hasClass('nav-menu-open')) {
        $('.nav-menu-open').removeClass('nav-menu-open').find('.nav-menu-2').hide();
        $menu_li_1.addClass('nav-menu-open');
        $menu_li_2.parents('.nav-menu-2').slideDown('fast');
    }
}

// 页面内容初始化
function initPage() {
    var hash = location.hash || '#/index/welcome';
    var uri = hash.substr(1);
    if (CURRENT_URI !== uri) {
        loadPage(uri);
    }
}

// 页面元素初始化
function initDom($root) {
    $root.find('select').each(function () {
        var $select = $(this);
        var value = $select.data('value');
        if (typeof (value) !== 'undefined' && value !== '') {
            $select.removeAttr('data-value');
            $select.val(value);
        }
    });
    $root.find('.radio').each(function (i, el) {
        var value = $(el).data('value');
        if (typeof (value) !== 'undefined' && value !== '') {
            $(el).removeAttr('data-value');
            $(el).find('input[value="' + value + '"]').prop('checked', true);
        }
    });
    $root.find('.checkbox').each(function (i, el) {
        var value = $(el).data('value');
        if (typeof (value) !== 'undefined' && value !== '') {
            var values = value.split(',');
            $(el).removeAttr('data-value');
            for (var j = 0; j < values.length; j++) {
                $(el).find('input[value="' + values[j] + '"]').prop('checked', true);
            }
        }
    });
    $root.find('.datetime').each(function (i, el) {
        var format = $(el).data('date-format') || 'Y-m-d H:i';
        $(el).datetimepicker({
            format: format,
            timepicker: format !== 'Y-m-d',
            datepicker: format !== 'H:i'
        });
        $(el).width(format === 'Y-m-d' ? 90 : 130);
        $(el).attr('autocomplete', 'off');
    });
    $root.find('.dropdown').click(function (e) {
        var $menu = $(this).find('.dropdown-menu');
        var visible = $menu.is(':hidden');
        $('.dropdown-menu').hide();
        $menu.toggle(visible);
        e.stopPropagation();
    });
    $root.find('.dropdown-menu').click(function (e) {
        e.stopPropagation();
    });
    $root.find('.dropdown-menu li, .dropdown-menu li a').click(function () {
        var $li = $(this).is('li') ? $(this) : $(this).parents('li');
        if ($li.hasClass('disabled')) return;
        $(this).parents('.dropdown-menu').hide();
    });
    $root.find('.panel').each(function () {
        var $panel = $(this);
        var $panelHead = $panel.find('.panel-head');
        if (!$panelHead.length) {
            var $body = $panel.children();
            var title = $panel.data('title');
            $panelHead = $('<div class="panel-head">' + title + '</div>');
            var panelBody = $('<div class="panel-body"></div>');
            $panelHead.appendTo($panel);
            panelBody.appendTo($panel);
            $body.appendTo(panelBody);
        }
    });
    $root.find('.panel.closeable').each(function () {
        var $panel = $(this);
        var icon = $panel.hasClass('closed') ? 'arrow-down.png' : 'arrow-up.png';
        var $arrow = $('<img class="panel-arrow" src="' + IMG_URL + '/icons/' + icon + '">');
        var $panelHead = $panel.find('.panel-head');
        $panelHead.css({cursor: "pointer"});
        $panelHead.attr('title', '点击展开/收起');
        $arrow.appendTo($panelHead);
        $panelHead.click(function () {
            $panel.find('.panel-body').slideToggle('fast', function () {
                if ($panel.hasClass('closed')) {
                    $panel.removeClass('closed');
                    $arrow.attr('src', IMG_URL + '/icons/arrow-up.png');
                } else {
                    $panel.addClass('closed');
                    $arrow.attr('src', IMG_URL + '/icons/arrow-down.png');
                }
            });
        });
    });
    $root.find('a').click(function () {
        var $el = $(this);
        var url = $el.attr('href');
        var target = $el.attr('target');
        if (!url || url.indexOf('javascript') === 0) {
            return false;
        } else if (target) {
            if (typeof BI_TOKEN !== 'undefined' && BI_TOKEN) {
                loadPage(url);
            } else {
                window.open(url, target);
            }
            return false;
        }
        if ($el.hasClass('ajax')) {
            if ($el.hasClass('confirm')) {
                var title = $el.data('title') || $el.text();
                showConfirm('确定要进行【' + title.replace(' ', '') + '】操作吗？', function () {
                    ajaxRequest(url);
                });
            } else {
                ajaxRequest(url);
            }
        } else {
            loadPage(url);
        }
        return false;
    });
    $root.find('table[data-editable="true"]').each(function (i, table) {
        requirejs(JS_URL + '/editable-table.js', function () {
            EditableTable.init($(table));
        });
    });
    $root.find('table').find('.btn-delete').each(function (i, btn) {
        var $btn = $(btn);
        $btn.click(function () {
            var id = $btn.parents('tr').data('id');
            var url = $btn.parents('table').data('delete-url');
            if (!id || !url) return;
            showConfirm('确定删除该数据吗？', function () {
                ajaxRequest(url, {id: id}, function () {
                    $btn.parents('tr').remove();
                });
            });
        });
    });
    $root.find('th').each(function () {
        var width = $(this).attr('width');
        if (width && width.slice(-1) !== '%') {
            var $th = $(this);
            var paddingLeft = $th.css('padding-left').slice(0, -2).toNumber();
            var paddingRight = $th.css('padding-right').slice(0, -2).toNumber();
            width = width.toNumber() - paddingLeft - paddingRight;
            var $div = $('<div style="width: ' + width + 'px; margin: 0 auto; white-space: normal;"></div>');
            $div.append($th.contents());
            $th.html('').append($div);
        }
    });
    $root.find('tr').click(function () {
        $(this).siblings().removeClass('tr-selected');
        $(this).addClass('tr-selected');
    });
    $root.find('.search-box').find('.btn[type=submit]').click(function () {
        var $form = $(this).parents('form');
        var params = $form.serialize();
        var url = $form.attr('action') || CURRENT_URI;
        url += (url.indexOf('?') > 0 ? '&' : '?') + params;
        var urls = url.split('?');
        params = query_parse(urls[1] + '&' + params);
        var limit = params['limit'];
        delete params['page'];
        delete params['limit'];
        delete params['_t'];
        if (limit) {
            params['page'] = 1;
            params['limit'] = limit;
        }
        params = http_build_query(params);
        url = urls[0] + (params ? ('?' + params) : '');
        loadPage(url);
    });
    $root.find('.btn-clear').click(function () {
        var $form = $(this).parents('form');
        $form.find('input,select').val('').change();
    });
    $root.find('[data-toggle=dialog]').click(function () {
        var $btn = $(this);
        var title = $btn.data('title') || $btn.text();
        var target = $btn.data('target');
        var options = parseOptions(this);
        if (!target) return;
        if (target.substr(0, 1) === '#') {
            showDialog(title, $(target).clone(), options);
        } else {
            options.url = urlCheck(target);
            showDialog(title, null, options);
        }
    });
    $root.find('input').click(function (e) {
        e.stopPropagation();
    });
    $root.find('.radio,.checkbox').find('label').click(function () {
        $(this).prev().click();
    });
    $root.find('input,textarea,select').change(function () {
        inputValidate($(this));
    });
    if ($root.find('.uploader').length) {
        requirejs(JS_URL + '/uploader.js', function () {
            $root.find('.uploader').each(function () {
                $(this).uploader();
            });
        });
    }
    $root.find('table[data-chart-enable=all],table[data-chart-enable=col]').find('th').click(function () {
        var $th = $(this);
        var col = $th.parent().children().index($th);
        var $table = $th.parents('table');
        showColChart($table, col);
    }).attr('title', '点击查看趋势图');
    $root.find('table[data-chart-enable=all],table[data-chart-enable=row]').find('td:first-child').click(function () {
        var $tr = $(this).parent();
        var $table = $tr.parents('table');
        var row = $table.find('tr').index($tr);
        showRowChart($table, row);
    }).attr('title', '点击查看分布图');
}

// 加载页面
function loadPage(uri, params) {
    clearTimers();
    if (uri.indexOf(BASE_URL) === 0) {
        uri = uri.substr(BASE_URL.length);
    }
    navActivate(uri);
    $('.dialog,.dialog-mask').remove();
    var $container = $('#body');
    var onLoad = function (html) {
        $(document).scrollTop(0);
        var r = json_decode(html);
        if (r) html = r.message;
        $container.html(html);
        initDom($container);
    };
    var onError = function (code, error) {
        $container.html(code + ' ' + error);
    };
    ajaxRequest(uri, params, onLoad, onError, 'html');
    CURRENT_URI = uri;
    location.hash = '#' + uri;
}

// 退出登录
function logout() {
    ajaxRequest('/account/logout', {}, function () {
        location.href = urlCheck('/account/login');
    });
}

// 显示图表
function showChart($container, options) {
    var files = [
        JS_URL + '/highcharts/highcharts.js',
        JS_URL + '/highcharts/modules/exporting.js',
        JS_URL + '/highcharts/modules/offline-exporting.js',
        JS_URL + '/highcharts/modules/export-data.js',
        JS_URL + '/highcharts/plugins/highcharts-zh_CN.js',
        JS_URL + '/highcharts/modules/no-data-to-display.js',
        JS_URL + '/resize.js'
    ];
    requirejs(files, function () {
        $container.addClass('chart');
        var $chartBox = $('<div class="chart-box"></div>');
        $chartBox.width($container[0].offsetWidth);
        $chartBox.height($container[0].offsetHeight);
        $container.html('').append($chartBox);
        var drawChart = function () {
            var chartOptions = {};
            chartOptions['title'] = {text: null};
            chartOptions['legend'] = {enabled: false};
            chartOptions['credits'] = {enabled: false};
            chartOptions['plotOptions'] = {series: {animation: true, marker: {enabled: false}}};
            chartOptions['lang'] = {noData: '没有数据'};
            chartOptions['noData'] = {style: {fontSize: '14px', color: '#999999'}};
            $.extend(true, chartOptions, options);
            $chartBox.highcharts(chartOptions);
        };
        $container.resize(function () {
            $chartBox.width($container[0].offsetWidth);
            $chartBox.height($container[0].offsetHeight);
            $chartBox.highcharts().reflow();
        });
        drawChart();
    });
}

// 显示在线曲线
function showOnline(start, end, step) {
    var params = {start: start, end: end, step: step};
    ajaxRequest('/analysis/getOnline', params, function (data) {
        var options = {};
        var pointStart = Date.parse(data['start']);
        options['title'] = {text: null};
        options['legend'] = {enabled: true};
        options['plotOptions'] = {series: {animation: false}};
        options['plotOptions']['area'] = {lineWidth: 1, pointStart: pointStart, pointInterval: data['step'] * 1000};
        options['tooltip'] = {xDateFormat: '%Y-%m-%d %H:%M:%S', shared: true};
        options['xAxis'] = {type: "datetime"};
        options['yAxis'] = {title: {text: "人数"}};
        options['series'] = [{
            type: "area", name: "同时在线", data: data['online']
        }, {
            type: "area", name: "同时在玩", data: data['playing']
        }];
        showChart($('#online-chart'), options);
    });
}

// 显示表格某列数据的走势图
function showColChart($table, col) {
    if (col === 0) return;
    var categories = [];
    var values = [];
    var $th = '';
    $table.find('tr').each(function (i, tr) {
        var $tds = $(tr).children();
        if (i === 0) {
            $th = $tds.eq(col);
            return;
        }
        var value = $tds.eq(col).text();
        categories.push($tds.eq(0).text());
        values.push(value.toNumber());
    });
    if (!categories.length) {
        alertError('没有可用数据');
        return;
    }
    if (categories.length >= 2 && categories[0] > categories[1]) {
        categories.reverse();
        values.reverse();
    }
    var options = {};
    options['title'] = {text: null};
    options['tooltip'] = {headerFormat: '{point.x}: ', pointFormat: '<b>{point.y}</b>'};
    options['xAxis'] = {title: {text: null}, categories: categories};
    options['yAxis'] = {title: {text: null}};
    options['series'] = [{data: values}];
    var title = $table.parents('.panel').find('.panel-head').text();
    title += '趋势图' + '[' + $th.text() + ']';
    var $dialog = showDialog(title, '<div class="chart" style="width: 1080px; height: 500px;"></div>');
    showChart($dialog.find('.chart'), options);
}

// 显示表格某行数据的分布图
function showRowChart($table, row) {
    if (row === 0) return;
    var $head = $table.find('tr').eq(0);
    var $tds = $table.find('tr').eq(row).children();
    var seriesData = [];
    $head.children().each(function (i, th) {
        if (i === 0) return;
        var value = $tds.eq(i).text().toNumber();
        var name = $(th).text();
        if (name === '' || !value) return;
        seriesData.push({name: name, y: value});
    });
    if (!seriesData.length) {
        alertError('没有可用数据');
        return;
    }
    var options = {};
    options.chart = {type: 'pie'};
    options.legend = {enabled: true};
    options.plotOptions = {
        pie: {
            dataLabels: {format: '<b>{point.name}</b>: {point.percentage:.2f}%'},
            showInLegend: true
        }
    };
    options.series = [{name: 'Value', data: seriesData}];
    var title = $table.parents('.panel').find('.panel-head').text();
    title += '分布[' + $tds.eq(0).text() + ']';
    var $dialog = showDialog(title, '<div class="chart" style="width: 1080px; height: 500px;"></div>');
    showChart($dialog.find('.chart'), options);
}

// 显示确认框
function showConfirm(message, onConfirm, onCancel) {
    var content = '<p>' + message + '</p>';
    showDialog('提示', content, {isConfirm: true, onSuccess: onConfirm, onCancel: onCancel});
}

// 图例全选或全不选
function selectLegends($dialog, data, totalKey) {
    $dialog.find('#dialog-legend :radio').click(function () {
        var legendType = $("input[name=legendType]:checked").val();
        var _visible = true;
        if (legendType === "none") {
            _visible = false;
        }
        var options = {};
        var seriesData = [];
        var _data = {};
        for (var d in data) {
            for (var k in data[d]) {
                if (!_data[k]) _data[k] = [];
                _data[k].push([Date.parse(d), data[d][k]]);
            }
        }
        if (totalKey) {
            seriesData.push({name: 'Total', data: _data[totalKey]});
        }
        delete _data[totalKey];
        for (var k in _data) {
            var name = k.split('-')[2];
            seriesData.push({name: name, data: _data[k], visible: _visible});
        }
        options.chart = {type: 'spline'};
        options.legend = {enabled: true};
        options.tooltip = {shared: true, xDateFormat: '%Y-%m-%d'};
        options.xAxis = {type: "datetime"};
        options.series = seriesData;
        showChart($dialog.find('.chart'), options);
    });
}

// 显示对话框（模态）
function showDialog(title, content, options) {
    options = options || {};
    var $mask = $('<div class="dialog-mask"></div>');
    var dialog = '<div class="dialog">';
    dialog += '<div class="dialog-header">' + title + '<a class="btn btn-close material-icons">close</a></div>';
    if (options['isLegendSelect']) {
        dialog += '<div id="dialog-legend">图例选择: <input type="radio" name="legendType" value="all" checked>all <input type="radio" name="legendType" value="none">none</div>';
    }
    dialog += '<div class="dialog-body"></div>';
    dialog += '</div>';
    var $dialog = $(dialog);
    if (content) {
        var $content = $(content);
        $content.removeAttr('id').css({display: 'block'});
        $dialog.find('.dialog-body').append($content);
    }
    $dialog.css({'top': (60 + $('.dialog').length * 40) + 'px'});
    if (options.width) {
        $dialog.css({width: options.width});
    }
    $dialog.appendTo($mask);
    $mask.css({'opacity': 0});
    $mask.appendTo($('body'));
    var addFooter = function () {
        if ($dialog.find('form').length || options['isConfirm']) {
            var confirmText = options['isConfirm'] ? '确 定' : (options['confirmText'] || '保 存');
            var $footer = $('<div class="dialog-footer"></div>');
            $footer.append('<a class="btn btn-middle btn-cancel">取 消</a>');
            $footer.append('<a class="btn btn-middle btn-primary" type="submit">' + confirmText + '</a>');
            $footer.appendTo($dialog);
            $footer.find('.btn[type=submit]').click(function () {
                if (options['isConfirm']) {
                    removeDialog($dialog);
                    return invokeFunc(options['onSuccess']);
                }
                var $form = $dialog.find('form');
                ajaxSubmit($form, function (data) {
                    removeDialog($dialog);
                    invokeFunc(options['onSuccess'], data);
                }, options['onError']);
            });
            $dialog.find('.btn-cancel').click(function () {
                removeDialog($dialog);
                invokeFunc(options['onCancel']);
            });
        }
    };
    $dialog.find('.btn-close').click(function () {
        removeDialog($dialog);
        invokeFunc(options['onClose']);
    });
    if (!content && options['url']) {
        var url = urlCheck(options['url']);
        if (typeof BI_TOKEN !== 'undefined' && BI_TOKEN) {
            url = urlAppend(url, {bi_token: BI_TOKEN});
        }
        $dialog.find('.dialog-body').load(url, function () {
            initDom($dialog);
            addFooter();
            $mask.css({'opacity': 1});
        });
    } else {
        initDom($dialog);
        addFooter();
        $mask.css({'opacity': 1});
    }
    return $dialog;
}

// 移除对话框
function removeDialog($dialog) {
    $dialog.parent().remove();
}

// 显示提示框
function showAlert(msg, style) {
    style = style || 'success';
    $('.alert').remove();
    var $tips = $('<div class="alert alert-' + style + '">' + msg + '</div>');
    $tips.appendTo($('body'));
    $tips.animate({top: 0}, 1000);
    $tips.fadeOut(1000);
}

// 页面重定向
function redirect(url) {
    loadPage(url);
}

// setTimeout加强版
function _setTimeout(func, delay) {
    _setInterval(func, delay, 1);
}

// setInterval加强版
function _setInterval(func, delay, limit) {
    limit = Math.max(0, parseInt(limit || 0));
    window._timers = window._timers || {};
    var timer = setInterval(function () {
        if (!window._timers[timer]) return;
        window._timers[timer]['times']++;
        var limit = window._timers[timer]['limit'];
        if (limit === 0) return;
        if (window._timers[timer]['times'] === limit) {
            delete window._timers[timer];
            clearInterval(timer);
        }
        invokeFunc(func);
    }, delay);
    window._timers[timer] = {limit: limit, times: 0};
}

// 清除所有定时器
function clearTimers() {
    if (!window._timers) return;
    for (var timer in window._timers) {
        clearInterval(parseInt(timer));
    }
    window._timers = null;
}

// 清除单个定时器
function clearTimer(timer) {
    if (!window._timers) return;
    delete window._timers[timer];
    clearInterval(timer);
}