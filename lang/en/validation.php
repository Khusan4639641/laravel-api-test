<?php

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute field must contain a valid email address.',
    'string' => 'The :attribute field must be a string.',
    'numeric' => 'The :attribute field must be a number.',
    'integer' => 'The :attribute field must be an integer.',
    'min' => ['string' => 'The :attribute field must be at least :min characters.', 'numeric' => 'The :attribute field must be at least :min.'],
    'max' => ['string' => 'The :attribute field must not exceed :max characters.', 'numeric' => 'The :attribute field must not exceed :max.'],
    'confirmed' => 'The :attribute confirmation does not match.',
    'unique' => 'The :attribute value is already in use.',
    'exists' => 'The selected :attribute value is invalid.',
];
