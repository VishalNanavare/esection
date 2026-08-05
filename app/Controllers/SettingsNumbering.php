<?php

namespace App\Controllers;

use App\Services\DocumentNumberingService;

class SettingsNumbering extends BaseController
{
    protected DocumentNumberingService $documentNumberingService;

    public function __construct()
    {
        $this->documentNumberingService = new DocumentNumberingService();
    }

    public function index()
    {
        $data = [
            'title'   => 'Settings — Document Numbering',
            'prefix'  => $this->documentNumberingService->getPrefix(),
            'preview' => $this->documentNumberingService->previewNext(),
        ];

        return view('settings/numbering', $data);
    }

    public function store()
    {
        try {
            $this->documentNumberingService->save($this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('settings/numbering'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsNumbering::store] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/numbering'))
                ->with('error', 'The numbering format could not be saved. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/numbering'))->with('success', 'Document numbering updated.');
    }
}
