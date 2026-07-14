(function ($) {
    'use strict';

    $(document).on('click', '.mxroute-view-log', function (e) {
        e.preventDefault();
        var logId = $(this).data('log-id');
        var $modal = $('#mxroute-log-modal');
        var $body = $('#mxroute-modal-body');

        $body.html('<p>Loading...</p>');
        $modal.css('display', 'flex');

        $.post(mxrouteMailer.ajaxUrl, {
            action: 'mxroute_log_detail',
            nonce: mxrouteMailer.logViewNonce,
            log_id: logId
        }, function (response) {
            if (response.success) {
                $body.html(response.data.html);
            } else {
                $body.html('<p>' + (response.data && response.data.message ? response.data.message : mxrouteMailer.i18n.failedLoad) + '</p>');
            }
        }).fail(function () {
            $body.html('<p>' + mxrouteMailer.i18n.failedLoad + '</p>');
        });
    });

    $(document).on('click', '.mxroute-modal-close', function () {
        $('#mxroute-log-modal').css('display', 'none');
    });

    $(document).on('click', function (e) {
        if ($(e.target).is('#mxroute-log-modal')) {
            $('#mxroute-log-modal').css('display', 'none');
        }
    });

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
