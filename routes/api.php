<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\API\FollowController;
use App\Http\Controllers\API\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function () {
    Route::post('/register', AuthController::class . '@register');
    Route::post('/login', AuthController::class . '@login');
    Route::post('/logout', AuthController::class . '@logout')->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/v1/posts', [PostController::class, 'store']);
    Route::get('/v1/posts', [PostController::class, 'getposts']);
    Route::delete('/v1/posts/{id}', [PostController::class, 'destroy']);
    
    Route::post('/v1/users/{username}/follow', [FollowController::class, 'follow']);
    Route::delete('/v1/users/{username}/unfollow', [FollowController::class, 'unfollow']);
    Route::get('/v1/following', [FollowController::class, 'getFollowing']);
    Route::put('/v1/users/{username}/accept', [FollowController::class, 'acceptFollowRequest']);
    Route::get('/v1/followers', [FollowController::class, 'getFollowers']);
    Route::get('/v1/pending-requests', [FollowController::class, 'getPendingRequests']);
    
    Route::get('/v1/users', [UserController::class, 'getUsers']);
    Route::get('/v1/users/{username}', [UserController::class, 'getUserDetail']);
    Route::put('/v1/profile', [UserController::class, 'updateProfile']);
});
