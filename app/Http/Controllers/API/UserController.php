<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Follows;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get all users that are not followed by logged in user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers()
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $followingIds = Auth::user()->followings()->pluck('users.id')->toArray();
        
        $followingIds[] = Auth::id();

        $users = User::whereNotIn('id', $followingIds)
            ->select('id', 'full_name', 'username', 'bio', 'is_private', 'created_at', 'updated_at')
            ->get();

        return response()->json(['users' => $users], 200);
    }

    /**
     * Get detailed user information
     * 
     * @param string $username
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserDetail($username)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $followStatus = null;
        if (Auth::id() !== $user->id) {
            $follow = Follows::where('follower_id', Auth::id())
                ->where('following_id', $user->id)
                ->first();
            
            if ($follow) {
                $followStatus = $follow->is_accepted ? 'following' : 'requested';
            }
        }

        $postsCount = $user->posts()->count();
        $followersCount = $user->followers()->where('is_accepted', true)->count();
        $followingCount = $user->followings()->where('is_accepted', true)->count();

        $posts = [];
        $canViewPosts = !$user->is_private || 
                        Auth::id() === $user->id || 
                        ($followStatus === 'following');

        if ($canViewPosts) {
            $posts = $user->posts()
                ->with('attachments')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'caption' => $post->caption,
                        'created_at' => $post->created_at,
                        'attachments' => $post->attachments->map(function ($attachment) {
                            return [
                                'id' => $attachment->id,
                                'storage_path' => $attachment->storage_path,
                            ];
                        }),
                    ];
                });
        }

        $userData = [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'username' => $user->username,
            'bio' => $user->bio,
            'is_private' => $user->is_private,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'follow_status' => $followStatus,
            'posts_count' => $postsCount,
            'followers_count' => $followersCount,
            'following_count' => $followingCount,
            'posts' => $posts
        ];

        return response()->json(['user' => $userData], 200);
    }

    /**
     * Update user profile
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'string|max:255',
            'bio' => 'nullable|string|max:500',
            'is_private' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        
        if ($request->has('full_name')) {
            $user->full_name = $request->full_name;
        }
        
        if ($request->has('bio')) {
            $user->bio = $request->bio;
        }
        
        if ($request->has('is_private')) {
            $oldIsPrivate = $user->is_private;
            $user->is_private = $request->is_private;
            
            if ($oldIsPrivate && !$request->is_private) {
                Follows::where('following_id', $user->id)
                    ->where('is_accepted', false)
                    ->update(['is_accepted' => true]);
            }
        }
        
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'bio' => $user->bio,
                'is_private' => $user->is_private,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ], 200);
    }
}