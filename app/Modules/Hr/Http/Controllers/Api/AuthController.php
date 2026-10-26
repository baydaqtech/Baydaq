<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Attendance\BindDevice;
use App\Modules\Hr\Http\Requests\Api\LoginRequest;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Sign in from the employee app: the credentials must belong to an employee's login, and the
     * phone is bound to that employee (first time) or must be the bound one.
     */
    public function login(LoginRequest $request, CurrentTenant $tenant, BindDevice $bindDevice): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);

        if (! Auth::guard('web')->validate($credentials)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $user = Auth::guard('web')->getProvider()->retrieveByCredentials($credentials);

        if ($user->client_id === null) {
            throw ValidationException::withMessages(['email' => __('hr.attendance.errors.not_employee')]);
        }

        $tenant->set($user->client);
        $employee = Employee::query()->firstWhere('user_id', $user->id) ?? throw ValidationException::withMessages(['email' => __('hr.attendance.errors.not_employee')]);

        $bindDevice->handle($employee, $request->validated('device_id'), $request->validated('platform'), $request->validated('model'));

        // One token per device: signing in again from the same phone replaces the old token.
        $user->tokens()->where('name', $request->validated('device_id'))->delete();
        $token = $user->createToken($request->validated('device_id'), ['employee'])->plainTextToken;

        return response()->json(['token' => $token, 'employee_id' => $employee->id]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }
}
