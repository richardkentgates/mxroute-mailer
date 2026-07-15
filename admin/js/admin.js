(function ($) {
    'use strict';

    function mxrouteAnnounce(message) {
        var $announcer = $('#mxroute-status-announcer');
        if ($announcer.length) {
            $announcer.text(message);
        }
    }

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
                $row.fadeOut(300, function () {
                    $(this).remove();
                    var $next = $('.mxroute-log-checkbox').first();
                    if ($next.length) {
                        $next.focus();
                    } else {
                        $('.mxroute-logs-wrap h1').attr('tabindex', '-1').focus();
                    }
                    mxrouteAnnounce(response.data.message);
                });
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
                $('.mxroute-logs-wrap h1').attr('tabindex', '-1').focus();
                mxrouteAnnounce(response.data.message);
            } else {
                alert(response.data && response.data.message ? response.data.message : mxrouteMailer.i18n.failedClear);
            }
        }).fail(function () {
            alert(mxrouteMailer.i18n.failedClear);
        });
    });

    $('#mxroute-select-all').on('change', function () {
        $('.mxroute-log-checkbox').prop('checked', this.checked);
    });

    $(document).on('change', '.mxroute-log-checkbox', function () {
        var total = $('.mxroute-log-checkbox').length;
        var checked = $('.mxroute-log-checkbox:checked').length;
        $('#mxroute-select-all').prop('checked', total === checked);
    });

    function mxrouteBulkDelete() {
        var ids = [];
        $('.mxroute-log-checkbox:checked').each(function () {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            alert(mxrouteMailer.i18n.noSelection);
            return;
        }

        if (!confirm(mxrouteMailer.i18n.confirmBulkDelete.replace('%d', ids.length))) {
            return;
        }

        $.post(mxrouteMailer.ajaxUrl, {
            action: 'mxroute_bulk_delete_logs',
            nonce: mxrouteMailer.logManageNonce,
            log_ids: ids
        }, function (response) {
            if (response.success) {
                $('.mxroute-log-checkbox:checked').closest('tr').fadeOut(300, function () {
                    $(this).remove();
                    var $next = $('.mxroute-log-checkbox').first();
                    if ($next.length) {
                        $next.focus();
                    } else {
                        $('.mxroute-logs-wrap h1').attr('tabindex', '-1').focus();
                    }
                });
                $('#mxroute-select-all').prop('checked', false);
                mxrouteAnnounce(response.data.message);
            } else {
                alert(response.data && response.data.message ? response.data.message : mxrouteMailer.i18n.failedBulkDelete);
            }
        }).fail(function () {
            alert(mxrouteMailer.i18n.failedBulkDelete);
        });
    }

    $('#mxroute-bulk-apply, #mxroute-bulk-apply-bottom').on('click', function (e) {
        e.preventDefault();
        var action = $(this).closest('.tablenav').find('select[name="action"]').val();
        if (action === 'bulk-delete') {
            mxrouteBulkDelete();
        }
    });
})(jQuery);
