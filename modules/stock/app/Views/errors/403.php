<?php /** @var string $title */ ?>
<div class="error-page-inline">
    <div class="error-card">
        <span class="error-code">403</span>
        <h1>Accès refusé</h1>
        <p>Votre rôle ne permet pas d’accéder à cette section. Contactez un administrateur si nécessaire.</p>
        <a class="btn btn-accent" href="<?= url('/dashboard') ?>">
            <i class="bi bi-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>
</div>
