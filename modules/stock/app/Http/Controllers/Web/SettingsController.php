<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Infrastructure\Persistence\PdoProductRepository;
use App\Infrastructure\Persistence\PdoSettingsRepository;
use RuntimeException;

final class SettingsController
{
    public function index(Request $request): Response
    {
        $settings = new PdoSettingsRepository();

        return View::render('settings/index', [
            'title'    => t('Paramètres'),
            'settings' => $settings->all(),
            'stats'    => (new PdoProductRepository())->stats(),
        ]);
    }

    public function update(Request $request): Response
    {
        try {
            $company = trim((string) $request->input('company_name', ''));
            $currency = mb_strtoupper(trim((string) $request->input('currency', 'MAD')));

            if ($company === '') {
                throw new RuntimeException(t('Le nom de l’entreprise est obligatoire.'));
            }
            if ($currency === '') {
                throw new RuntimeException(t('La devise est obligatoire.'));
            }

            (new PdoSettingsRepository())->setMany([
                'company_name'         => mb_substr($company, 0, 120),
                'currency'             => mb_substr($currency, 0, 8),
                'allow_negative_stock' => $request->input('allow_negative_stock') === '1' ? '1' : '0',
                'cump_method'          => 'global',
            ]);

            flash('success', t('Paramètres enregistrés.'));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url('/parametres'));
    }
}
