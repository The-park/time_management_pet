<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdministratorController extends Controller
{
    public function index(Request $request)
    {
        $admins = Admin::query()
            ->orderBy('name')
            ->paginate(50);

        return view('admin.administrators.index', [
            'admins' => $admins,
            'currentId' => Auth::guard('admin')->id(),
        ]);
    }

    public function create()
    {
        return view('admin.administrators.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('admins', 'email')],
            'password' => ['required', 'string', 'min:10', 'confirmed', 'regex:/[A-Za-z]/', 'regex:/\d/'],
        ], [
            'password.regex' => 'Password must contain letters and digits.',
        ]);

        $admin = Admin::create($data);
        AdminAudit::log('created_admin', null);

        return redirect()
            ->route('admin.administrators.index')
            ->with('toast', "Created admin {$admin->email}.");
    }

    public function edit($id)
    {
        $admin = Admin::query()->findOrFail($id);
        return view('admin.administrators.edit', [
            'admin' => $admin,
            'isSelf' => Auth::guard('admin')->id() === (int) $id,
        ]);
    }

    public function update($id, Request $request)
    {
        $admin = Admin::query()->findOrFail($id);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
        ];

        // Password is optional on update — only validated if provided.
        if ($request->filled('password')) {
            $rules['password'] = ['required', 'string', 'min:10', 'confirmed', 'regex:/[A-Za-z]/', 'regex:/\d/'];
        }

        $data = $request->validate($rules, [
            'password.regex' => 'Password must contain letters and digits.',
        ]);

        $admin->name = $data['name'];
        $admin->email = $data['email'];
        if (! empty($data['password'])) {
            $admin->password = $data['password']; // hashed via Eloquent cast
        }
        $admin->save();

        AdminAudit::log('updated_admin', null);

        return redirect()
            ->route('admin.administrators.index')
            ->with('toast', "Updated admin {$admin->email}.");
    }

    public function destroy($id)
    {
        $admin = Admin::query()->findOrFail($id);
        $currentId = Auth::guard('admin')->id();

        if ((int) $id === $currentId) {
            throw ValidationException::withMessages([
                'admin' => "You can't delete the admin you're signed in as.",
            ]);
        }

        if (Admin::query()->count() <= 1) {
            throw ValidationException::withMessages([
                'admin' => "Can't delete the last admin — at least one must remain.",
            ]);
        }

        $email = $admin->email;
        $admin->delete();
        AdminAudit::log('deleted_admin', null);

        return redirect()
            ->route('admin.administrators.index')
            ->with('toast', "Deleted admin {$email}.");
    }
}
