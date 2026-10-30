<?php

namespace App\Http\Controllers\Selling;

use App\Enums\Grader;
use App\Http\Controllers\Controller;
use App\Services\Psa\PsaCertLookup;
use App\Services\Psa\PsaLookupFailed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Looks up a slab's certification number with the grading company (PSA only for now).
 *
 * 200 {result: CertLookupResult}; 404/422/502/503 {message} explain what to do next.
 */
class CertLookupController extends Controller
{
    public function __invoke(Request $request, PsaCertLookup $psa): JsonResponse
    {
        $validated = $request->validate([
            'grader' => ['required', Rule::enum(Grader::class)],
            'cert_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
        ], [
            'cert_number.required' => 'Enter the cert number from the slab label.',
            'cert_number.regex' => 'Cert numbers only contain letters and digits. Check the number on the slab label.',
        ]);

        if ($validated['grader'] !== Grader::Psa->value) {
            return response()->json(['message' => 'Cert lookup only works for PSA slabs right now. Enter the details from the label by hand.'], 422);
        }

        if (! $psa->isConfigured()) {
            return response()->json(['message' => 'PSA cert lookup isn’t set up yet. Add your PSA API token as PSA_API_TOKEN in the .env file, then try again.'], 503);
        }

        try {
            $result = $psa->find($validated['cert_number']);
        } catch (PsaLookupFailed $exception) {
            report($exception);

            return response()->json(['message' => 'PSA didn’t answer. Check that your PSA_API_TOKEN is still valid, or try again in a few minutes.'], 502);
        }

        if ($result === null) {
            return response()->json(['message' => "PSA has no cert {$validated['cert_number']}. Check the number on the slab label."], 404);
        }

        return response()->json(['result' => $result]);
    }
}
