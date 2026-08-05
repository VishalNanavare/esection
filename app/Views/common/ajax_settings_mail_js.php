<script>
$(document).ready(function () {
    // A test send waits on a real SMTP handshake, which can take a few
    // seconds (or stall on a wrong host until the timeout). Without feedback
    // the button looks broken and gets clicked repeatedly.
    $('.mail-test-form').on('submit', function () {
        $(this).find('button[type="submit"]')
            .prop('disabled', true)
            .html('<i class="fa fa-spinner fa-spin me-1"></i> Sending...');
    });
});
</script>
