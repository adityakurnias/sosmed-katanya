<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Follows;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FollowController extends Controller
{
    /**
     * Follow a user
     * 
     * @param string $username
     * @return \Illuminate\Http\JsonResponse
     */
    public function follow($username)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'You are not allowed to follow yourself'], 422);
        }

        $existingFollow = Follows::where('follower_id', Auth::id())
            ->where('following_id', $user->id)
            ->first();

        if ($existingFollow) {
            return response()->json([
                'message' => 'You already followed',
                'status' => $existingFollow->is_accepted ? 'following' : 'requested'
            ], 422);
        }

        $follow = Follows::create([
            'follower_id' => Auth::id(),
            'following_id' => $user->id,
            'is_accepted' => !$user->is_private, // Auto-accept if user is not private
        ]);

        return response()->json([
            'message' => 'Follow success',
            'status' => $follow->is_accepted ? 'following' : 'requested'
        ], 200);
    }

    /**
     * Unfollow a user
     * 
     * @param string $username
     * @return \Illuminate\Http\JsonResponse
     */
    public function unfollow($username)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $follow = Follows::where('follower_id', Auth::id())
            ->where('following_id', $user->id)
            ->first();

        if (!$follow) {
            return response()->json(['message' => 'You are not following the user'], 422);
        }

        $follow->delete();

        return response()->json(null, 204);
    }

    /**
     * Get users that the authenticated user is following
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFollowing()
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $following = Auth::user()->followings()
            ->with(['followers' => function ($query) {
                $query->where('follower_id', Auth::id());
            }])
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'username' => $user->username,
                    'bio' => $user->bio,
                    'is_private' => $user->is_private,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'is_requested' => $user->followers->first() ? 
                        !$user->followers->first()->pivot->is_accepted : false
                ];
            });

        return response()->json(['following' => $following], 200);
    }

    /**
     * Accept a follow request
     * 
     * @param string $username
     * @return \Illuminate\Http\JsonResponse
     */
    public function acceptFollowRequest($username)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $follow = Follows::where('follower_id', $user->id)
            ->where('following_id', Auth::id())
            ->first();

        if (!$follow) {
            return response()->json(['message' => 'The user is not following you'], 422);
        }

        if ($follow->is_accepted) {
            return response()->json(['message' => 'Follow request is already accepted'], 422);
        }

        $follow->is_accepted = true;
        $follow->save();

        return response()->json(['message' => 'Follow request accepted'], 200);
    }

    /**
     * Get followers of the authenticated user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFollowers()
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $followers = Auth::user()->followers()
            ->withPivot('is_accepted')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'username' => $user->username,
                    'bio' => $user->bio,
                    'is_private' => $user->is_private,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'is_accepted' => $user->pivot->is_accepted
                ];
            });

        return response()->json(['followers' => $followers], 200);
    }

    /**
     * Get pending follow requests for the authenticated user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingRequests()
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $pendingRequests = Auth::user()->followers()
            ->withPivot('is_accepted')
            ->wherePivot('is_accepted', false)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'username' => $user->username,
                    'bio' => $user->bio,
                    'is_private' => $user->is_private,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s')
                ];
            });

        return response()->json(['pending_requests' => $pendingRequests], 200);
    }
}