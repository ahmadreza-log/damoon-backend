<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نصب سامانه</title>
    <link href="{{ asset('fonts/iranyekan/iranyekan.css') }}" rel="stylesheet">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem 1rem;
            background: #09090b;
            color: #f4f4f5;
            font-family: iranyekan, Tahoma, sans-serif;
        }
        form {
            width: min(100%, 32rem);
            display: grid;
            gap: 1rem;
            padding: 2rem;
            border: 1px solid rgb(255 255 255 / 10%);
            border-radius: 1rem;
            background: #18181b;
        }
        h1 { margin: 0; font-size: 1.5rem; text-align: center; }
        p.lead { margin: 0; color: #a1a1aa; text-align: center; line-height: 1.7; }
        label { display: grid; gap: 0.4rem; font-size: 0.95rem; }
        input, textarea {
            width: 100%;
            border: 1px solid rgb(255 255 255 / 15%);
            border-radius: 0.7rem;
            background: #09090b;
            color: inherit;
            font: inherit;
            padding: 0.75rem 0.9rem;
        }
        textarea { min-height: 6rem; resize: vertical; }
        input:focus, textarea:focus { outline: 2px solid #00377B; border-color: #00377B; }
        button {
            border: 0;
            border-radius: 0.7rem;
            background: #00377B;
            color: white;
            font: inherit;
            font-weight: 600;
            padding: 0.85rem 1rem;
            cursor: pointer;
        }
        .error { color: #fca5a5; font-size: 0.85rem; }
        h2 { margin: 0.5rem 0 0; font-size: 1.05rem; }
    </style>
</head>
<body>
    <form method="post" action="{{ route('install.store') }}">
        @csrf
        <h1>نصب سامانه</h1>
        <p class="lead">عنوان سایت، توضیحات و حساب مالک را وارد کنید. بعد از نصب با همین نام کاربری و رمز عبور وارد می‌شوید.</p>

        <h2>تنظیمات</h2>
        <label>
            عنوان
            <input name="title" value="{{ old('title') }}" required autofocus>
            @error('title') <span class="error">{{ $message }}</span> @enderror
        </label>
        <label>
            توضیحات
            <textarea name="description" required>{{ old('description') }}</textarea>
            @error('description') <span class="error">{{ $message }}</span> @enderror
        </label>

        <h2>مالک</h2>
        <label>
            نام
            <input name="firstname" value="{{ old('firstname') }}" required>
            @error('firstname') <span class="error">{{ $message }}</span> @enderror
        </label>
        <label>
            خانوادگی
            <input name="lastname" value="{{ old('lastname') }}" required>
            @error('lastname') <span class="error">{{ $message }}</span> @enderror
        </label>
        <label>
            کاربری
            <input name="username" value="{{ old('username') }}" required autocomplete="username">
            @error('username') <span class="error">{{ $message }}</span> @enderror
        </label>
        <label>
            ایمیل
            <input name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
            @error('email') <span class="error">{{ $message }}</span> @enderror
        </label>
        <label>
            رمز
            <input name="password" type="password" required autocomplete="new-password">
            @error('password') <span class="error">{{ $message }}</span> @enderror
        </label>
        <label>
            تکرار
            <input name="password_confirmation" type="password" required autocomplete="new-password">
        </label>

        <button type="submit">نصب و ادامه</button>
    </form>
</body>
</html>
