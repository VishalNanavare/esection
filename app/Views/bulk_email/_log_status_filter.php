<?php
/**
 * The status <select> options.
 *
 * Refreshed separately because the counts come from an UNFILTERED query, so a
 * retry moves them regardless of which filter is active.
 *
 * @var array  $counts
 * @var string $status
 */
?>
<option value="" <?= $status === '' ? 'selected' : '' ?>>-- All (<?= (int) (($counts['sent'] ?? 0) + ($counts['failed'] ?? 0)) ?>) --</option>
<option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent (<?= (int) ($counts['sent'] ?? 0) ?>)</option>
<option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed (<?= (int) ($counts['failed'] ?? 0) ?>)</option>
