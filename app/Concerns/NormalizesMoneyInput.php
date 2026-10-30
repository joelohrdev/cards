<?php

namespace App\Concerns;

use Illuminate\Http\Request;

trait NormalizesMoneyInput
{
    /**
     * Strip currency symbols and thousands separators so "$1,200" validates as a number.
     *
     * @param  list<string>  $keys
     */
    protected function normalizeMoneyInput(Request $request, array $keys): void
    {
        $normalized = [];

        foreach ($keys as $key) {
            $value = $request->input($key);

            if (is_string($value)) {
                $normalized[$key] = str_replace(['$', ',', ' '], '', $value);
            }
        }

        $request->merge($normalized);
    }
}
