<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateaOPtionsMarks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:updatea-options-marks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $marks = [0,2,3,4];

        $questions = \App\Models\Question::get()->map(function($question) use ($marks){
            $options = $question->QuesOptions;
            foreach($options as $option){
                if($option->marks != 5){
                    $option->marks = $marks[array_rand($marks)];
                    $option->save();
                }
            }
        });
    }
}
