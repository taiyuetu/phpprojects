<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class HelpController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->view('help/index');
    }
}
