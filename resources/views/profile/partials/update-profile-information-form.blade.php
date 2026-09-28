<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="profile-current-password" value="Contraseña actual (necesaria para cambiar el correo)" />
            <x-text-input id="profile-current-password" name="current_password" type="password" autocomplete="current-password" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>
        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
    @if (session('status') === 'email-change-confirmed')
        <p role="status">Correo confirmado y actualizado.</p>
    @endif
    @if ($user->pending_email)
        <div class="mt-6 space-y-4">
            <p>Enviamos un código a {{ $user->pending_email }}. Tu correo actual se mantiene hasta confirmar. El código vence en 10 minutos.</p>
            <form method="post" action="{{ route('profile.email.confirm') }}" class="space-y-3">
                @csrf
                <x-input-label for="email-code" value="Código de confirmación" />
                <x-text-input id="email-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="block w-full" />
                <x-input-error :messages="$errors->get('code')" />
                <x-primary-button>Confirmar correo</x-primary-button>
            </form>
            <form method="post" action="{{ route('profile.email.cancel') }}">
                @csrf @method('DELETE')
                <x-secondary-button type="submit">Cancelar cambio</x-secondary-button>
            </form>
        </div>
    @endif
</section>
