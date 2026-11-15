<?php
declare(strict_types=1);

require_login();

if (get('new') === '1') {
    redirect('payment_new', ['client_id' => (int) get('client_id')]);
}

$q = trim((string) get('q'));
$from = (string) get('from', date('Y-m-01'));
$to = (string) get('to', today());
$method = (string) get('method');
$clientId = (int) get('client_id');

$fromSql = 'FROM payments p
        JOIN clients c ON c.id = p.client_id
        LEFT JOIN users u ON u.id = p.user_id
        WHERE p.date BETWEEN ? AND ?';
$where = '';
$params = [$from, $to];

if ($q !== '') {
    $where .= ' AND (c.name LIKE ? OR c.phone LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if (in_array($method, ['cash', 'card', 'transfer'], true)) {
    $where .= ' AND p.method = ?';
    $params[] = $method;
}

if ($clientId > 0) {
    $where .= ' AND p.client_id = ?';
    $params[] = $clientId;
}

$totals = one(
    'SELECT COUNT(*) AS n, COALESCE(SUM(p.amount), 0) AS amount_sum,
            COUNT(DISTINCT p.client_id) AS clients_n '
    . $fromSql . $where,
    $params
);

$p = paginate((int) ($totals['n'] ?? 0), 50);

$payments = all(
    'SELECT p.*, c.name AS client_name, c.phone AS client_phone, u.name AS user_name '
    . $fromSql . $where
    . ' ORDER BY p.date DESC, p.id DESC'
    . ' LIMIT ' . $p['per_page'] . ' OFFSET ' . $p['offset'],
    $params
);

$sumAmount = (float) ($totals['amount_sum'] ?? 0);

$clients = all('SELECT id, name FROM clients WHERE is_active = 1 AND is_passenger = 0 ORDER BY name');

layout_start(t('payments'), 'payments');
print_header(t('payments'), fr_date($from) . ' → ' . fr_date($to));
?>

<div class="toolbar">
    <form class="filters" method="get" action="<?= BASE ?>/index.php">
        <input type="hidden" name="p" value="payments">
        <label class="field">
            <span><?= e(t('search')) ?></span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('client') . ' / ' . t('phone')) ?>">
        </label>
        <label class="field">
            <span><?= e(t('from')) ?></span>
            <input type="date" name="from" value="<?= e($from) ?>">
        </label>
        <label class="field">
            <span><?= e(t('to')) ?></span>
            <input type="date" name="to" value="<?= e($to) ?>">
        </label>
        <label class="field">
            <span><?= e(t('client')) ?></span>
            <select name="client_id" data-quick="client">
                <option value=""><?= e(t('all')) ?></option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= (int) $client['id'] ?>" <?= $clientId === (int) $client['id'] ? 'selected' : '' ?>><?= e($client['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span><?= e(t('method')) ?></span>
            <select name="method">
                <option value=""><?= e(t('all')) ?></option>
                <option value="cash" <?= $method === 'cash' ? 'selected' : '' ?>><?= e(t('cash')) ?></option>
                <option value="card" <?= $method === 'card' ? 'selected' : '' ?>><?= e(t('card')) ?></option>
                <option value="transfer" <?= $method === 'transfer' ? 'selected' : '' ?>><?= e(t('transfer')) ?></option>
            </select>
        </label>
        <button class="btn btn-ghost" type="submit"><?= e(t('filter')) ?></button>
        <a class="btn btn-ghost" href="<?= url('payments') ?>"><?= e(t('reset')) ?></a>
    </form>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('export', ['type' => 'payments', 'from' => $from, 'to' => $to]) ?>"><?= e(t('export_excel')) ?></a>
    <?php pdf_button('payments', 0, ['q' => $q, 'from' => $from, 'to' => $to, 'method' => $method, 'client_id' => $clientId]); ?>
    <?php print_button(); ?>
    <a class="btn btn-primary" href="<?= url('payment_new') ?>">+ <?= e(t('payment_new')) ?></a>
</div>

<div class="grid grid-3">
    <div class="card stat">
        <div class="stat-label"><?= e(t('kpi_payments')) ?></div>
        <div class="stat-value" style="color:var(--green)"><?= money($sumAmount) ?></div>
        <div class="stat-foot"><?= number_format((int) $p['total'], 0, ',', ' ') ?> <?= e(t('payments')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('period')) ?></div>
        <div class="stat-value" style="font-size:16px"><?= fr_date($from) ?> → <?= fr_date($to) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('clients_count')) ?></div>
        <div class="stat-value"><?= number_format((int) ($totals['clients_n'] ?? 0), 0, ',', ' ') ?></div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table table-list table-payments" data-nopage>
            <thead>
            <tr>
                <th>#</th>
                <th><?= e(t('date')) ?></th>
                <th><?= e(t('client')) ?></th>
                <th class="num"><?= e(t('amount')) ?></th>
                <th><?= e(t('method')) ?></th>
                <th><?= e(t('notes')) ?></th>
                <th><?= e(t('nav_users')) ?></th>
                <th class="actions"><?= e(t('actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (! $payments): ?>
                <tr><td colspan="8" class="empty"><?= e(t('no_results')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td class="muted">#<?= (int) $payment['id'] ?></td>
                    <td><?= fr_date($payment['date']) ?></td>
                    <td>
                        <a href="<?= url('client', ['id' => $payment['client_id']]) ?>" style="font-weight:600"><?= e($payment['client_name']) ?></a>
                        <div class="small muted"><?= e($payment['client_phone'] ?: '') ?></div>
                    </td>
                    <td class="num" style="font-weight:700;color:var(--green)"><?= money($payment['amount']) ?></td>
                    <td><?= e(t($payment['method'])) ?></td>
                    <td class="muted"><?= e($payment['note'] ?: '—') ?></td>
                    <td class="muted"><?= e($payment['user_name'] ?: '—') ?></td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm btn-icon" href="<?= url('ticket', ['type' => 'payment', 'id' => $payment['id']]) ?>" target="_blank"
                           title="<?= e(t('receipt')) ?>" aria-label="<?= e(t('receipt')) ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
                        </a>
                        <?php if (is_admin()): ?>
                            <a class="btn btn-ghost btn-sm btn-icon" href="<?= url('payment_edit', ['id' => $payment['id']]) ?>"
                               title="<?= e(t('edit')) ?>" aria-label="<?= e(t('edit')) ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                            </a>
                            <form method="post" action="<?= url('payment_delete') ?>" style="display:inline">
                                <input type="hidden" name="_token" value="<?= csrf() ?>">
                                <input type="hidden" name="id" value="<?= (int) $payment['id'] ?>">
                                <button class="btn btn-danger btn-sm btn-icon" type="submit"
                                        title="<?= e(t('delete')) ?>" aria-label="<?= e(t('delete')) ?>"
                                        data-action="confirm" data-message="<?= e(t('confirm_delete_payment')) ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php pager($p); ?>
</div>

<?php layout_end(); ?>
