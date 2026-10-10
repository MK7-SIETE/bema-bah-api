<?php

namespace App\Support;

class CarInput
{
    /**
     * Forms sent as multipart (details + photos together) turn empty boxes into "".
     * Convert them to null, and fall back to defaults for columns that cannot be NULL.
     */
    public static function clean(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value === '') {
                $data[$key] = null;
            }
        }

        if (array_key_exists('mileage', $data) && $data['mileage'] === null) {
            $data['mileage'] = 0;
        }

        // Leave these out when empty so the database default applies.
        foreach (['is_featured', 'is_available', 'is_classic', 'source_country', 'condition'] as $column) {
            if (array_key_exists($column, $data) && $data[$column] === null) {
                unset($data[$column]);
            }
        }

        return $data;
    }
}
