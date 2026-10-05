<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AddQuestionsToTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-questions-to-table';

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
        $questions = [
                [
                    "profession_id" => 122,
                    "question" => "What is the primary duty of a citizen in a democracy?",
                    "difficulty_level" => "easy",
                    "answer" => "A",
                    "options" => [
                        [ "label" => "A", "value" => "Voting in elections", "marks" => 5],
                        ["label" => "B", "value" => "Paying taxes", "marks" => 3],
                        ["label" => "C", "value" => "Serving in the military", "marks" => 2],
                        ["label" => "D", "value" => "Obeying traffic laws", "marks" => 2],
                        ["label" => "E", "value" => "Participating in civic life", "marks" => 4],
                    ]
                ],
                [
                    "profession_id" => 122,
                    "question" => "Which of the following is a key principle of democracy?",
                    "difficulty_level" => "medium",
                    "answer" => "B",
                    "options" => [
                            ["label" => "A", "value" => "Majority rule", "marks" => 4],
                                ["label" => "B", "value" => "Freedom of speech", "marks" => 5],
                                ["label" => "C", "value" => "One-party rule", "marks" => 0],
                                ["label" => "D", "value" => "Equal rights for all citizens", "marks" => 5],
                                ["label" => "E", "value" => "Separation of powers", "marks" => 5]
                    ]
                ],
                [
                    "profession_id" => 122,
                    "question" => "What is the main function of a constitution?",
                    "difficulty_level" => "medium",
                    "answer" => "C",
                    "options" => [
                        ["label" => "A", "value" => "To regulate trade", "marks" => 1],
                        ["label" => "B", "value" => "To outline citizens’ duties", "marks" => 4],
                        ["label" => "C", "value" => "To define government structure", "marks" => 5],
                        ["label" => "D", "value" => "To set foreign policy", "marks" => 0],
                        ["label" => "E", "value" => "To guarantee fundamental rights", "marks" => 5]
                    ]
                ],
                [
                    "profession_id" => 122,
                    "question" => "Which of the following actions best demonstrates social responsibility?",
                    "difficulty_level" => "easy",
                    "answer" => "D",
                    "options" => [
                        ["label" => "A", "value" => "Volunteering in the community", "marks" => 5],
                        ["label" => "B", "value" => "Recycling waste", "marks" => 4],
                        ["label" => "C", "value" => "Avoiding political discussions", "marks" => 0],
                        ["label" => "D", "value" => "Ignoring social issues", "marks" => 0],
                        ["label" => "E", "value" => "Participating in local decision-making", "marks" => 5]
                    ]
                ],
                [
                    "profession_id" => 122,
                    "question" => "A civil society organization is best described as:",
                    "difficulty_level" => "hard",
                    "answer" => "A",
                    "options" => [
                            ["label" => "A", "value" => "A government body", "marks" => 0],
                                ["label" => "B", "value" => "A private business", "marks" => 0],
                                ["label" => "C", "value" => "A non-governmental organization", "marks" => 5],
                                ["label" => "D", "value" => "A criminal group", "marks" => 0],
                                ["label" => "E", "value" => "A group advocating social causes", "marks" => 4]
                    ]
                    ],
                    [
                    "profession_id" => 123,
                    "question" => "What is the primary responsibility of a citizen in a democratic society?",
                    "difficulty_level" => "medium",
                    "answer" => "E",
                    "options" => [
                        ["label" => "A", "value" => "Voting in elections", "marks" => 2],
                        ["label" => "B", "value" => "Following laws", "marks" => 2],
                        ["label" => "C", "value" => "Respecting others' rights", "marks" => 2],
                        ["label" => "D", "value" => "Paying taxes", "marks" => 2],
                        ["label" => "E", "value" => "All of the above", "marks" => 5]
                    ]
                ],
                [
                    "profession_id" => 123,
                    "question" => "Which of the following is an example of civic duty?",
                    "difficulty_level" => "medium",
                    "answer" => "B",
                    "options" => [
                        ["label" => "A", "value" => "Volunteering in the community", "marks" => 2],
                        ["label" => "B", "value" => "Obeying traffic laws", "marks" => 5],
                        ["label" => "C", "value" => "Attending a wedding", "marks" => 0],
                        ["label" => "D", "value" => "Donating money", "marks" => 1],
                        ["label" => "E", "value" => "Reading newspapers", "marks" => 1]
                    ]
                ],
                [
                    "profession_id" => 123,
                    "question" => "What does 'social responsibility' mean?",
                    "difficulty_level" => "medium",
                    "answer" => "B",
                    "options" => [
                        ["label" => "A", "value" => "Caring for your family only", "marks" => 0],
                        ["label" => "B", "value" => "Contributing positively to society", "marks" => 5],
                        ["label" => "C", "value" => "Following personal goals only", "marks" => 0],
                        ["label" => "D", "value" => "Avoiding all conflicts", "marks" => 1],
                        ["label" => "E", "value" => "Not getting involved in politics", "marks" => 0]
                    ]
                ],
                [
                    "profession_id" => 123,
                    "question" => "What is the purpose of community service?",
                    "difficulty_level" => "medium",
                    "answer" => "B",
                    "options" => [
                        ["label" => "A", "value" => "Gain personal benefits", "marks" => 0],
                        ["label" => "B", "value" => "Fulfill social responsibilities", "marks" => 5],
                        ["label" => "C", "value" => "Earn money", "marks" => 0],
                        ["label" => "D", "value" => "Make connections", "marks" => 2],
                        ["label" => "E", "value" => "Avoid punishment", "marks" => 0]
                    ]
                ],
                [
                    "profession_id" => 123,
                    "question" => "Which of the following best describes social justice?",
                    "difficulty_level" => "medium",
                    "answer" => "B",
                    "options" => [
                        ["label" => "A", "value" => "Equal distribution of wealth", "marks" => 2],
                        ["label" => "B", "value" => "Equal access to opportunities", "marks" => 5],
                        ["label" => "C", "value" => "Only rich people having privileges", "marks" => 0],
                        ["label" => "D", "value" => "Discrimination based on race", "marks" => 0],
                        ["label" => "E", "value" => "No government involvement", "marks" => 0]
                    ]
                ],
                [
                    "profession_id" => 124,
                    "question" => "What is emotional intelligence primarily about?",
                    "difficulty_level" => "medium",
                    "answer" => "E",
                    "options" => [
                        ["label" => "A", "value" => "Managing your own emotions", "marks" => 2],
                        ["label" => "B", "value" => "Understanding others' emotions", "marks" => 2],
                        ["label" => "C", "value" => "Motivating yourself", "marks" => 2],
                        ["label" => "D", "value" => "Building relationships", "marks" => 2],
                        ["label" => "E", "value" => "All of the above", "marks" => 5],
                    ]
                ],
                [
                    "profession_id" => 124,
                    "question" => "Empathy means:",
                    "difficulty_level" => "medium",
                    "answer" => "B",
                    "options" => [
                        ["label" => "A", "value" => "Feeling pity for others", "marks" => 0],
                        ["label" => "B", "value" => "Understanding and sharing others' feelings", "marks" => 5],
                        ["label" => "C", "value" => "Avoiding others' problems", "marks" => 0],
                        ["label" => "D", "value" => "Fixing all issues immediately", "marks" => 1],
                        ["label" => "E", "value" => "Ignoring emotions", "marks" => 0],
                    ]
                ],
                [
                    "profession_id" => 124,
                    "question" => "Which skill is essential for resolving conflicts?",
                    "difficulty_level" => "medium",
                    "answer" => "B",
                    "options" => [
                        ["label" => "A", "value" => "Avoiding people", "marks" => 0],
                        ["label" => "B", "value" => "Effective communication", "marks" => 5],
                        ["label" => "C", "value" => "Suppressing emotions", "marks" => 0],
                        ["label" => "D", "value" => "Being aggressive", "marks" => 0],
                        ["label" => "E", "value" => "Blaming others", "marks" => 0],
                    ]
                ],
                [
                    "profession_id" => 124,
                    "question" => "How can emotional intelligence improve teamwork?",
                    "difficulty_level" => "medium",
                    "answer" => "B",
                    "options" => [
                        ["label" => "A", "value" => "By ignoring emotions", "marks" => 0],
                        ["label" => "B", "value" => "By improving cooperation and trust", "marks" => 5],
                        ["label" => "C", "value" => "By focusing on individual goals only", "marks" => 0],
                        ["label" => "D", "value" => "By creating conflicts", "marks" => 0],
                        ["label" => "E", "value" => "By avoiding responsibilities", "marks" => 0],
                    ]
                ],
                [
                    "profession_id" => 124,
                    "question" => "Which is a key element of self-regulation in emotional intelligence?",
                    "difficulty_level" => "medium",
                    "answer" => "C",
                    "options" => [
                        ["label" => "A", "value" => "Impulsiveness", "marks" => 0],
                        ["label" => "B", "value" => "Blaming others", "marks" => 0],
                        ["label" => "C", "value" => "Self-control", "marks" => 5],
                        ["label" => "D", "value" => "Avoidance", "marks" => 0],
                        ["label" => "E", "value" => "Ignoring feedback", "marks" => 0],
                    ]
                    ],
                    [
                    "profession_id" => 125,
                    "question" => "What is ethical reasoning?",
                    "difficulty_level" => "medium",
                    "answer" => "A",
                    "options" => [
                        ["label" => "A", "value" => "Judging actions as right or wrong", "marks" => 5],
                        ["label" => "B", "value" => "Ignoring moral values", "marks" => 0],
                        ["label" => "C", "value" => "Following personal desires only", "marks" => 0],
                        ["label" => "D", "value" => "Doing what is popular", "marks" => 0],
                        ["label" => "E", "value" => "Avoiding responsibility", "marks" => 0],
                    ]
                ],
                [
                    "profession_id" => 125,
                    "question" => "Which of the following is an example of unethical behavior?",
                    "difficulty_level" => "medium",
                    "answer" => "B",
                    "options" => [
                        ["label" => "A", "value" => "Honesty", "marks" => 0],
                        ["label" => "B", "value" => "Corruption", "marks" => 5],
                        ["label" => "C", "value" => "Fair treatment", "marks" => 0],
                        ["label" => "D", "value" => "Transparency", "marks" => 0],
                        ["label" => "E", "value" => "Justice", "marks" => 0],
                    ]
                ],
                [
                    "profession_id" => 125,
                    "question" => "Why is integrity important in professional life?",
                    "difficulty_level" => "medium",
                    "answer" => "A",
                    "options" => [
                        ["label" => "A", "value" => "To gain trust", "marks" => 5],
                        ["label" => "B", "value" => "To earn more money", "marks" => 0],
                        ["label" => "C", "value" => "To avoid criticism", "marks" => 0],
                        ["label" => "D", "value" => "To manipulate others", "marks" => 0],
                        ["label" => "E", "value" => "To work less", "marks" => 0],
                    ]
                ],
                [
                    "profession_id" => 125,
                    "question" => "What does moral reasoning involve?",
                    "difficulty_level" => "medium",
                    "answer" => "A",
                    "options" => [
                        ["label" => "A", "value" => "Applying ethical principles in decisions", "marks" => 5],
                        ["label" => "B", "value" => "Avoiding responsibility", "marks" => 0],
                        ["label" => "C", "value" => "Seeking personal benefit only", "marks" => 0],
                        ["label" => "D", "value" => "Ignoring social norms", "marks" => 0],
                        ["label" => "E", "value" => "Acting without thinking", "marks" => 0],
                    ]
                ],
                [
                    "profession_id" => 125,
                    "question" => "Which is a universal ethical principle?",
                    "difficulty_level" => "medium",
                    "answer" => "A",
                    "options" => [
                        ["label" => "A", "value" => "Respect for human rights", "marks" => 5],
                        ["label" => "B", "value" => "Discrimination", "marks" => 0],
                        ["label" => "C", "value" => "Exploitation", "marks" => 0],
                        ["label" => "D", "value" => "Lying", "marks" => 0],
                        ["label" => "E", "value" => "Cheating", "marks" => 0],
                    ]
                ]

            ];


            $questions = collect($questions)->map(function($question){
                $q = new \App\Models\Question();
                $q->profession_id = $question['profession_id'];
                $q->question = $question['question'];
                $q->difficulty_level = $question['difficulty_level'];
                $q->answer = $question['answer'];
                $q->save();

                foreach($question['options'] as $option){
                    \App\Models\Option::create([
                        'question_id' => $q->id,
                        'label' => $option['label'],
                        'value' => $option['value'],
                        'marks' => $option['marks'],
                    ]);
                }

                return $q;
            });
    }
}
