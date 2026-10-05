<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Question;
use App\Models\Option;

class addingOptionsInToSeperateTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:adding-options-in-to-seperate-table';

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
        Question::all()->each(function ($question) {
            dd(is_array($question->options));
            if (is_array($question->options)) {

                collect($question->options)->map(function ($option, $index) use ($question) {
                     \App\Models\Option::create([
                        'question_id' => $question->id,
                        'label' => $index ?? '',
                        'value' => $option ?? null,
                        'marks' => ($question->answer == $index) ?  5 : 0,
                    ]);
                });
            }
        });       
    }
}
