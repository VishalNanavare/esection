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
            return $this->respondToPost(false, $e->getMessage(), base_url('settings/numbering'));
        } catch (\Throwable $e) {
            log_message('error', '[SettingsNumbering::store] {message}', ['message' => (string) $e]);

            return $this->respondToPost(
                false,
                'The numbering format could not be saved. The issue has been logged.',
                base_url('settings/numbering'),
                [],
                500
            );
        }

        // Both values travel back. save() uppercases the prefix, and
        // previewNext() re-rolls its random suffix on every call, so neither
        // can be inferred on the client from what was typed.
        return $this->respondToPost(true, 'Document numbering updated.', base_url('settings/numbering'), [
            'state' => [
                'prefix'  => $this->documentNumberingService->getPrefix(),
                'preview' => $this->documentNumberingService->previewNext(),
            ],
        ]);
    }
}
