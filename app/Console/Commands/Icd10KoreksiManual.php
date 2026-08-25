<?php

namespace App\Console\Commands;

use App\Models\Icd10ImportLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi manual satu kali jalan untuk masalah kualitas data yang ditemukan
 * saat QA sampling (prd/seeder_icd10_who_resmi.md §9) terhadap 10.480 baris
 * icd10 existing:
 *
 * 1. Bug apostrof sistematis -- proses import sebelumnya (master_icd_x.json,
 *    provenance tidak terdokumentasi) menghilangkan tanda kutip (') dari
 *    SEMUA nama penyakit eponim posesif (mis. "Parkinsons disease" yang
 *    seharusnya "Parkinson's disease"). Ditemukan 47 nama unik / ~130 baris
 *    terdampak lewat pencarian pola regex, diverifikasi satu-satu secara
 *    manual (bukan auto-replace pola umum, supaya tidak salah kena istilah
 *    generik seperti "vertiginous"/"vitreous" yang BUKAN eponim).
 * 2. 6 dari 11 baris dengan nama_en kosong diisi -- kode format standar WHO
 *    (1 digit desimal), keyakinan tinggi berdasar terminologi ICD-10 baku.
 *
 * PENTING: ini BUKAN pengganti verifikasi live ke WHO ICD-10 Online Browser
 * (§9) -- percobaan WebFetch ke icd.who.int/browse10 gagal karena situsnya
 * client-rendered (SPA), tidak bisa diotomasi di lingkungan ini. Koreksi di
 * sini murni berdasarkan pengetahuan terminologi ICD-10 yang sudah baku &
 * stabil (dipakai luas di literatur medis, bukan hasil fetch langsung).
 * Rekomendasi tetap: cross-check manual oleh tim sebelum dianggap 100% final,
 * terutama untuk baris "keyakinan sedang" yang ditandai di bawah.
 */
class Icd10KoreksiManual extends Command
{
    protected $signature = 'icd:koreksi-manual {--dry-run : Tampilkan ringkasan tanpa menulis ke database}';

    protected $description = 'Koreksi bug apostrof hilang + isi nama_en kosong pada data icd10 existing (hasil QA sampling)';

    /**
     * Cari-ganti apostrof, HANYA untuk nama yang sudah diverifikasi manual
     * sebagai eponim posesif genuine (bukan kata generik). Urutan tidak
     * signifikan -- semua needle cukup spesifik untuk tidak tumpang tindih.
     */
    private const KOREKSI_APOSTROF = [
        'Legionnaires disease'      => "Legionnaires' disease",
        'Brills disease'            => "Brill's disease",
        'Burkitts lymphoma'         => "Burkitt's lymphoma",
        'Burkitts tumour'           => "Burkitt's tumour",
        'non-Hodgkins'              => "non-Hodgkin's",
        'Non-Hodgkins'              => "Non-Hodgkin's",
        'Hodgkins disease'          => "Hodgkin's disease",
        "Meckels diverticulum"      => "Meckel's diverticulum",
        'Sezarys disease'           => "Sezary's disease",
        'Von Willebrands disease'   => "Von Willebrand's disease",
        'Nezelofs syndrome'         => "Nezelof's syndrome",
        'Di Georges syndrome'       => "Di George's syndrome",
        'Nelsons syndrome'          => "Nelson's syndrome",
        'Cushings'                  => "Cushing's",
        'Gilberts syndrome'         => "Gilbert's syndrome",
        'Alzheimers disease'        => "Alzheimer's disease",
        'Picks disease'             => "Pick's disease",
        'Huntingtons disease'       => "Huntington's disease",
        'Parkinsons disease'        => "Parkinson's disease",
        'Retts syndrome'            => "Rett's syndrome",
        'Aspergers syndrome'        => "Asperger's syndrome",
        'Melkerssons syndrome'      => "Melkersson's syndrome",
        'Refsums disease'           => "Refsum's disease",
        'Horners syndrome'          => "Horner's syndrome",
        'Reyes syndrome'            => "Reye's syndrome",
        'Menieres disease'          => "Meniere's disease",
        'Dresslers syndrome'        => "Dressler's syndrome",
        'Raynauds syndrome'         => "Raynaud's syndrome",
        'MacLeods syndrome'         => "MacLeod's syndrome",
        'Flax-dressers disease'     => "Flax-dressers' disease",
        'Mendelsons syndrome'       => "Mendelson's syndrome",
        'Crohns disease'            => "Crohn's disease",
        'Reiters disease'           => "Reiter's disease",
        'Feltys syndrome'           => "Felty's syndrome",
        'Stills disease'            => "Still's disease",
        'Heberdens nodes'           => "Heberden's nodes",
        'Bouchards nodes'           => "Bouchard's nodes",
        'Behcets disease'           => "Behcet's disease",
        'Schmorls nodes'            => "Schmorl's nodes",
        'Pagets disease'            => "Paget's disease",
        'Kienbocks disease'         => "Kienbock's disease",
        'Hirschsprungs disease'     => "Hirschsprung's disease",
        'Potters syndrome'          => "Potter's syndrome",
        'Marfans syndrome'          => "Marfan's syndrome",
        'Downs syndrome'            => "Down's syndrome",
        'Pataus syndrome'           => "Patau's syndrome",
        'Turners syndrome'          => "Turner's syndrome",
        'Klinefelters syndrome'     => "Klinefelter's syndrome",
        'Bells palsy'               => "Bell's palsy",
        'Wegeners granulomatosis'   => "Wegener's granulomatosis",
    ];

    /**
     * nama_en yang tadinya kosong -- cuma kode berformat standar WHO (1
     * digit desimal) yang diisi. 5 kode lain (E11.65, K57.30, K80.20,
     * E83.51 -- format 2 digit desimal ala ICD-10-CM, bukan WHO murni; dan
     * R68.9 yang keabsahannya sebagai kode WHO diragukan) SENGAJA tidak
     * diisi di sini, lihat $this->kodeDiluarCakupan().
     */
    private const ISI_NAMA_EN_KOSONG = [
        'C80.9' => ['nama_en' => 'Malignant neoplasm, unspecified', 'keyakinan' => 'tinggi'],
        'I48.9' => ['nama_en' => 'Atrial fibrillation and flutter, unspecified', 'keyakinan' => 'tinggi'],
        'O80'   => ['nama_en' => 'Single spontaneous delivery', 'keyakinan' => 'tinggi'],
        'R07.9' => ['nama_en' => 'Pain in throat and chest, unspecified', 'keyakinan' => 'sedang'],
        'R10.9' => ['nama_en' => 'Unspecified abdominal pain', 'keyakinan' => 'tinggi'],
        'N40.0' => ['nama_en' => 'Hyperplasia of prostate without lower urinary tract symptoms', 'keyakinan' => 'sedang'],
    ];

    private const KODE_DILUAR_CAKUPAN = [
        'E11.65' => 'Format 2 digit desimal (gaya ICD-10-CM/US), bukan kode WHO ICD-10 murni. nama_id existing juga tampak keliru (hipoglikemia vs hiperglikemia) -- butuh keputusan produk terpisah.',
        'K57.30' => 'Format 2 digit desimal (gaya ICD-10-CM/US), bukan kode WHO ICD-10 murni.',
        'K80.20' => 'Format 2 digit desimal (gaya ICD-10-CM/US), bukan kode WHO ICD-10 murni.',
        'E83.51' => 'Format 2 digit desimal (gaya ICD-10-CM/US), bukan kode WHO ICD-10 murni.',
        'R68.9'  => 'Kategori R68 di WHO ICD-10 murni cuma sampai R68.8 -- keabsahan R68.9 sebagai kode WHO diragukan, tidak diisi supaya tidak fabrikasi.',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // ── Bagian 1: koreksi apostrof ──────────────────────────────
        $this->info('=== Koreksi apostrof (eponim posesif) ===');
        $totalBarisApostrof = 0;
        $ringkasanApostrof = [];

        foreach (self::KOREKSI_APOSTROF as $salah => $benar) {
            $baris = DB::table('icd10')->where('nama_en', 'like', "%{$salah}%")->get(['id', 'kode', 'nama_en']);
            foreach ($baris as $b) {
                $namaBaru = str_replace($salah, $benar, $b->nama_en);
                if ($namaBaru === $b->nama_en) continue;

                $ringkasanApostrof[] = [$b->kode, $b->nama_en, $namaBaru];
                $totalBarisApostrof++;

                if (!$dryRun) {
                    DB::table('icd10')->where('id', $b->id)->update(['nama_en' => $namaBaru]);
                }
            }
        }

        $this->table(['Kode', 'nama_en lama', 'nama_en baru'], array_slice($ringkasanApostrof, 0, 20));
        if (count($ringkasanApostrof) > 20) {
            $this->line('... dan ' . (count($ringkasanApostrof) - 20) . ' baris lainnya.');
        }
        $this->info("Total baris nama_en diperbaiki (apostrof): {$totalBarisApostrof}");
        $this->newLine();

        // ── Bagian 2: isi nama_en yang kosong ────────────────────────
        $this->info('=== Isi nama_en yang kosong ===');
        $totalDiisi = 0;
        $ringkasanIsi = [];

        foreach (self::ISI_NAMA_EN_KOSONG as $kode => $info) {
            $row = DB::table('icd10')->where('kode', $kode)->first();
            if (!$row) {
                $this->warn("  Kode {$kode} tidak ditemukan di tabel icd10, dilewati.");
                continue;
            }
            $ringkasanIsi[] = [$kode, $info['nama_en'], $info['keyakinan']];
            $totalDiisi++;

            if (!$dryRun) {
                DB::table('icd10')->where('kode', $kode)->update(['nama_en' => $info['nama_en']]);
            }
        }

        $this->table(['Kode', 'nama_en baru', 'Keyakinan'], $ringkasanIsi);
        $this->info("Total baris nama_en diisi: {$totalDiisi}");
        $this->newLine();

        // ── Bagian 3: kode di luar cakupan (tidak disentuh) ─────────
        $this->warn('=== Kode di luar cakupan PRD ini (TIDAK diisi, perlu keputusan terpisah) ===');
        foreach (self::KODE_DILUAR_CAKUPAN as $kode => $alasan) {
            $this->line("  {$kode}: {$alasan}");
        }
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN -- tidak ada perubahan ditulis ke database.');
            return self::SUCCESS;
        }

        Icd10ImportLog::create([
            'sumber'            => 'Koreksi manual QA sampling atas data existing (master_icd_x.json, sumber asli tidak terdokumentasi)',
            'sumber_url'        => null,
            'versi_who'         => 'Tidak terverifikasi live ke WHO ICD-10 Online Browser (icd.who.int/browse10) -- situs client-rendered (SPA), tidak dapat diotomasi. Koreksi berdasar pengetahuan terminologi ICD-10 baku.',
            'mode'              => 'koreksi-manual',
            'jumlah_baris'      => $totalBarisApostrof + $totalDiisi,
            'jumlah_baru'       => 0,
            'jumlah_diperbarui' => $totalBarisApostrof + $totalDiisi,
            'catatan_qa'        => sprintf(
                "Ditemukan & diperbaiki: bug apostrof hilang sistematis (%d baris, %d nama eponim unik), %d baris nama_en kosong diisi (keyakinan tinggi/sedang, kode format standar WHO). %d kode format non-standar (2 digit desimal ala ICD-10-CM) sengaja TIDAK diisi -- lihat KODE_DILUAR_CAKUPAN di Icd10KoreksiManual.php. Rekomendasi: cross-check manual ke WHO ICD-10 Online Browser sebelum dianggap final (lihat prd/seeder_icd10_who_resmi.md §9).",
                $totalBarisApostrof,
                count(self::KOREKSI_APOSTROF),
                $totalDiisi,
                count(self::KODE_DILUAR_CAKUPAN)
            ),
            'dijalankan_oleh'   => auth()->id(),
        ]);

        $this->info('✓ Koreksi selesai. Tercatat di icd10_import_log.');
        return self::SUCCESS;
    }
}
