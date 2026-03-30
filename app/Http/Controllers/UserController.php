<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        $users = $query->get();

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Viewed all users',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'users' => $users
        ]);
    }

    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Viewed a user',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'user' => $user
        ]);
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->delete();

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Deleted a user',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    public function CreateUser()
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'nullable|in:admin,superadmin,worker'
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'] ?? 'admin'
        ]);

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Created a new user',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    public function UpdateUser(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|required|string|min:8',
            'role' => 'sometimes|nullable|in:admin,superadmin,worker'
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        $user->update($validated);

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Updated a user',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    public function getProfile()
    {
        $user = auth()->user();

        ActivityLog::create([
            'user_id' => $user->id,
            'description' => 'Viewed own profile',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'user' => $user
        ]);
    }
}
