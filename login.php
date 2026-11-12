<?php
declare(strict_types=1);

require __DIR__ . '/shared/bootstrap.php';
require_once __DIR__ . '/shared/layout.php';

// Déjà connecté ?
if (core_user() !== null) {
    sh_redirect(sh_url('/index.php'));
}

$error = null;
$login = '';
$expired = isset($_GET['expired']);
$next = (string) ($_GET['next'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    core_csrf_check_or_fail();
    $login = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $next = (string) ($_POST['next'] ?? '');

    $error = core_login($login, $password, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    if ($error === null) {
        // Redirection uniquement vers une URL locale de l'application.
        if ($next !== '' && (str_starts_with($next, sh_base() . '/') || str_starts_with($next, '/modules/'))) {
            sh_redirect($next);
        }
        sh_redirect(sh_url('/index.php'));
    }
}

sh_auth_layout_start('Connexion');
?>
<div class="gos-auth-card">
    <div class="gos-auth-brand">
        <span class="gos-brand-mark">G</span>
        <div>
            <h1><?= sh_e(GOS_APP_NAME) ?></h1>
            <p>Stock · Crédits & Ventes · Chèques & Effets</p>
        </div>
    </div>

    <?php if ($expired): ?>
        <div class="gos-alert gos-alert-warning">Session expirée. Veuillez vous reconnecter.</div>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <div class="gos-alert gos-alert-error"><?= sh_e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= sh_e(sh_url('/login.php')) ?>" autocomplete="on">
        <?= core_csrf_field() ?>
        <input type="hidden" name="next" value="<?= sh_e($next) ?>">

        <div class="gos-form-field">
            <label for="login">Identifiant</label>
            <input type="text" id="login" name="login" value="<?= sh_e($login) ?>" required autofocus autocomplete="username" placeholder="Votre identifiant">
        </div>

        <div class="gos-form-field">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </div>

        <button type="submit" class="gos-btn gos-btn-primary gos-auth-submit">Se connecter</button>
    </form>

    <div class="gos-auth-links">
        <a href="<?= sh_e(sh_module_url('stock', '/dashboard')) ?>">Stock</a>
        <a href="<?= sh_e(sh_module_url('credits', '/index.php?p=dashboard')) ?>">Crédits</a>
        <a href="<?= sh_e(sh_module_url('cheques', '/index.php')) ?>">Chèques</a>
    </div>

    <div class="gos-auth-meta">
        <span>Une seule connexion pour les 3 modules</span>
        <span>v<?= sh_e(GOS_VERSION) ?></span>
    </div>
</div>
<?php
sh_auth_layout_end();
