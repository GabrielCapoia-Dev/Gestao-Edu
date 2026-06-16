<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProfilePreviewService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfilePreviewController extends Controller
{
    public function start(Request $request, ProfilePreviewService $preview): RedirectResponse
    {
        $data = $request->validate([
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        /** @var User $user */
        $user = $preview->controlUser() ?? $request->user();

        $preview->start((int) $data['target_user_id'], $user);

        Notification::make()
            ->title('Modo visualização ativado')
            ->body('Você está navegando como outro usuário. Ações de escrita serão bloqueadas.')
            ->success()
            ->send();

        return redirect()->to(Filament::getPanel('admin')->getUrl());
    }

    public function stop(ProfilePreviewService $preview): RedirectResponse
    {
        $preview->stop();

        Notification::make()
            ->title('Modo visualização finalizado')
            ->body('Seu acesso normal foi restaurado.')
            ->success()
            ->send();

        return redirect()->to(Filament::getPanel('admin')->getUrl());
    }
}
