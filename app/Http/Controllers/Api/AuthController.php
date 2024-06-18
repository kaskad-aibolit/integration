<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Log;
class AuthController extends Controller
{
    public function login(Request $request){
        log::info('login');
        log::info($request);

        $fields = Validator::make($request->all(),
            [
                'login' => 'required',
                'password' => 'required'
            ]);

        if($fields->fails()){
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $fields->errors()
            ], 401);
        }
        $token = Hash::make($request['password']);
        $response = [
            'token' => $token,
        ];

        return response($response,201);

    }
}
