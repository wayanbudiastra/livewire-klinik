<?php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\IcdDiagnosis;
use App\Models\Klinik;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class IcdManager extends Component
{
    use WithPagination, WithFileUploads;

    // ── Browse ─────────────────────────────────────────
    #[Url]
    public string $search = '';
    public string $tab = 'browse';

    // ── Import JSON bawaan ─────────────────────────────
    public string $bahasaImport  = 'id';    // id | en | both
    public bool   $jsonImporting = false;
    public array  $jsonResult    = [];
    public string $jsonError     = '';

    // ── Ganti bahasa tampilan ──────────────────────────
    public bool   $switchingLang = false;

    // ── Import file (CSV/Excel) ─────────────────────────
    public $uploadedFile  = null;
    public array  $preview          = [];
    public array  $detectedHeaders  = [];
    public string $kodeCol          = '';
    public string $namaCol          = '';
    public string $kategoriCol      = '';
    public string $importMode       = 'upsert';
    public string $importState      = 'idle';
    public array  $importResult     = [];
    public string $importError      = '';

    // ── Browse ─────────────────────────────────────────
    #[Computed]
    public function icdList()
    {
        return IcdDiagnosis::query()
            ->when($this->search, fn ($q) => $q
                ->where('kode', 'like', "%{$this->search}%")
                ->orWhere('nama', 'like', "%{$this->search}%")
                ->orWhere('kategori', 'like', "%{$this->search}%")
            )
            ->orderBy('kode')
            ->paginate(20);
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function setTab(string $tab): void { $this->tab = $tab; }

    // ── Stats ──────────────────────────────────────────
    #[Computed]
    public function stats(): array
    {
        $latest = IcdDiagnosis::max('updated_at');
        $klinik = Klinik::first();
        return [
            'total'      => IcdDiagnosis::count(),
            'updated_at' => $latest ? \Carbon\Carbon::parse($latest)->format('d/m/Y H:i') : '-',
            'bahasa'     => $klinik?->bahasa_icd ?? 'id',
        ];
    }

    #[Computed]
    public function jsonFileExists(): bool
    {
        return file_exists(base_path('master_icd_x.json'));
    }

    // ── Import dari JSON bawaan ────────────────────────
    public function importDariJson(): void
    {
        $this->jsonError  = '';
        $this->jsonResult = [];

        $path = base_path('master_icd_x.json');
        if (!file_exists($path)) {
            $this->jsonError = 'File master_icd_x.json tidak ditemukan di root project.';
            return;
        }

        $raw = file_get_contents($path);
        $data = json_decode($raw, true);

        if (!is_array($data) || empty($data)) {
            $this->jsonError = 'File JSON kosong atau format tidak valid.';
            return;
        }

        $imported = 0;
        $skipped  = 0;
        $batch    = [];
        $now      = now();
        $bahasa   = $this->bahasaImport; // 'id', 'en', atau 'both' (simpan keduanya, aktifkan id)

        foreach ($data as $row) {
            $kode    = strtoupper(trim($row['kode_icd'] ?? ''));
            $namaEn  = trim($row['nama_icd'] ?? '');
            $namaId  = trim($row['nama_icd_indo'] ?? '');

            if ($kode === '' || ($namaEn === '' && $namaId === '')) {
                $skipped++;
                continue;
            }

            // Pilih nama aktif berdasarkan bahasa import
            $namaAktif = match ($bahasa) {
                'en'    => $namaEn ?: $namaId,
                default => $namaId ?: $namaEn,   // 'id' atau 'both' → default Indonesia
            };

            $batch[] = [
                'kode'       => $kode,
                'nama'       => $namaAktif,
                'nama_en'    => $namaEn ?: null,
                'nama_id'    => $namaId ?: null,
                'kategori'   => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $imported++;

            if (count($batch) >= 500) {
                DB::table('icd10')->upsert($batch, ['kode'], ['nama', 'nama_en', 'nama_id', 'kategori', 'updated_at']);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('icd10')->upsert($batch, ['kode'], ['nama', 'nama_en', 'nama_id', 'kategori', 'updated_at']);
        }

        // Simpan setting bahasa ke tabel klinik
        $bahasaSetting = ($bahasa === 'en') ? 'en' : 'id';
        $klinik = Klinik::first();
        if ($klinik) {
            $klinik->update(['bahasa_icd' => $bahasaSetting]);
        } else {
            Klinik::create(['nama' => config('app.name', 'Klinik'), 'alamat' => '-', 'bahasa_icd' => $bahasaSetting]);
        }

        $this->jsonResult = [
            'imported' => $imported,
            'skipped'  => $skipped,
            'bahasa'   => $bahasaSetting,
        ];

        $this->dispatch('notify', type: 'success', message: "Import selesai — {$imported} kode ICD-10 berhasil dimuat.");
    }

    // ── Ganti bahasa tampilan (tanpa re-import) ────────
    public function gantiBarasa(string $bahasa): void
    {
        if (!in_array($bahasa, ['id', 'en'])) return;

        $col = $bahasa === 'en' ? 'nama_en' : 'nama_id';

        // Bulk update: nama = nama_en atau nama = nama_id (skip jika kolom null)
        DB::statement("UPDATE icd10 SET nama = COALESCE({$col}, nama), updated_at = NOW() WHERE {$col} IS NOT NULL AND {$col} != ''");

        $klinik = Klinik::first();
        if ($klinik) {
            $klinik->update(['bahasa_icd' => $bahasa]);
        } else {
            Klinik::create(['nama' => config('app.name', 'Klinik'), 'alamat' => '-', 'bahasa_icd' => $bahasa]);
        }

        $label = $bahasa === 'en' ? 'International (English)' : 'Indonesia';
        $this->dispatch('notify', type: 'success', message: "Bahasa ICD-10 diganti ke: {$label}");
    }

    // ── Import CSV/Excel ───────────────────────────────
    public function updatedUploadedFile(): void
    {
        $this->importError  = '';
        $this->preview      = [];
        $this->detectedHeaders = [];
        $this->importState  = 'idle';

        if (!$this->uploadedFile) return;

        $ext = strtolower($this->uploadedFile->getClientOriginalExtension());

        try {
            if ($ext === 'csv') {
                $this->parseFromCsv();
            } elseif (in_array($ext, ['xlsx', 'xls'])) {
                $this->parseFromExcel();
            } else {
                $this->importError = 'Format tidak didukung. Gunakan CSV, XLS, atau XLSX.';
                return;
            }

            $this->kodeCol     = $this->guessColumn(['kode','code','icd','icd10','icd_10','kode_icd','kode icd']);
            $this->namaCol     = $this->guessColumn(['nama','name','deskripsi','description','diagnosa','title','nama penyakit','nama_icd']);
            $this->kategoriCol = $this->guessColumn(['kategori','category','bab','chapter','blok','block']);

            $this->importState = 'preview';
        } catch (\Throwable $e) {
            $this->importError = 'Gagal membaca file: ' . $e->getMessage();
        }
    }

    private function parseFromCsv(): void
    {
        $path   = $this->uploadedFile->getRealPath();
        $handle = fopen($path, 'r');
        $bom    = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);
        $firstLine = fgets($handle); rewind($handle);
        if ($bom !== "\xEF\xBB\xBF") {}
        $delim = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        $headers = null; $rows = []; $count = 0;
        while (($row = fgetcsv($handle, 0, $delim)) !== false) {
            if ($headers === null) { $headers = array_map('trim', $row); continue; }
            $rows[] = $row;
            if (++$count >= 10) break;
        }
        fclose($handle);
        $this->detectedHeaders = $headers ?? [];
        $this->preview = $rows;
    }

    private function parseFromExcel(): void
    {
        $collection = \Maatwebsite\Excel\Facades\Excel::toCollection(null, $this->uploadedFile->getRealPath())->first();
        if (!$collection || $collection->isEmpty()) throw new \RuntimeException('Sheet kosong.');
        $this->detectedHeaders = array_map('trim', array_values($collection->first()->toArray()));
        $this->preview = $collection->skip(1)->take(10)->map(fn ($r) => array_values($r->toArray()))->toArray();
    }

    private function guessColumn(array $aliases): string
    {
        foreach ($this->detectedHeaders as $idx => $h) {
            if (in_array(mb_strtolower(trim($h)), $aliases)) return (string) $idx;
        }
        return '';
    }

    public function doImport(): void
    {
        if ($this->importState !== 'preview' || !$this->uploadedFile) return;

        $kodeIdx     = $this->kodeCol !== '' ? (int) $this->kodeCol : null;
        $namaIdx     = $this->namaCol !== '' ? (int) $this->namaCol : null;
        $kategoriIdx = $this->kategoriCol !== '' ? (int) $this->kategoriCol : null;

        if ($kodeIdx === null || $namaIdx === null) {
            $this->importError = 'Kolom Kode dan Nama wajib dipetakan.';
            return;
        }

        $ext = strtolower($this->uploadedFile->getClientOriginalExtension());

        try {
            $allRows  = $this->readAllRows($ext);
            $imported = 0; $skipped = 0; $errors = []; $batch = [];

            if ($this->importMode === 'replace') DB::table('icd10')->truncate();

            $now = now();
            foreach ($allRows as $lineNo => $row) {
                $kode = strtoupper(trim($row[$kodeIdx] ?? ''));
                $nama = trim($row[$namaIdx] ?? '');

                if ($kode === '' || $nama === '') { $skipped++; continue; }

                if (!preg_match('/^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/i', $kode)) {
                    $errors[] = "Baris " . ($lineNo + 2) . ": kode \"$kode\" tidak valid.";
                    if (count($errors) >= 10) { $errors[] = '...dan lainnya.'; break; }
                    $skipped++; continue;
                }

                $kategori = $kategoriIdx !== null ? trim($row[$kategoriIdx] ?? '') : null;
                $batch[] = [
                    'kode' => $kode, 'nama' => $nama,
                    'nama_en' => null, 'nama_id' => null,
                    'kategori' => $kategori ?: null,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                $imported++;

                if (count($batch) >= 500) {
                    DB::table('icd10')->upsert($batch, ['kode'], ['nama', 'kategori', 'updated_at']);
                    $batch = [];
                }
            }
            if (!empty($batch)) DB::table('icd10')->upsert($batch, ['kode'], ['nama', 'kategori', 'updated_at']);

            $this->importResult = ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
            $this->importState  = 'done';
            $this->uploadedFile = null;
        } catch (\Throwable $e) {
            $this->importError = 'Gagal: ' . $e->getMessage();
        }
    }

    private function readAllRows(string $ext): array
    {
        if ($ext === 'csv') {
            $path = $this->uploadedFile->getRealPath();
            $handle = fopen($path, 'r');
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);
            $firstLine = fgets($handle); rewind($handle);
            if ($bom !== "\xEF\xBB\xBF") {}
            $delim = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
            $rows = []; $isFirst = true;
            while (($row = fgetcsv($handle, 0, $delim)) !== false) {
                if ($isFirst) { $isFirst = false; continue; }
                $rows[] = $row;
            }
            fclose($handle);
            return $rows;
        }
        $collection = \Maatwebsite\Excel\Facades\Excel::toCollection(null, $this->uploadedFile->getRealPath())->first();
        return $collection->skip(1)->map(fn ($r) => array_values($r->toArray()))->toArray();
    }

    public function resetImport(): void
    {
        $this->uploadedFile = null; $this->preview = []; $this->detectedHeaders = [];
        $this->kodeCol = ''; $this->namaCol = ''; $this->kategoriCol = '';
        $this->importMode = 'upsert'; $this->importState = 'idle';
        $this->importResult = []; $this->importError = '';
    }

    public function render()
    {
        return view('livewire.pengaturan.masterdata.icd-manager');
    }
}
