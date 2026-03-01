<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;

class SocialiteController extends Controller
{
    public function redirectToProvider(Request $request, string $provider): RedirectResponse
    {
        $this->validateProvider($request);
 
        return Socialite::driver($provider)->redirect();
    }
 
    public function handleProviderCallback(Request $request, string $provider)
    {
        $this->validateProvider($request);
 
        $response = Socialite::driver($provider)->user();
 
        $user = User::updateOrCreate(
            ['email' => $response->getEmail()],
            [
                'password' => Str::password(17),
                'name' => $response->getName() ?? $response->getNickname,
                'email_verified_at' => now(),
                $provider . '_id' => $response->getId()
            ]
        );
 
        if ($user->wasRecentlyCreated) {
            event(new Registered($user));
        }
 
        Auth::login($user, remember: true);
 
        return redirect(config('app.frontend_url') . "/dashboard");
    }
 
    protected function validateProvider(Request $request): array
    {
        return $this->getValidationFactory()->make(
            $request->route()->parameters(),
            ['provider' => 'in:google,github']
        )->validate();
    }
}
