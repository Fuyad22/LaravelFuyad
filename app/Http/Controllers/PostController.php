<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PostController extends Controller
{
    /**
     * Get all posts
     * GET /api/posts
     */
    public function index()
    {
        try {
            // Use file storage
            $posts = $this->getPostsFromFile();
            
            return response()->json([
                'success' => true,
                'data' => $posts
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch posts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create a new post
     * POST /api/posts
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'author' => 'required|string|max:100',
            'email' => 'required|email|max:255'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Use file storage
            $post = $this->savePostToFile($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Post created successfully',
                'data' => $post
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create post',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get a single post
     * GET /api/posts/{id}
     */
    public function show($id)
    {
        try {
            // Use file storage
            $post = $this->getPostFromFile($id);
            if (!$post) throw new \Exception('Not found');
            
            return response()->json([
                'success' => true,
                'data' => $post
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found'
            ], 404);
        }
    }
    
    /**
     * Update a post
     * PUT /api/posts/{id}
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'author' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email|max:255'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Use file storage
            $post = $this->updatePostInFile($id, $request->all());
            if (!$post) throw new \Exception('Not found');
            
            return response()->json([
                'success' => true,
                'message' => 'Post updated successfully',
                'data' => $post
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update post',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a post
     * DELETE /api/posts/{id}
     */
    public function destroy($id)
    {
        try {
            // Use file storage
            $deleted = $this->deletePostFromFile($id);
            if (!$deleted) throw new \Exception('Not found');
            
            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete post'
            ], 500);
        }
    }

    // File storage fallback methods
    private function getPostsFromFile()
    {
        $filePath = storage_path('posts.json');
        if (!file_exists($filePath)) {
            return [];
        }
        $content = file_get_contents($filePath);
        $posts = json_decode($content, true) ?: [];
        return array_map(function($post) {
            return (object) $post;
        }, $posts);
    }

    private function getPostFromFile($id)
    {
        $posts = $this->getPostsFromFile();
        foreach ($posts as $post) {
            if ($post->_id == $id) {
                return $post;
            }
        }
        return null;
    }

    private function savePostToFile($postData)
    {
        $filePath = storage_path('posts.json');
        $posts = $this->getPostsFromFile();
        
        $newPost = [
            '_id' => (string) (count($posts) + 1),
            'title' => $postData['title'],
            'content' => $postData['content'],
            'author' => $postData['author'],
            'email' => $postData['email'],
            'email_verified' => false,
            'verification_code' => null,
            'code_expires_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $posts[] = (object) $newPost;
        file_put_contents($filePath, json_encode($posts, JSON_PRETTY_PRINT));
        
        return (object) $newPost;
    }

    private function updatePostInFile($id, $data)
    {
        $filePath = storage_path('posts.json');
        $content = file_get_contents($filePath);
        $posts = json_decode($content, true) ?: [];
        
        foreach ($posts as &$post) {
            if ($post['_id'] == $id) {
                if (isset($data['title'])) $post['title'] = $data['title'];
                if (isset($data['content'])) $post['content'] = $data['content'];
                if (isset($data['author'])) $post['author'] = $data['author'];
                if (isset($data['email'])) {
                    if ($data['email'] !== $post['email']) {
                        $post['email_verified'] = false;
                    }
                    $post['email'] = $data['email'];
                }
                $post['updated_at'] = date('Y-m-d H:i:s');
                
                file_put_contents($filePath, json_encode($posts, JSON_PRETTY_PRINT));
                return (object) $post;
            }
        }
        return null;
    }

    private function deletePostFromFile($id)
    {
        $filePath = storage_path('posts.json');
        $content = file_get_contents($filePath);
        $posts = json_decode($content, true) ?: [];
        
        $filtered = array_filter($posts, function($post) use ($id) {
            return $post['_id'] != $id;
        });
        
        if (count($filtered) === count($posts)) {
            return false;
        }
        
        file_put_contents($filePath, json_encode(array_values($filtered), JSON_PRETTY_PRINT));
        return true;
    }
}
