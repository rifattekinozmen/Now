<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Logo Connect XML — ek Order alt alanları
    |--------------------------------------------------------------------------
    |
    | Anahtar: XML etiketi. Değer: Order model özelliği veya özel anahtar.
    | Özel: ordered_at → ISO-8601, status → enum değeri.
    |
    */

    'order_fields' => [
        'OrderedAt' => 'ordered_at',
        'OrderStatus' => 'status',
    ],

];
