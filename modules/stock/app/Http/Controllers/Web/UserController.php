<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Infrastructure\Persistence\PdoReferenceRepository;
use App\Infrastructure\Persistence\PdoUserRepository;
use RuntimeException;
use Throwable;

final class UserController
{
    public function index(Request $request): Response
    {
        $repo = new PdoUserRepository();

        return View::render('users/index', [
            'title'  => 'Utilisateurs',
            'users'  => $repo->all(),
            'errors' => [],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->formView(null, [], []);
    }

    public function store(Request $request): Response
    {
        try {
            $data = $this->validated($request, true);
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
            (new PdoUserRepository())->create($data);
            flash('success', 'Utilisateur créé.');
            return Response::redirect(url('/utilisateurs'));
        } catch (Throwable $e) {
            return $this->formView(null, $request->all(), [$e->getMessage()]);
        }
    }

    public function edit(Request $request, string $id): Response
    {
        $user = (new PdoUserRepository())->findRaw((int) $id);
        if ($user === null) {
            return View::render('errors/404', ['title' => 'Utilisateur introuvable'])->withStatus(404);
        }

        return $this->formView($user, $user, []);
    }

    public function update(Request $request, string $id): Response
    {
        $repo = new PdoUserRepository();
        $user = $repo->findRaw((int) $id);
        if ($user === null) {
            return View::render('errors/404', ['title' => 'Utilisateur introuvable'])->withStatus(404);
        }

        try {
            $data = $this->validated($request, false);

            $currentRoleId = (int) ($user['role_id'] ?? 0);
            $currentRoleCode = '';
            $newRoleCode = '';
            foreach ($repo->roles() as $role) {
                if ((int) $role['id'] === $currentRoleId) {
                    $currentRoleCode = $role['code'];
                }
                if ((int) $role['id'] === $data['role_id']) {
                    $newRoleCode = $role['code'];
                }
            }

            if ((int) $id === Auth::id() && !$data['active']) {
                throw new RuntimeException('Vous ne pouvez pas désactiver votre propre compte.');
            }
            if ($currentRoleCode === 'admin'
                && (int) ($user['active'] ?? 0) === 1
                && ($newRoleCode !== 'admin' || !$data['active'])
                && $repo->countActiveAdmins() <= 1
            ) {
                throw new RuntimeException('Il doit rester au moins un administrateur actif.');
            }

            if ($data['password'] !== '') {
                $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            } else {
                $data['password_hash'] = '';
            }
            unset($data['password']);
            $repo->update((int) $id, $data);
            flash('success', 'Utilisateur mis à jour.');
            return Response::redirect(url('/utilisateurs'));
        } catch (Throwable $e) {
            return $this->formView($user, $request->all(), [$e->getMessage()]);
        }
    }

    public function toggle(Request $request, string $id): Response
    {
        $repo = new PdoUserRepository();
        $target = $repo->findById((int) $id);
        if ($target === null) {
            flash('error', 'Utilisateur introuvable.');
            return Response::redirect(url('/utilisateurs'));
        }

        if ($target->id() === Auth::id()) {
            flash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
            return Response::redirect(url('/utilisateurs'));
        }

        if ($target->roleCode() === 'admin' && $target->isActive() && $repo->countActiveAdmins() <= 1) {
            flash('error', 'Il doit rester au moins un administrateur actif.');
            return Response::redirect(url('/utilisateurs'));
        }

        try {
            $repo->toggle((int) $id);
            flash('success', 'Statut de l’utilisateur modifié.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url('/utilisateurs'));
    }

    /**
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $old
     * @param array<int, string> $errors
     */
    private function formView(?array $user, array $old, array $errors): Response
    {
        $repo = new PdoUserRepository();

        return View::render('users/form', [
            'title'     => $user === null ? 'Nouvel utilisateur' : 'Modifier l’utilisateur',
            'user'      => $user,
            'old'       => $old,
            'errors'    => $errors,
            'roles'     => $repo->roles(),
            'locations' => (new PdoReferenceRepository())->locations(true),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $passwordRequired): array
    {
        $data = [
            'name'        => trim((string) $request->input('name', '')),
            'login'       => trim((string) $request->input('login', '')),
            'email'       => trim((string) $request->input('email', '')),
            'role_id'     => (int) $request->input('role_id', 0),
            'location_id' => (int) $request->input('location_id', 0),
            'password'    => (string) $request->input('password', ''),
            'active'      => $request->input('active') === '1',
        ];

        if ($data['name'] === '') {
            throw new RuntimeException('Le nom est obligatoire.');
        }
        if ($data['login'] === '') {
            throw new RuntimeException('Le login est obligatoire.');
        }
        if ($data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('L’adresse e-mail est invalide.');
        }

        $roleIds = array_map(static fn (array $role): int => (int) $role['id'], (new PdoUserRepository())->roles());
        if (!in_array($data['role_id'], $roleIds, true)) {
            throw new RuntimeException('Sélectionnez un rôle valide.');
        }

        if ($data['location_id'] > 0) {
            if (!(new PdoReferenceRepository())->exists('location', $data['location_id'])) {
                throw new RuntimeException('Sélectionnez un site actif.');
            }
        } else {
            $data['location_id'] = null;
        }

        if ($passwordRequired && $data['password'] === '') {
            throw new RuntimeException('Le mot de passe est obligatoire.');
        }
        if ($data['password'] !== '' && mb_strlen($data['password']) < 8) {
            throw new RuntimeException('Le mot de passe doit contenir au moins 8 caractères.');
        }

        return $data;
    }
}
