<?php
/** @var string $content */
sh_auth_layout_start($title ?? 'Connexion');
echo '<div class="gos-auth-card">' . $content . '</div>';
sh_auth_layout_end();
