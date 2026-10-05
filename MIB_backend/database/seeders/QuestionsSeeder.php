<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\QuestionsImport;

class QuestionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            ProfessionSeeder::class,
        ]);

        $path = storage_path('app/imports/MyIntellibook_Question_Bank_v2.0.xlsx');
        if (!is_file($path)) {
            throw new \RuntimeException("Question bank file not found: {$path}");
        }

        Excel::import(new QuestionsImport(), $path);
    }
}
