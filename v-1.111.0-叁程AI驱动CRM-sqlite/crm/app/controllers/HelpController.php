<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class HelpController extends Controller
{
    /** 使用说明页：技术参考区由 AppMap 实时算出，不手写，因此不会和代码脱节。 */
    public function index(): void
    {
        $this->requireAuth();
        $this->view('help/index', ['map' => AppMap::all()]);
    }

    /**
     * GET /help/context — the same map as plain text, meant to be pasted into an
     * LLM (or fetched by a script) so an assistant gets the whole project at once.
     * Layout "none" makes Controller::view() emit just the view.
     */
    public function context(): void
    {
        $this->requireAuth();
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: private, no-store');
        $this->view('help/context', ['text' => AppMap::toText()], 'none');
    }
}
