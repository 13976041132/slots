/**
 * 选择器
 * 识别样式:uploader
 * data-options
 * {
 *   btnText:上传按钮文字
 *   onUploaded:上传完成回调
 *   fileType:允许上传的文件格式，逗号分隔
 *   maxSize:允许上传的文件最大体积
 *   uploadUrl:文件上传地址
 *   multi:是否支持多个文件
 * }
 *
 * @example
 * <input name="attach" class="uploader"
 * data-options="uploadUrl:'/attach/upload',fileType:'png,jpg,jpeg',maxSize:10*1024*1024"/>
 *
 */

$(document).ready(function () {

    var Uploader = function () {
    };

    Uploader.prototype.initialize = function (input) {
        this.input = input;
        this.id = input.uploader_id;
        this.options = parseOptions(this.input);
        this.options.maxSize = sizeParse(this.options.maxSize) || (20 * 1024 * 1024);
        this.files = [];
        this.createUploader();
        this.uploading = false;
    };

    Uploader.prototype.createUploader = function () {
        var _this = this;
        if ($(this.input).get(0).tagName.toLowerCase() === 'input') {
            var $input = $('<input placeholder="点击进行上传" readonly>');
            $input.width($(this.input).width()).css({cursor: 'pointer'});
            $(this.input).after($input.get(0)).hide();
            this._input = $input.get(0);
            this.createClearButton();
            $(this._input).bind('click', function () {
                _this.createUploadDialog();
            });
            if (this.options.files) {
                this.setFiles(this.options.files);
            }
        } else {
            $(this.input).bind('click', function () {
                _this.createUploadDialog();
            });
        }
    };

    Uploader.prototype.createClearButton = function () {
        var _this = this;
        var button = '<a class="btn uploader-btn-clear">清除</a>';
        $(button).insertAfter(this._input).bind('click', function () {
            $(_this.input).val('');
            if (_this._input) {
                $(_this._input).val('');
            }
        });
    };

    Uploader.prototype.createUploadDialog = function () {
        var content = '';
        content += '<div>';
        content += '<div class="uploader-notice"><div></div></div>';
        content += '<div class="uploader-file-list"><ul></ul></div>';
        content += '<div class="uploader-btn-group"><a class="btn btn-submit">上传文件</a></div>';
        content += '<input type="file" class="form-control" style="display: none;">';
        content += '</div>';
        var title = '上传';
        if (this.options.fileDesc) {
            title += '-' + this.options.fileDesc;
        }
        this.dialog = showDialog(title, content);
        this.noticer = this.dialog.find('.uploader-notice>div');
        this.setNotice(this.getDefaultNotice());
        this.addListener();
    };

    Uploader.prototype.addListener = function () {
        var _this = this;
        this.dialog.find('.uploader-notice').bind('click', function () {
            if (_this.uploading) return;
            _this.copyName(_this.options.fileDesc);
            _this.dialog.find('input[type=file]').click();
        });
        this.dialog.find('.btn-submit').bind('click', function () {
            _this.uploadFile();
        });
        this.dialog.find('.btn-close').click(function () {
            _this.onClose();
        });
        this.addFileListener();
    };

    Uploader.prototype.addFileListener = function () {
        var _this = this;
        this.dialog.find('input[type=file]').bind('change', function (e) {
            _this.onFileSelected(e.currentTarget);
        });
    };

    Uploader.prototype.onFileSelected = function (input) {
        var file = null;
        var isIE = /msie/i.test(navigator.userAgent) && !window.opera;
        if (isIE && !input.files && input.value) {
            var filePath = input.value;
            var fileSystem = new ActiveXObject("Scripting.FileSystemObject");
            file = fileSystem.GetFile(filePath);
        } else if (input.files.length > 0) {
            file = input.files[0];
        }
        if (!file) {
            return;
        }
        if (this.checkFile(file)) {
            this.addFile(file);
        } else {
            this.resetFileInput();
        }
    };

    Uploader.prototype.checkFile = function (file) {
        if (this.options.fileType) {
            var matched = false;
            var type = file.name.split('.').pop().toLowerCase();
            var typeAllowed = this.options.fileType.split(',');
            for (var i = 0; i < typeAllowed.length; i++) {
                if ($.trim(typeAllowed[i]).toLowerCase() === type) {
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                this.setNotice('文件类型[' + type + ']不支持');
                return false;
            }
        }
        if (this.options.maxSize) {
            if (file.size > this.options.maxSize) {
                var size = sizeFormat(file.size);
                var maxSize = sizeFormat(this.options.maxSize);
                this.setNotice('文件大小超过上限<br>当前文件大小为' + size + '<br>最大支持' + maxSize);
                return false;
            }
        }
        return true;
    };

    Uploader.prototype.addFile = function (file) {
        var _this = this;
        var $ul = this.dialog.find('.uploader-file-list>ul');
        if (!this.options.multi && this.files.length) {
            console.log('xxxxxxxxxx');
            $ul.children().remove();
            this.files = [];
        }
        var li = '<li>';
        li += '<span>已选取文件：</span>';
        li += '<span>' + file.name + ' <i style="color:#ccc;">[' + sizeFormat(file.size) + ']</i>' + '</span>';
        li += '<span class="glyphicon glyphicon-remove pull-right"></span>';
        li += '</li>';
        var $li = $(li);
        $li.appendTo($ul);
        $li.find('.glyphicon-remove').bind('click', function () {
            if (_this.uploading) return;
            var index = $li.prevAll().length + 1;
            _this.files.splice(index - 1, 1);
            $li.remove();
        });
        this.files.push(file);
    };

    Uploader.prototype.uploadFile = function () {
        if (!this.files.length) {
            this.setNotice(this.getDefaultNotice());
            return;
        }
        if (!this.options.uploadUrl) {
            this.setNotice('上传地址缺失');
            return;
        }
        if (this.uploading) return;
        var _this = this;
        var url = this.options.uploadUrl;
        var formData = new FormData();
        for (var i = 0; i < this.files.length; i++) {
            formData.append('files-' + i, this.files[i]);
        }
        if (typeof BI_TOKEN !== 'undefined' && BI_TOKEN) {
            url = urlAppend(url, {bi_token: BI_TOKEN});
        }
        $.ajax({
            url: urlCheck(url),
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            xhr: function () {
                var xhr = $.ajaxSettings.xhr();
                xhr.upload.addEventListener("progress", function (ev) {
                    _this.onProgress(ev);
                }, false);
                return xhr;
            },
            beforeSend: function () {
                _this.uploading = true;
                _this.setNotice('正在上传...0%');
            },
            complete: function () {
                _this.uploading = false;
            },
            success: function (r) {
                _this.onUploaded(r);
            },
            error: function (xhr) {
                _this.setNotice('发生错误：' + xhr.status + ' ' + xhr.statusText);
            }
        });
    };

    Uploader.prototype.onProgress = function (ev) {
        var progress = number_format(ev.loaded * 100 / ev.total, 1);
        this.setNotice('正在上传...' + progress + '%', false);
        if (progress === '100') {
            this.setNotice('文件上传完成，正在进行处理...');
        }
    };

    Uploader.prototype.onUploaded = function (r) {
        if (r.code) {
            this.resetFileInput();
            this.setNotice(r.message);
            return;
        }
        var _this = this;
        this.setFiles(r.data);
        this.setNotice('上传成功');
        setTimeout(function () {
            _this.close();
        }, 800);
        var callback = this.options.onUploaded;
        if (callback) {
            if (typeof(callback) === 'function') {
                callback(this, r.data);
            } else {
                eval(callback);
            }
        } else if (r.data) {
            if (r.data.message) {
                alertSuccess(r.data.message);
            }
            if (r.data.reload) {
                reload();
            }
        }
    };

    Uploader.prototype.setNotice = function (text, flashing) {
        var _this = this;
        if (typeof flashing === 'undefined') flashing = true;
        if (flashing) {
            var speed = 100;
            this.noticer.html(text).fadeOut(speed, function () {
                _this.noticer.fadeIn(speed);
            });
        } else {
            this.noticer.html(text);
        }
    };

    Uploader.prototype.resetFileInput = function () {
        if (!this.files.length) return;
        this.dialog.find('.uploader-file-list>ul').html('');
        var $input = this.dialog.find('input[type=file]');
        $input.after($input.clone().val(''));
        $input.remove();
        this.addFileListener();
        this.files = [];
    };

    Uploader.prototype.setUploadUrl = function (url) {
        this.options.uploadUrl = url;
    };

    Uploader.prototype.setOptions = function (options) {
        for (var k in options) {
            this.options[k] = options[k];
        }
    };

    Uploader.prototype.setFiles = function (files) {
        if (!this._input) return;
        var uuid = [], name = [];
        for (var i = 0; i < files.length; i++) {
            uuid.push(files[i]['uuid']);
            name.push(files[i]['filename']);
        }
        $(this._input).val(name.join(' '));
        $(this.input).val(uuid.join(','));
    };

    Uploader.prototype.getDefaultNotice = function () {
        var text = '点击选取文件';
        if (this.options.fileType) {
            text += '<br>(仅支持' + this.options.fileType + ')';
        }
        if (this.options.maxSize) {
            text += '<br>(附件大小限制为<span style="color: red;">' + sizeFormat(this.options.maxSize) + '</span>以内)';
        }
        if (this.options.multi) {
            text += '<br>(<span style="color: #357ebd;">可上传多个附件</span>)';
        }
        return text;
    };

    Uploader.prototype.close = function () {
        this.dialog.find('.btn-close').click();
    };

    Uploader.prototype.onClose = function () {
        this.dialog = null;
        this.files = [];
    };
    // 策划需求，传配置时，复制文件名称
    Uploader.prototype.copyName = function (copyVal) {
        var input = document.createElement('input');
        input.setAttribute('value', copyVal);
        document.body.appendChild(input);
        input.select();
        document.execCommand("copy");
        document.body.removeChild(input);
    };

    //扩展为jQuery插件
    (function ($) {
        var uploaders = {};
        var uploader_id = 1;
        $.fn.uploader = function () {
            if (this.length === 0) {
                return null;
            }
            var element = this[0];
            if (!element.uploader_id) {
                element.uploader_id = uploader_id++;
            }
            var id = element.uploader_id;
            if (!uploaders[id]) {
                uploaders[id] = new Uploader();
                uploaders[id].initialize(this[0]);
            }
            return uploaders[id];
        };
    })(jQuery);

    $(".uploader").each(function () {
        $(this).uploader();
    });
});