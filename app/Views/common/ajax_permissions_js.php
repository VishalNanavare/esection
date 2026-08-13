<script>
$(document).ready(function () {

    // Behaviour for the grouped permission cards (settings/_permission_groups.php).
    // Delegated on document, because the cards appear inside modals that are
    // re-rendered and inside the Access Rights screen that swaps them per user.

    /**
     * The card set an element belongs to.
     *
     * The per-card "Select all" sits INSIDE .permission-groups, but the toolbar
     * ("Select all" / "Clear all" beside the Permission heading) is rendered by
     * the host above it -- so .closest() returned an empty set for those two and
     * every global button silently did nothing. Fall back to searching the
     * enclosing form/modal, which is where the card set actually lives.
     */
    function scopeOf($el) {
        var $scope = $el.closest('.permission-groups');

        if ($scope.length > 0) {
            return $scope;
        }

        var $host = $el.closest('form, .modal-content, .glass-card');

        return $host.length > 0 ? $host.find('.permission-groups').first() : $();
    }

    // Every action in a module implies that module's `view`. Ticking Delete
    // without View would otherwise create a user who may delete records but
    // cannot open the page holding them -- a state nobody could diagnose from
    // the screen. AccessRightsService::validatePageKeys() enforces the same
    // rule, so a hand-crafted POST cannot bypass this either.
    function applyViewImplication($scope, module) {
        var $module = $scope.find('[data-perm-module="' + module + '"]');
        var $checks = $module.find('.js-perm-check');
        var $view   = $module.find('.js-perm-view');
        var anyNonViewTicked = $checks.filter(':checked').not('.js-perm-view').length > 0;

        if (anyNonViewTicked) {
            $view.prop('checked', true).prop('disabled', true);
        } else {
            $view.prop('disabled', false);
        }
    }

    function refresh($scope) {
        if ($scope.length === 0) { return; }

        $scope.find('.permission-card').each(function () {
            applyViewImplication($scope, $(this).data('perm-module'));
        });

        var total = $scope.find('.js-perm-check').length;
        var on    = $scope.find('.js-perm-check:checked').length;

        // The counter and the footer summary live OUTSIDE .permission-groups
        // (the host supplies its own toolbar and modal footer), so walk up to
        // the nearest form or modal rather than searching inside the scope.
        var $host = $scope.closest('form, .modal-content, .glass-card');
        if ($host.length === 0) { $host = $scope.parent(); }

        $host.find('.perm-count').text(on + ' selected');

        $host.find('.perm-summary').text(
            on === 0
                ? 'No permissions selected'
                : on + ' of ' + total + ' permissions selected'
        );
    }

    $(document).on('change', '.js-perm-check', function () {
        refresh(scopeOf($(this)));
    });

    $(document).on('click', '.js-perm-group-all', function () {
        var $btn    = $(this);
        var $scope  = scopeOf($btn);
        var $checks = $scope.find('[data-perm-module="' + $btn.data('perm-module') + '"] .js-perm-check');
        // Toggle: if everything in the card is already on, this clears it.
        var turnOn  = $checks.filter(':checked').length !== $checks.length;

        $checks.prop('disabled', false).prop('checked', turnOn);
        refresh($scope);
    });

    $(document).on('click', '.js-perm-all', function () {
        var $scope = scopeOf($(this));
        $scope.find('.js-perm-check').prop('disabled', false).prop('checked', true);
        refresh($scope);
    });

    $(document).on('click', '.js-perm-none', function () {
        var $scope = scopeOf($(this));
        $scope.find('.js-perm-check').prop('disabled', false).prop('checked', false);
        refresh($scope);
    });

    // A disabled checkbox is NOT serialized by jQuery, so the implied `view`
    // would silently never be submitted. The server re-applies the implication
    // regardless, but relying on that alone would make the posted payload
    // disagree with what the operator saw ticked.
    //
    // This MUST be a native capture-phase listener, not $(document).on('submit').
    // The .js-ajax handler in ajax_common_js.php is also delegated on document,
    // so both would sit on the same node and fire in BINDING order -- and that
    // file is included first, so it called serialize() while the boxes were
    // still disabled and this ran uselessly afterwards. Capture runs before any
    // bubbling handler regardless of the order the partials are included in.
    document.addEventListener('submit', function (e) {
        var form = e.target;

        if (! form || typeof form.querySelectorAll !== 'function') { return; }

        form.querySelectorAll('.js-perm-check:disabled').forEach(function (box) {
            box.disabled = false;
        });
    }, true);

    // --- Role: Admin has no permissions to set -------------------------------
    // Admin bypasses AccessFilter by role and deliberately holds ZERO grant
    // rows, and UserManagementService skips grant handling entirely when the
    // role is admin. Leaving 32 chips on screen would imply they do something.
    function syncRoleVisibility($form) {
        var isAdmin = $form.find('.js-role-select').val() === 'admin';

        $form.find('.access-pages-block .permission-groups').toggleClass('d-none', isAdmin);
        $form.find('.access-pages-block .perm-count, .access-pages-block .js-perm-all, .access-pages-block .js-perm-none')
             .toggleClass('d-none', isAdmin);
        $form.find('.js-admin-note').toggleClass('d-none', ! isAdmin);
        $form.find('.perm-summary').toggleClass('d-none', isAdmin);
    }

    $(document).on('change', '.js-role-select', function () {
        syncRoleVisibility($(this).closest('form'));
    });

    // Initial paint for any card set already on the page.
    $('.permission-groups').each(function () {
        refresh($(this));
    });

    $('.js-role-select').each(function () {
        syncRoleVisibility($(this).closest('form'));
    });

    // Modals render their cards before the user opens them, and the Access
    // Rights screen swaps them in via AJAX; both need a repaint.
    $(document).on('shown.bs.modal', function (e) {
        var $modal = $(e.target);
        $modal.find('.permission-groups').each(function () { refresh($(this)); });
        $modal.find('.js-role-select').each(function () { syncRoleVisibility($(this).closest('form')); });
    });

    $(document).on('es:permissions-rendered', function () {
        $('.permission-groups').each(function () { refresh($(this)); });
    });
});
</script>
