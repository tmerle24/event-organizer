<?php

/**
 * Planungs-Templates (Spec Abschnitt 4, Schritt 4 + 5).
 *
 * Die Sektionen sind Vorschlaege und einzeln entfernbar. Die Tasks erscheinen
 * im UI als inaktive Vorschlagsliste mit "Uebernehmen" pro Zeile — die KI (bzw.
 * das Template) weist niemals selbst zu.
 *
 * 'title' und 'tasks' sind Uebersetzungs-Keys aus lang/{locale}/planning.php.
 */
return [

    'templates' => [

        'barbecue' => [
            'sections' => [
                'food' => ['grill', 'sides', 'bread'],
                'drinks' => ['soft_drinks', 'beer', 'ice'],
                'equipment' => ['grill_device', 'charcoal', 'tableware'],
                'organization' => ['location', 'music'],
            ],
        ],

        'dinner' => [
            'sections' => [
                'food' => ['starter', 'main', 'dessert'],
                'drinks' => ['wine', 'water'],
                'equipment' => ['tableware', 'decoration'],
                'organization' => ['reservation'],
            ],
        ],

        'party' => [
            'sections' => [
                'drinks' => ['soft_drinks', 'beer', 'ice'],
                'food' => ['snacks', 'cake'],
                'equipment' => ['music', 'decoration', 'tableware'],
                'organization' => ['location', 'invitations'],
            ],
        ],

        'trip' => [
            'sections' => [
                'travel' => ['transport', 'accommodation'],
                'organization' => ['route', 'budget'],
                'equipment' => ['packing_list'],
            ],
        ],

        'meeting' => [
            'sections' => [
                'organization' => ['agenda', 'room', 'minutes'],
                'equipment' => ['presentation'],
            ],
        ],

        'generic' => [
            'sections' => [
                'organization' => ['todo_1', 'todo_2'],
                'food' => [],
                'equipment' => [],
            ],
        ],
    ],

    /**
     * event_type -> planning_template. Bewusst getrennt gehalten: der Typ
     * beschreibt das Event, das Template die Planungsstruktur.
     */
    'type_to_template' => [
        'barbecue' => 'barbecue',
        'dinner' => 'dinner',
        'party' => 'party',
        'trip' => 'trip',
        'meeting' => 'meeting',
        'sports' => 'generic',
        'generic' => 'generic',
    ],
];
