<?php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\IcdDiagnosis;
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

    // ── Import ─────────────────────────────────────────
    public $uploadedFile  = null;
    public array  $preview          = [];      // 10 baris pertama untuk preview
    public array  $detectedHeaders  = [];      // header kolom dari file
    public string $kodeCol          = '';      // index/nama kolom kode
    public string $namaCol          = '';      // index/nama kolom nama
    public string $kategoriCol      = '';      // index/nama kolom kategori (opsional)
    public string $importMode       = 'upsert'; // upsert | replace
    public string $importState      = 'idle';   // idle | preview | done
    public array  $importResult     = [];
    public string $importError      = '';

    // ── Browse computed ────────────────────────────────
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    // ── Upload & Preview ───────────────────────────────
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
                $this->importError = 'Format file tidak didukung. Gunakan CSV, XLS, atau XLSX.';
                return;
            }

            // Auto-detect kolom
            $this->kodeCol     = $this->guessColumn(['kode','code','icd','icd10','icd_10','kode_icd','kode icd']);
            $this->namaCol     = $this->guessColumn(['nama','name','deskripsi','description','diagnosa','title','nama penyakit']);
            $this->kategoriCol = $this->guessColumn(['kategori','category','bab','chapter','blok','block','kategori/bab']);

            $this->importState = 'preview';
        } catch (\Throwable $e) {
            $this->importError = 'Gagal membaca file: ' . $e->getMessage();
        }
    }

    private function parseFromCsv(): void
    {
        $path = $this->uploadedFile->getRealPath();
        $handle = fopen($path, 'r');

        // Detect BOM (UTF-8 BOM dari Excel)
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        // Detect delimiter: koma atau titik koma
        $firstLine = fgets($handle);
        rewind($handle);
        if ($bom !== "\xEF\xBB\xBF") {
            // skip BOM check, already rewound
        }
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        $rows = [];
        $headers = null;
        $count = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($headers === null) {
                $headers = array_map('trim', $row);
                continue;
            }
            $rows[] = $row;
            if (++$count >= 10) break;
        }
        fclose($handle);

        $this->detectedHeaders = $headers ?? [];
        $this->preview = $rows;
    }

    private function parseFromExcel(): void
    {
        $collection = \Maatwebsite\Excel\Facades\Excel::toCollection(
            null,
            $this->uploadedFile->getRealPath()
        )->first();

        if (!$collection || $collection->isEmpty()) {
            throw new \RuntimeException('Sheet kosong atau tidak ditemukan.');
        }

        $firstRow = $collection->first();
        $this->detectedHeaders = array_map('trim', array_values($firstRow->toArray()));

        $this->preview = $collection->skip(1)->take(10)
            ->map(fn ($r) => array_values($r->toArray()))
            ->toArray();
    }

    private function guessColumn(array $aliases): string
    {
        foreach ($this->detectedHeaders as $idx => $h) {
            if (in_array(mb_strtolower(trim($h)), $aliases)) {
                return (string) $idx;
            }
        }
        return '';
    }

    // ── Run Import ─────────────────────────────────────
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
            $allRows = $this->readAllRows($ext);

            $imported = 0;
            $skipped  = 0;
            $errors   = [];
            $batch    = [];

            if ($this->importMode === 'replace') {
                DB::table('icd10')->truncate();
            }

            $now = now();

            foreach ($allRows as $lineNo => $row) {
                $kode = trim($row[$kodeIdx] ?? '');
                $nama = trim($row[$namaIdx] ?? '');

                if ($kode === '' || $nama === '') {
                    $skipped++;
                    continue;
                }

                // Validasi format kode ICD-10 (misal A00, A00.0, Z99.9, dll.)
                if (!preg_match('/^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/i', $kode)) {
                    $errors[] = "Baris " . ($lineNo + 2) . ": kode \"$kode\" tidak valid.";
                    if (count($errors) >= 10) { $errors[] = '...dan lainnya.'; break; }
                    $skipped++;
                    continue;
                }

                $kategori = $kategoriIdx !== null ? trim($row[$kategoriIdx] ?? '') : null;

                $batch[] = [
                    'kode'       => strtoupper($kode),
                    'nama'       => $nama,
                    'kategori'   => $kategori ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $imported++;

                // Batch upsert setiap 500 baris
                if (count($batch) >= 500) {
                    DB::table('icd10')->upsert($batch, ['kode'], ['nama', 'kategori', 'updated_at']);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('icd10')->upsert($batch, ['kode'], ['nama', 'kategori', 'updated_at']);
            }

            $this->importResult = [
                'imported' => $imported,
                'skipped'  => $skipped,
                'errors'   => $errors,
            ];
            $this->importState  = 'done';
            $this->uploadedFile = null;

        } catch (\Throwable $e) {
            $this->importError = 'Gagal memproses import: ' . $e->getMessage();
        }
    }

    private function readAllRows(string $ext): array
    {
        if ($ext === 'csv') {
            $path = $this->uploadedFile->getRealPath();
            $handle = fopen($path, 'r');

            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);

            $firstLine = fgets($handle);
            rewind($handle);
            if ($bom !== "\xEF\xBB\xBF") {}
            $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

            $rows    = [];
            $isFirst = true;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($isFirst) { $isFirst = false; continue; } // skip header
                $rows[] = $row;
            }
            fclose($handle);
            return $rows;
        }

        // Excel
        $collection = \Maatwebsite\Excel\Facades\Excel::toCollection(
            null,
            $this->uploadedFile->getRealPath()
        )->first();

        return $collection->skip(1)
            ->map(fn ($r) => array_values($r->toArray()))
            ->toArray();
    }

    public function resetImport(): void
    {
        $this->uploadedFile    = null;
        $this->preview         = [];
        $this->detectedHeaders = [];
        $this->kodeCol         = '';
        $this->namaCol         = '';
        $this->kategoriCol     = '';
        $this->importMode      = 'upsert';
        $this->importState     = 'idle';
        $this->importResult    = [];
        $this->importError     = '';
    }

    // ── Stats ──────────────────────────────────────────
    #[Computed]
    public function stats(): array
    {
        $latest = IcdDiagnosis::max('updated_at');
        return [
            'total'      => IcdDiagnosis::count(),
            'updated_at' => $latest ? \Carbon\Carbon::parse($latest)->format('d/m/Y H:i') : '-',
        ];
    }

    public function render()
    {
        return view('livewire.pengaturan.masterdata.icd-manager');
    }
}
