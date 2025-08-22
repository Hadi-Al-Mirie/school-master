<?php

return [
    'messages' => [
        'created' => 'Attendance saved successfully.',
        'server_error' => 'Something went wrong while saving attendance. Please try again.',
    ],
    'errors' => [
        'no_active_semester' => 'There is no active semester.',
        'duplicate' => 'Attendance for this person and date is already recorded.',
    ],
    'validation' => [
        'type' => [
            'required' => 'The type field is required.',
            'in' => 'The type must be either student or teacher.',
        ],
        'attendable_id' => [
            'required' => 'The person ID is required.',
            'integer' => 'The person ID must be an integer.',
            'min' => 'The person ID must be at least 1.',
            'not_student' => 'Selected student was not found.',
            'not_teacher' => 'Selected teacher was not found.',
        ],
        'attendance_type_id' => [
            'required' => 'The attendance type is required.',
            'integer' => 'The attendance type must be an integer.',
            'exists' => 'The selected attendance type is invalid.',
        ],
        'att_date' => [
            'required' => 'The attendance date is required.',
            'date_format' => 'The attendance date must match the format Y-m-d.',
            'outside_semester' => 'The attendance date must be within the active semester.',
        ],
        'justification' => [
            'required_if' => 'Justification is required when the attendance type is 2 or 3.',
            'string' => 'The justification must be a text.',
            'min' => 'The justification must be at least :min characters.',
            'max' => 'The justification may not be greater than :max characters.',
            'must_be_null' => 'Justification must be null/omitted when the attendance type is 1.',
        ],
    ],
];
