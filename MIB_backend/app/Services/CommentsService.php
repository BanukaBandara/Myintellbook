<?php

namespace App\Services;
use Carbon\Carbon;
use App\Models\Post;

class CommentsService{
    public function setComment($request)
    {
        try{

            if($request->type == 'post'){
                $post_id = $request->post_id;
            }else{
                $post_id = Post::where('user_id',auth()->user()->id)->where('question_id',$request->post_id)->first()->id;
            }
            

            $comment = new \App\Models\Comment();
            $comment->post_id = $post_id;
            $comment->user_id = auth()->id();
            $comment->comment = $request->text;
            $comment->is_like = $request->isLike; // Default value for isLike
            $comment->created_at = Carbon::now();
            $comment->save();

            return response()->json(['message' => 'Comment added successfully', 'comment' => $comment], 200);
        }catch(\Exception $e){

            // Log the error for debugging purposes
            \Log::error('Error adding comment: ' . $e->getMessage());
            return response()->json(['message' => 'Error adding comment: ' . $e->getMessage()], 500);
        }
        
    }
}