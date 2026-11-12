<?php
declare(strict_types=1);

require __DIR__ . '/config/config.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/i18n.php';
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/MontantEnLettres.php';

security_headers();

start_session();
handle_lang_switch();
