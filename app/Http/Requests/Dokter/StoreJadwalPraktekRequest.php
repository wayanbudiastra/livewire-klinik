<?php

namespace App\Http\Requests\Dokter;

use Illuminate\Foundation\Http\FormRequest;

class StoreJadwalPraktekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masterdata.edit');
    }

    public function rules(): array
    {
        return [
            'dokter_poli_id' => ['required', 'integer', 'exists:dokter_poli,id'],
            'hari'           => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'jam_mulai'      => ['required', 'date_format:H:i'],
            'jam_selesai'    => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'kuota_pasien'   => ['required', 'integer', 'min:1', 'max:200'],
            'keterangan'     => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
        ];
    }
}
