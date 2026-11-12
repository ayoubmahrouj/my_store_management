<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

core_logout();
sh_redirect(sh_url('/login.php'));
