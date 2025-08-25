<?php

return [
    'success' => [
        'loaded' => 'Quizzes loaded successfully.',
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
        'quiz_id' => [
            'integer' => 'The quiz_id must be an integer.',
            'exists' => 'The selected quiz_id is invalid.',
        ],
        'subject_id' => [
            'integer' => 'The subject_id must be an integer.',
            'exists' => 'The selected subject_id is invalid.',
        ],
        'teacher_id' => [
            'integer' => 'The teacher_id must be an integer.',
            'exists' => 'The selected teacher_id is invalid.',
        ],
        'min_score' => [
            'numeric' => 'The min_score must be a number.',
            'min' => 'The min_score must be at least 0.',
        ],
        'max_score' => [
            'numeric' => 'The max_score must be a number.',
            'min' => 'The max_score must be at least 0.',
            'gte' => 'The max_score must be greater than or equal to min_score.',
        ],
        'submitted_from' => [
            'date' => 'The submitted_from must be a valid date (Y-m-d).',
        ],
        'submitted_to' => [
            'date' => 'The submitted_to must be a valid date (Y-m-d).',
            'after_or_equal' => 'The submitted_to must be a date after or equal to submitted_from.',
        ],
        'sort' => [
            'in' => 'The sort must be one of: newest, oldest, highest_score, lowest_score.',
        ],
    ],
];