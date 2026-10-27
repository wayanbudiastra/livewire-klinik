<?php

return [

    /*
    | Batas hari order susulan (lab/obat tambahan) sejak tanggal kunjungan asal.
    | Dikonfirmasi klinik: 14 hari. Lihat docs/PERANCANGAN_COMBINE_BILL_RITEL.md.
    */
    'susulan_maks_hari' => (int) env('SUSULAN_MAKS_HARI', 14),

];
