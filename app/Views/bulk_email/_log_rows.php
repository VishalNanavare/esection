<?php
/**
 * The <tbody> contents of the sent-email log.
 *
 * @var array  $rows
 * @var string $status
 * @var string $search
 */
?>
<?php if (empty($rows)): ?>
    <tr><td colspan="6" class="text-center text-muted py-4">
        <?= ($status || $search) ? 'No emails match the current filters.' : 'No emails have been sent yet.' ?>
    </td></tr>
<?php else: ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="small text-muted"><?= esc($r['created_at']) ?></td>
            <td>
                <div class="fw-semibold text-dark"><?= esc($r['recipient_name'] ?: '-') ?></div>
                <div class="small text-muted"><?= esc($r['recipient_email']) ?></div>
            </td>
            <td class="small text-muted"><?= esc(ucfirst((string) $r['recipient_type'])) ?></td>
            <td class="small"><?= esc($r['subject'] ?: '-') ?></td>
            <td>
                <?php if ($r['status'] === 'sent'): ?>
                    <span class="badge badge-glass-emerald"><i class="fa fa-check-circle me-1"></i> Sent</span>
                <?php else: ?>
                    <span class="badge badge-glass-indigo text-danger" title="<?= esc($r['error_message'] ?: '') ?>">
                        <i class="fa fa-exclamation-circle me-1"></i> Failed
                    </span>
                    <?php if (!empty($r['error_message'])): ?>
                        <div class="small text-muted mt-1" style="max-width:260px;"><?= esc(mb_strimwidth((string) $r['error_message'], 0, 120, '...')) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ((int) $r['attempts'] > 1): ?>
                    <div class="small text-muted"><?= (int) $r['attempts'] ?> attempts</div>
                <?php endif; ?>
            </td>
            <td class="text-end">
                <?php // Matches the guard in BulkEmailService::retry(): while the
                      // bulk-email toggle is off, every retry path refuses. ?>
                <?php if ($r['status'] === 'failed' && feature_enabled('feature_bulk_email_enabled')): ?>
                    <form action="<?= base_url('bulk-email/retry/' . $r['id']) ?>" method="post" class="d-inline js-ajax"
                          data-title="Sent emails"
                          data-refresh='<?= esc(json_encode([
                              '#log_rows'         => 'html',
                              '#log_actions'      => 'actionsHtml',
                              '#log_status_filter'=> 'filterHtml',
                              '#log_pager_region' => 'pagerHtml',
                          ]), 'attr') ?>'
                          data-keep-query="1"
                          data-busy-button="Retrying..."
                          data-busy-title="Resending..."
                          data-busy-text="Connecting to the mail server.">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-glass text-primary"><i class="fa fa-refresh"></i> Retry</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
