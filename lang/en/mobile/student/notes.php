<?php

return [
    'success' => [
        'loaded' => 'Notes loaded successfully.',
    ],
    'errors' => [
        'student_not_found' => 'Student profile not found.',
        'active_year_not_found' => 'Active year not found.',
        'unexpected' => 'Unexpected error. Please try again later.',
    ],
    'validation' => [
        'status' => [
            'in' => 'The status must be one of: pending, approved, dismissed.',
        ],
        'type' => [
            'in' => 'The type must be one of: positive, negative.',
        ],
        'sort' => [
            'in' => 'The sort must be one of: newest, oldest, highest_value, lowest_value.',
        ],
        'per_page' => [
            'integer' => 'The per_page must be an integer.',
            'min' => 'The per_page must be at least 1.',
            'max' => 'The per_page may not be greater than 100.',
        ],
        'semester_id' => [
            'integer' => 'The semester_id must be an integer.',
            'exists' => 'The selected semester_id is invalid.',
        ],
    ],
];