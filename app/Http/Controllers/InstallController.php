<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InstallController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Setting::isInstalled()) {
            return redirect()->route('filament.admin.auth.login');
        }

        return view('install');
    }

    public function store(Request $request): RedirectResponse
    {
        if (Setting::isInstalled()) {
            return redirect()->route('filament.admin.auth.login');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash:ascii', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'required' => 'فیلد :attribute الزامی است.',
            'email' => 'ایمیل معتبر نیست.',
            'unique' => 'این :attribute قبلاً ثبت شده است.',
            'min.string' => ':attribute باید حداقل :min نویسه باشد.',
            'confirmed' => 'تکرار با رمز یکسان نیست.',
            'alpha_dash' => 'کاربری فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.',
            'max.string' => ':attribute طولانی‌تر از حد مجاز است.',
        ], [
            'title' => 'عنوان',
            'description' => 'توضیحات',
            'firstname' => 'نام',
            'lastname' => 'خانوادگی',
            'username' => 'کاربری',
            'email' => 'ایمیل',
            'password' => 'رمز',
        ]);

        DB::transaction(function () use ($data): void {
            if (Setting::isInstalled()) {
                return;
            }

            User::query()->create([
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            Setting::query()->create([
                'title' => $data['title'],
                'description' => $data['description'],
                'installed_at' => now(),
            ]);
        });

        return redirect()
            ->route('filament.admin.auth.login')
            ->with('status', 'نصب انجام شد. با نام کاربری و رمز عبوری که ثبت کردید وارد شوید.');
    }
}
