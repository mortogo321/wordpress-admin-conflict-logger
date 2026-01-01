/**
 * Admin Conflict Logger - Admin Page Scripts
 */

(function($) {
    'use strict';

    // Refresh page
    $('#acl-refresh').on('click', function() {
        window.location.reload();
    });

    // Clear all logs
    $('#acl-clear-logs').on('click', function() {
        if (!confirm(aclAdmin.confirmClear)) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true);

        $.post(aclAdmin.ajaxUrl, {
            action: 'acl_clear_logs',
            nonce: aclAdmin.nonce
        })
        .done(function(response) {
            if (response.success) {
                window.location.reload();
            } else {
                alert('Failed to clear logs');
            }
        })
        .fail(function() {
            alert('Failed to clear logs');
        })
        .always(function() {
            $button.prop('disabled', false);
        });
    });

    // Delete single log
    $('.acl-delete-log').on('click', function() {
        if (!confirm(aclAdmin.confirmDelete)) {
            return;
        }

        var $button = $(this);
        var $row = $button.closest('tr');
        var logId = $button.data('log-id');

        $row.css('opacity', '0.5');

        $.post(aclAdmin.ajaxUrl, {
            action: 'acl_delete_log',
            nonce: aclAdmin.nonce,
            log_id: logId
        })
        .done(function(response) {
            if (response.success) {
                $row.fadeOut(300, function() {
                    $(this).remove();
                    // Update count
                    var $count = $('.acl-stat-number').first();
                    var current = parseInt($count.text(), 10);
                    $count.text(current - 1);
                });
            } else {
                $row.css('opacity', '1');
                alert('Failed to delete log');
            }
        })
        .fail(function() {
            $row.css('opacity', '1');
            alert('Failed to delete log');
        });
    });

    // Show stack trace modal
    $('.acl-show-stack').on('click', function() {
        var stack = $(this).data('stack');
        $('#acl-stack-content').text(stack);
        $('#acl-stack-modal').show();
    });

    // Close modal
    $('.acl-modal-close, .acl-modal').on('click', function(e) {
        if (e.target === this) {
            $('#acl-stack-modal').hide();
        }
    });

    // Close modal on escape
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#acl-stack-modal').hide();
        }
    });

})(jQuery);
