<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CetakInvoiceLog extends Model
{
    protected $table = 'cetak_invoice_log';

    protected $fillable = ['billing_id', 'user_id', 'nomor_cetak', 'jenis', 'ip_address'];

    public function billing(): BelongsTo { return $this->belongsTo(Invoice::class, 'billing_id'); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
}
