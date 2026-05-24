<?php

namespace Database\Seeders;

use App\Models\IcdDiagnosis;
use Illuminate\Database\Seeder;

class Icd10Seeder extends Seeder
{
    public function run(): void
    {
        $data = [
            // ── Infeksi & Parasit (A00-B99) ──
            ['kode' => 'A09', 'nama' => 'Gastroenteritis dan kolitis infeksi yang tidak ditentukan', 'kategori' => 'Infeksi'],
            ['kode' => 'A15.3', 'nama' => 'Tuberkulosis paru, dikonfirmasi oleh cara yang tidak ditentukan', 'kategori' => 'Infeksi'],
            ['kode' => 'A37.9', 'nama' => 'Pertusis, tidak ditentukan', 'kategori' => 'Infeksi'],
            ['kode' => 'A90', 'nama' => 'Demam dengue (dengue klasik)', 'kategori' => 'Infeksi'],
            ['kode' => 'A91', 'nama' => 'Demam berdarah dengue', 'kategori' => 'Infeksi'],
            ['kode' => 'B34.9', 'nama' => 'Infeksi virus, tidak ditentukan', 'kategori' => 'Infeksi'],
            ['kode' => 'B82.9', 'nama' => 'Parasitosis intestinal, tidak ditentukan', 'kategori' => 'Infeksi'],

            // ── Neoplasma (C00-D48) ──
            ['kode' => 'C50.9', 'nama' => 'Neoplasma ganas payudara, tidak ditentukan', 'kategori' => 'Neoplasma'],
            ['kode' => 'C80.9', 'nama' => 'Neoplasma ganas, primer, tempat tidak ditentukan', 'kategori' => 'Neoplasma'],
            ['kode' => 'D25.9', 'nama' => 'Leiomioma uteri, tidak ditentukan', 'kategori' => 'Neoplasma'],

            // ── Endokrin & Metabolik (E00-E90) ──
            ['kode' => 'E10.9', 'nama' => 'Diabetes melitus tergantung insulin, tanpa komplikasi', 'kategori' => 'Endokrin'],
            ['kode' => 'E11.9', 'nama' => 'Diabetes melitus tidak tergantung insulin, tanpa komplikasi', 'kategori' => 'Endokrin'],
            ['kode' => 'E11.65', 'nama' => 'Diabetes melitus tipe 2 dengan hipoglikemia', 'kategori' => 'Endokrin'],
            ['kode' => 'E03.9', 'nama' => 'Hipotiroid, tidak ditentukan', 'kategori' => 'Endokrin'],
            ['kode' => 'E05.9', 'nama' => 'Tirotoksikosis, tidak ditentukan', 'kategori' => 'Endokrin'],
            ['kode' => 'E11.65', 'nama' => 'DM tipe 2 dengan komplikasi hipoglikemia', 'kategori' => 'Endokrin'],
            ['kode' => 'E78.5', 'nama' => 'Hiperlipidemia, tidak ditentukan', 'kategori' => 'Endokrin'],
            ['kode' => 'E78.0', 'nama' => 'Hiperkolesterolemia murni', 'kategori' => 'Endokrin'],
            ['kode' => 'E66.9', 'nama' => 'Obesitas, tidak ditentukan', 'kategori' => 'Endokrin'],
            ['kode' => 'E87.1', 'nama' => 'Hiposmolalitas dan hiponatremia', 'kategori' => 'Endokrin'],
            ['kode' => 'E83.51', 'nama' => 'Hiperkalsemia', 'kategori' => 'Endokrin'],

            // ── Mental & Perilaku (F00-F99) ──
            ['kode' => 'F10.9', 'nama' => 'Gangguan mental dan perilaku akibat alkohol, tidak ditentukan', 'kategori' => 'Mental'],
            ['kode' => 'F32.9', 'nama' => 'Episode depresif, tidak ditentukan', 'kategori' => 'Mental'],
            ['kode' => 'F40.9', 'nama' => 'Gangguan fobia, tidak ditentukan', 'kategori' => 'Mental'],
            ['kode' => 'F41.1', 'nama' => 'Gangguan kecemasan menyeluruh', 'kategori' => 'Mental'],
            ['kode' => 'F43.2', 'nama' => 'Gangguan penyesuaian', 'kategori' => 'Mental'],

            // ── Neurologi (G00-G99) ──
            ['kode' => 'G20', 'nama' => 'Penyakit Parkinson', 'kategori' => 'Neurologi'],
            ['kode' => 'G35', 'nama' => 'Multiple sklerosis', 'kategori' => 'Neurologi'],
            ['kode' => 'G40.9', 'nama' => 'Epilepsi, tidak ditentukan', 'kategori' => 'Neurologi'],
            ['kode' => 'G43.9', 'nama' => 'Migren, tidak ditentukan', 'kategori' => 'Neurologi'],
            ['kode' => 'G44.2', 'nama' => 'Nyeri kepala tipe tegang', 'kategori' => 'Neurologi'],
            ['kode' => 'G45.9', 'nama' => 'Serangan iskemia otak sementara, tidak ditentukan', 'kategori' => 'Neurologi'],
            ['kode' => 'G51.0', 'nama' => 'Bell palsy', 'kategori' => 'Neurologi'],
            ['kode' => 'G54.2', 'nama' => 'Gangguan radiks servikal, tidak ditentukan', 'kategori' => 'Neurologi'],

            // ── Kardiovaskular (I00-I99) ──
            ['kode' => 'I10', 'nama' => 'Hipertensi esensial (primer)', 'kategori' => 'Kardiovaskular'],
            ['kode' => 'I11.9', 'nama' => 'Penyakit jantung hipertensi tanpa gagal jantung', 'kategori' => 'Kardiovaskular'],
            ['kode' => 'I20.9', 'nama' => 'Angina pectoris, tidak ditentukan', 'kategori' => 'Kardiovaskular'],
            ['kode' => 'I21.9', 'nama' => 'Infark miokard akut, tidak ditentukan', 'kategori' => 'Kardiovaskular'],
            ['kode' => 'I25.9', 'nama' => 'Penyakit jantung iskemik kronis, tidak ditentukan', 'kategori' => 'Kardiovaskular'],
            ['kode' => 'I48.9', 'nama' => 'Fibrilasi dan flutter atrium, tidak ditentukan', 'kategori' => 'Kardiovaskular'],
            ['kode' => 'I50.9', 'nama' => 'Gagal jantung, tidak ditentukan', 'kategori' => 'Kardiovaskular'],
            ['kode' => 'I63.9', 'nama' => 'Infark serebral, tidak ditentukan', 'kategori' => 'Kardiovaskular'],
            ['kode' => 'I64', 'nama' => 'Stroke, tidak ditentukan apakah infark atau perdarahan', 'kategori' => 'Kardiovaskular'],
            ['kode' => 'I83.9', 'nama' => 'Varises vena tungkai bawah tanpa ulkus atau radang', 'kategori' => 'Kardiovaskular'],

            // ── Pernapasan (J00-J99) ──
            ['kode' => 'J00', 'nama' => 'Nasofaringitis akut (pilek)', 'kategori' => 'Pernapasan'],
            ['kode' => 'J01.9', 'nama' => 'Sinusitis akut, tidak ditentukan', 'kategori' => 'Pernapasan'],
            ['kode' => 'J02.9', 'nama' => 'Faringitis akut, tidak ditentukan', 'kategori' => 'Pernapasan'],
            ['kode' => 'J03.9', 'nama' => 'Tonsilitis akut, tidak ditentukan', 'kategori' => 'Pernapasan'],
            ['kode' => 'J04.0', 'nama' => 'Laringitis akut', 'kategori' => 'Pernapasan'],
            ['kode' => 'J06.9', 'nama' => 'Infeksi saluran napas atas akut, tidak ditentukan', 'kategori' => 'Pernapasan'],
            ['kode' => 'J10.1', 'nama' => 'Influenza dengan manifestasi pernapasan lain', 'kategori' => 'Pernapasan'],
            ['kode' => 'J11.1', 'nama' => 'Influenza dengan manifestasi pernapasan lain, virus tidak diidentifikasi', 'kategori' => 'Pernapasan'],
            ['kode' => 'J18.9', 'nama' => 'Pneumonia, tidak ditentukan', 'kategori' => 'Pernapasan'],
            ['kode' => 'J20.9', 'nama' => 'Bronkitis akut, tidak ditentukan', 'kategori' => 'Pernapasan'],
            ['kode' => 'J30.4', 'nama' => 'Rinitis alergi, tidak ditentukan', 'kategori' => 'Pernapasan'],
            ['kode' => 'J45.9', 'nama' => 'Asma, tidak ditentukan', 'kategori' => 'Pernapasan'],
            ['kode' => 'J44.1', 'nama' => 'Penyakit paru obstruktif kronik dengan eksaserbasi akut', 'kategori' => 'Pernapasan'],
            ['kode' => 'J98.9', 'nama' => 'Gangguan pernapasan, tidak ditentukan', 'kategori' => 'Pernapasan'],

            // ── Pencernaan (K00-K93) ──
            ['kode' => 'K04.7', 'nama' => 'Abses periapical tanpa sinus', 'kategori' => 'Pencernaan'],
            ['kode' => 'K08.9', 'nama' => 'Gangguan gigi dan struktur pendukung, tidak ditentukan', 'kategori' => 'Pencernaan'],
            ['kode' => 'K21.0', 'nama' => 'Penyakit refluks gastroesofageal dengan esofagitis', 'kategori' => 'Pencernaan'],
            ['kode' => 'K21.9', 'nama' => 'Penyakit refluks gastroesofageal tanpa esofagitis (GERD)', 'kategori' => 'Pencernaan'],
            ['kode' => 'K25.9', 'nama' => 'Tukak lambung, tidak ditentukan sebagai akut atau kronik', 'kategori' => 'Pencernaan'],
            ['kode' => 'K29.7', 'nama' => 'Gastritis, tidak ditentukan', 'kategori' => 'Pencernaan'],
            ['kode' => 'K35.9', 'nama' => 'Apendisitis akut, tidak ditentukan', 'kategori' => 'Pencernaan'],
            ['kode' => 'K57.30', 'nama' => 'Divertikulosis usus besar tanpa perforasi atau abses', 'kategori' => 'Pencernaan'],
            ['kode' => 'K58.9', 'nama' => 'Sindrom kolon iritabel, tidak ditentukan', 'kategori' => 'Pencernaan'],
            ['kode' => 'K59.0', 'nama' => 'Konstipasi', 'kategori' => 'Pencernaan'],
            ['kode' => 'K63.9', 'nama' => 'Penyakit usus, tidak ditentukan', 'kategori' => 'Pencernaan'],
            ['kode' => 'K74.6', 'nama' => 'Sirosis hati, tidak ditentukan', 'kategori' => 'Pencernaan'],
            ['kode' => 'K80.20', 'nama' => 'Kalkulus empedu tanpa kolangitis atau kolesistitis', 'kategori' => 'Pencernaan'],
            ['kode' => 'K92.9', 'nama' => 'Penyakit sistem pencernaan, tidak ditentukan', 'kategori' => 'Pencernaan'],

            // ── Kulit (L00-L99) ──
            ['kode' => 'L01.0', 'nama' => 'Impetigo', 'kategori' => 'Kulit'],
            ['kode' => 'L02.9', 'nama' => 'Abses kulit, furunkel dan karbunkel, tempat tidak ditentukan', 'kategori' => 'Kulit'],
            ['kode' => 'L20.9', 'nama' => 'Dermatitis atopik, tidak ditentukan', 'kategori' => 'Kulit'],
            ['kode' => 'L23.9', 'nama' => 'Dermatitis kontak alergi, tidak ditentukan', 'kategori' => 'Kulit'],
            ['kode' => 'L25.9', 'nama' => 'Dermatitis kontak, tidak ditentukan', 'kategori' => 'Kulit'],
            ['kode' => 'L40.9', 'nama' => 'Psoriasis, tidak ditentukan', 'kategori' => 'Kulit'],
            ['kode' => 'L50.9', 'nama' => 'Urtikaria, tidak ditentukan', 'kategori' => 'Kulit'],
            ['kode' => 'L73.9', 'nama' => 'Gangguan folikel, tidak ditentukan (Acne)', 'kategori' => 'Kulit'],

            // ── Muskuloskeletal (M00-M99) ──
            ['kode' => 'M05.9', 'nama' => 'Artritis reumatoid seropositif, tidak ditentukan', 'kategori' => 'Muskuloskeletal'],
            ['kode' => 'M10.9', 'nama' => 'Gout, tidak ditentukan', 'kategori' => 'Muskuloskeletal'],
            ['kode' => 'M13.9', 'nama' => 'Artritis, tidak ditentukan', 'kategori' => 'Muskuloskeletal'],
            ['kode' => 'M17.9', 'nama' => 'Gonarthrosis (artritis lutut), tidak ditentukan', 'kategori' => 'Muskuloskeletal'],
            ['kode' => 'M47.9', 'nama' => 'Spondylosis, tidak ditentukan', 'kategori' => 'Muskuloskeletal'],
            ['kode' => 'M54.5', 'nama' => 'Nyeri punggung bawah (LBP)', 'kategori' => 'Muskuloskeletal'],
            ['kode' => 'M54.6', 'nama' => 'Nyeri tulang belakang', 'kategori' => 'Muskuloskeletal'],
            ['kode' => 'M75.1', 'nama' => 'Sindrom manset rotator', 'kategori' => 'Muskuloskeletal'],
            ['kode' => 'M79.3', 'nama' => 'Pannikulitis, tidak ditentukan', 'kategori' => 'Muskuloskeletal'],
            ['kode' => 'M81.9', 'nama' => 'Osteoporosis, tidak ditentukan', 'kategori' => 'Muskuloskeletal'],

            // ── Genitourinari (N00-N99) ──
            ['kode' => 'N18.9', 'nama' => 'Penyakit ginjal kronik, tidak ditentukan', 'kategori' => 'Genitourinari'],
            ['kode' => 'N20.0', 'nama' => 'Kalkulus ginjal', 'kategori' => 'Genitourinari'],
            ['kode' => 'N30.0', 'nama' => 'Sistitis akut', 'kategori' => 'Genitourinari'],
            ['kode' => 'N39.0', 'nama' => 'Infeksi saluran kemih, tempat tidak ditentukan', 'kategori' => 'Genitourinari'],
            ['kode' => 'N40.0', 'nama' => 'Benign prostatic hyperplasia (BPH) tanpa LUTS', 'kategori' => 'Genitourinari'],
            ['kode' => 'N83.2', 'nama' => 'Kista ovarium lainnya', 'kategori' => 'Genitourinari'],
            ['kode' => 'N92.6', 'nama' => 'Menstruasi tidak teratur, tidak ditentukan', 'kategori' => 'Genitourinari'],
            ['kode' => 'N94.3', 'nama' => 'Sindrom pramenstruasi', 'kategori' => 'Genitourinari'],
            ['kode' => 'N95.1', 'nama' => 'Menopause dan kondisi premenopause wanita', 'kategori' => 'Genitourinari'],

            // ── Mata (H00-H59) ──
            ['kode' => 'H01.0', 'nama' => 'Blefaritis', 'kategori' => 'Mata'],
            ['kode' => 'H10.9', 'nama' => 'Konjungtivitis, tidak ditentukan', 'kategori' => 'Mata'],
            ['kode' => 'H26.9', 'nama' => 'Katarak, tidak ditentukan', 'kategori' => 'Mata'],
            ['kode' => 'H40.9', 'nama' => 'Glaukoma, tidak ditentukan', 'kategori' => 'Mata'],
            ['kode' => 'H52.4', 'nama' => 'Presbiopia', 'kategori' => 'Mata'],

            // ── Telinga (H60-H95) ──
            ['kode' => 'H66.9', 'nama' => 'Otitis media, tidak ditentukan', 'kategori' => 'Telinga'],
            ['kode' => 'H72.9', 'nama' => 'Perforasi membran timpani, tidak ditentukan', 'kategori' => 'Telinga'],
            ['kode' => 'H81.0', 'nama' => 'Penyakit Meniere', 'kategori' => 'Telinga'],
            ['kode' => 'H81.3', 'nama' => 'Vertigo perifer lainnya', 'kategori' => 'Telinga'],
            ['kode' => 'H91.9', 'nama' => 'Ketulian, tidak ditentukan', 'kategori' => 'Telinga'],

            // ── Kehamilan (O00-O99) ──
            ['kode' => 'O21.0', 'nama' => 'Hyperemesis gravidarum ringan', 'kategori' => 'Kehamilan'],
            ['kode' => 'O14.9', 'nama' => 'Preeklamsia, tidak ditentukan', 'kategori' => 'Kehamilan'],
            ['kode' => 'O80', 'nama' => 'Persalinan normal', 'kategori' => 'Kehamilan'],

            // ── Gejala & Tanda (R00-R99) ──
            ['kode' => 'R00.0', 'nama' => 'Takikardia, tidak ditentukan', 'kategori' => 'Gejala'],
            ['kode' => 'R05', 'nama' => 'Batuk', 'kategori' => 'Gejala'],
            ['kode' => 'R06.0', 'nama' => 'Dispnea', 'kategori' => 'Gejala'],
            ['kode' => 'R07.9', 'nama' => 'Nyeri dada, tidak ditentukan', 'kategori' => 'Gejala'],
            ['kode' => 'R10.9', 'nama' => 'Nyeri perut yang tidak ditentukan', 'kategori' => 'Gejala'],
            ['kode' => 'R11', 'nama' => 'Nausea dan muntah', 'kategori' => 'Gejala'],
            ['kode' => 'R50.9', 'nama' => 'Demam, tidak ditentukan', 'kategori' => 'Gejala'],
            ['kode' => 'R51', 'nama' => 'Sakit kepala', 'kategori' => 'Gejala'],
            ['kode' => 'R55', 'nama' => 'Sinkop dan kolaps', 'kategori' => 'Gejala'],
            ['kode' => 'R68.9', 'nama' => 'Gejala dan tanda umum lainnya, tidak ditentukan', 'kategori' => 'Gejala'],

            // ── Cedera (S00-T98) ──
            ['kode' => 'S09.9', 'nama' => 'Cedera kepala, tidak ditentukan', 'kategori' => 'Cedera'],
            ['kode' => 'S60.9', 'nama' => 'Cedera pergelangan tangan dan tangan, tidak ditentukan', 'kategori' => 'Cedera'],
            ['kode' => 'S80.9', 'nama' => 'Cedera tungkai bawah, tidak ditentukan', 'kategori' => 'Cedera'],
            ['kode' => 'T14.9', 'nama' => 'Cedera, tidak ditentukan', 'kategori' => 'Cedera'],
            ['kode' => 'T78.4', 'nama' => 'Alergi, tidak ditentukan', 'kategori' => 'Cedera'],

            // ── Faktor Pelayanan Kesehatan (Z00-Z99) ──
            ['kode' => 'Z00.0', 'nama' => 'Pemeriksaan kesehatan umum', 'kategori' => 'Preventif'],
            ['kode' => 'Z13.9', 'nama' => 'Pemeriksaan skrining, tidak ditentukan', 'kategori' => 'Preventif'],
            ['kode' => 'Z30.9', 'nama' => 'Pemantauan kontrasepsi, tidak ditentukan', 'kategori' => 'Preventif'],
            ['kode' => 'Z34.9', 'nama' => 'Pengawasan kehamilan normal, tidak ditentukan', 'kategori' => 'Preventif'],
        ];

        foreach ($data as $row) {
            IcdDiagnosis::firstOrCreate(['kode' => $row['kode']], $row);
        }

        $this->command->info('✓ ICD-10: ' . count($data) . ' kode diagnosa');
    }
}
