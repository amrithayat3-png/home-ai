<?php

namespace App\Http\Controllers;

use App\Services\AskHomeAi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
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

            // Only our own configuration messages are shown. Anything else stays in the log.
            $detail = $exception instanceof RuntimeException ? ' '.$exception->getMessage() : '';

            return response()->json([
                'error' => 'HOME AI could not answer right now.'.$detail.' Please try again in a moment.',
            ], 500);
        }
    }
}
