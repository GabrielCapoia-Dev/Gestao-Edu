<x-filament-panels::page>
    <form wire:submit="salvar" class="mx-auto w-full max-w-xl space-y-6">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-6 space-y-2">
                <p class="text-sm font-semibold text-primary-600">
                    Senha obrigatoria
                </p>
                <h1 class="text-2xl font-semibold text-gray-950">
                    Redefina sua senha
                </h1>
                <p class="text-sm leading-6 text-gray-600">
                    Para continuar no sistema, cadastre uma nova senha pessoal.
                </p>
            </div>

            <div class="space-y-5">
                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-gray-950">
                        Nova senha
                    </label>
                    <input
                        id="password"
                        type="password"
                        wire:model.defer="password"
                        autocomplete="new-password"
                        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    />
                    @error('password')
                        <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium text-gray-950">
                        Confirmar senha
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        wire:model.defer="password_confirmation"
                        autocomplete="new-password"
                        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    />
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit" icon="heroicon-o-check">
                    Salvar nova senha
                </x-filament::button>
            </div>
        </div>
    </form>
</x-filament-panels::page>
