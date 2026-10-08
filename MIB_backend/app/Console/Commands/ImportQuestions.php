<?php

namespace App\Console\Commands;

use App\Imports\QuestionsImport;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProfessionSeeder;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportQuestions extends Command
{
    protected $signature = 'import:questions {file}';

    protected $description = 'Import questions from an Excel workbook';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (!is_file($filePath)) {
            $storedFilePath = storage_path('app/imports/'.basename($filePath));
            if (is_file($storedFilePath)) {
                $filePath = $storedFilePath;
            }
        }

        if (!is_file($filePath)) {
            $this->error("Question workbook not found: {$this->argument('file')}");
            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => CategorySeeder::class]);
        $this->call('db:seed', ['--class' => ProfessionSeeder::class]);

        Excel::import(new QuestionsImport(), $filePath);

        $this->info('Questions imported successfully.');
        return self::SUCCESS;
    }
}
