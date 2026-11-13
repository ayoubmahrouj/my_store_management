<?php
declare(strict_types=1);

/**
 * Bons remis par les sociétés partenaires.
 *
 * Cycle : le bon est remis (attente) → la société l'encaisse (encaisse)
 * → l'argent nous est versé (paye). Chaque bon peut être photographié.
 */

require_login();

$statuses = [
    'attente'  => t('bon_status_attente'),
    'encaisse' => t('bon_status_encaisse'),
    'paye'     => t('bon_status_paye'),
];

$statusBadges = [
    'attente'  => 'badge-orange',
    'encaisse' => 'badge-gold',
    'paye'     => 'badge-green',
];

$errors = [];
$form = [
    'id'           => 0,
    'client_id'    => '',
    'reference'    => '',
    'bon_date'     => today(),
    'amount'       => '',
    'status'       => 'attente',
    'received_at'  => '',
    'note'         => '',
    'image_path'   => null,
];
$showModal = false;

/* -------------------------------------------------------------------------
| Traitement des formulaires
|-------------------------------------------------------------------------*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) post('action');

    if ($action === 'save') {
        $id = post_int('id');
        $clientId = post_int('client_id');
        $reference = trim((string) post('reference'));
        $bonDate = trim((string) post('bon_date'));
        $amount = post_float('amount');
        $status = (string) post('status');
        $receivedAt = trim((string) post('received_at'));
        $note = trim((string) post('note'));

        $existing = $id > 0 ? one('SELECT * FROM credit_bons WHERE id = ?', [$id]) : null;
        if ($id > 0 && ! $existing) {
            flash(t('bon_invalid'), 'error');
            redirect('bons');
        }

        if ($clientId <= 0 || ! one('SELECT id FROM clients WHERE id = ?', [$clientId])) {
            $errors[] = t('bon_select_company');
        }
        if ($bonDate === '' || ! strtotime($bonDate)) {
            $errors[] = t('bon_date');
        }
        if ($amount <= 0) {
            $errors[] = t('bon_amount');
        }
        if (! array_key_exists($status, $statuses)) {
            $status = 'attente';
        }
        if ($receivedAt !== '' && ! strtotime($receivedAt)) {
            $errors[] = t('bon_received_at');
        }
        if (mb_strlen($reference) > 80) {
            $errors[] = t('bon_reference') . ' : 80 caractères maximum.';
        }
        if (mb_strlen($note) > 500) {
            $errors[] = t('bon_note') . ' : 500 caractères maximum.';
        }

        $form = [
            'id'          => $id,
            'client_id'   => (string) $clientId,
            'reference'   => $reference,
            'bon_date'    => $bonDate,
            'amount'      => (string) $amount,
            'status'      => $status,
            'received_at' => $receivedAt,
            'note'        => $note,
            'image_path'  => $existing['image_path'] ?? null,
        ];
        $showModal = true;

        if (! $errors) {
            $imagePath = $existing['image_path'] ?? null;
            $newImage = null;

            if (! empty($_FILES['image']['name'])) {
                $upload = sh_store_image($_FILES['image'], 'bons');
                if ($upload['ok']) {
                    $newImage = $upload['path'];
                    $imagePath = $newImage;
                } elseif (($upload['error'] ?? '') !== '') {
                    $errors[] = $upload['error'];
                }
            } elseif (post('remove_image') !== '' && $imagePath !== null) {
                sh_delete_upload($imagePath);
                $imagePath = null;
            }

            $form['image_path'] = $imagePath;

            if ($status === 'paye' && $receivedAt === '') {
                $receivedAt = date('Y-m-d');
                $form['received_at'] = $receivedAt;
            }
            if ($status !== 'paye') {
                $receivedAt = '';
                $form['received_at'] = '';
            }

            if (! $errors) {
                try {
                    if ($existing) {
                        q(
                            'UPDATE credit_bons
                             SET client_id = ?, reference = ?, bon_date = ?, amount = ?, status = ?,
                                 received_at = ?, note = ?, image_path = ?, updated_at = NOW()
                             WHERE id = ?',
                            [
                                $clientId,
                                $reference !== '' ? $reference : null,
                                $bonDate,
                                $amount,
                                $status,
                                $receivedAt !== '' ? $receivedAt : null,
                                $note !== '' ? $note : null,
                                $imagePath,
                                $id,
                            ]
                        );
                    } else {
                        q(
                            'INSERT INTO credit_bons
                                (client_id, reference, bon_date, amount, status, received_at, note, image_path, created_by, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                            [
                                $clientId,
                                $reference !== '' ? $reference : null,
                                $bonDate,
                                $amount,
                                $status,
                                $receivedAt !== '' ? $receivedAt : null,
                                $note !== '' ? $note : null,
                                $imagePath,
                                (int) (user()['id'] ?? 0),
                            ]
                        );
                    }
                    flash(t('bon_saved'));
                    redirect('bons');
                } catch (Throwable $exception) {
                    if ($newImage !== null) {
                        sh_delete_upload($newImage);
                        $form['image_path'] = $existing['image_path'] ?? null;
                    }
                    $errors[] = "Enregistrement impossible : " . $exception->getMessage();
                }
            } elseif ($newImage !== null) {
                // L'image a été stockée mais le formulaire est invalide : on nettoie.
                sh_delete_upload($newImage);
                $form['image_path'] = $existing['image_path'] ?? null;
            }
        }
    }

    if ($action === 'status') {
        $id = post_int('id');
        $status = (string) post('status');

        if (! array_key_exists($status, $statuses)) {
            flash(t('bon_invalid'), 'error');
            redirect('bons');
        }

        $bon = one('SELECT * FROM credit_bons WHERE id = ?', [$id]);
        if ($bon) {
            $receivedAt = $status === 'paye'
                ? ($bon['received_at'] ?: date('Y-m-d'))
                : null;
            q(
                'UPDATE credit_bons SET status = ?, received_at = ?, updated_at = NOW() WHERE id = ?',
                [$status, $receivedAt, $id]
            );
            flash(t('bon_status_updated'));
        }
        redirect('bons');
    }

    if ($action === 'delete') {
        $id = post_int('id');
        $bon = one('SELECT image_path FROM credit_bons WHERE id = ?', [$id]);
        if ($bon) {
            sh_delete_upload($bon['image_path'] ?? null);
            q('DELETE FROM credit_bons WHERE id = ?', [$id]);
            flash(t('bon_deleted'));
        }
        redirect('bons');
    }
}

/* -------------------------------------------------------------------------
| Édition
|-------------------------------------------------------------------------*/

$editId = (int) get('edit');
if ($editId > 0 && ! $errors) {
    $editing = one('SELECT * FROM credit_bons WHERE id = ?', [$editId]);
    if ($editing) {
        $form = array_merge($form, $editing);
        $showModal = true;
    }
}

if (get('new') === '1') {
    $showModal = true;
}

/* -------------------------------------------------------------------------
| Filtres et listes
|-------------------------------------------------------------------------*/

$fCompany = (int) get('company');
$fStatus = (string) get('status');
$fMonth = (string) get('month');
$fQuery = trim((string) get('q'));

$conditions = [];
$params = [];

if ($fCompany > 0) {
    $conditions[] = 'b.client_id = ?';
    $params[] = $fCompany;
}
if (array_key_exists($fStatus, $statuses)) {
    $conditions[] = 'b.status = ?';
    $params[] = $fStatus;
}
if (preg_match('/^\d{4}-\d{2}$/', $fMonth)) {
    $conditions[] = "DATE_FORMAT(b.bon_date, '%Y-%m') = ?";
    $params[] = $fMonth;
}
if ($fQuery !== '') {
    $conditions[] = '(b.reference LIKE ? OR b.note LIKE ? OR c.name LIKE ?)';
    $like = '%' . $fQuery . '%';
    array_push($params, $like, $like, $like);
}

$from = ' FROM credit_bons b JOIN clients c ON c.id = b.client_id';
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$companies = all("SELECT id, name FROM clients WHERE type = 'CLIENT' AND nature = 'SOCIETE' ORDER BY name");

$totals = one(
    "SELECT COUNT(*) AS n,
            COALESCE(SUM(b.amount), 0) AS total,
            COALESCE(SUM(CASE WHEN b.status = 'attente' THEN b.amount ELSE 0 END), 0) AS pending,
            COALESCE(SUM(CASE WHEN b.status = 'encaisse' THEN b.amount ELSE 0 END), 0) AS encaisse,
            COALESCE(SUM(CASE WHEN b.status = 'paye' THEN b.amount ELSE 0 END), 0) AS paid"
    . $from . $where,
    $params
) ?: ['n' => 0, 'total' => 0, 'pending' => 0, 'encaisse' => 0, 'paid' => 0];

$recap = all(
    "SELECT c.id, c.name, COUNT(*) AS n,
            COALESCE(SUM(b.amount), 0) AS total,
            COALESCE(SUM(CASE WHEN b.status = 'paye' THEN b.amount ELSE 0 END), 0) AS paid,
            COALESCE(SUM(CASE WHEN b.status <> 'paye' THEN b.amount ELSE 0 END), 0) AS remaining"
    . $from . $where
    . ' GROUP BY c.id, c.name ORDER BY remaining DESC, c.name',
    $params
);

$bons = all(
    'SELECT b.*, c.name AS company_name'
    . $from . $where
    . ' ORDER BY b.bon_date DESC, b.id DESC LIMIT 500',
    $params
);

layout_start(t('bons_title'), 'bons');
print_header(t('bons_title'));
?>

<div class="bons-page">
<div class="toolbar">
    <form class="filters" method="get" action="<?= BASE ?>/index.php">
        <input type="hidden" name="p" value="bons">
        <label class="field">
            <span><?= e(t('search')) ?></span>
            <input type="search" name="q" value="<?= e($fQuery) ?>" placeholder="<?= e(t('bon_reference') . ' / ' . t('bon_company')) ?>">
        </label>
        <label class="field">
            <span><?= e(t('bon_company')) ?></span>
            <select name="company">
                <option value=""><?= e(t('bon_all_companies')) ?></option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= (int) $company['id'] ?>" <?= $fCompany === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span><?= e(t('bon_status')) ?></span>
            <select name="status">
                <option value=""><?= e(t('all')) ?></option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $fStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span><?= e(t('bon_filter_month')) ?></span>
            <input type="month" name="month" value="<?= e($fMonth) ?>">
        </label>
        <button class="btn btn-ghost" type="submit"><?= e(t('filter')) ?></button>
        <a class="btn btn-ghost" href="<?= url('bons') ?>"><?= e(t('reset')) ?></a>
    </form>
    <div class="spacer"></div>
    <?php print_button(); ?>
    <button class="btn btn-primary" type="button" data-action="modal-open" data-target="bon-modal">+ <?= e(t('bon_new')) ?></button>
</div>

<div class="grid grid-4" style="margin-bottom:16px">
    <div class="card stat">
        <div class="stat-label"><?= e(t('bon_total')) ?></div>
        <div class="stat-value"><?= money($totals['total']) ?></div>
        <div class="stat-foot"><?= (int) $totals['n'] ?> <?= e(t('bon_count')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('bon_pending_total')) ?></div>
        <div class="stat-value" style="color:var(--orange)"><?= money($totals['pending']) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('bon_encaisse_total')) ?></div>
        <div class="stat-value gold"><?= money($totals['encaisse']) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('bon_paid_total')) ?></div>
        <div class="stat-value" style="color:var(--green)"><?= money($totals['paid']) ?></div>
    </div>
</div>

<div class="card" style="margin-bottom:16px">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px">
        <h2 style="margin:0;font-size:15px"><?= e(t('bon_company_recap')) ?></h2>
        <span class="small muted"><?= e(t('bon_remaining')) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th><?= e(t('bon_company')) ?></th>
                <th class="num"><?= e(t('bon_count')) ?></th>
                <th class="num"><?= e(t('bon_total')) ?></th>
                <th class="num"><?= e(t('bon_paid_total')) ?></th>
                <th class="num"><?= e(t('bon_remaining')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (! $recap): ?>
                <tr><td colspan="5" class="empty"><?= e(t('bons_empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($recap as $line): ?>
                <tr>
                    <td style="font-weight:600"><?= e($line['name']) ?></td>
                    <td class="num"><?= (int) $line['n'] ?></td>
                    <td class="num"><?= money($line['total']) ?></td>
                    <td class="num" style="color:var(--green)"><?= money($line['paid']) ?></td>
                    <td class="num" style="font-weight:700;color:<?= $line['remaining'] > 0 ? 'var(--orange)' : 'inherit' ?>"><?= money($line['remaining']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th><?= e(t('bon_image')) ?></th>
                <th><?= e(t('bon_company')) ?></th>
                <th><?= e(t('bon_reference')) ?></th>
                <th><?= e(t('bon_date')) ?></th>
                <th class="num"><?= e(t('bon_amount')) ?></th>
                <th><?= e(t('bon_status')) ?></th>
                <th><?= e(t('bon_received_at')) ?></th>
                <th class="actions"><?= e(t('actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (! $bons): ?>
                <tr><td colspan="8" class="empty"><?= e(t('bons_empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($bons as $bon): ?>
                <tr>
                    <td style="width:72px">
                        <?php if (! empty($bon['image_path'])): ?>
                            <a href="<?= BASE ?>/bon_image.php?id=<?= (int) $bon['id'] ?>"
                               data-gos-image
                               data-title="<?= e(t('bon_view_image') . ' — ' . $bon['company_name']) ?>"
                               title="<?= e(t('bon_view_image')) ?>">
                                <img src="<?= BASE ?>/bon_image.php?id=<?= (int) $bon['id'] ?>"
                                     alt="<?= e(t('bon_image')) ?>"
                                     style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid var(--line)">
                            </a>
                        <?php else: ?>
                            <span class="small muted"><?= e(t('bon_no_image')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:600"><?= e($bon['company_name']) ?></td>
                    <td class="mono"><?= e($bon['reference'] ?: '—') ?></td>
                    <td class="nowrap"><?= e(fr_date($bon['bon_date'])) ?></td>
                    <td class="num" style="font-weight:700"><?= money($bon['amount']) ?></td>
                    <td><span class="badge <?= e($statusBadges[$bon['status']] ?? 'badge-muted') ?>"><?= e($statuses[$bon['status']] ?? $bon['status']) ?></span></td>
                    <td class="nowrap"><?= e($bon['received_at'] ? fr_date($bon['received_at']) : '—') ?></td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="<?= url('bons', ['edit' => $bon['id']]) ?>"><?= e(t('edit')) ?></a>

                        <?php if ($bon['status'] === 'attente'): ?>
                            <form method="post" action="<?= url('bons') ?>" style="display:inline">
                                <input type="hidden" name="_token" value="<?= csrf() ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= (int) $bon['id'] ?>">
                                <input type="hidden" name="status" value="encaisse">
                                <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('bon_status_encaisse')) ?></button>
                            </form>
                        <?php endif; ?>

                        <?php if ($bon['status'] !== 'paye'): ?>
                            <form method="post" action="<?= url('bons') ?>" style="display:inline">
                                <input type="hidden" name="_token" value="<?= csrf() ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= (int) $bon['id'] ?>">
                                <input type="hidden" name="status" value="paye">
                                <button class="btn btn-sm" style="background:rgba(53,196,138,.14);color:var(--green)" type="submit"><?= e(t('bon_status_paye')) ?></button>
                            </form>
                        <?php endif; ?>

                        <form method="post" action="<?= url('bons') ?>" style="display:inline" data-confirm>
                            <input type="hidden" name="_token" value="<?= csrf() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $bon['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit"
                                    data-action="confirm" data-message="<?= e(t('bon_delete_confirm')) ?>"><?= e(t('delete')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modale bon -->
<div class="modal <?= $showModal ? 'is-open' : '' ?>" id="bon-modal">
    <div class="modal-backdrop"></div>
    <div class="modal-box" style="max-width:620px">
        <div class="modal-head">
            <h2><?= e($form['id'] > 0 ? t('bon_edit') : t('bon_new')) ?></h2>
            <button class="btn btn-ghost btn-sm" type="button" data-action="modal-close"><?= e(t('close')) ?></button>
        </div>
        <form class="modal-body form" method="post" action="<?= url('bons') ?>" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

            <?php if ($errors): ?>
                <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>

            <label class="field">
                <span><?= e(t('bon_company')) ?> *</span>
                <select name="client_id" data-quick="societe" required>
                    <option value=""><?= e(t('bon_select_company')) ?></option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= (int) $company['id'] ?>" <?= (string) $form['client_id'] === (string) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="small muted"><?= e($companies ? t('bon_company_hint') : t('bon_no_company')) ?></span>
            </label>

            <div class="form-row">
                <label class="field">
                    <span><?= e(t('bon_reference')) ?></span>
                    <input type="text" name="reference" maxlength="80" value="<?= e($form['reference']) ?>" placeholder="<?= e(t('bon_reference')) ?>">
                </label>
                <label class="field">
                    <span><?= e(t('bon_date')) ?> *</span>
                    <input type="date" name="bon_date" value="<?= e($form['bon_date']) ?>" required>
                </label>
            </div>

            <div class="form-row">
                <label class="field">
                    <span><?= e(t('bon_amount')) ?> (<?= e(CURRENCY) ?>) *</span>
                    <input type="number" name="amount" min="0.01" step="0.01" value="<?= e($form['amount']) ?>" required>
                </label>
                <label class="field">
                    <span><?= e(t('bon_status')) ?></span>
                    <select name="status">
                        <?php foreach ($statuses as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $form['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="form-row">
                <label class="field">
                    <span><?= e(t('bon_received_at')) ?></span>
                    <input type="date" name="received_at" value="<?= e($form['received_at']) ?>">
                </label>
                <label class="field">
                    <span><?= e(t('bon_note')) ?></span>
                    <input type="text" name="note" maxlength="500" value="<?= e($form['note']) ?>">
                </label>
            </div>

            <label class="field">
                <span><?= e(t('bon_image')) ?></span>
                <?php if (! empty($form['image_path'])): ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                        <a href="<?= BASE ?>/bon_image.php?id=<?= (int) $form['id'] ?>" data-gos-image data-title="<?= e(t('bon_view_image')) ?>">
                            <img src="<?= BASE ?>/bon_image.php?id=<?= (int) $form['id'] ?>" alt="<?= e(t('bon_image')) ?>"
                                 style="width:84px;height:84px;object-fit:cover;border-radius:8px;border:1px solid var(--line)">
                        </a>
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px">
                            <input type="checkbox" name="remove_image" value="1" style="width:auto">
                            <?= e(t('bon_remove_image')) ?>
                        </label>
                    </div>
                <?php endif; ?>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                <span class="small muted"><?= e(t('bon_image_hint')) ?></span>
            </label>

            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button class="btn btn-ghost" type="button" data-action="modal-close"><?= e(t('cancel')) ?></button>
                <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>
</div><!-- /.bons-page -->

<?php layout_end(); ?>
