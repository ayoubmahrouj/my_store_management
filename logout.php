<?php
declare(strict_types=1);

require __DIR__ . '/shared/bootstrap.php';
require_once __DIR__ . '/shared/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    core_csrf_check_or_fail();
    core_logout();
    sh_redirect(sh_url('/login.php'));
}

// Déconnexion via un simple lien : on demande confirmation (CSRF).
sh_auth_layout_start('Déconnexion');
?>
<div class="gos-auth-card">
    <div class="gos-auth-brand">
        <span class="gos-brand-mark">G</span>
        <div><h1>Déconnexion</h1><p>Fermer la session en cours</p></div>
    </div>
    <p style="color:var(--gos-muted);font-size:13px">Voulez-vous vraiment vous déconnecter de <?= sh_e(GOS_APP_NAME) ?> ?</p>
    <form method="post" action="<?= sh_e(sh_url('/logout.php')) ?>" style="display:flex;gap:10px">
        <?= core_csrf_field() ?>
        <button type="submit" class="gos-btn gos-btn-danger">Se déconnecter</button>
        <a class="gos-btn" href="<?= sh_e(sh_url('/index.php')) ?>">Retour</a>
    </form>
</div>
<?php
sh_auth_layout_end();
