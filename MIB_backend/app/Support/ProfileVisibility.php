<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Server-side enforcement of the per-field visibility settings (AccountSettings/PrivacyInfo).
 * Previously the API sent every field plus a `visibility` map and left hiding to the browser,
 * so "Only Me" data was readable by anyone calling the API. Fields marked "Only Me" are now
 * removed before the response leaves the server, for everyone except the profile's owner.
 *
 * Setting keys -> fields:
 *   first_name, last_name, gender, birth_date (private unless explicitly Public)
 *   title / organization / location / employment_type / location_type -> experience fields
 *   school / degree / field_of_study -> education fields, skills -> skill lists
 */
class ProfileVisibility
{
    private const EXPERIENCE_FIELDS = [
        'title' => ['title'],
        'organization' => ['company'],
        'location' => ['location'],
        'employment_type' => ['selectEmpType'],
        'location_type' => ['locationType'],
    ];

    private const EDUCATION_FIELDS = [
        'school' => ['school'],
        'degree' => ['degree'],
        'field_of_study' => ['field_of_study'],
    ];

    public static function isHidden(array $visibility, string $key, bool $hiddenByDefault = false): bool
    {
        $value = $visibility[$key] ?? null;
        if ($value === null || $value === '') {
            return $hiddenByDefault;
        }

        return in_array($value, ['Only Me', 'Private'], true);
    }

    /**
     * @param array $profile  Profile payload (ProfileController::show or helpers formatUserInfo shape).
     * @param array $visibility  key => 'Public' | 'Only Me' | ''.
     */
    public static function filter(array $profile, array $visibility, bool $isOwner): array
    {
        if ($isOwner) {
            return $profile;
        }

        $hidden = fn (string $key, bool $default = false) => self::isHidden($visibility, $key, $default);

        $nameHidden = false;
        foreach (['first_name', 'last_name'] as $key) {
            if ($hidden($key) && array_key_exists($key, $profile)) {
                $profile[$key] = '';
                $nameHidden = true;
            }
        }
        if ($nameHidden && array_key_exists('full_name', $profile)) {
            $profile['full_name'] = trim(($profile['first_name'] ?? '').' '.($profile['last_name'] ?? ''));
        }

        if (array_key_exists('birth_date', $profile) && $hidden('birth_date', true)) {
            $profile['birth_date'] = null;
        }
        if (array_key_exists('gender', $profile) && $hidden('gender')) {
            $profile['gender'] = null;
        }
        if (array_key_exists('school', $profile) && $hidden('school')) {
            $profile['school'] = '';
        }

        if (isset($profile['profession']) && is_array($profile['profession'])) {
            $map = ['title' => 'profession', 'organization' => 'company', 'location' => 'location'];
            foreach ($map as $key => $field) {
                if ($hidden($key) && array_key_exists($field, $profile['profession'])) {
                    $profile['profession'][$field] = '';
                }
            }
        }

        if (array_key_exists('experiance', $profile)) {
            $profile['experiance'] = self::filterItems($profile['experiance'], $visibility, self::EXPERIENCE_FIELDS);
        }
        if (array_key_exists('education', $profile)) {
            $profile['education'] = self::filterItems($profile['education'], $visibility, self::EDUCATION_FIELDS);
        }

        if (array_key_exists('skills', $profile) && $hidden('skills')) {
            $skills = self::toArray($profile['skills']);
            $profile['skills'] = array_key_exists('licensed', $skills)
                ? array_map(fn () => [], $skills)
                : [];
        }

        return $profile;
    }

    /** Experience entries for another user's profile (GET /get-work-experiances/{slug}). */
    public static function filterExperiences(mixed $items, array $visibility, bool $isOwner): mixed
    {
        return $isOwner ? $items : self::filterItems($items, $visibility, self::EXPERIENCE_FIELDS);
    }

    /** Education entries for another user's profile (GET /get-education-details/{slug}). */
    public static function filterEducation(mixed $items, array $visibility, bool $isOwner): mixed
    {
        return $isOwner ? $items : self::filterItems($items, $visibility, self::EDUCATION_FIELDS);
    }

    private static function filterItems(mixed $items, array $visibility, array $fieldMap): array
    {
        $hiddenFields = [];
        foreach ($fieldMap as $key => $fields) {
            if (self::isHidden($visibility, $key)) {
                array_push($hiddenFields, ...$fields);
            }
        }

        return array_map(function ($item) use ($hiddenFields) {
            $item = self::toArray($item);
            foreach ($hiddenFields as $field) {
                if (array_key_exists($field, $item)) {
                    $item[$field] = '';
                }
            }
            return $item;
        }, array_values(self::toArray($items)));
    }

    private static function toArray(mixed $value): array
    {
        if ($value instanceof Arrayable) {
            return $value->toArray();
        }
        if ($value instanceof \JsonSerializable) {
            return (array) $value->jsonSerialize();
        }

        return is_array($value) ? $value : [];
    }
}
