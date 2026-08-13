<?php
/**
 * The "Retry all failed (N)" action block.
 *
 * Refreshed independently because retrying can drive the failed count to zero,
 * at which point this block disappears entirely -- leaving a stale button that
 * offers to retry emails that no longer exist.
 *
 * @var array $counts from EmailLogModel::statusCounts()
 */
?>
<?php // The toggle is the master kill switch for every send path, retries
      // included -- BulkEmailService::retry() refuses while it is off, so
      // showing the button would only offer a guaranteed error. ?>
<?php if (($counts['failed'] ?? 0) > 0 && feature_enabled('feature_bulk_email_enabled')): ?>
    <form action="<?= base_url('bulk-email/retryAllFailed') ?>" method="post" class="d-inline js-ajax"
          data-title="Sent emails"
          data-refresh='<?= esc(json_encode([
              '#log_rows'         => 'html',
              '#log_actions'      => 'actionsHtml',
              '#log_status_filter'=> 'filterHtml',
              '#log_pager_region' => 'pagerHtml',
          ]), 'attr') ?>'
          data-busy-button="Retrying..."
          data-busy-title="Retrying failed emails..."
          data-busy-text="Each message is re-sent one at a time. This can take a while."
          data-confirm-title="Retry all failed emails?"
          data-confirm-text="<?= (int) $counts['failed'] ?> message(s) will be sent again."
          data-confirm-button="Yes, retry them">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-glass text-danger me-1">
            <i class="fa fa-refresh me-1"></i> Retry all failed (<?= (int) $counts['failed'] ?>)
        </button>
    </form>
<?php endif; ?>
