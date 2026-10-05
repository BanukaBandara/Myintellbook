<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CommentRequest;

class CommentController extends Controller
{
    private $commentsService;

    public function __construct()
    {
        $this->commentsService = new \App\Services\CommentsService();
    }

    public function setComment(CommentRequest $request){
        $comment = $this->commentsService->setComment($request);
        return $comment;
    }
}
