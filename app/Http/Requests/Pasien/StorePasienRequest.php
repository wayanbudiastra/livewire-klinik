<?php

namespace App\Http\Requests\Pasien;

use Illuminate\Foundation\Http\FormRequest;

class StorePasienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pasien.create');
    }

    public function rules(): array
    {
        return [
            'nama'          => ['required', 'string', 'min:3', 'max:100'],
            'tempat_lahir'  => ['required', 'string', 'min:2', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today', 'after:1874-12-31'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tipe_pasien'   => ['required', 'in:WNI,WNA'],
            'nik'           => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/', 'unique:pasien,nik'],
            'no_bpjs'       => ['nullable', 'string', 'size:13', 'regex:/^\d{13}$/', 'unique:pasien,no_bpjs'],
            'no_paspor'     => ['nullable', 'string', 'min:5', 'max:20', 'unique:pasien,no_paspor'],
            'negara_asal'   => ['nullable', 'string', 'max:100'],
            'alamat'        => ['required', 'string', 'min:10', 'max:500'],
            'telepon'       => ['required', 'string', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'email'         => ['nullable', 'email', 'max:255'],
            'golongan_darah'=> ['nullable', 'in:A,B,AB,O,tidak_diketahui'],
            'alergi'        => ['nullable', 'string', 'max:1000'],
            'no_asuransi'   => ['nullable', 'string', 'max:50'],
            'kontak'              => ['array'],
            'kontak.*.nama'       => ['required_with:kontak', 'string', 'min:3'],
            'kontak.*.nomor_hp'   => ['required_with:kontak', 'string', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'kontak.*.hubungan'   => ['required_with:kontak', 'in:suami,istri,ayah,ibu,anak,kakak,adik,kakek,nenek,paman,bibi,keponakan,teman,rekan_kerja,lainnya'],
            'kontak.*.alamat'     => ['nullable', 'string'],
            'kontak.*.is_primary' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $data = $this->all();
            $tipe = $data['tipe_pasien'] ?? '';

            if ($tipe === 'WNI' && empty($data['nik'])) {
                $v->errors()->add('nik', 'NIK wajib diisi untuk pasien WNI.');
            }
            if ($tipe === 'WNA') {
                if (empty($data['no_paspor'])) {
                    $v->errors()->add('no_paspor', 'No. Paspor wajib untuk pasien WNA.');
                }
                if (empty($data['negara_asal'])) {
                    $v->errors()->add('negara_asal', 'Negara asal wajib untuk pasien WNA.');
                }
            }

            foreach ($data['kontak'] ?? [] as $i => $k) {
                if (isset($k['nomor_hp']) && $k['nomor_hp'] === ($data['telepon'] ?? '')) {
                    $v->errors()->add("kontak.{$i}.nomor_hp",
                        'Nomor HP kontak tidak boleh sama dengan nomor HP pasien.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'nama.min'          => 'Nama minimal 3 karakter.',
            'nik.size'          => 'NIK harus tepat 16 digit.',
            'nik.regex'         => 'NIK hanya boleh berisi angka.',
            'nik.unique'        => 'NIK sudah terdaftar.',
            'no_bpjs.size'      => 'Nomor BPJS harus tepat 13 digit.',
            'no_bpjs.unique'    => 'Nomor BPJS sudah terdaftar.',
            'telepon.regex'     => 'Format telepon tidak valid (08xx / +62xx).',
            'alamat.min'        => 'Alamat minimal 10 karakter.',
            'tanggal_lahir.after' => 'Tanggal lahir tidak valid.',
        ];
    }
}
