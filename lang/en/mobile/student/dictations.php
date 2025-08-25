<?php

return [
    'success' => [
        'loaded' => 'Dictations loaded successfully.',
    ],
    'errors' => [
        'student_not_found' => 'Student profile not found.',
        'active_year_not_found' => 'Active year not found.',
        'unexpected' => 'Unexpected error. Please try again later.',
    ],
    'validation' => [
        'semester_id' => [
            'integer' => 'The semester_id must be an integer.',
            'exists' => 'The selected semester_id is invalid.',
        ],
        'teacher_id' => [
            'integer' => 'The teacher_id must be an integer.',
            'exists' => 'The selected teacher_id is invalid.',
        ],
        'section_id' => [
            'integer' => 'The section_id must be an integer.',
            'exists' => 'The selected section_id is invalid.',
        ],
        'sort' => [
            'in' => 'The sort must be one of: newest, oldest, highest_result, lowest_result.',
        ],
    ],
];