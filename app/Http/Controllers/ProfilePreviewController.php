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
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $preview->start((int) $data['target_user_id'], $user);

        Notification::make()
            ->title('Modo visualizacao ativado')
            ->body('Voce esta navegando como outro usuario. Acoes de escrita serao bloqueadas.')
            ->success()
            ->send();

        return redirect()->to(Filament::getPanel('admin')->getUrl());
    }

    public function stop(ProfilePreviewService $preview): RedirectResponse
    {
        $preview->stop();

        Notification::make()
            ->title('Modo visualizacao finalizado')
            ->body('Seu acesso normal foi restaurado.')
            ->success()
            ->send();

        return redirect()->to(Filament::getPanel('admin')->getUrl());
    }
}
