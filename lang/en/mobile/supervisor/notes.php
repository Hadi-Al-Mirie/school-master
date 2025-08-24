<?php

return [
    'created' => 'Note created successfully.',

    'validation_failed' => 'Validation failed. Please correct the errors and try again.',

    'messages' => [
        'loaded' => 'Notes loaded successfully.',
        'approved' => 'Note approved successfully.',
        'dismissed' => 'Note dismissed successfully.',
        'server_error' => 'Something went wrong while processing notes.',
    ],

    'validation' => [
        'student_required' => 'Student is required.',
        'student_integer' => 'Student id must be an integer.',
        'student_exists' => 'Selected student does not exist.',

        'type_required' => 'Note type is required.',
        'type_in' => 'Note type must be either positive or negative.',

        'reason_required' => 'Reason is required.',
        'reason_string' => 'Reason must be a string.',
        'reason_min' => 'Reason must be at least :min characters.',
        'reason_max' => 'Reason must not exceed :max characters.',

        'value_required' => 'Value is required.',
        'value_numeric' => 'Value must be numeric.',
        'value_min' => 'Value must be at least :min.',
        'value_max' => 'Value must be less that :max.',

        'course_integer' => 'Course id must be an integer.',
        'course_exists' => 'Selected course does not exist.',

        'status_in' => 'Invalid status value.',

        'status' => [
            'required' => 'The status filter is required.',
            'in' => 'The status must be one of pending, approved, dismissed, or all.',
        ],
        'per_page' => [
            'integer' => 'The per_page value must be an integer.',
            'min' => 'The per_page value must be at least :min.',
            'max' => 'The per_page value may not be greater than :max.',
        ],
        'decision' => [
            'required' => 'The decision is required.',
            'in' => 'The decision must be approved or dismissed.',
        ],
        'value' => [
            'required_if' => 'The value is required when approving a note.',
            'numeric' => 'The value must be numeric.',
        ],
    ],

    'errors' => [
        'not_supervisor' => 'Authenticated user is not a supervisor.',
        'student_not_in_stage' => 'The specified student is not in your assigned stage.',
        'save_failed' => 'Failed to save the note. Please try again.',
        'supervisor_not_found' => 'Supervisor profile not found for the current user.',
        'note_not_in_stage' => 'This note does not belong to your stage.',
        'note_already_processed' => 'This note has already been processed.',
    ],
];