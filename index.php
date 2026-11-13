<?php
declare(strict_types=1);

require __DIR__ . '/shared/bootstrap.php';
require_once __DIR__ . '/shared/layout.php';

/* -------------------------------------------------------------------------
| Vérifie que la base est installée
|-------------------------------------------------------------------------*/

try {
    core_db()->query('SELECT 1 FROM users LIMIT 1');
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Installation requise</title>'
        . '<body style="font-family:system-ui;background:#080b10;color:#e7eef8;padding:48px;line-height:1.6">'
        . '<h1>Base de données non installée</h1>'
        . '<p>Lancez dans un terminal, depuis le dossier <code>gestion_unifiee</code> :</p>'
        . '<pre style="background:#0f151e;padding:16px;border-radius:12px">php database/install.php --fresh<br>php database/migrate_legacy.php --force</pre>'
        . '<p style="color:#8fa1b8">Détail : ' . sh_e($e->getMessage()) . '</p></body></html>';
    exit;
}

core_require_login();

$user = core_user();
$metrics = [];

/* -------------------------------------------------------------------------
| Indicateurs des 3 modules (tolérants aux bases vides)
|-------------------------------------------------------------------------*/

function safe_scalar(string $sql, array $params = []): float
{
    try {
        $value = core_scalar($sql, $params);
        return $value === false || $value === null ? 0.0 : (float) $value;
    } catch (Throwable) {
        return 0.0;
    }
}

$metrics['products'] = (int) safe_scalar('SELECT COUNT(*) FROM products WHERE active = 1');
$metrics['stock_value'] = safe_scalar(
    'SELECT COALESCE(SUM(sl.qty * p.purchase_cost), 0)
     FROM stock_levels sl JOIN products p ON p.id = sl.product_id WHERE p.active = 1'
);
$metrics['low_stock'] = (int) safe_scalar(
    'SELECT COUNT(*) FROM (
        SELECT p.id FROM products p
        LEFT JOIN stock_levels sl ON sl.product_id = p.id
        WHERE p.active = 1
        GROUP BY p.id, p.min_stock
        HAVING COALESCE(SUM(sl.qty), 0) < p.min_stock
     ) low'
);
$metrics['drafts'] = (int) safe_scalar("SELECT COUNT(*) FROM stock_documents WHERE status = 'brouillon'");

$metrics['credit_due'] = safe_scalar("SELECT COALESCE(SUM(remaining), 0) FROM credits WHERE status = 'open'");
$metrics['clients'] = (int) safe_scalar('SELECT COUNT(*) FROM clients WHERE is_active = 1');
$metrics['sales_today'] = safe_scalar('SELECT COALESCE(SUM(total), 0) FROM sales WHERE date = CURDATE()');

$metrics['cheques_soon'] = (int) safe_scalar(
    'SELECT COUNT(*) FROM cheques_documents
     WHERE regle = 0 AND rejete = 0 AND date_echeance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)'
);
$metrics['cheques_unpaid'] = safe_scalar('SELECT COALESCE(SUM(montant), 0) FROM cheques_documents WHERE regle = 0 AND rejete = 0');
$metrics['cheques_rejected'] = (int) safe_scalar('SELECT COUNT(*) FROM cheques_documents WHERE rejete = 1');

$recent = [];
try {
    $recent = core_all(
        'SELECT a.action, a.entity_type, a.created_at, u.name AS user_name
         FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.id DESC LIMIT 8'
    );
} catch (Throwable) {
    $recent = [];
}

$nav = [];
if (core_is_admin()) {
    $nav[sh_t('administration')] = [
        ['key' => 'users', 'label' => sh_t('users'), 'href' => sh_module_url('stock', '/utilisateurs'), 'icon' => 'users'],
        ['key' => 'settings', 'label' => sh_t('settings'), 'href' => sh_module_url('stock', '/parametres'), 'icon' => 'settings'],
    ];
}

sh_layout_start([
    'title' => sh_t('dashboard'),
    'module' => 'home',
    'active' => '',
    'nav' => $nav,
    'subtitle' => sh_t('overview') . ' · ' . sh_date_long(),
]);
?>

<section class="gos-kpis">
    <div class="gos-kpi">
        <div class="gos-kpi-label"><?= sh_e(sh_t('kpi_products')) ?></div>
        <div class="gos-kpi-value"><?= number_format($metrics['products'], 0, ',', ' ') ?></div>
        <div class="gos-kpi-hint"><?= sh_e(sh_t('kpi_stock_value', ['amount' => sh_money($metrics['stock_value'])])) ?></div>
    </div>
    <div class="gos-kpi green">
        <div class="gos-kpi-label"><?= sh_e(sh_t('kpi_credits_open')) ?></div>
        <div class="gos-kpi-value"><?= sh_money($metrics['credit_due']) ?></div>
        <div class="gos-kpi-hint"><?= sh_e(sh_t('kpi_clients_active', ['count' => number_format($metrics['clients'], 0, ',', ' ')])) ?></div>
    </div>
    <div class="gos-kpi gold">
        <div class="gos-kpi-label"><?= sh_e(sh_t('kpi_cheques_soon')) ?></div>
        <div class="gos-kpi-value"><?= number_format($metrics['cheques_soon'], 0, ',', ' ') ?></div>
        <div class="gos-kpi-hint"><?= sh_e(sh_t('kpi_unpaid', ['amount' => sh_money($metrics['cheques_unpaid'])])) ?></div>
    </div>
    <div class="gos-kpi danger">
        <div class="gos-kpi-label"><?= sh_e(sh_t('kpi_alerts')) ?></div>
        <div class="gos-kpi-value"><?= number_format($metrics['low_stock'] + $metrics['cheques_rejected'], 0, ',', ' ') ?></div>
        <div class="gos-kpi-hint"><?= sh_e(sh_t('kpi_alerts_hint', ['low' => (int) $metrics['low_stock'], 'rejected' => (int) $metrics['cheques_rejected']])) ?></div>
    </div>
</section>

<section class="gos-grid gos-grid-3" style="margin-top:16px">
    <article class="gos-module-card">
        <div class="gos-module-icon"><?= sh_nav_icon('box') ?></div>
        <h3><?= sh_e(sh_t('stock')) ?></h3>
        <p><?= sh_e(sh_t('module_stock_desc')) ?></p>
        <div class="gos-module-links">
            <a href="<?= sh_e(sh_module_url('stock', '/dashboard')) ?>"><?= sh_e(sh_t('link_dashboard')) ?></a>
            <a href="<?= sh_e(sh_module_url('stock', '/produits')) ?>"><?= sh_e(sh_t('link_products')) ?></a>
            <a href="<?= sh_e(sh_module_url('stock', '/entrees')) ?>"><?= sh_e(sh_t('link_entries')) ?></a>
            <a href="<?= sh_e(sh_module_url('stock', '/sorties')) ?>"><?= sh_e(sh_t('link_exits')) ?></a>
            <a href="<?= sh_e(sh_module_url('stock', '/referentiels')) ?>"><?= sh_e(sh_t('link_references')) ?></a>
        </div>
    </article>

    <article class="gos-module-card">
        <div class="gos-module-icon"><?= sh_nav_icon('cart') ?></div>
        <h3><?= sh_e(sh_t('credits')) ?></h3>
        <p><?= sh_e(sh_t('module_credits_desc')) ?></p>
        <div class="gos-module-links">
            <a href="<?= sh_e(sh_module_url('credits', '/index.php?p=dashboard')) ?>"><?= sh_e(sh_t('link_dashboard')) ?></a>
            <a href="<?= sh_e(sh_module_url('credits', '/index.php?p=pos')) ?>"><?= sh_e(sh_t('link_pos')) ?></a>
            <a href="<?= sh_e(sh_module_url('credits', '/index.php?p=clients')) ?>"><?= sh_e(sh_t('link_clients')) ?></a>
            <a href="<?= sh_e(sh_module_url('credits', '/index.php?p=credits')) ?>"><?= sh_e(sh_t('link_credits')) ?></a>
            <a href="<?= sh_e(sh_module_url('credits', '/index.php?p=payments')) ?>"><?= sh_e(sh_t('link_payments')) ?></a>
        </div>
    </article>

    <article class="gos-module-card">
        <div class="gos-module-icon"><?= sh_nav_icon('wallet') ?></div>
        <h3><?= sh_e(sh_t('cheques')) ?></h3>
        <p><?= sh_e(sh_t('module_cheques_desc')) ?></p>
        <div class="gos-module-links">
            <a href="<?= sh_e(sh_module_url('cheques', '/index.php')) ?>"><?= sh_e(sh_t('link_dashboard')) ?></a>
            <a href="<?= sh_e(sh_module_url('cheques', '/documents/index.php')) ?>"><?= sh_e(sh_t('link_documents')) ?></a>
            <a href="<?= sh_e(sh_module_url('cheques', '/beneficiaires/index.php')) ?>"><?= sh_e(sh_t('link_beneficiaries')) ?></a>
            <a href="<?= sh_e(sh_module_url('cheques', '/calendrier/index.php')) ?>"><?= sh_e(sh_t('link_calendar')) ?></a>
            <a href="<?= sh_e(sh_module_url('cheques', '/alertes/index.php')) ?>"><?= sh_e(sh_t('link_alerts')) ?></a>
        </div>
    </article>
</section>

<section class="gos-grid gos-grid-2" style="margin-top:16px">
    <div class="gos-card">
        <div class="gos-card-title"><h2><?= sh_e(sh_t('recent_activity')) ?></h2><span class="gos-badge blue"><?= sh_e(sh_t('events', ['count' => count($recent)])) ?></span></div>
        <?php if ($recent === []): ?>
            <div class="gos-empty"><?= sh_e(sh_t('no_activity')) ?></div>
        <?php else: ?>
            <table class="gos-table">
                <thead><tr><th><?= sh_e(sh_t('col_action')) ?></th><th><?= sh_e(sh_t('col_item')) ?></th><th><?= sh_e(sh_t('col_user')) ?></th><th><?= sh_e(sh_t('col_date')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($recent as $entry): ?>
                    <tr>
                        <td><strong><?= sh_e((string) $entry['action']) ?></strong></td>
                        <td><?= sh_e((string) ($entry['entity_type'] ?? '—')) ?></td>
                        <td><?= sh_e((string) ($entry['user_name'] ?? sh_t('system'))) ?></td>
                        <td><?= sh_e(date('d/m/Y H:i', strtotime((string) $entry['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="gos-card">
        <div class="gos-card-title"><h2><?= sh_e(sh_t('quick_start')) ?></h2></div>
        <p class="gos-help" style="margin-top:0"><?= sh_t('quick_start_help') ?></p>
        <ul style="margin:10px 0 0;padding-inline-start:18px;color:var(--gos-muted);font-size:12.5px;line-height:1.9">
            <li><?= sh_e(sh_t('quick_start_refs')) ?> <a href="<?= sh_e(sh_module_url('stock', '/referentiels')) ?>"><?= sh_e(sh_t('stock')) ?> → <?= sh_e(sh_t('link_references')) ?></a>.</li>
            <li><?= sh_e(sh_t('quick_start_client')) ?> <a href="<?= sh_e(sh_module_url('credits', '/index.php?p=clients')) ?>"><?= sh_e(sh_t('credits')) ?> → <?= sh_e(sh_t('link_clients')) ?></a>.</li>
            <li><?= sh_e(sh_t('quick_start_cheque')) ?> <a href="<?= sh_e(sh_module_url('cheques', '/documents/form.php')) ?>"><?= sh_e(sh_t('cheques')) ?> → <?= sh_e(sh_t('new')) ?></a>.</li>
            <?php if (core_is_admin()): ?>
                <li><?= sh_e(sh_t('quick_start_users')) ?> <a href="<?= sh_e(sh_module_url('stock', '/utilisateurs')) ?>"><?= sh_e(sh_t('users')) ?></a>.</li>
            <?php endif; ?>
        </ul>
        <?php if ((int) $metrics['drafts'] > 0): ?>
            <div class="gos-alert gos-alert-warning" style="margin-top:14px">
                <?= sh_e(sh_t('drafts_alert', ['count' => (int) $metrics['drafts']])) ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
sh_layout_end();
