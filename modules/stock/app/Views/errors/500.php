<?php /** @var string $title */ /** @var string $message */ ?>
<div class="error-page">
    <div class="error-card">
        <span class="error-code">500</span>
        <h1>Une erreur est survenue</h1>
        <p><?= e($message ?? 'Veuillez réessayer dans quelques instants.') ?></p>
        <a class="btn btn-accent" href="<?= url('/dashboard') ?>"><i class="bi bi-arrow-left"></i> Retour au tableau de bord</a>
    </div>
</div>
