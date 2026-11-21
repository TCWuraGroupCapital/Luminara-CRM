<?php

namespace Webkul\Admin\Http\Controllers\Configuration;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Email\Exceptions\UnsafeSmtpHost;
use Webkul\Email\Support\BusinessMailer;

/**
 * Sends one test email to the signed-in person through the business's saved outgoing account.
 */
class SmtpTestController extends Controller
{
    public function __invoke(BusinessMailer $business): JsonResponse
    {
        if (! $business->configured()) {
            return new JsonResponse([
                'message' => trans('admin::app.configuration.index.email.smtp.not-saved'),
            ], 422);
        }

        $to = (string) data_get(auth()->guard('user')->user(), 'email');

        try {
            $business->build($business->settings())->raw(
                trans('admin::app.configuration.index.email.smtp.test-body', ['app' => config('app.name')]),
                static fn ($message) => $message->to($to)->subject(trans('admin::app.configuration.index.email.smtp.test-subject')),
            );
        } catch (UnsafeSmtpHost $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'message' => trans('admin::app.configuration.index.email.smtp.test-failed', [
                    'reason' => Str::limit(trim(strtok($exception->getMessage(), "\n") ?: ''), 200),
                ]),
            ], 422);
        }

        return new JsonResponse([
            'message' => trans('admin::app.configuration.index.email.smtp.test-sent', ['email' => $to]),
        ]);
    }
}
