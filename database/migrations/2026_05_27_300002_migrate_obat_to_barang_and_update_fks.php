<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Migrate data obat → barang (idempotent) ──────────────────────────
        if (Schema::hasTable('obat')) {
            $obats = DB::table('obat')
                ->leftJoin('satuan as sk', 'sk.id', '=', 'obat.satuan_kecil_id')
                ->leftJoin('satuan as sb', 'sb.id', '=', 'obat.satuan_besar_id')
                ->select(
                    'obat.id as obat_id',
                    'obat.kode', 'obat.barcode', 'obat.nama', 'obat.generik',
                    'obat.jenis_barang', 'obat.is_paten', 'obat.kategori',
                    'obat.satuan',
                    'sk.nama as satuan_kecil_nama',
                    'sb.nama as satuan_besar_nama',
                    'obat.konversi',
                    'obat.stok', 'obat.harga', 'obat.harga_beli', 'obat.harga_bpjs',
                    'obat.is_active', 'obat.expired_date',
                    'obat.created_at', 'obat.updated_at'
                )
                ->get();

            $obatToBarang = [];

            foreach ($obats as $o) {
                $existing = DB::table('barang')->where('kode', $o->kode)->value('id');

                if ($existing) {
                    $obatToBarang[$o->obat_id] = $existing;
                    continue;
                }

                $satuan      = $o->satuan_kecil_nama ?? $o->satuan;
                $satuan_besar = $o->satuan_besar_nama;
                $isi = ($o->konversi && $o->konversi > 1) ? $o->konversi : null;

                $barangId = DB::table('barang')->insertGetId([
                    'kode'            => $o->kode,
                    'barcode'         => $o->barcode,
                    'nama'            => $o->nama,
                    'nama_generik'    => $o->generik,
                    'jenis'           => in_array($o->jenis_barang, ['obat', 'alkes']) ? $o->jenis_barang : 'obat',
                    'kategori'        => $o->kategori,
                    'satuan'          => $satuan ?: 'pcs',
                    'satuan_besar'    => $satuan_besar,
                    'isi_satuan_besar'=> $isi,
                    'stok'            => $o->stok ?? 0,
                    'stok_minimum'    => 10,
                    'harga_pokok'     => $o->harga_beli ?? 0,
                    'harga_jual'      => $o->harga ?? 0,
                    'harga_bpjs'      => $o->harga_bpjs,
                    'is_paten'        => (bool) $o->is_paten,
                    'butuh_resep'     => ($o->jenis_barang === 'obat'),
                    'is_active'       => (bool) $o->is_active,
                    'expired_date'    => $o->expired_date,
                    'created_at'      => $o->created_at,
                    'updated_at'      => $o->updated_at,
                ]);

                $obatToBarang[$o->obat_id] = $barangId;
            }
        } else {
            // Obat table already dropped — rebuild mapping from barang
            $obatToBarang = [];
        }

        // ── 2. item_resep (skip jika sudah done) ────────────────────────────────
        if (Schema::hasColumn('item_resep', 'obat_id')) {
            if (! Schema::hasColumn('item_resep', 'barang_id')) {
                Schema::table('item_resep', function (Blueprint $table) {
                    $table->unsignedBigInteger('barang_id')->nullable()->after('resep_id');
                });
            }

            foreach ($obatToBarang as $obatId => $barangId) {
                DB::table('item_resep')->where('obat_id', $obatId)->update(['barang_id' => $barangId]);
            }
            DB::statement('UPDATE item_resep SET barang_id = 1 WHERE barang_id IS NULL');

            Schema::table('item_resep', function (Blueprint $table) {
                $table->unsignedBigInteger('barang_id')->nullable(false)->change();
                $table->foreign('barang_id')->references('id')->on('barang')->onDelete('restrict');
                $table->dropForeign(['obat_id']);
                $table->dropColumn('obat_id');
            });
        }

        // ── 3. bahan_racikan (skip jika sudah done) ─────────────────────────────
        if (Schema::hasColumn('bahan_racikan', 'obat_id')) {
            if (! Schema::hasColumn('bahan_racikan', 'barang_id')) {
                Schema::table('bahan_racikan', function (Blueprint $table) {
                    $table->unsignedBigInteger('barang_id')->nullable()->after('racikan_id');
                });
            }

            foreach ($obatToBarang as $obatId => $barangId) {
                DB::table('bahan_racikan')->where('obat_id', $obatId)->update(['barang_id' => $barangId]);
            }
            DB::statement('UPDATE bahan_racikan SET barang_id = 1 WHERE barang_id IS NULL');

            Schema::table('bahan_racikan', function (Blueprint $table) {
                $table->unsignedBigInteger('barang_id')->nullable(false)->change();
                $table->foreign('barang_id')->references('id')->on('barang')->onDelete('restrict');
                $table->dropForeign(['obat_id']);
                $table->dropColumn('obat_id');
            });
        }

        // ── 4. stok_gudang ──────────────────────────────────────────────────────
        if (Schema::hasColumn('stok_gudang', 'obat_id')) {
            if (! Schema::hasColumn('stok_gudang', 'barang_id')) {
                Schema::table('stok_gudang', function (Blueprint $table) {
                    $table->unsignedBigInteger('barang_id')->nullable()->after('id');
                });
            }

            foreach ($obatToBarang as $obatId => $barangId) {
                DB::table('stok_gudang')->where('obat_id', $obatId)->update(['barang_id' => $barangId]);
            }

            // Hapus duplikat
            DB::statement('
                DELETE sg1 FROM stok_gudang sg1
                INNER JOIN stok_gudang sg2
                ON sg1.barang_id = sg2.barang_id
                AND sg1.lokasi_gudang_id = sg2.lokasi_gudang_id
                AND sg1.id > sg2.id
            ');

            Schema::table('stok_gudang', function (Blueprint $table) {
                // FK drop harus sebelum unique index drop
                $table->dropForeign(['obat_id']);
                $table->dropUnique(['obat_id', 'lokasi_gudang_id']);
                $table->dropColumn('obat_id');

                $table->unsignedBigInteger('barang_id')->nullable(false)->change();
                $table->foreign('barang_id')->references('id')->on('barang')->onDelete('cascade');
                $table->unique(['barang_id', 'lokasi_gudang_id']);
            });
        }

        // ── 5. batch_expired ────────────────────────────────────────────────────
        if (Schema::hasColumn('batch_expired', 'obat_id')) {
            if (! Schema::hasColumn('batch_expired', 'barang_id')) {
                Schema::table('batch_expired', function (Blueprint $table) {
                    $table->unsignedBigInteger('barang_id')->nullable()->after('id');
                });
            }

            foreach ($obatToBarang as $obatId => $barangId) {
                DB::table('batch_expired')->where('obat_id', $obatId)->update(['barang_id' => $barangId]);
            }

            Schema::table('batch_expired', function (Blueprint $table) {
                $table->dropForeign(['obat_id']);
                $table->dropColumn('obat_id');

                $table->unsignedBigInteger('barang_id')->nullable(false)->change();
                $table->foreign('barang_id')->references('id')->on('barang')->onDelete('cascade');
            });
        }

        // ── 6. pemakaian_alkes ──────────────────────────────────────────────────
        if (Schema::hasColumn('pemakaian_alkes', 'obat_id')) {
            if (! Schema::hasColumn('pemakaian_alkes', 'barang_id')) {
                Schema::table('pemakaian_alkes', function (Blueprint $table) {
                    $table->unsignedBigInteger('barang_id')->nullable()->after('kunjungan_id');
                });
            }

            foreach ($obatToBarang as $obatId => $barangId) {
                DB::table('pemakaian_alkes')->where('obat_id', $obatId)->update(['barang_id' => $barangId]);
            }
            DB::statement('UPDATE pemakaian_alkes SET barang_id = 1 WHERE barang_id IS NULL');

            Schema::table('pemakaian_alkes', function (Blueprint $table) {
                $table->dropForeign(['obat_id']);
                $table->dropColumn('obat_id');

                $table->unsignedBigInteger('barang_id')->nullable(false)->change();
                $table->foreign('barang_id')->references('id')->on('barang')->onDelete('restrict');
            });
        }

        // ── 7. Drop tabel obat ──────────────────────────────────────────────────
        Schema::dropIfExists('obat');
    }

    public function down(): void
    {
        throw new \RuntimeException('Rollback tidak didukung untuk migration ini.');
    }
};
