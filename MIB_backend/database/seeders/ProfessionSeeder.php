<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\profession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProfessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $now = Carbon::now();

        // Define industries and job titles
        $jobTitles = [
            'Engineering & Technical' => [
                'Engineering Analyst',
                'Junior Engineer',
                'Senior Engineer',
                'Design Engineer',
                'Chartered Engineer',
                'Mechanical Engineer',
                'Electrical Engineer',
                'Civil Engineer',
                'Chemical Engineer',
                'Structural Engineer',
                'Software Engineer',
                'Systems Engineer',
                'Marine Engineer',
                'QA/QC Engineer',
                'Project Engineer',
                'Mechatronics Engineer',
            ],
            'Information Technology' => [
                'IT Support Specialist',
                'Software Developer',
                'Web Developer',
                'Database Administrator',
                'Cybersecurity Analyst',
                'Network Engineer',
                'UI/UX Designer',
                'Cloud Architect',
                'DevOps Engineer',
                'Data Analyst',
                'Machine Learning Engineer',
            ],
            'Healthcare & Medical' => [
                'Medical Doctor',
                'Nurse',
                'Pharmacist',
                'Lab Technician',
                'Radiologist',
                'Surgeon',
                'Dental Surgeon',
                'Physiotherapist',
                'Paramedic',
                'Psychologist',
                'Public Health Officer',
            ],
            'Education' => [
                'Teacher',
                'Lecturer',
                'Professor',
                'Education Coordinator',
                'School Principal',
                'Tutor',
                'Curriculum Developer',
                'Special Needs Educator',
            ],
            'Science & Research' => [
                'Scientist',
                'Chemist',
                'Physicist',
                'Biologist',
                'Environmental Scientist',
                'Research Assistant',
                'Laboratory Analyst',
                'Meteorologist',
            ],
            'Law & Public Policy' => [
                'Lawyer',
                'Judge',
                'Legal Officer',
                'Attorney-at-Law',
                'Compliance Officer',
                'Policy Analyst',
                'Public Administrator',
                'Political Scientist',
            ],
            'Finance & Accounting' => [
                'Accountant',
                'Chartered Accountant',
                'Auditor',
                'Finance Manager',
                'Financial Analyst',
                'Investment Advisor',
                'Tax Consultant',
                'Bank Officer',
            ],
            'Business & Management' => [
                'CEO / Managing Director',
                'Operations Manager',
                'Project Manager',
                'Marketing Executive',
                'Sales Representative',
                'HR Manager',
                'Procurement Officer',
                'Customer Service Officer',
                'Entrepreneur',
                'Business Analyst',
            ],
            'Trade & Skilled Work' => [
                'Electrician',
                'Plumber',
                'Welder',
                'Fitter',
                'Carpenter',
                'Mason',
                'Mechanic',
                'Painter',
                'Equipment Technician',
                'HVAC Technician',
            ],
            'Transportation & Logistics' => [
                'Driver',
                'Pilot',
                'Ship Captain',
                'Aircraft Engineer',
                'Logistics Manager',
                'Freight Forwarder',
                'Transport Planner',
            ],
            'Arts, Design & Media' => [
                'Graphic Designer',
                'Animator',
                'Architect',
                'Interior Designer',
                'Photographer',
                'Journalist',
                'News Presenter',
                'Content Creator',
                'Fashion Designer',
            ],
            'Agriculture & Environment' => [
                'Farmer',
                'Agricultural Officer',
                'Veterinarian',
                'Forestry Officer',
                'Environmental Officer',
                'Irrigation Technician',
            ],
            'Hospitality & Tourism' => [
                'Chef',
                'Hotel Manager',
                'Receptionist',
                'Tour Guide',
                'Travel Agent',
                'Event Planner',
                'Housekeeper',
            ],
            'recent questions'=>[
                'today special questions',
            ]
        ];

        $rows = [];

        foreach ($jobTitles as $industryName => $titles) {
            $industry = DB::table('categories')->where('name', $industryName)->first();
            if (!$industry) {
                continue; // skip if industry not found
            }

            foreach ($titles as $title) {
                $rows[] = [
                    'name'        => $title,
                    'category_id' => $industry->id,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        DB::table('professions')->upsert(
            $rows,               // unique key
            ['name', 'category_id', 'updated_at'] // update if exists
        );
    }
}