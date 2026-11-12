<?php
/** @var array<int, \App\Domain\Entities\User> $users */
$currentId = \App\Core\Auth::id();
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Administration</span>
        <h2>Utilisateurs &amp; rôles</h2>
        <p>Gérez les accès, les rôles et le site de rattachement de chaque opérateur.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-accent btn-add" href="<?= url('/utilisateurs/nouveau') ?>" data-modal
           title="Nouvel utilisateur" aria-label="Nouvel utilisateur">
            <i class="bi bi-plus-lg"></i>
        </a>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <div><h3>Comptes</h3><p><?= count($users) ?> utilisateur(s)</p></div>
    </div>
    <div class="table-responsive">
        <table class="table table-premium table-hover align-middle">
            <thead>
            <tr>
                <th>Utilisateur</th>
                <th>Login</th>
                <th>Rôle</th>
                <th>Site</th>
                <th>Statut</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($users === []): ?>
                <tr><td colspan="6" class="text-center muted py-4">Aucun utilisateur.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $user): ?>
                <tr class="<?= $user->isActive() ? '' : 'row-inactive' ?>">
                    <td>
                        <div class="product-cell">
                            <span class="product-avatar"><?= e(mb_substr($user->name(), 0, 1)) ?></span>
                            <div>
                                <strong><?= e($user->name()) ?></strong>
                                <?php if ($user->id() === $currentId): ?><small class="pill pill-info">Vous</small><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><span class="code-chip"><?= e($user->login()) ?></span></td>
                    <td><span class="pill pill-muted"><?= e($user->roleLabel()) ?></span></td>
                    <td><?= e($user->locationName() ?? 'Tous les sites') ?></td>
                    <td><span class="pill <?= $user->isActive() ? 'pill-success' : 'pill-muted' ?>"><?= $user->isActive() ? 'Actif' : 'Inactif' ?></span></td>
                    <td class="text-end">
                        <div class="table-actions">
                            <a class="btn btn-icon btn-sm" href="<?= url('/utilisateurs/' . $user->id() . '/modifier') ?>" title="Modifier">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($user->id() !== $currentId): ?>
                                <form method="post" action="<?= url('/utilisateurs/' . $user->id() . '/basculer') ?>"
                                      data-confirm="<?= $user->isActive() ? 'Désactiver cet utilisateur ?' : 'Réactiver cet utilisateur ?' ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-icon btn-sm" type="submit" title="<?= $user->isActive() ? 'Désactiver' : 'Réactiver' ?>">
                                        <i class="bi <?= $user->isActive() ? 'bi-toggle-on text-success' : 'bi-toggle-off' ?>"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
