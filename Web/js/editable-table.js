/**
 * 可编辑表格
 */
var EditableTable = {};

EditableTable.init = function ($table) {
    $table.find('thead > tr').children().each(function (i, th) {
        var $th = $(th);
        if (!$th.attr('data-key')) return;
        var index = $th.index() + 1;
        $table.find('tbody').find('td:nth-child(' + index + ')').each(function (i, td) {
            var $td = $(td);
            $td.attr('data-value', $td.text());
            $td.dblclick(function () {
                EditableTable.onDoubleClick($table, $th, $td);
            });
        });
    });
};

EditableTable.onDoubleClick = function ($table, $th, $td) {
    var value = $td.attr('data-value');
    if (!$td.find('.editor').length) {
        $td.parent().addClass('tr-selected');
        var $editor = $('<input class="editor">');
        $td.html('').append($editor);
        $editor.focus().val(value).blur(function () {
            EditableTable.onEditComplete($table, $th, $td);
        });
        $editor.keyup(function (e) {
            if (e.key === 'Enter') {
                EditableTable.onEditComplete($table, $th, $td);
            }
        });
    } else {
        EditableTable.onEditComplete($table, $th, $td);
    }
};

EditableTable.onEditComplete = function ($table, $th, $td) {
    EditableTable.saveValue($table, $th, $td);
};

EditableTable.setValue = function ($td, value) {
    $td.parent().removeClass('tr-selected');
    $td.attr('data-value', value);
    $td.text(value);
};

EditableTable.saveValue = function ($table, $th, $td) {
    var oldValue = $td.attr('data-value');
    var newValue = $td.find('.editor').val();
    if (newValue !== oldValue) {
        var id = $td.parent().attr('data-id');
        var key = $th.attr('data-key');
        var url = $table.attr('data-save-url');
        if (id && url) {
            var params = {id: id, key: key, value: newValue};
            ajaxRequest(url, params, function () {
                EditableTable.setValue($td, newValue);
                alertSuccess('已保存');
            }, function () {
                EditableTable.setValue($td, oldValue);
            });
        } else {
            EditableTable.setValue($td, oldValue);
        }
    } else {
        EditableTable.setValue($td, oldValue);
    }
};