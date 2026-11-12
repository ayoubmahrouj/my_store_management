<?php
declare(strict_types=1);

require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'save') {
        $id = post_int('id');
        $name = trim((string) post('name'));
        $phone = trim((string) post('phone'));
        $address = trim((string) post('address'));
        $limit = post_float('credit_limit');

        if ($name === '') {
            $errors[] = t('error_required');
        }

        if (! $errors) {
            if ($id > 0) {
                q('UPDATE clients SET name=?, phone=?, address=?, credit_limit=? WHERE id=? AND is_passenger = 0', [$name, $phone, $address, $limit, $id]);
            } else {
                q('INSERT INTO clients (name, phone, address, credit_limit) VALUES (?, ?, ?, ?)', [$name, $phone, $address, $limit]);
                $id = (int) db()->lastInsertId();
            }

            flash(t('saved'));
            redirect('clients');
        }
    }

    if ($action === 'toggle') {
        $id = post_int('id');
        $current = one('SELECT is_active FROM clients WHERE id = ? AND is_passenger = 0', [$id]);

        if ($current) {
            q('UPDATE clients SET is_active = ? WHERE id = ?', [$current['is_active'] ? 0 : 1, $id]);
            flash($current['is_active'] ? t('deleted') : t('restored'));
        }

        redirect('clients');
    }
}

/* ------------------------------- Filtres ------------------------------- */

$q = trim((string) get('q'));
$status = (string) get('status', 'active');
$owed = (string) get('owed');

$where = ' WHERE c.is_passenger = 0';
$params = [];

if ($q !== '') {
    $where .= ' AND (c.name LIKE ? OR c.phone LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($status === 'active') {
    $where .= ' AND c.is_active = 1';
} elseif ($status === 'inactive') {
    $where .= ' AND c.is_active = 0';
}

$base = 'SELECT c.*, COALESCE((SELECT SUM(remaining) FROM credits cr WHERE cr.client_id = c.id AND cr.status = \'open\'), 0) AS balance
         FROM clients c' . $where;
$balanceFilter = $owed === '1' ? ' WHERE t.balance > 0' : '';

/* ------------------------------ Pagination ------------------------------ */

$perPage = 50;
$countRow = one('SELECT COUNT(*) AS n FROM (' . $base . ') AS t' . $balanceFilter, $params);
$totalClients = (int) ($countRow['n'] ?? 0);
$pages = max(1, (int) ceil($totalClients / $perPage));
$page = min(max(1, (int) get('page', 1)), $pages);

$clients = all(
    'SELECT * FROM (' . $base . ') AS t' . $balanceFilter
    . ' ORDER BY t.name LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
    $params
);

/* ---------------------------- Édition / ajout --------------------------- */

$editing = null;

if ($editId = (int) get('edit')) {
    $editing = one('SELECT * FROM clients WHERE id = ?', [$editId]);
}

$showModal = $editing !== null || get('new') === '1' || $errors;

layout_start(t('clients'), 'clients');
print_header(t('clients'), $status === 'active' ? '' : t('show_inactive'));
?>

<div class="toolbar">
    <form class="filters" method="get" action="<?= BASE ?>/index.php">
        <input type="hidden" name="p" value="clients">
        <label class="field">
            <span><?= e(t('search')) ?></span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('name') . ' / ' . t('phone')) ?>">
        </label>
        <label class="field">
            <span><?= e(t('status')) ?></span>
            <select name="status">
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= e(t('active')) ?></option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>><?= e(t('inactive')) ?></option>
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>><?= e(t('all')) ?></option>
            </select>
        </label>
        <label class="field" style="min-width:auto">
            <span>&nbsp;</span>
            <label style="display:flex;align-items:center;gap:8px;height:40px">
                <input type="checkbox" name="owed" value="1" <?= $owed === '1' ? 'checked' : '' ?> style="width:auto">
                <span class="small"><?= e(t('balance')) ?> &gt; 0</span>
            </label>
        </label>
        <button class="btn btn-ghost" type="submit"><?= e(t('filter')) ?></button>
        <a class="btn btn-ghost" href="<?= url('clients') ?>"><?= e(t('reset')) ?></a>
    </form>
    <div class="spacer"></div>
    <?php print_button(); ?>
    <button class="btn btn-primary" type="button" data-action="modal-open" data-target="client-modal">+ <?= e(t('client_new')) ?></button>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th><?= e(t('name')) ?></th>
                <th><?= e(t('phone')) ?></th>
                <th class="num"><?= e(t('balance')) ?></th>
                <th class="num"><?= e(t('credit_limit')) ?></th>
                <th><?= e(t('status')) ?></th>
                <th class="actions"><?= e(t('actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (! $clients): ?>
                <tr><td colspan="6" class="empty"><?= e(t('no_results')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td>
                        <a href="<?= url('client', ['id' => $client['id']]) ?>" style="font-weight:600"><?= e($client['name']) ?></a>
                        <?php if ($client['address']): ?><div class="small muted"><?= e($client['address']) ?></div><?php endif; ?>
                    </td>
                    <td class="nowrap"><?= e($client['phone'] ?: '—') ?></td>
                    <td class="num" style="font-weight:700;color:<?= $client['balance'] > 0 ? 'var(--orange)' : 'inherit' ?>"><?= money($client['balance']) ?></td>
                    <td class="num muted"><?= money($client['credit_limit']) ?></td>
                    <td>
                        <?php if ($client['is_active']): ?>
                            <span class="badge badge-green"><?= e(t('active')) ?></span>
                        <?php else: ?>
                            <span class="badge badge-muted"><?= e(t('inactive')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="<?= url('client', ['id' => $client['id']]) ?>"><?= e(t('client_detail')) ?></a>
                        <a class="btn btn-ghost btn-sm" href="<?= url('clients', ['edit' => $client['id']]) ?>"><?= e(t('edit')) ?></a>
                        <form method="post" action="<?= url('clients') ?>" style="display:inline" data-confirm>
                            <input type="hidden" name="_token" value="<?= csrf() ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
                            <button class="btn btn-sm <?= $client['is_active'] ? 'btn-danger' : 'btn-ghost' ?>" type="submit"
                                    data-action="confirm" data-message="<?= e(t('confirm_disable')) ?>">
                                <?= e($client['is_active'] ? t('disable') : t('enable')) ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <div class="table-pager">
            <span class="pager-info"><?= $totalClients ?> <?= e(t('results')) ?></span>
            <div class="pager-nav">
                <a class="pager-btn" href="<?= $page <= 1 ? '#' : e(url('clients', ['q' => $q, 'status' => $status, 'owed' => $owed, 'page' => $page - 1])) ?>"
                   <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?> aria-label="Page précédente">&lsaquo;</a>
                <span class="pager-page"><?= $page ?> / <?= $pages ?></span>
                <a class="pager-btn" href="<?= $page >= $pages ? '#' : e(url('clients', ['q' => $q, 'status' => $status, 'owed' => $owed, 'page' => $page + 1])) ?>"
                   <?= $page >= $pages ? 'aria-disabled="true" tabindex="-1"' : '' ?> aria-label="Page suivante">&rsaquo;</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modale client -->
<div class="modal <?= $showModal ? 'is-open' : '' ?>" id="client-modal">
    <div class="modal-backdrop"></div>
    <div class="modal-box">
        <div class="modal-head">
            <h2><?= e($editing ? t('client_edit') : t('client_new')) ?></h2>
            <button class="btn btn-ghost btn-sm" type="button" data-action="modal-close"><?= e(t('close')) ?></button>
        </div>
        <form class="modal-body form" method="post" action="<?= url('clients') ?>">
            <input type="hidden" name="_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">

            <?php if ($errors): ?>
                <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>

            <label class="field">
                <span><?= e(t('name')) ?> *</span>
                <input type="text" name="name" value="<?= e($editing['name'] ?? post('name')) ?>" required>
            </label>
            <div class="form-row">
                <label class="field">
                    <span><?= e(t('phone')) ?></span>
                    <input type="text" name="phone" value="<?= e($editing['phone'] ?? post('phone')) ?>">
                </label>
                <label class="field">
                    <span><?= e(t('credit_limit')) ?></span>
                    <input type="number" step="0.01" min="0" name="credit_limit" value="<?= e($editing['credit_limit'] ?? post('credit_limit', '0')) ?>">
                </label>
            </div>
            <label class="field">
                <span><?= e(t('address')) ?></span>
                <input type="text" name="address" value="<?= e($editing['address'] ?? post('address')) ?>">
            </label>

            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button class="btn btn-ghost" type="button" data-action="modal-close"><?= e(t('cancel')) ?></button>
                <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php layout_end(); ?>
