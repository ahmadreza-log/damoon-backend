<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * One-time application install.
 *
 * The /install form collects the site title and the owner account.
 * After install, both routes redirect to the panel login page.
 *
 * Extending:
 * - Add a new form field in validate, resources/views/install.blade.php, and the related model.
 * - The first saved user becomes the owner inside User. Do not assign the role by hand here.
 */
class InstallController extends Controller
{
    /**
     * Shows the install form. Redirects to the panel login when install is already finished.
     *
     * create is this controller's name for showing the form.
     */
    public function create(): View|RedirectResponse
    {
        if (Setting::installed()) {
            return redirect()->route('filament.admin.auth.login');
        }

        return view('install');
    }

    /**
     * Stores the install: one owner user and one settings record with installed_at.
     *
     * The installed check runs again inside the transaction so two simultaneous submits
     * cannot create two owners. The users table lock is in User::save.
     */
    public function store(Request $request): RedirectResponse
    {
        if (Setting::installed()) {
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
            if (Setting::installed()) {
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
