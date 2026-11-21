<?php

namespace App\Http\Controllers;

use App\Support\SiteContent;

abstract class Controller
{
    protected function siteImages(): array
    {
        return app(SiteContent::class)->images();
    }
}
