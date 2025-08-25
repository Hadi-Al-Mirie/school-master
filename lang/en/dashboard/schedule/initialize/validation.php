<?php

return [
    'teacher_availabilities' => [
        'required' => 'Teacher availabilities are required.',
        'array' => 'Teacher availabilities must be an array.',
        'min' => 'Provide at least one teacher availability.',
    ],
    'teacher_id' => [
        'required' => 'Teacher is required.',
        'integer' => 'Teacher must be a valid ID.',
        'exists' => 'Selected teacher does not exist.',
    ],
    'day_of_week' => [
        'required' => 'Day of week is required.',
        'string' => 'Day of week must be a string.',
        'in' => 'Day of week must be one of: saturday, sunday, monday, tuesday, wednesday, thursday.',
    ],
    'period_ids' => [
        'required' => 'Period IDs are required.',
        'array' => 'Period IDs must be an array.',
        'min' => 'Provide at least one period.',
        'duplicate_within' => 'Duplicate period IDs are not allowed within the same availability.',
    ],
    'period_ids_item' => [
        'required' => 'Each period ID is required.',
        'integer' => 'Each period ID must be a valid integer.',
        'distinct' => 'Duplicate period IDs are not allowed.',
        'exists' => 'One or more period IDs do not exist.',
    ],
    'classrooms' => [
        'required' => 'Classrooms configuration is required.',
        'array' => 'Classrooms configuration must be an array.',
        'min' => 'Provide at least one classroom configuration.',
    ],
    'classroom_id' => [
        'required' => 'Classroom is required.',
        'integer' => 'Classroom must be a valid ID.',
        'exists' => 'Selected classroom does not exist.',
    ],
    'periods_per_day' => [
        'required' => 'Periods-per-day is required.',
        'array' => 'Periods-per-day must be an array.',
        'min' => 'Provide at least one day in periods-per-day.',
    ],
    'periods_per_day_day' => [
        'integer' => 'The number of periods must be an integer.',
        'min' => 'The number of periods cannot be negative.',
        'exceeds_max' => 'The number of periods exceeds the maximum available per day (:max).',
    ],
];
