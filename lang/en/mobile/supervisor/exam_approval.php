<?php

return [
    'messages' => [
        'waiting_loaded'  => 'Waiting exams loaded successfully.',
        'attempts_loaded' => 'Exam attempts loaded successfully.',
        'finalized'       => 'Exam results approved and exam released.',
        'server_error'    => 'Something went wrong while processing exam approvals.',
    ],
    'errors' => [
        'supervisor_not_found'    => 'Supervisor profile not found for the current user.',
        'exam_not_in_stage'       => 'The selected exam does not belong to your stage.',
        'exam_already_released'   => 'This exam has already been released.',
        'no_attempts'             => 'No attempts found for this exam.',
        'section_empty'           => 'The section has no enrolled students.',
        'attempt_not_belong'      => 'One or more attempts do not belong to the selected exam.',
        'incomplete_section'      => 'Finalization requires attempts for all students in the section.',
        'result_out_of_bounds'    => 'Result must be between :min and :max.',
        'result_over_min'         => 'Result must not exceed the minimum threshold :min.', // use only if you truly want "under min"
    ],
    'validation' => [
        'attempts' => [
            'required' => 'Attempts array is required.',
            'array'    => 'Attempts must be an array.',
            'min'      => 'At least :min attempt must be provided.',
        ],
        'attempt_id' => [
            'required' => 'Attempt id is required.',
            'integer'  => 'Attempt id must be an integer.',
            'exists'   => 'Attempt id is invalid.',
        ],
        'result' => [
            'required' => 'Result is required.',
            'numeric'  => 'Result must be a number.',
            'min'      => 'Result must be at least :min.',
        ],
        'approve' => [
            'boolean'  => 'Approve must be true or false.',
        ],
    ],
];
