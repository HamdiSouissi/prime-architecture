<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SitePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('site.photos.manage');
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'max:'.(int) config('site.photo_max_kb'), 'mimes:jpg,jpeg,png,webp'],
            'caption' => ['nullable', 'string', 'max:200'],
            'taken_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'site_report_id' => ['nullable', 'integer', 'min:1'],
            'snag_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
