<?php

return [
    'success' => [
        'submittable_loaded' => 'Submittable quizzes loaded successfully.',
        'quiz_loaded' => 'Quiz loaded successfully.',
        'submitted' => 'Quiz submitted successfully.',
    ],
    'errors' => [
        'student_not_found' => 'Student profile not found.',
        'forbidden_quiz' => 'You are not allowed to access this quiz.',
        'not_yet_submittable' => 'This quiz is not yet submittable.',
        'already_submitted' => 'You have already submitted this quiz.',
        'must_include_all_questions' => 'You must include answers for all quiz questions (answer_id may be null).',
        'duplicate_questions' => 'Duplicate question entries are not allowed.',
        'answer_not_belongs' => 'One or more answers do not belong to their questions.',
        'unexpected' => 'Unexpected error. Please try again later.',
    ],
    'validation' => [
        'subject_id' => [
            'integer' => 'The subject_id must be an integer.',
            'exists' => 'The selected subject_id is invalid.',
        ],
        'sort' => [
            'in' => 'The sort must be one of: newest, oldest, ending_soon.',
        ],
        'answers' => [
            'required' => 'The answers field is required.',
            'array' => 'The answers must be an array.',
            'min' => 'The answers array must contain at least one item.',
        ],
        'question_id' => [
            'required' => 'Each answers item must contain question_id.',
            'integer' => 'The question_id must be an integer.',
            'exists' => 'The specified question_id was not found.',
            'distinct' => 'Duplicate question_id values are not allowed.',
        ],
        'answer_id' => [
            'integer' => 'The answer_id must be an integer.',
            'exists' => 'The specified answer_id was not found.',
        ],
    ],
];