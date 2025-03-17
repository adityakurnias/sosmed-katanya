<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    public function register() {
        $validator = Validator::make(request()->all(), [
            'full_name' => 'required|string',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid field',
                'errors' => $validator->errors()
            ], 422);
        }

        $input = request()->all();
        $input['password'] = bcrypt($input['password']);
        $user = User::create($input);
        $token = $user->createToken('token')->plainTextToken;
        $success['full_name'] = $user->full_name;
        $success['username'] = $user->username;

        return response()->json([
            'message' => "Register success",
            'token' => $token,
            'success' => $success
        ], 201);
    }

    public function login(Request $request) {
        $validator = Validator::make(request()->all(), [
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid field',
                'errors' => $validator->errors()
            ], 422);
        }

        if (Auth::attempt(["username" => $request->username, "password" => $request->password])) {
            $user = Auth::user();
            $token = $user->createToken("token")->plainTextToken;

            return response()->json([
                "message" => "Login success",
                "token" => $token,
                "user" => [
                    "full_name" => $user->full_name,
                    "username" => $user->username,
                ]
            ]);
        } else {
            return response()->json([
                "message" => "Username or password incorrect"
            ], 401);
        }
    }

    public function logout(Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message' => 'Logout success'
        ], 200);
    }
}
