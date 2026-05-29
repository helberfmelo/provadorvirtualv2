<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreWidgetUsageEventRequest extends RecommendationConfigCheckRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'event_name' => [
                'required',
                'string',
                Rule::in([
                    'button_impression',
                    'virtual_try_on_open',
                    'measurement_table_open',
                    'recommendation_generated',
                    'size_selected',
                    'feedback_submitted',
                ]),
            ],
            'event_id' => ['required', 'string', 'max:160'],
            'recommendation_id' => ['nullable', 'integer'],
            'selected_size' => ['nullable', 'string', 'max:40'],
            'session_key' => ['nullable', 'string', 'max:80'],
            'visit_key' => ['nullable', 'string', 'max:80'],
            'occurred_at' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
        ]);
    }
}
