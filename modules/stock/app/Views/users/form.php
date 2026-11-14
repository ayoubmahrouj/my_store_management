<?php
/** @var array<string, mixed>|null $user */
/** @var array<string, mixed> $old */
/** @var array<int, string> $errors */
/** @var array<int, array{id: int, code: string, label: string}> $roles */
/** @var array<int, \App\Domain\Entities\Location> $locations */
$user = $user ?? null;
$old = is_array($old) ? $old : [];
$errors = is_array($errors) ? $errors : [];
$isEdit = $user !== null;
$value = static function (string $key, mixed $default = '') use ($old, $user): mixed {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if ($user !== null && array_key_exists($key, $user)) {
        return $user[$key];
    }
    return $default;
};
$activeRaw = array_key_exists('active', $old) ? $old['active'] : ($user['active'] ?? 1);
$isActive = (int) $activeRaw === 1 || $activeRaw === true || $activeRaw === '1';
?>
<div class="page-head">
    <div>
        <span class="eyebrow"><?= e(t('Administration')) ?></span>
        <h2><?= e($isEdit ? t('Modifier l’utilisateur') : t('Nouvel utilisateur')) ?></h2>
        <p><?= e(t('Le rôle détermine les actions autorisées. Un site de rattachement est optionnel.')) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-soft" href="<?= url('/utilisateurs') ?>"><i class="bi bi-arrow-left"></i> <?= e(t('Retour')) ?></a>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-premium alert-danger-soft">
        <i class="bi bi-exclamation-octagon"></i>
        <div>
            <strong><?= e(t('L’utilisateur n’a pas pu être enregistré.')) ?></strong>
            <ul class="mb-0 mt-1"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $isEdit ? url('/utilisateurs/' . (int) $user['id']) : url('/utilisateurs') ?>" autocomplete="off">
    <?= csrf_field() ?>

    <section class="panel">
        <div class="panel-head">
            <div><h3><?= e(t('Identité & accès')) ?></h3><p><?= e(t('Informations du compte')) ?></p></div>
            <span class="pill pill-muted"><?= e($isEdit ? t('Édition') : t('Création')) ?></span>
        </div>

        <div class="form-grid form-grid-3">
            <div class="field">
                <label for="name"><?= e(t('Nom complet')) ?> <span class="req">*</span></label>
                <input class="form-control" type="text" id="name" name="name" maxlength="120" required
                       value="<?= e((string) $value('name')) ?>">
            </div>
            <div class="field">
                <label for="login"><?= e(t('Login')) ?> <span class="req">*</span></label>
                <input class="form-control" type="text" id="login" name="login" maxlength="60" required autocomplete="off"
                       value="<?= e((string) $value('login')) ?>">
            </div>
            <div class="field">
                <label for="email"><?= e(t('E-mail')) ?></label>
                <input class="form-control" type="email" id="email" name="email" maxlength="160"
                       value="<?= e((string) $value('email')) ?>">
            </div>

            <div class="field">
                <label for="roleId"><?= e(t('Rôle')) ?> <span class="req">*</span></label>
                <select class="form-select" id="roleId" name="role_id" required>
                    <option value=""><?= e(t('— Sélectionner —')) ?></option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>" <?= (int) $value('role_id') === (int) $role['id'] ? 'selected' : '' ?>>
                            <?= e($role['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="locationId"><?= e(t('Site de rattachement')) ?></label>
                <select class="form-select" id="locationId" name="location_id" data-quick="location">
                    <option value="0"><?= e(t('Tous les sites')) ?></option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location->id() ?>" <?= (int) $value('location_id') === $location->id() ? 'selected' : '' ?>>
                            <?= e($location->code()) ?> · <?= e($location->name()) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="password"><?= e(t('Mot de passe')) ?> <?= $isEdit ? '' : '<span class="req">*</span>' ?></label>
                <input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password"
                       placeholder="<?= e($isEdit ? t('Laisser vide pour conserver') : t('8 caractères minimum')) ?>">
                <?php if ($isEdit): ?><small class="field-hint"><?= e(t('Renseignez uniquement pour changer le mot de passe.')) ?></small><?php endif; ?>
            </div>

            <div class="field field-wide">
                <label class="check-line">
                    <input class="form-check-input" type="checkbox" name="active" value="1" <?= $isActive ? 'checked' : '' ?>>
                    <?= e(t('Compte actif')) ?>
                </label>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= url('/utilisateurs') ?>"><?= e(t('Annuler')) ?></a>
        <button class="btn btn-accent" type="submit">
            <i class="bi bi-save"></i> <?= e($isEdit ? t('Enregistrer les modifications') : t('Créer l’utilisateur')) ?>
        </button>
    </div>
</form>
