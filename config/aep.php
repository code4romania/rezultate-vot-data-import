<?php
return [

    'presence' => env('AEP_PRESENCE_URL', 'https://prezenta.roaep.ro/locale09062024/data/json/simpv/presence/'),
    'presence_county' => env('AEP_PRESENCE_COUNTY_URL', 'presence_{short_county}_now.json'),

    ];
