        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/app.js?v=<?= md5_file(__DIR__ . '/../assets/js/app.js') ?>"></script>
<script>
    $(function() {
        function isMobile() { return window.matchMedia('(max-width: 767.98px)').matches; }
        $('#sidebarCollapse').on('click', function() {
            $('#sidebar, #content').toggleClass('active');
            if (isMobile()) {
                if ($('#sidebar').hasClass('active')) {
                    $('#sidebarOverlay').addClass('show');
                } else {
                    $('#sidebarOverlay').removeClass('show');
                }
            }
        });
        $('#sidebarOverlay').on('click', function() {
            $('#sidebar, #content').removeClass('active');
            $(this).removeClass('show');
        });
        $(window).on('resize', function() {
            if (!isMobile()) $('#sidebarOverlay').removeClass('show');
        });
        $('.select2').select2({ theme: 'bootstrap', width: '100%' });
        $('.datepicker').flatpickr({ dateFormat: 'd/m/Y' });
        $('.datetimepicker').flatpickr({ dateFormat: 'd/m/Y H:i', enableTime: true, time_24hr: true });
        $('[data-toggle="tooltip"]').tooltip();
        setTimeout(function() { $(".alert:not(.alert-important)").fadeOut(3000); }, 2000);
    });
</script>
</body>
</html>
