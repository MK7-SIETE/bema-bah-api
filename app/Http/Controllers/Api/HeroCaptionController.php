<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroImage;
use Illuminate\Http\Request;

class HeroCaptionController extends Controller
{
    // SUPER: change the caption (a title and one line) shown on a home page hero photo.
    // An empty box means "no caption".
    public function update(Request $request, HeroImage $heroImage)
    {
        $data = $request->validate([
            'title'    => ['nullable', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:200'],
        ]);

        foreach (['title', 'subtitle'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $heroImage->{$field} = $value === '' ? null : $value;
            }
        }

        $heroImage->save();

        return response()->json($heroImage);
    }
}
