<?php

namespace App\Support;

/**
 * The six question-bank categories from the Excel import. `source` is the value
 * stored in questions.category for that sheet.
 */
final class LearnCategories
{
    public const ALL = [
        1 => [
            'name' => 'Core Intelligence & Self Awareness',
            'source' => 'Core Intelligence',
            'icon' => 'bi-lightbulb',
            'description' => 'Reasoning, self-reflection and knowing your own strengths and limits.',
        ],
        2 => [
            'name' => 'Emotional Intelligence & Empathy',
            'source' => 'Emotional Intelligence',
            'icon' => 'bi-heart',
            'description' => 'Managing emotions and understanding the feelings of others.',
        ],
        3 => [
            'name' => 'Civic & Social Awareness',
            'source' => 'Civic Awareness',
            'icon' => 'bi-people',
            'description' => 'Citizenship, social responsibility and community life.',
        ],
        4 => [
            'name' => 'Leadership & Decision-Making',
            'source' => 'Leadership',
            'icon' => 'bi-compass',
            'description' => 'Guiding teams and making sound, accountable decisions.',
        ],
        5 => [
            'name' => 'Cultural & Global Awareness',
            'source' => 'Cultural Awareness',
            'icon' => 'bi-globe2',
            'description' => 'Respecting cultures and understanding global perspectives.',
        ],
        6 => [
            'name' => 'Quality Management System (QMS)',
            'source' => 'QMS',
            'icon' => 'bi-clipboard-check',
            'description' => 'Quality standards, processes and continuous improvement.',
        ],
    ];

    public static function find(int $id): ?array
    {
        return isset(self::ALL[$id]) ? ['id' => $id, ...self::ALL[$id]] : null;
    }

    public static function list(): array
    {
        return array_map(fn (int $id) => self::find($id), array_keys(self::ALL));
    }
}
