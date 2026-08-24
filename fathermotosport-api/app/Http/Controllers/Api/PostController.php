<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    /**
     * Listado paginado de artículos publicados.
     */
    public function index(): AnonymousResourceCollection
    {
        $posts = Post::query()
            ->published()
            ->with('author:id,first_name,last_name')
            ->latest('published_at')
            ->paginate(10)
            ->withQueryString();

        return PostResource::collection($posts);
    }

    /**
     * Detalle de un artículo publicado por slug.
     */
    public function show(string $slug): PostResource
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->with('author:id,first_name,last_name')
            ->firstOrFail();

        return new PostResource($post);
    }
}
