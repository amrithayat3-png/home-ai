<?php

namespace App\Http\Controllers;

use App\Services\AskHomeAi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class AskController extends Controller
{
    public function ask(Request $request, AskHomeAi $assistant): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:600'],
        ]);

        try {
            return response()->json($assistant->answer($data['question']));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'error' => 'HOME AI could not answer right now. '.Str::limit($exception->getMessage(), 200),
            ], 500);
        }
    }
}
