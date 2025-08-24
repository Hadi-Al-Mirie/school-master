<?php

return [
    'success' => [
        'loaded' => 'Home summary loaded successfully.',
    ],
    'errors' => [
        'student_not_found' => 'Student profile not found.',
        'semester_not_found' => 'Semester not found.',
        'unexpected' => 'Unexpected error. Please try again later.',
    ],
    'validation' => [
        'semester_id' => [
            'integer' => 'The semester_id must be an integer.',
            'exists' => 'The selected semester_id is invalid.',
        ],
    ],
];