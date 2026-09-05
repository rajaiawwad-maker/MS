$(function() {
    $('body').on('click', '.confirm-delete', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        if (confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
            window.location.href = href;
        }
    });

    $('body').on('click', '.confirm-action', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        var msg = $(this).data('confirm') || 'Are you sure?';
        if (confirm(msg)) { window.location.href = href; }
    });

    $('body').on('submit', 'form', function() {
        var btn = $(this).find('button[type="submit"], input[type="submit"]');
        if (btn.length) {
            btn.prop('disabled', true).append(' <i class="fas fa-spinner fa-spin"></i>');
        }
    });

    $('body').on('change', 'input[type="number"][data-min]', function() {
        var min = parseFloat($(this).data('min') || 0);
        if (parseFloat($(this).val()) < min) $(this).val(min);
    });
    $('body').on('change', 'input[type="number"][data-max]', function() {
        var max = parseFloat($(this).data('max'));
        if (parseFloat($(this).val()) > max) $(this).val(max);
    });

    if ($('.dataTable').length) {
        $('.dataTable').each(function() {
            var opts = { pageLength: 25, responsive: true };
            if ($(this).data('order')) opts.order = $(this).data('order');
            if (!$.fn.DataTable) return;
            if (!$.fn.DataTable.isDataTable(this)) $(this).DataTable(opts);
        });
    }
});

function buildWhatsappMessage(phone, text) {
    phone = (phone || '').toString().replace(/\D/g, '');
    if (!phone) return '#';
    var url = 'https://wa.me/' + phone;
    if (text) url += '?text=' + encodeURIComponent(text);
    return url;
}
