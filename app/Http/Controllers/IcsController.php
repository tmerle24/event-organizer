<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\IcsBuilder;

class IcsController extends Controller
{
    public function __construct(private readonly IcsBuilder $builder) {}

    public function download(Event $event)
    {
        abort_unless($event->decided_option_id, 404);

        return response($this->builder->build($event), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$this->builder->filename($event).'"',
        ]);
    }
}
