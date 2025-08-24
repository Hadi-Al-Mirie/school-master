<?php

return [
    'first_name' => [
        'required' => 'First name is required.',
        'string'   => 'First name must be a string.',
        'min'      => 'First name must be at least :min characters.',
        'max'      => 'First name may not be greater than :max characters.',
    ],

    'last_name' => [
        'required' => 'Last name is required.',
        'string'   => 'Last name must be a string.',
        'min'      => 'Last name must be at least :min characters.',
        'max'      => 'Last name may not be greater than :max characters.',
    ],

    'email' => [
        'required' => 'Email is required.',
        'email'    => 'Email must be a valid email address.',
        'min'      => 'Email must be at least :min characters.',
        'max'      => 'Email may not be greater than :max characters.',
        'unique'   => 'This email is already taken.',
    ],

    'password' => [
        'required' => 'Password is required.',
        'string'   => 'Password must be a string.',
        'min'      => 'Password must be at least :min characters.',
        'max'      => 'Password may not be greater than :max characters.',
    ],

    'phone' => [
        'required' => 'Phone number is required.',
        'string'   => 'Phone number must be a string.',
        'min'      => 'Phone number must be at least :min characters.',
        'max'      => 'Phone number may not be greater than :max characters.',
        'unique'   => 'This phone number is already in use.',
    ],

    'section_subjects' => [
        'array'   => 'Section subjects must be an array.',
        'min'     => 'Provide at least one section subject.',
        'duplicate' => 'Duplicate section/subject entry detected.',
        'section_id' => [
            'required' => 'Section is required.',
            'integer'  => 'Section must be a valid ID.',
            'exists'   => 'Selected section does not exist.',
        ],
        'subject_id' => [
            'required' => 'Subject is required.',
            'integer'  => 'Subject must be a valid ID.',
            'exists'   => 'Selected subject does not exist.',
        ],
        'mismatched_classroom' => 'Subject must belong to the same classroom as the section.',
    ],
];
