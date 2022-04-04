<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class StoreFormRequest
 * @package App\Http\Requests\Admin\Period
 */
class StoreFormRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'number'    => 'string',
            'date_from' => 'date',
            'date_to'   => 'date',
        ];
    }
}
