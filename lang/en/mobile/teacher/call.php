<?php

return [
    // success messages
    'created' => 'Call created successfully.',
    'scheduled_created' => 'Call scheduled successfully.',
    'started' => 'Scheduled call started.',

    'validation' => [
        'section_required' => 'Section is required.',
        'section_integer' => 'Section id must be an integer.',
        'section_exists' => 'Selected section does not exist.',

        'subject_required' => 'Subject is required.',
        'subject_integer' => 'Subject id must be an integer.',
        'subject_exists' => 'Selected subject does not exist.',

        'scheduled_required' => 'Scheduled time is required.',
        'scheduled_date' => 'Scheduled time must be a valid date/time.',
        'scheduled_after_now' => 'Scheduled time must be in the future.',

        'started_required' => 'Start time is required.',
        'started_date' => 'Start time must be a valid date/time.',
        'started_in_past' => 'Start time cannot be in the past.',

        'duration_integer' => 'Duration must be an integer (minutes).',
        'duration_min' => 'Duration must be at least :min minutes.',
        'duration_max' => 'Duration must not exceed :max minutes.',

        'channel_string' => 'Channel name must be a string.',
        'channel_max' => 'Channel name must not exceed :max characters.',
    ],

    'messages' => [
        'deleted' => 'Scheduled call deleted successfully.',
        'server_error' => 'Something went wrong while deleting the scheduled call.',
    ],

    'errors' => [
        'fetch_scheduled_failed' => 'Failed to fetch scheduled calls. Please try again.',
        'section_or_subject_not_assigned' => 'You are not assigned to teach/manage the selected section and subject.',
        'scheduled_overlap' => 'You already have a scheduled call that overlaps this time.',
        'scheduled_overlap_with_active' => 'You already have an active call that overlaps the scheduled time.',
        'active_call_exists' => 'You already have an active call. End it before starting another one.',
        'not_owner_of_scheduled' => 'You are not the owner of this scheduled call.',
        'invalid_scheduled_status' => 'This scheduled call cannot be started (invalid status).',
        'cannot_start_before_scheduled' => 'You cannot start a scheduled call before its scheduled time.',
        'cannot_start_before_scheduled_detail' => 'Scheduled call cannot be started before :scheduled_at.',
        'save_failed' => 'Failed to save call data. Please try again.',
        'not_teacher' => 'Only teachers can perform this action.',
        'not_owner' => 'You can only delete scheduled calls you created.',
        'not_deletable' => 'This scheduled call cannot be deleted (it is not in scheduled status).',
    ],
];
