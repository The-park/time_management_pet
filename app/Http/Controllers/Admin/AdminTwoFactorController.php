<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\TwoFactorAuthenticationProvider;

class AdminTwoFactorController extends Controller
{
    public function showSetup(Request $request)
    {
        $admin = $this->adminFromSession($request, 'admin.two_factor_setup_id');

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $qrCodeSvg = $this->qrCodeSvg($admin);
        $recoveryCodes = $this->recoveryCodes($admin);

        return view('admin.auth.two-factor-setup', [
            'admin' => $admin,
            'qrCodeSvg' => $qrCodeSvg,
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $admin = $this->adminFromSession($request, 'admin.two_factor_setup_id');

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        if (! $this->verifyCode($admin, $request->input('code'))) {
            return back()->withErrors([
                'code' => 'The provided two factor code was invalid.',
            ]);
        }

        $admin->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('admin.two_factor_setup_id');
        Auth::guard('admin')->login($admin);

        return redirect('/admin/users');
    }

    public function challenge(Request $request)
    {
        $admin = $this->adminFromSession($request, 'admin.two_factor_login_id');

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $admin = $this->adminFromSession($request, 'admin.two_factor_login_id');

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $code = trim((string) $request->input('code'));
        $recoveryCode = trim((string) $request->input('recovery_code'));

        if ($code !== '' && $this->verifyCode($admin, $code)) {
            return $this->finalizeLogin($request, $admin);
        }

        if ($recoveryCode !== '' && $this->useRecoveryCode($admin, $recoveryCode)) {
            return $this->finalizeLogin($request, $admin);
        }

        return back()->withErrors([
            'code' => 'The provided two factor code was invalid.',
        ]);
    }

    protected function finalizeLogin(Request $request, Admin $admin)
    {
        $request->session()->forget('admin.two_factor_login_id');
        Auth::guard('admin')->login($admin);

        return redirect('/admin/users');
    }

    protected function adminFromSession(Request $request, string $key): ?Admin
    {
        $adminId = $request->session()->get($key);

        if (! $adminId) {
            return null;
        }

        return Admin::find($adminId);
    }

    protected function qrCodeSvg(Admin $admin): string
    {
        $provider = app(TwoFactorAuthenticationProvider::class);

        return $provider->qrCodeSvg(
            config('app.name'),
            $admin->email,
            decrypt($admin->two_factor_secret)
        );
    }

    protected function recoveryCodes(Admin $admin): array
    {
        $codes = decrypt($admin->two_factor_recovery_codes);

        return is_string($codes) ? json_decode($codes, true) ?? [] : [];
    }

    protected function verifyCode(Admin $admin, string $code): bool
    {
        $provider = app(TwoFactorAuthenticationProvider::class);

        return $provider->verify(decrypt($admin->two_factor_secret), $code);
    }

    protected function useRecoveryCode(Admin $admin, string $recoveryCode): bool
    {
        $codes = $this->recoveryCodes($admin);
        $index = array_search($recoveryCode, $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);

        $admin->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode(array_values($codes))),
        ])->save();

        return true;
    }
}
