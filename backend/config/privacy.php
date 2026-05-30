<?php

return [
    'widget_data_retention_days' => (int) env('PRIVACY_WIDGET_DATA_RETENTION_DAYS', 30),
    'feedback_comment_retention_days' => (int) env('FEEDBACK_COMMENT_RETENTION_DAYS', 90),
    'profile_retention_days' => (int) env('PRIVACY_PROFILE_RETENTION_DAYS', 180),
    'learning_event_payload_retention_days' => (int) env('LEARNING_EVENT_PAYLOAD_RETENTION_DAYS', 180),
    'operational_log_retention_days' => (int) env('OPERATIONAL_LOG_RETENTION_DAYS', 180),
];
