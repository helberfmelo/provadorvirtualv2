<?php

return [
    'widget_data_retention_days' => (int) env('PRIVACY_WIDGET_DATA_RETENTION_DAYS', 30),
    'operational_log_retention_days' => (int) env('OPERATIONAL_LOG_RETENTION_DAYS', 180),
];
