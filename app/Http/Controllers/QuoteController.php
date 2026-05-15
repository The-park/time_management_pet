<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\JsonResponse;

class QuoteController extends Controller
{
    public function random(): JsonResponse
    {
        $quote = Quote::active()->inRandomOrder()->first();

        if (! $quote) {
            return response()->json([
                'text' => null,
                'author' => null,
                'source' => null,
                'category' => null,
            ]);
        }

        return response()->json([
            'text' => $quote->text,
            'author' => $quote->author,
            'source' => $quote->source,
            'category' => $quote->category,
        ]);
    }
}
