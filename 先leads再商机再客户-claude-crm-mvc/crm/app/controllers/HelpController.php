<?php

class HelpController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->view('help/index');
    }
}
