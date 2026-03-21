<?php

namespace App\Http\Controllers;

use App\Mail\ManagerWelcomeMail;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class SuperuserController extends Controller
{
    public function index()
    {
        $properties = Property::with('manager:id,name,email')
            ->orderBy('name')
            ->get();

        $managers = User::query()
            ->whereIn('role', ['manager', 'superuser'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'property_id']);

        return Inertia::render('Superuser/Index', [
            'properties' => $properties,
            'managers' => $managers,
        ]);
    }

    public function storeProperty(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
            'status' => 'required|in:active,trial,inactive',
            'unit_count' => 'nullable|integer|min:0',
            'occupied_units' => 'nullable|integer|min:0',
            'manager_user_id' => 'nullable|exists:users,id',
        ]);

        $maxBldCode = Property::query()
            ->where('code', 'like', 'BLD%')
            ->pluck('code')
            ->map(function ($code) {
                if (!is_string($code)) {
                    return 0;
                }

                if (!preg_match('/^BLD(\d+)$/', strtoupper($code), $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() ?? 0;

        $nextCode = $maxBldCode + 1;
        $data['code'] = 'BLD' . str_pad((string) $nextCode, 2, '0', STR_PAD_LEFT);
        $data['country'] = $data['country'] ?: 'Tanzania';

        $property = Property::create($data);

        if (!empty($data['manager_user_id'])) {
            User::where('id', $data['manager_user_id'])->update([
                'role' => 'manager',
                'property_id' => $property->id,
            ]);
        }

        return back()->with('success', 'Property created successfully.');
    }

    public function assignManager(Request $request, Property $property)
    {
        $data = $request->validate([
            'manager_user_id' => 'required|exists:users,id',
        ]);

        $newManager = User::findOrFail($data['manager_user_id']);

        if ($property->manager_user_id && $property->manager_user_id !== $newManager->id) {
            User::where('id', $property->manager_user_id)->update(['property_id' => null]);
        }

        $property->update(['manager_user_id' => $newManager->id]);
        $newManager->update([
            'role' => 'manager',
            'property_id' => $property->id,
        ]);

        return back()->with('success', 'Manager assigned successfully.');
    }

    public function storeManager(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:120|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'role' => 'required|in:manager,accountant,viewer',
            'property_id' => 'nullable|exists:properties,id',
            'twoFA' => 'nullable|in:yes,no',
        ]);

        $initialPassword = 'password';

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($initialPassword),
            'role' => $data['role'],
            'property_id' => $data['role'] === 'manager' ? ($data['property_id'] ?? null) : null,
            'must_change_password' => $data['role'] === 'manager',
        ]);

        $assignedPropertyName = null;

        if ($data['role'] === 'manager' && !empty($data['property_id'])) {
            $property = Property::findOrFail($data['property_id']);
            $assignedPropertyName = $property->name;

            if ($property->manager_user_id && $property->manager_user_id !== $user->id) {
                User::where('id', $property->manager_user_id)->update(['property_id' => null]);
            }

            $property->update(['manager_user_id' => $user->id]);
        }

        if ($data['role'] === 'manager') {
            Mail::to($user->email)->send(new ManagerWelcomeMail(
                managerName: $user->name,
                email: $user->email,
                initialPassword: $initialPassword,
                loginUrl: url('/login'),
                propertyName: $assignedPropertyName,
            ));
        }

        return back()->with('success', 'User created successfully.');
    }
}
