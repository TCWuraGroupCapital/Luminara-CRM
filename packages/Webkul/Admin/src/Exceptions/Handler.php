<?php

namespace Webkul\Admin\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Container\Container;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Json error messages.
     *
     * @var array
     */
    protected $jsonErrorMessages = [];

    /**
     * Create handler instance.
     *
     * @return void
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->jsonErrorMessages = [
            '404' => trans('admin::app.errors.404.title'),
            '403' => trans('admin::app.errors.403.title'),
            '401' => trans('admin::app.errors.401.title'),
            '500' => trans('admin::app.errors.500.title'),
        ];
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     * @return Response
     */
    public function render($request, Throwable $exception)
    {
        if (! config('app.debug')) {
            return $this->renderCustomResponse($exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  Request  $request
     * @return Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->jsonErrorMessages[401]], 401);
        }

        return redirect()->guest(route('customer.session.index'));
    }

    /**
     * Render custom HTTP response.
     *
     * @return Response|null
     */
    private function renderCustomResponse(Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            return parent::render(request(), $exception);
        }

        /**
         * Laravel's own mapping first: an expired CSRF token becomes 419, a missing model 404, a
         * failed policy 403. Then every HTTP error keeps its real status and headers (Allow on
         * 405, Retry-After on 429). Before, anything but 401/403/404/503, a rate limit and an
         * expired form included, was answered as a server error.
         */
        $exception = $this->prepareException($exception);

        if ($exception instanceof HttpExceptionInterface) {
            return $this->response($exception->getStatusCode(), $exception->getHeaders());
        }

        return $this->response(500);
    }

    /**
     * Status codes the error page has its own wording for; others show the nearest general page.
     */
    private const PAGES = [400, 401, 403, 404, 419, 429, 500, 503];

    /**
     * Return custom response.
     *
     * @param  array<string, string>  $headers
     * @return mixed
     */
    private function response(int $status, array $headers = [])
    {
        $errorCode = in_array($status, self::PAGES, true) ? $status : ($status >= 500 ? 500 : 400);

        if (request()->expectsJson()) {
            return response()->json([
                'message' => trans("admin::app.errors.{$errorCode}.title"),
            ], $status, $headers);
        }

        /**
         * The page is sent with the status it describes. Without it every error, a failed
         * webhook included, reached the caller as "200 OK".
         */
        return response()->view('admin::errors.index', compact('errorCode'), $status, $headers);
    }
}
