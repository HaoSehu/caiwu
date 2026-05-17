<?php

namespace App\Http\Controllers;

use App\Support\SiteSeoConfig;

class SiteConfigController extends Controller
{
    public function index()
    {
        return $this->success(SiteSeoConfig::payload());
    }
}
