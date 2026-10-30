<?php

namespace App\Http\Controllers\Selling;

use App\Http\Controllers\Controller;
use App\Services\CardIdentification\CardIdentificationFailed;
use App\Services\CardIdentification\CardIdentifier;
use App\Services\CardIdentification\CatalogMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Identifies a card from a photo with a vision model and finds matching catalog cards.
 */
class PhotoIdentificationController extends Controller
{
    /**
     * Whether photo identification is available, so the button can explain itself up front.
     */
    public function show(CardIdentifier $identifier): JsonResponse
    {
        return response()->json(['configured' => $identifier->isConfigured()]);
    }

    /**
     * 200 {suggestion: PhotoSuggestion}; 422/429/502/503 {message} explain what to do next.
     */
    public function store(Request $request, CardIdentifier $identifier, CatalogMatcher $matcher): JsonResponse
    {
        if (! $identifier->isConfigured()) {
            return response()->json(['message' => 'Photo identification isn’t set up yet. Add an Anthropic API key as ANTHROPIC_API_KEY in the .env file to turn it on.'], 503);
        }

        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:3584'],
        ], [
            'photo.required' => 'Take or choose a photo of the card.',
            'photo.mimes' => 'Use a JPEG, PNG, WebP, or GIF photo. On iPhone, set the camera format to “Most Compatible”.',
            'photo.max' => 'That photo is too large. Use one under 3.5 MB.',
        ]);

        try {
            $suggestion = $identifier->identify($request->file('photo'));
        } catch (CardIdentificationFailed $exception) {
            report($exception);

            return response()->json(['message' => 'Couldn’t read the card from that photo. Try again with the whole card in frame, in good light, without glare.'], 502);
        }

        return response()->json([
            'suggestion' => [...$suggestion, 'matches' => $matcher->matches($suggestion)],
        ]);
    }
}
