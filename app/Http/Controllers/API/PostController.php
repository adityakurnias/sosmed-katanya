<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostAttachments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'caption' => 'required|string',
            'post_attachments' => 'required|array',
            'post_attachments.*' => 'required|file|mimetypes:image/jpeg,image/png,image/gif',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $post = Post::create([
            'user_id' => Auth::id(),
            'caption' => $request->caption,
        ]);

        if ($request->hasFile('post_attachments')) {
            foreach ($request->file('post_attachments') as $file) {
                $path = $file->store('posts', 'public');

                PostAttachments::create([
                    'post_id' => $post->id,
                    'storage_path' => str_replace('public/', '', $path),
                ]);
            }
        }

        return response()->json([
            'message' => 'Post created successfully',
            'post' => $post,
            'attachments' => PostAttachments::where('post_id', $post->id)->get()
        ], 201);
    }

    public function getposts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:0',
            'size' => 'integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $page = $request->query('page', 0);
        $size = $request->query('size', 10);

        $posts = Post::with(['user', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->offset($page * $size)
            ->limit($size)
            ->get();

        $response = $posts->map(function ($post) {
            return [
                'id' => $post->id,
                'caption' => $post->caption,
                'created_at' => $post->created_at,
                'deleted_at' => $post->deleted_at,
                'user' => [
                    'id' => $post->user->id,
                    'full_name' => $post->user->full_name,
                    'username' => $post->user->username,
                    'bio' => $post->user->bio,
                    'is_private' => $post->user->is_private,
                    'created_at' => $post->user->created_at,
                ],
                'attachments' => $post->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'storage_path' => $attachment->storage_path,
                    ];
                }),
            ];
        });

        return response()->json([
            'page' => $page,
            'size' => $size,
            'posts' => $response
        ], 200);
    }
}
