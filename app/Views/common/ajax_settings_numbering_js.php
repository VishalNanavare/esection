<script>
$(document).ready(function () {
    var $prefixInput = $('#case_no_prefix');
    var $preview = $('#preview_example');
    var currentYear = new Date().getFullYear();

    // Cosmetic only -- the real suffix is a random 4-digit number chosen
    // server-side by generate_case_no(); this just echoes the format live
    // as the admin types, ahead of the authoritative server-rendered value.
    $prefixInput.on('input', function () {
        var prefix = ($(this).val() || 'CASE').toUpperCase();
        $preview.val(prefix + '-' + currentYear + '/0000');
    });
});
</script>
