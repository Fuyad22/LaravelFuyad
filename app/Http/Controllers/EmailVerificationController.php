<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Mail\VerificationCodeMail;
use Carbon\Carbon;

class EmailVerificationController extends Controller
{
    /**
     * Send verification code
     * POST /api/send-verification-code
     */
    public function sendVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Find post by email (use file storage)
            $post = $this->findPostByEmailInFile($request->email);
            
            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'No post found with this email'
                ], 404);
            }
            
            // Generate 6-digit code
            $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            
            // Update post with verification code
            $this->updateVerificationCodeInFile($request->email, $code);
            
            // Send email (logged to storage/logs/laravel.log)
            try {
                Mail::to($request->email)->send(new VerificationCodeMail($code));
            } catch (\Exception $mailError) {
                // Log but don't fail if email sending fails
                \Log::warning('Failed to send verification email: ' . $mailError->getMessage());
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Verification code sent to ' . $request->email . '. Check your email!',
                'code' => $code, // For demo - shows code in response
                'note' => 'Email logged to storage/logs/laravel.log'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Verify email with code
     * POST /api/verify-email
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Find post by email (use file storage)
            $post = $this->findPostByEmailInFile($request->email);
            
            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not found'
                ], 404);
            }
            
            // Check if code matches
            if ($post->verification_code !== $request->code) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification code'
                ], 400);
            }
            
            // Check if code expired
            if (isset($post->code_expires_at) && $post->code_expires_at && Carbon::now()->greaterThan($post->code_expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification code has expired'
                ], 400);
            }
            
            // Mark email as verified
            $this->verifyEmailInFile($request->email);
            
            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully',
                'data' => $post
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // File storage helper methods
    private function findPostByEmailInFile($email)
    {
        $filePath = storage_path('posts.json');
        if (!file_exists($filePath)) {
            return null;
        }
        $content = file_get_contents($filePath);
        $posts = json_decode($content, true) ?: [];
        
        foreach ($posts as $post) {
            if (isset($post['email']) && $post['email'] === $email) {
                // Convert dates to Carbon objects for consistency
                if (isset($post['code_expires_at']) && $post['code_expires_at']) {
                    $post['code_expires_at'] = Carbon::parse($post['code_expires_at']);
                }
                return (object) $post;
            }
        }
        return null;
    }

    private function updateVerificationCodeInFile($email, $code)
    {
        $filePath = storage_path('posts.json');
        $content = file_get_contents($filePath);
        $posts = json_decode($content, true) ?: [];
        
        foreach ($posts as &$post) {
            if ($post['email'] === $email) {
                $post['verification_code'] = $code;
                $post['code_expires_at'] = Carbon::now()->addMinutes(15)->toDateTimeString();
                file_put_contents($filePath, json_encode($posts, JSON_PRETTY_PRINT));
                return;
            }
        }
    }

    private function verifyEmailInFile($email)
    {
        $filePath = storage_path('posts.json');
        $content = file_get_contents($filePath);
        $posts = json_decode($content, true) ?: [];
        
        foreach ($posts as &$post) {
            if ($post['email'] === $email) {
                $post['email_verified'] = true;
                $post['verification_code'] = null;
                $post['code_expires_at'] = null;
                file_put_contents($filePath, json_encode($posts, JSON_PRETTY_PRINT));
                return;
            }
        }
    }
}
