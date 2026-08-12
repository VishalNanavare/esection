<?php
/**
 * The "Status" summary on the Email settings screen.
 *
 * Extracted because saving the SMTP form changes all three lines, and with no
 * page reload they would otherwise keep showing what the page was first
 * rendered with -- an admin who has just supplied a password would still be
 * told "Password: not set".
 *
 * @var array $settings from MailSettingsService::getAll()
 */
?>
<strong class="text-dark d-block mb-1">Status</strong>
SMTP server: <?= $settings['mail_smtp_host'] !== '' ? '<span class="text-emerald">set</span>' : '<span class="text-amber">not set</span>' ?><br>
From address: <?= $settings['mail_from_email'] !== '' ? '<span class="text-emerald">set</span>' : '<span class="text-amber">not set</span>' ?><br>
Password: <?= $settings['password_configured'] ? '<span class="text-emerald">saved (encrypted)</span>' : '<span class="text-amber">not set</span>' ?>
