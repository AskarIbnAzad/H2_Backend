<?php

// ============================================================================
// FILE: app/Http/Controllers/AuthController.php
// MINOR UPDATES - Ensure compatibility with new User/Role models
// ============================================================================

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    // All methods remain the same as original, just ensure User model is imported correctly
    // No changes needed as authentication doesn't depend on JSON structure

    public function addContributor(Request $req)
    {
        try {
            // Find or create Contributor role
            $contributorRole = Role::firstOrCreate(
                ['name' => 'Contributor'],
                ['description' => 'Content Contributor']
            );

            $user = new User;
            $user->name = $req->fullName ?? null;
            $user->email = $req->email ?? null;
            $user->role_id = $contributorRole->id;
            $user->status = 'Pending'; // Awaiting approval
            $user->password = Hash::make('password');
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Contributor added successfully',
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function register(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
            'name' => 'required',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $req->name,
            'email' => $req->email,
            'password' => Hash::make($req->password),
            'role_id' => $req->role_id,
            'status' => 'Active',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'Registered Successfully',
            'token' => $token,
            'user' => $user->load('role'),
        ], 200);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password) && $user->status == 'Active') {
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'token' => $token,
                'user' => $user->load('role'),
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Invalid email or password',
        ], 401);
    }

    public function checkAuth(Request $request)
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token not provided',
            ], 401);
        }

        $user = $request->user();

        if ($user) {
            return response()->json([
                'status' => 'success',
                'user' => $user->load('role'),
                'role' => $user->role->name ?? null,
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Invalid token or user not authenticated',
        ], 401);
    }

    // ==========================================
    // Password Reset Methods (keep all as-is from original)
    // ==========================================

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = $request->email;
        $otp = random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($otp),
                'created_at' => Carbon::now(),
            ]
        );

        Mail::raw(
            "Your password reset OTP is: {$otp}\nThis code expires in 10 minutes.",
            function ($message) use ($email) {
                $message->to($email)->subject('Password Reset OTP');
            }
        );

        return response()->json([
            'status' => 'success',
            'message' => 'OTP sent to your email.',
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $reset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->orderByDesc('created_at')
            ->first();

        if (! $reset) {
            return response()->json([
                'status' => 'error',
                'message' => 'No OTP request found for this email.',
            ], 400);
        }

        if (Carbon::parse($reset->created_at)->addMinutes(10)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'status' => 'error',
                'message' => 'OTP expired. Please request a new one.',
            ], 400);
        }

        if (! Hash::check($request->token, $reset->token)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid OTP.',
            ], 400);
        }

        User::where('email', $request->email)
            ->update(['password' => Hash::make($request->password)]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been reset successfully.',
        ], 200);
    }

    // ==========================================
    // EMAIL OTP FLOW (request/resend/verify/reset)
    // ==========================================

    /**
     * Request an OTP for password reset (sent to email)
     * POST: /api/auth/password/otp/request
     * Payload: { "email": "user@example.com" }
     */
    public function requestPasswordOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = $request->email;
        $user = User::where('email', $email)->first();

        if ($user && isset($user->status) && $user->status !== 'Active') {
            return response()->json([
                'status' => 'error',
                'message' => 'User is not active.',
            ], 403);
        }

        $otp = random_int(100000, 999999);
        $otpHash = Hash::make($otp);
        $expiresAt = Carbon::now()->addMinutes(10);
        $cooldown = Carbon::now()->addSeconds(60);

        DB::table('password_otps')->updateOrInsert(
            ['email' => $email, 'purpose' => 'password_reset'],
            [
                'otp_hash' => $otpHash,
                'attempts' => 0,
                'expires_at' => $expiresAt,
                'resend_after' => $cooldown,
                'verified_at' => null,
                'verify_token' => null,
                'token_expires_at' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Mail::raw(
            "Your password reset OTP is: {$otp}\nThis code expires in 10 minutes.",
            function ($message) use ($email) {
                $message->to($email)->subject('Your Password Reset OTP');
            }
        );

        return response()->json([
            'status' => 'success',
            'message' => 'OTP sent to your email.',
            'data' => [
                'expires_in_minutes' => 10,
                'resend_after_seconds' => 60,
            ],
        ], 200);
    }

    /**
     * Resend OTP (respects cooldown)
     * POST: /api/auth/password/otp/resend
     * Payload: { "email": "user@example.com" }
     */
    public function resendPasswordOtp(Request $request)
    {
        // Custom validation logic
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            // Return a JSON response with validation errors
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422); // HTTP 422 - Unprocessable Entity
        }

        // Check if there is an existing OTP entry for the given email and purpose
        $row = DB::table('password_otps')
            ->where('email', $request->email)
            ->where('purpose', 'password_reset')
            ->first();

        // If an OTP exists and the resend cooldown hasn't passed
        if ($row && $row->resend_after && Carbon::now()->lt(Carbon::parse($row->resend_after))) {
            // Calculate how long the user has to wait before requesting another OTP
            $wait = Carbon::parse($row->resend_after)->diffInSeconds(Carbon::now());

            // Return an error response with the wait time
            return response()->json([
                'status' => 'error',
                'message' => 'Please wait before requesting another OTP.',
                'data' => ['wait_seconds' => $wait],
            ], 429); // HTTP 429 - Too Many Requests
        }

        // If the condition is not met, proceed with requesting the OTP
        return $this->requestPasswordOtp($request);
    }

    /**
     * Verify OTP → returns short-lived verify_token
     * POST: /api/auth/password/otp/verify
     * Payload: { "email": "user@example.com", "otp": "123456" }
     */
    public function verifyPasswordOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
        ]);

        $row = DB::table('password_otps')
            ->where('email', $request->email)
            ->where('purpose', 'password_reset')
            ->first();

        if (! $row) {
            return response()->json([
                'status' => 'error',
                'message' => 'No OTP request found for this email.',
            ], 404);
        }

        if ($row->attempts >= 5) {
            return response()->json([
                'status' => 'error',
                'message' => 'Too many attempts. Please request a new OTP.',
            ], 429);
        }

        if (Carbon::now()->isAfter(Carbon::parse($row->expires_at))) {
            return response()->json([
                'status' => 'error',
                'message' => 'OTP expired. Please request a new one.',
            ], 400);
        }

        $isValid = Hash::check($request->otp, $row->otp_hash);

        DB::table('password_otps')
            ->where('id', $row->id)
            ->update(['attempts' => $row->attempts + 1]);

        if (! $isValid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid OTP.',
                'data' => ['remaining_attempts' => max(0, 5 - ($row->attempts + 1))],
            ], 400);
        }

        $verifyToken = Str::random(64);
        $tokenExpiry = Carbon::now()->addMinutes(15);

        DB::table('password_otps')
            ->where('id', $row->id)
            ->update([
                'verified_at' => now(),
                'verify_token' => $verifyToken,
                'token_expires_at' => $tokenExpiry,
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'OTP verified.',
            'data' => [
                'verify_token' => $verifyToken,
                'token_expires_in_minutes' => 15,
            ],
        ], 200);
    }

    /**
     * Reset password using verify_token (after OTP verified)
     * POST: /api/auth/password/otp/reset
     * Payload:
     * {
     *   "email": "user@example.com",
     *   "verify_token": "64-char-token",
     *   "password": "NewStrongPass123",
     *   "password_confirmation": "NewStrongPass123"
     * }
     */
    public function resetPasswordWithOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'verify_token' => 'required|string',
            'password' => 'required|min:6|confirmed',
        ]);

        $row = DB::table('password_otps')
            ->where('email', $request->email)
            ->where('purpose', 'password_reset')
            ->first();

        if (! $row || ! $row->verify_token || $row->verify_token !== $request->verify_token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid verify token.',
            ], 400);
        }

        if (! $row->verified_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'OTP not verified.',
            ], 400);
        }

        if (Carbon::now()->isAfter(Carbon::parse($row->token_expires_at))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Verify token expired. Please verify OTP again.',
            ], 400);
        }

        User::where('email', $request->email)
            ->update(['password' => Hash::make($request->password)]);

        DB::table('password_otps')
            ->where('id', $row->id)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been reset successfully.',
        ], 200);
    }
}
