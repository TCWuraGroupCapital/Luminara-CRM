<?php

namespace Webkul\Lead\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser;

class MagicAIService
{
    /**
     * API endpoint for OpenRouter AI service.
     */
    const OPEN_ROUTER_URL = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * Maximum token limit for AI prompt.
     */
    const MAX_TOKENS = 100000;

    /**
     * Flag to prevent re-entrant calls.
     */
    private static $isExtracting = false;

    /**
     * Extract data from base64-encoded file.
     */
    public static function extractDataFromFile($base64File)
    {
        if (self::$isExtracting) {
            throw new Exception(trans('admin::app.leads.file.recursive-call'));
        }

        self::$isExtracting = true;

        try {
            $payload = self::extractTextFromBase64File($base64File);

            if (empty(trim((string) ($payload['text'] ?? ''))) && empty($payload['images'])) {
                throw new Exception(trans('admin::app.leads.file.failed-extract'));
            }

            return self::processPromptWithAI($payload);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        } finally {
            self::$isExtracting = false;
        }
    }

    /**
     * Extract text and image data URIs from base64-encoded file.
     */
    private static function extractTextFromBase64File($base64File)
    {
        $decoded = is_string($base64File) ? base64_decode($base64File, true) : false;

        if ($decoded === false || $decoded === '') {
            throw new Exception(trans('admin::app.leads.file.invalid-base64'));
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->buffer($decoded);

        $data = [
            'text' => '',
            'images' => [],
        ];

        try {
            if ($mimeType === 'application/pdf') {
                $pdfParser = (new Parser)->parseContent($decoded);

                $data['text'] = $pdfParser->getText();

                $images = $pdfParser->getObjectsByType('XObject', 'Image');

                foreach (array_slice($images, 0, 3) as $image) {
                    $rawImage = $image->getContent();

                    if (! is_string($rawImage) || $rawImage === '') {
                        continue;
                    }

                    $imageMime = (string) $finfo->buffer($rawImage);

                    if (str_starts_with($imageMime, 'image/')) {
                        $data['images'][] = 'data:'.$imageMime.';base64,'.self::handleBase64($rawImage);
                    }
                }
            } elseif (str_starts_with($mimeType, 'image/')) {
                $data['images'][] = 'data:'.$mimeType.';base64,'.self::handleBase64($decoded);
            } elseif (str_starts_with($mimeType, 'text/')) {
                $data['text'] = $decoded;
            }

            if (empty(trim($data['text'])) && empty($data['images'])) {
                throw new Exception(trans('admin::app.leads.file.data-extraction-failed'));
            }

            return $data;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Send extracted data to AI for processing.
     */
    private static function processPromptWithAI($prompt)
    {
        $model = core()->getConfigData('general.magic_ai.settings.other_model') ?: core()->getConfigData('general.magic_ai.settings.model');

        $apiKey = core()->getConfigData('general.magic_ai.settings.api_key');

        if (! $apiKey || ! $model) {
            return ['error' => trans('admin::app.leads.file.missing-api-key')];
        }

        $promptText = self::truncatePrompt((string) ($prompt['text'] ?? ''));

        $promptImages = array_values(array_filter($prompt['images'] ?? [], fn ($uri) => is_string($uri) && $uri !== ''));

        return self::ask($promptText, $promptImages, $model, $apiKey);
    }

    /**
     * Truncate prompt to fit within token limit.
     */
    private static function truncatePrompt($prompt)
    {
        if (mb_strlen($prompt) > self::MAX_TOKENS) {
            $start = mb_substr($prompt, 0, (int) (self::MAX_TOKENS * 0.4));

            $end = mb_substr($prompt, (int) (-self::MAX_TOKENS * 0.4));

            return $start."\n...\n".$end;
        }

        return $prompt;
    }

    /**
     * Send prompt request to AI for processing.
     */
    private static function ask(string $promptText, array $promptImages, $model, $apiKey)
    {
        $contentBlocks = [];

        $contentBlocks[] = [
            'type' => 'text',
            'text' => $promptText !== ''
                ? $promptText
                : 'Extract structured lead and contact details from the attached document image.',
        ];

        foreach ($promptImages as $dataUri) {
            $contentBlocks[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $dataUri,
                ],
            ];
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(20)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$apiKey,
                ])->post(self::OPEN_ROUTER_URL, [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => self::getSystemPrompt(),
                        ], [
                            'role' => 'user',
                            'content' => $contentBlocks,
                        ],
                    ],
                ]);

            if ($response->failed()) {
                throw new Exception($response->body());
            }

            $data = $response->json();

            if (isset($data['error'])) {
                $errorMessage = is_array($data['error'])
                    ? (string) ($data['error']['message'] ?? 'AI request failed.')
                    : (string) $data['error'];

                throw new Exception($errorMessage);
            }

            return $data;
        } catch (Exception $e) {
            return ['error' => trans('admin::app.leads.file.insufficient-info')];
        }
    }

    /**
     * Define system prompt for AI processing.
     *
     * @return string System prompt for AI model.
     */
    private static function getSystemPrompt()
    {
        return <<<'PROMPT'
            You are an AI assistant specialized in extracting structured data from text.  
            The user will provide text extracted from a file (in Base64 or plain text).  
            Your task is to accurately extract the following fields. If the value is not available, use the default values provided:  

            ### **Output Format:** 
            ```json
            {
                "status": 1,
                "title": "Untitled Lead",
                "lead_value": 0,
                "person": {
                    "name": "Unknown",
                    "emails": {
                        "value": null,
                        "label": null
                    },
                    "contact_numbers": {
                        "value": null,
                        "label": null
                    }
                }
            }
            ```
            ### **Fields to Extract:**
            - **Title:** Title of the lead. Default: "Untitled Lead"
            - **Lead Value:** Value of the lead. Default: 0
            - **Person Name:** Name of the person. Default: "Unknown"
            - **Person Email:** Email of the person. Default: null
            - **Person Email Label:** Label for the email. Default: null
            - **Person Contact Number:** Contact number of the person. Default: null
            - **Person Contact Number Label:** Label for the contact number. Default: null
        PROMPT;
    }

    /**
     * process for encoding and decoding base64 data.
     */
    private static function handleBase64($base64, string $type = 'encode')
    {
        if ($type === 'encode') {
            return base64_encode($base64);
        }

        return base64_decode($base64);
    }
}
