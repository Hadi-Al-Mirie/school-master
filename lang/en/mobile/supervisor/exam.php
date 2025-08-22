<?php

return [
    'messages' => [
        'created' => 'Exam created for all sections in the classroom.',
        'server_error' => 'Something went wrong while creating exams.',
    ],
    'errors' => [
        'no_active_semester' => 'There is no active semester.',
        'supervisor_not_found' => 'Supervisor profile not found for the current user.',
        'classroom_stage_mismatch' => 'Selected classroom does not belong to your stage.',
        'subject_not_in_classroom' => 'Selected subject does not belong to the chosen classroom.',
        'no_sections_in_classroom' => 'The selected classroom has no sections.',
    ],
    'validation' => [
        'classroom_id' => [
            'required' => 'The classroom is required.',
            'integer' => 'The classroom id must be an integer.',
            'exists' => 'The selected classroom is invalid.',
        ],
        'subject_id' => [
            'required' => 'The subject is required.',
            'integer' => 'The subject id must be an integer.',
            'exists' => 'The selected subject is invalid.',
        ],
        'max_result' => [
            'required' => 'The maximum result is required.',
            'numeric' => 'The maximum result must be a number.',
            'min' => 'The maximum result must be at least :min.',
            'max' => 'The maximum result may not be greater than :max.',
        ],
        'name' => [
            'required' => 'The name is required',
            'string' => 'The name must be a text.',
            'max' => 'The name may not be greater than :max characters.',
            'min' => 'The name may not be less than :min characters.',
        ],
    ],
];
