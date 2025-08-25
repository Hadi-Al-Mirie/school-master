<?php

return [
    'success' => [
        'loaded' => 'Attendances loaded successfully.',
    ],
    'errors' => [
        'student_not_found' => 'Student profile not found.',
        'active_year_not_found' => 'Active year not found.',
        'unexpected' => 'Unexpected error. Please try again later.',
    ],
    'validation' => [
        'attendance_type_id' => [
            'integer' => 'The attendance_type_id must be an integer.',
            'exists' => 'The selected attendance_type_id is invalid.',
        ],
        'semester_id' => [
            'integer' => 'The semester_id must be an integer.',
            'exists' => 'The selected semester_id is invalid.',
        ],
        'date_from' => [
            'date' => 'The date_from must be a valid date (Y-m-d).',
        ],
        'date_to' => [
            'date' => 'The date_to must be a valid date (Y-m-d).',
            'after_or_equal' => 'The date_to must be a date after or equal to date_from.',
        ],
        'sort' => [
            'in' => 'The sort must be one of: newest, oldest.',
        ],
    ],
];