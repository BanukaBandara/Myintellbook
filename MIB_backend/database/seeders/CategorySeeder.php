<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $now = Carbon::now();

        $items = [
            'Engineering & Technical',
            'Information Technology',
            'Healthcare & Medical',
            'Education',
            'Science & Research',
            'Law & Public Policy',
            'Finance & Accounting',
            'Business & Management',
            'Trade & Skilled Work',
            'Transportation & Logistics',
            'Arts, Design & Media',
            'Agriculture & Environment',
            'Hospitality & Tourism',
            'recent questions'
        ];

        $rows = array_map(function ($name) use ($now) {
            return [
                'name'       => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $items);

        // Upsert to avoid duplicates on repeated runs
        DB::table('categories')->upsert(
            $rows,                // unique key
            ['name', 'updated_at']    // columns to update if exists
        );
    }
}
