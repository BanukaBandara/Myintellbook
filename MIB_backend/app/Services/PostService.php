<?php

namespace App\Services;
use Carbon\Carbon;
use App\Models\Post;
use App\Models\Question;
use App\Models\Score;
use App\Notifications\NewUserNotification;
use Illuminate\Support\Facades\Log; 

class PostService{

    public function postDetails($posts)
    {
        
        return $posts->map(function($post){

            // Format the post content
            
            return [
                'post_id'=>$post->id,
                'post_content'=> $this->formatPostContent($post),
                'post_by'=>$post->user->profile->first_name,
                'posted_at'=>Carbon::parse($post->posting_date)->diffForHumans(),
                'post_image'=>$post->post_image,
                'profile_image'=>$post->user->profile->profile_image,
                'comments'=> ($post->comments) ? $this->formatCommentContent($post->comments)->toArray() : [],
            ];
        });
    }

    private function formatPostContent($post)
    {
        $content =$post->content;

        return [
            'id' => $content['id'],
            'question' => $content['question'],
            'category'=> $content['category'] ?? 'General',
            'options' => $content['options'],
            'level' => $content['difficulty_level'] ?? 'unknown',
            'answer' => $content['answer'],
            'points' => $content['points'] ?? 0,
        ];
    }

    private function formatCommentContent($comments)
    {
        $formated = $comments->map(function($comment){
            return [
                'id' => $comment->id,
                'user_id' => $comment->user_id,
                'comment' => $comment->comment,
                'is_like' => $comment->is_like,
                'created_at' => Carbon::parse($comment->created_at)->diffForHumans(),
                'profile_image' => $comment->user->profile->profile_image,
                'comment_by' => $comment->user->profile->first_name,
            ];
        });

        return $formated;
    }

    private function checkPostExists($user_id, $question_id, $date)
    {
        $post = Post::where('user_id', $user_id)
                ->where('question_id', $question_id)
                ->whereDate('posting_date', $date)
                ->first();

        return $post ? true : false;
    }

    public function createPost($user, $question, $date)
    {
        if($this->checkPostExists($user->id, $question->id, $date)){
            return;
        }

        $post = Post::create([
                'user_id' => $user->id,
                'content' => $this->jsonEncode($question,$user),
                'question_id' => $question->id,
                'posting_date' => $date,
            ]);
        
        return $post;
    }

    public function updatePost($question, $user, $answer,$yesterday){

        $post = Post::where('user_id',$user->id)->whereDate('posting_date',$yesterday)->update([
            'user_id' => $user->id,
            'content' => $this->jsonEncode($question,$user),
            'is_approved' => true,
        ]);
        $marks = ($answer== 'correct') ? 5 : 0;
        $user->notify(new NewUserNotification("You have did the daily question $yesterday ! you have gain $marks points"));
        return $post;
    }

    private function jsonEncode($question,$user){

        $scoresService = new \App\Services\ScoreService();
        $point = ($scoresService->getScores($user->id,$question->id,Question::class)) ? $scoresService->getScores($user->id,$question->id,Question::class)->points : 0; 
        $options = $this->formatOptions($question->QuesOptions);
        
        $json = json_encode([
                    'id' => $question->id,
                    'question' => $question->question,
                    'options' => $options,
                    'answer' => $question->answer,
                    'difficulty_level' => $question->difficulty_level,
                    'category' => $question->profession->name,
                    'points' => $point,
                ]);

        return $json;
    }

     private function formatOptions($options)
    {
        return collect($options)->mapWithKeys(function($opt){
            return [$opt->label => $opt->value];
        });
    }
}