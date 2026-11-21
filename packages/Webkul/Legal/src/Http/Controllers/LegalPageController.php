<?php

namespace Webkul\Legal\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Webkul\Legal\Support\LegalDocuments;

class LegalPageController extends Controller
{
    public function show(string $document): Response
    {
        abort_unless(LegalDocuments::enabled(), 404);

        $html = LegalDocuments::html($document);

        abort_if($html === null, 404);

        return response()
            ->view('legal::page', [
                'document' => $document,
                'title' => LegalDocuments::title($document),
                'html' => $html,
                'version' => trim((string) config('legal.version')),
            ])
            ->header('Cache-Control', 'public, max-age=300');
    }
}
