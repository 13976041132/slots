/**
 * 通用JS代码
 */

$(document).ready(function () {
    $.getScript = function (url, callback, cache) {
        $.ajax({type: 'GET', url: url, success: callback, dataType: 'script', ifModified: true, cache: cache});
    };
});

if (!Object.keys) {
    Object.keys = function (obj) {
        var result = [];
        for (var key in obj) {
            if (obj.hasOwnProperty(key)) {
                result.push(key);
            }
        }
        return result;
    };
}

String.prototype.toNumber = function () {
    var value = this.replace(new RegExp(',', 'g'), '');
    value = value.replace(new RegExp('$', 'g'), '');
    value = value.replace(new RegExp('%', 'g'), '');
    if (value === parseInt(value).toString()) {
        value = parseInt(value);
    } else {
        value = parseFloat(value);
    }
    if (isNaN(value)) value = 0;
    return value;
};

function requirejs(file, onLoad) {
    var loaded = 0;
    var files = typeof file === 'string' ? [file] : file;
    var _onLoad = function () {
        loaded++;
        if (loaded < files.length) {
            _require(files[loaded], _onLoad);
        } else {
            invokeFunc(onLoad);
        }
    };
    _require(files[0], _onLoad);
}

function _require(file, onLoad) {
    file = urlCheck(file);
    file += (file.indexOf('?') >= 0 ? '&' : '?') + 'v=' + RES_VER;
    window._required = window._required || {};
    if (window._required[file]) {
        return invokeFunc(onLoad);
    }
    window._requiring = window._requiring || {};
    window._requiredCbs = window._requiredCbs || {};
    window._requiredCbs[file] = window._requiredCbs[file] || [];
    if (onLoad) {
        window._requiredCbs[file].push(onLoad);
    }
    if (window._requiring[file]) {
        return;
    }
    window._requiring[file] = true;
    $.getScript(file, function () {
        window._required[file] = true;
        window._requiring[file] = false;
        while (window._requiredCbs[file].length) {
            invokeFunc(window._requiredCbs[file].shift());
        }
    }, true);
}

function int(value) {
    return parseInt(value || 0);
}

function json_decode(str) {
    try {
        return JSON.parse(str);
    } catch (e) {
        return null;
    }
}

function date(format, time) {
    var date = new Date();
    if (time > 0) date.setTime(time);
    if (time < 0) date.setTime(date.getTime() + time);
    var Y = date.getFullYear();
    var m = date.getMonth() + 1;
    var d = date.getDate();
    var H = date.getHours();
    var i = date.getMinutes();
    var s = date.getSeconds();
    var replaces = {'Y': Y, 'm': m, 'd': d, 'H': H, 'i': i, 's': s};
    format = format || 'Y-m-d H:i:s';
    for (var k in replaces) {
        if (replaces[k] < 10) replaces[k] = '0' + replaces[k];
        format = format.replace(new RegExp(k, 'g'), replaces[k]);
    }
    return format;
}

/**
 * 解析配置选项
 * 格式：类json字符串，可不加首尾花括号，单双引号均支持
 * @param target
 * @returns {{}}
 */
function parseOptions(target) {
    var t = $(target);
    var options = {};
    var s = $.trim(t.data('options'));
    if (!s) return options;
    if (s.substring(0, 1) !== '{') {
        s = '{' + s + '}';
    }
    try {
        options = (new Function('return ' + s))();
    } catch (e) {
        console.log('格式错误: ' + s);
    }
    return options;
}

function query_parse(str) {
    var query = {};
    var ss = str.split('&');
    var sss;
    for (var i = 0; i < ss.length; i++) {
        if (ss[i] === '') continue;
        if (ss[i].indexOf('=') <= 0) continue;
        sss = ss[i].split('=');
        query[sss[0]] = sss[1];
    }
    return query;
}

/**
 * 数字格式化为千分位
 * 支持负数，默认保持2位小数
 */
function number_format(num, decimals) {
    var ret = '';
    var flag = '';
    var numString = num.toString();
    var numSplit = numString.split('.');
    if (typeof decimals === 'undefined' || decimals === '' || decimals === null) decimals = 2;
    if (numSplit.length === 2 && decimals > 0) {
        ret = '.' + numSplit[1].substr(0, decimals);
    }
    numString = numSplit[0];
    if (numString.substr(0, 1) === '-') {
        numString = numString.substr(1);
        flag = '-';
    }
    while (numString.length > 3) {
        ret = ',' + numString.substr(numString.length - 3) + ret;
        numString = numString.substr(0, numString.length - 3);
    }
    ret = flag + numString + ret;
    return ret;
}

function http_build_query(data) {
    var query = [];
    for (var key in data) {
        if (data.hasOwnProperty(key) && data[key]) {
            if (data[key] !== '' && data[key] !== null) {
                query.push(key + '=' + data[key]);
            }
        }
    }
    return query.join('&');
}

function urlAppend(url, params) {
    params = params || {};
    var urls = url.split('?');
    if (urls[1]) {
        params = $.extend(query_parse(urls[1]), params);
    }
    return urls[0] + '?' + http_build_query(params);
}

//补全、校正url
function urlCheck(url) {
    if (url.indexOf('http://') === 0) return url;
    var _baseUrl;
    if (typeof (BASE_URL) === 'undefined' || !BASE_URL) {
        _baseUrl = 'http://' + location.hostname;
        if (location.port !== '80') {
            _baseUrl += ':' + location.port;
        }
    } else {
        _baseUrl = BASE_URL;
    }
    _baseUrl = _baseUrl.substr(-1) === '/' ? _baseUrl.substr(0, _baseUrl.length - 1) : _baseUrl;//去除末尾斜杠
    url = url.substr(0, 1) === '/' ? url : ('/' + url);//开头加斜杠
    return _baseUrl + url;
}

// 表单验证
function formValidate($form) {
    var valid = true;
    $form.find('input,textarea,select').each(function () {
        if (!inputValidate($(this))) valid = false;
    });
    return valid;
}

//以ajax方式异步提交表单
function ajaxSubmit(form, onSuccess, onError) {
    if (typeof (form) === 'function') {
        onError = onSuccess;
        onSuccess = form;
        form = null;
    }
    var $form = form || $("form");
    if (!formValidate($form)) return alertError('请检查输入内容');
    var action = $form.attr('action') || '/';
    var data = new FormData($form[0]);
    ajaxRequest(action, data, onSuccess, onError);
}

//发起ajax请求
function ajaxRequest(url, data, onSuccess, onError, format) {
    for (var k in data) {
        if (data.hasOwnProperty(k) && data[k] === null) {
            data[k] = '';
        }
    }
    var formData;
    if (!(data instanceof FormData)) {
        formData = new FormData();
        for (var k in data) {
            if (typeof data[k] === 'object') {
                for (var _k in data[k]) {
                    formData.append(k + '[' + _k + ']', data[k][_k]);
                }
            } else {
                formData.append(k, data[k]);
            }
        }
    } else {
        formData = data;
    }
    if (typeof BI_TOKEN !== 'undefined' && BI_TOKEN) {
        url = urlAppend(url, {bi_token: BI_TOKEN});
    }
    url = urlCheck(url);
    format = format || 'json';
    $.ajax({
        url: url,
        data: formData,
        type: 'POST',
        dataType: format,
        contentType: false,
        processData: false,
        success: function (r) {
            invokeFunc(ajaxCallback, r, onSuccess, onError);
        },
        error: function (xhr) {
            invokeFunc(onError, xhr.status, xhr.statusText);
        }
    });
}

//完成ajax请求后的回调
function ajaxCallback(r, onSuccess, onError) {
    if (!r) {
        var errMsg = '请求没有返回数据';
        alertError(errMsg);
        invokeFunc(onError, -9999, errMsg);
        return;
    }
    if (typeof (r) === 'string' || typeof (r.code) === 'undefined') {
        r = {code: 0, message: '', data: r};
    }
    if (r.code) {
        if (onError) {
            invokeFunc(onError, r.code, r.message);
        } else {
            alertError(r.message);
        }
        return;
    }
    var msg = r.message || (r.data && r.data.message);
    if (msg) {
        alertSuccess(msg);
    }
    if (onSuccess) {
        invokeFunc(onSuccess, r.data);
    }
    if (r.data && r.data.reload && !r.data.redirect) {
        r.data.redirect = CURRENT_URI || location.href;
    }
    if (r.data && r.data.redirect) {
        redirect(r.data.redirect);
    }
}

//call_user_func
function invokeFunc(func) {
    if (!!func && typeof func === 'function') {
        func.apply(null, Array.prototype.slice.call(arguments, 1));
    }
}

//页面跳转
window.redirect = window.redirect || function (href) {
    //$('input[type=password]').val('').prop('type', 'text');
    //$('input[type=password]').val('');
    location.href = urlCheck(href);
};

// 重新加载当前页面
window.reload = window.reload || function () {
    redirect(CURRENT_URI || location.href);
};

//显示提示框
window.showAlert = window.showAlert || function (msg) {
    alert(msg);
};


//成功提示
function alertSuccess(msg) {
    showAlert(msg, 'success');
    return true;
}

//错误提示
function alertError(msg) {
    showAlert(msg, 'error');
    return false;
}

// 输入框验证
function inputValidate($input) {
    var valid = true;
    var format = '';
    var required = $input.prop('required');
    var value = $.trim($input.val());
    if ($input.attr('type') !== 'file') {
        $input.val(value);
        var type = $input.data('type') || 'text';
        var minLength = parseInt($input.attr('minlength') || '0');
        if (minLength > 0) required = true;
        if (minLength > 0 && value.length < minLength) valid = false;
        var regulars = {
            email: /^\w+(\.\w+)*@\w+(\.\w+)*\.[a-z]{2,3}$/,
            mobile: /^(1[3589][0-9])\d{8}$/,
            number: /^[+-]?(0|([1-9]\d*))(\.\d+)?$/,
            integer: /^[+-]?(0|([1-9]\d*))$/,
            chinese: /^[\u4e00-\u9fa5]+$/
        };
        format = eval($input.data('format')) || regulars[type];
    }
    if (valid && required && value === '') valid = false;
    if (valid && format && value !== '' && !format.test(value)) valid = false;
    valid ? $input.removeClass('error') : $input.addClass('error');
    return valid;
}

function showJson($container, jsonData) {
    requirejs(JS_URL + '/jquery.json.js', function () {
        var jsonString = typeof (jsonData) !== 'string' ? JSON.stringify(jsonData) : jsonData;
        var jsonFormat = new JSONFormat(jsonString).toString();
        $container.html(jsonFormat);
    });
}

function sizeParse(size) {
    if (typeof size === 'number') {
        return size;
    } else if (typeof size !== 'string') {
        return 0;
    }
    if (size.substr(-1).toUpperCase() === 'B') {
        return this.sizeParse(size.slice(0, -1));
    }
    var suffix = size.substr(-1).toUpperCase();
    size = parseInt(size.slice(0, -1));
    if (suffix === 'K') return size * 1024;
    if (suffix === 'M') return size * 1024 * 1024;
    if (suffix === 'G') return size * 1024 * 1024 * 1024;
    return size;
}

function sizeFormat(size) {
    if (size < 1024) {
        size = size + 'B'
    } else if (size < 1024 * 1204) {
        size = parseFloat((size / 1024).toFixed(2)) + 'KB';
    } else {
        size = parseFloat((size / (1024 * 1024)).toFixed(2)) + 'M';
    }
    return size;
}

function getImageBlob(url, cb) {
    var xhr = new XMLHttpRequest();
    xhr.open("get", url, true);
    xhr.responseType = "blob";
    xhr.onload = function () {
        if (this.status === 200) {
            if (cb) cb(this.response);
        }
    };
    xhr.send();
}

function calFileMd5(file, cb) {
    requirejs(JS_URL + '/spark-md5.js', function () {
        var fileReader = new FileReader();
        var blobSlice = File.prototype.mozSlice || File.prototype.webkitSlice || File.prototype.slice,
            chunkSize = 2097152, // read in chunks of 2MB
            chunks = Math.ceil(file.size / chunkSize),
            currentChunk = 0,
            spark = new SparkMD5();
        fileReader.onload = function (e) {
            console.log("read chunk", currentChunk + 1, "of", chunks);
            spark.appendBinary(e.target.result);
            currentChunk++;
            if (currentChunk < chunks) {
                loadNext();
            } else {
                var md5 = spark.end();
                console.log("Md5: " + md5);
                cb(md5);
            }
        };
        var loadNext = function () {
            var start = currentChunk * chunkSize;
            var end = start + chunkSize >= file.size ? file.size : start + chunkSize;
            fileReader.readAsBinaryString(blobSlice.call(file, start, end));
        };
        loadNext();
    });
}