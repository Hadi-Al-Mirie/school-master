<?php

return [
    'q' => [
        'required' => 'Search query is required.',
        'string' => 'Search query must be a string.',
        'min' => 'Search query must be at least :min characters.',
        'max' => 'Search query may not be greater than :max characters.',
    ],
    'type' => [
        'required' => 'Search type is required.',
        'in' => 'Search type must be one of: teacher, student, supervisor.',
    ],
];