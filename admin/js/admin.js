(function ($) {
    'use strict';

    $(document).on('click', '.mxroute-delete-log', function (e) {
        e.preventDefault();
        if (!confirm(mxrouteMailer.i18n.confirmDelete)) {
            return;
        }
        var logId = $(this).data('log-id');
        var $row = $(this).closest('tr');

        $.post(mxrouteMailer.ajaxUrl, {
            action: 'mxroute_delete_log',
            nonce: mxrouteMailer.logManageNonce,
            log_id: logId
        }, function (response) {
            if (response.success) {
                $row.fadeOut(300, function () { $(this).remove(); });
            } else {
                alert(response.data && response.data.message ? response.data.message : mxrouteMailer.i18n.failedDelete);
            }
        }).fail(function () {
            alert(mxrouteMailer.i18n.failedDelete);
        });
    });

    $(document).on('click', '.mxroute-clear-logs', function (e) {
        e.preventDefault();
        if (!confirm(mxrouteMailer.i18n.confirmClear)) {
            return;
        }

        $.post(mxrouteMailer.ajaxUrl, {
            action: 'mxroute_clear_logs',
            nonce: mxrouteMailer.logManageNonce
        }, function (response) {
            if (response.success) {
                $('.mxroute-logs-table tbody').empty();
                alert(response.data.message);
            } else {
                alert(response.data && response.data.message ? response.data.message : mxrouteMailer.i18n.failedClear);
            }
        }).fail(function () {
            alert(mxrouteMailer.i18n.failedClear);
        });
    });
})(jQuery);
