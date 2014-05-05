WP_WAITLISTS = {};
WP_WAITLISTS.UI = {};

WP_WAITLISTS.UI.init = function () {
    // TODO: Hide on first load?
    if (!jQuery('#wp-waitlist_enabled').is(':checked')) { jQuery('#wp-waitlist_lists-meta-box .form-table').hide(); }
    jQuery('#wp-waitlist_enabled').click(function (e) {
        var list_ui = jQuery('#wp-waitlist_lists-meta-box .form-table')
        if (list_ui.is(':visible')) {
            list_ui.hide();
        } else {
            list_ui.show();
        }
    });
    jQuery('#wp-waitlist_lists-meta-box [class~="wp-waitlist_list-remove"]').each(function () {
        jQuery(this).click(function (e) {
            e.preventDefault();
            var rows = jQuery(this).closest('tbody').find('tr');
            if (rows.length > 1) { // Don't remove if this is the only row.
                jQuery(this).closest('tr').remove();
            }
        });
    });
    jQuery('#wp-waitlist_list-new').each(function () {
        jQuery(this).click(function (e) {
            e.preventDefault();
            var new_row = jQuery('#wp-waitlist_lists-meta-box tr[id]:last-child').clone(true);
            // Clear inputs in cloned row.
            jQuery(new_row.find('input')).each(function () {
                jQuery(this).val('');
            });
            jQuery(new_row.find('select')).each(function () {
                jQuery(this).removeAttr('selected');
            });
            jQuery(new_row.find('[id^=wp-waitlist_list-users-]')).remove();
            var row_num = parseInt(jQuery(new_row).attr('id').match(/[0-9]+$/)[0]);
            jQuery(new_row).attr('id', jQuery(new_row).attr('id').replace(/[0-9]+$/, row_num + 1));
            jQuery(new_row).find('[id]').each(function () {
                jQuery(this).attr('id', jQuery(this).attr('id').replace(/[0-9]+$/, row_num + 1));
            });
            jQuery(new_row).find('[name]').each(function () {
                jQuery(this).attr('name', jQuery(this).attr('name').replace(/\[[0-9]+\]/, '[' + (row_num + 1) + ']'));
            });
            jQuery('#wp-waitlist_lists-meta-box tbody').append(new_row);
        });
    });
};

WP_WAITLISTS.init = function () {
    WP_WAITLISTS.UI.init();
};
window.addEventListener('DOMContentLoaded', WP_WAITLISTS.init);
