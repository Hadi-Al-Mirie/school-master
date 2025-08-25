<?php

return [
    'success' => [
        'loaded' => 'Exams loaded successfully.',
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
        'exam_id' => [
            'integer' => 'The exam_id must be an integer.',
            'exists' => 'The selected exam_id is invalid.',
        ],
        'subject_id' => [
            'integer' => 'The subject_id must be an integer.',
            'exists' => 'The selected subject_id is invalid.',
        ],
        'teacher_id' => [
            'integer' => 'The teacher_id must be an integer.',
            'exists' => 'The selected teacher_id is invalid.',
        ],
        'status' => [
            'in' => 'The status must be one of: approved, wait.',
        ],
        'min_result' => [
            'numeric' => 'The min_result must be a number.',
            'min' => 'The min_result must be at least 0.',
        ],
        'max_result' => [
            'numeric' => 'The max_result must be a number.',
            'min' => 'The max_result must be at least 0.',
            'gte' => 'The max_result must be greater than or equal to min_result.',
        ],
        'submitted_from' => [
            'date' => 'The submitted_from must be a valid date (Y-m-d).',
        ],
        'submitted_to' => [
            'date' => 'The submitted_to must be a valid date (Y-m-d).',
            'after_or_equal' => 'The submitted_to must be a date after or equal to submitted_from.',
        ],
        'sort' => [
            'in' => 'The sort must be one of: newest, oldest, highest_result, lowest_result.',
        ],
    ],
];