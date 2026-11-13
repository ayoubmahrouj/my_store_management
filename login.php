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

sh_auth_layout_start(sh_t('login_title'));
?>
<div class="gos-auth-card">
    <div class="gos-auth-brand">
        <span class="gos-brand-mark">G</span>
        <div>
            <h1><?= sh_e(GOS_APP_NAME) ?></h1>
            <p><?= sh_e(sh_t('modules_tagline')) ?></p>
        </div>
        <div class="gos-auth-lang"><?php sh_lang_switcher(); ?></div>
    </div>

    <?php if ($expired): ?>
        <div class="gos-alert gos-alert-warning"><?= sh_e(sh_t('session_expired')) ?></div>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <div class="gos-alert gos-alert-error"><?= sh_e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= sh_e(sh_url('/login.php')) ?>" autocomplete="on">
        <?= core_csrf_field() ?>
        <input type="hidden" name="next" value="<?= sh_e($next) ?>">

        <div class="gos-form-field">
            <label for="login"><?= sh_e(sh_t('identifier')) ?></label>
            <input type="text" id="login" name="login" value="<?= sh_e($login) ?>" required autofocus autocomplete="username" placeholder="<?= sh_e(sh_t('identifier_placeholder')) ?>">
        </div>

        <div class="gos-form-field">
            <label for="password"><?= sh_e(sh_t('password')) ?></label>
            <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </div>

        <button type="submit" class="gos-btn gos-btn-primary gos-auth-submit"><?= sh_e(sh_t('sign_in')) ?></button>
    </form>

    <div class="gos-auth-links">
        <a href="<?= sh_e(sh_module_url('stock', '/dashboard')) ?>"><?= sh_e(sh_t('stock')) ?></a>
        <a href="<?= sh_e(sh_module_url('credits', '/index.php?p=dashboard')) ?>"><?= sh_e(sh_t('credits')) ?></a>
        <a href="<?= sh_e(sh_module_url('cheques', '/index.php')) ?>"><?= sh_e(sh_t('cheques')) ?></a>
    </div>

    <div class="gos-auth-meta">
        <span><?= sh_e(sh_t('single_login')) ?></span>
        <span>v<?= sh_e(GOS_VERSION) ?></span>
    </div>
</div>
<?php
sh_auth_layout_end();
