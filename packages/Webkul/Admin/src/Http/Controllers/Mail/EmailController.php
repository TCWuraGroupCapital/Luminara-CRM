<?php

namespace Webkul\Admin\Http\Controllers\Mail;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;
use Webkul\Admin\DataGrids\Mail\EmailDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\EmailResource;
use Webkul\Admin\Traits\ScopesMailAccess;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Email\Enums\SupportedFolderEnum;
use Webkul\Email\Exceptions\BusinessMailNotConfigured;
use Webkul\Email\Exceptions\UnsafeSmtpHost;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;
use Webkul\Email\Mails\Email;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Lead\Repositories\LeadRepository;

class EmailController extends Controller
{
    use ScopesMailAccess;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected EmailRepository $emailRepository,
        protected AttachmentRepository $attachmentRepository
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse|RedirectResponse
    {
        $route = $this->mailFolder();

        if (! $route) {
            return redirect()->route('admin.mail.index', ['route' => SupportedFolderEnum::INBOX->value]);
        }

        if (! bouncer()->hasPermission('mail.'.$route)) {
            abort(401, trans('admin::app.mail.unauthorized'));
        }

        if (request()->ajax()) {
            return datagrid(EmailDataGrid::class)->process();
        }

        return view('admin::mail.index', compact('route'));
    }

    /**
     * The mail folder being browsed, taken only from the `{route}` URL segment.
     *
     * The sanitize_url middleware whitelists that segment. `request('route')` must not be used: it
     * returns query-string or body input before the route segment, so `?route=…` slipped any value
     * past the whitelist and into pages that Vue compiles.
     */
    protected function mailFolder(): ?string
    {
        return request()->route('route');
    }

    /**
     * Deny linking a mail to a lead, person or parent mail outside the acting user's data scope.
     *
     * The ids come straight from the request and are mass assignable, so without this a user could
     * file a mail under any record and read that record back through the mail resource. Each link
     * is held to the same check as opening the record it names.
     */
    protected function preventUnauthorizedMailLinks(array $data): void
    {
        if (! empty($data['lead_id'])) {
            $this->preventUnauthorizedAccess($this->leadRepository->findOrFail($data['lead_id'])->user_id);
        }

        if (! empty($data['person_id'])) {
            $this->preventUnauthorizedAccess(app(PersonRepository::class)->findOrFail($data['person_id'])->user_id);
        }

        if (! empty($data['parent_id'])) {
            $this->preventUnauthorizedMailAccess($this->emailRepository->findOrFail($data['parent_id']));
        }
    }

    /**
     * Reduce a set of mails to the ones the acting user may write to, keeping unlinked (shared)
     * mail. Mass actions silently skip what is out of scope instead of failing the whole batch,
     * matching the other mass endpoints.
     */
    protected function filterAuthorizedMails($mails)
    {
        $userIds = bouncer()->getAuthorizedUserIds();

        if ($userIds === null) {
            return $mails;
        }

        return $mails->filter(fn ($email) => $this->isMailInScope($email, $userIds))->values();
    }

    /**
     * Display a resource.
     *
     * @return View
     */
    public function view()
    {
        $route = $this->mailFolder();

        $email = $this->emailRepository
            ->with([
                'emails',
                'attachments',
                'emails.attachments',
                'lead',
                'lead.person',
                'lead.tags',
                'lead.source',
                'lead.type',
                'person',
            ])
            ->findOrFail(request('id'));

        /**
         * A mail linked to a lead or person is scoped to whoever owns that record. The mailbox
         * itself is shared, so unlinked mail stays visible to everyone with mail access; but a
         * linked mail belonging to a lead/person outside the acting user's data scope is denied
         * outright, rather than merely hiding its `lead_id` while still returning the subject,
         * body, thread and attachments.
         */
        $this->preventUnauthorizedMailAccess($email);

        if ($route == SupportedFolderEnum::DRAFT->value) {
            return response()->json([
                'data' => new EmailResource($email),
            ]);
        }

        return view('admin::mail.view', compact('email', 'route'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store()
    {
        $this->validate(request(), [
            'reply_to' => 'required|array|min:1',
            'reply_to.*' => 'email',
            'reply' => 'required',
            'attachments' => 'sometimes|array',
            'attachments.*' => 'file|max:20480',
        ]);

        $this->preventUnauthorizedMailLinks(request()->all());

        Event::dispatch('email.create.before');

        $email = $this->emailRepository->create(request()->all());

        $sendError = null;

        if (! request('is_draft')) {
            $sendError = $this->deliver($email, [SupportedFolderEnum::SENT->value]);
        }

        Event::dispatch('email.create.after', $email);

        if ($sendError !== null) {
            if (request()->ajax()) {
                return response()->json([
                    'data' => new EmailResource($this->emailRepository->find($email->id)),
                    'message' => $sendError,
                ], 422);
            }

            session()->flash('error', $sendError);

            return redirect()->route('admin.mail.index', ['route' => SupportedFolderEnum::OUTBOX->value]);
        }

        if (request()->ajax()) {
            return response()->json([
                'data' => new EmailResource($email),
                'message' => trans('admin::app.mail.create-success'),
            ]);
        }

        if (request('is_draft')) {
            session()->flash('success', trans('admin::app.mail.saved-to-draft'));

            return redirect()->route('admin.mail.index', ['route' => SupportedFolderEnum::DRAFT->value]);
        }

        session()->flash('success', trans('admin::app.mail.create-success'));

        return redirect()->route('admin.mail.index', ['route' => SupportedFolderEnum::SENT->value]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update($id)
    {
        /**
         * The path id is authoritative. Honouring `request('id')` let a body parameter override it,
         * so a request to one mail could be redirected to update another.
         */
        $this->preventUnauthorizedMailAccess($this->emailRepository->findOrFail($id));

        $data = request()->all();

        unset($data['id']);

        $this->preventUnauthorizedMailLinks($data);

        Event::dispatch('email.update.before', $id);

        if (! is_null(request('is_draft'))) {
            $data['folders'] = request('is_draft') ? [SupportedFolderEnum::DRAFT->value] : [SupportedFolderEnum::OUTBOX->value];
        }

        $email = $this->emailRepository->update($data, $id);

        Event::dispatch('email.update.after', $email);

        $sendError = null;

        if (! is_null(request('is_draft')) && ! request('is_draft')) {
            $sendError = $this->deliver($email, [SupportedFolderEnum::INBOX->value, SupportedFolderEnum::SENT->value]);
        }

        if ($sendError !== null) {
            if (request()->ajax()) {
                return response()->json([
                    'data' => new EmailResource($this->emailRepository->find($email->id)),
                    'message' => $sendError,
                ], 422);
            }

            session()->flash('error', $sendError);

            return redirect()->route('admin.mail.index', ['route' => SupportedFolderEnum::OUTBOX->value]);
        }

        if (! is_null(request('is_draft'))) {
            if (request('is_draft')) {
                session()->flash('success', trans('admin::app.mail.saved-to-draft'));

                return redirect()->route('admin.mail.index', ['route' => SupportedFolderEnum::DRAFT->value]);
            } else {
                session()->flash('success', trans('admin::app.mail.create-success'));

                return redirect()->route('admin.mail.index', ['route' => SupportedFolderEnum::INBOX->value]);
            }
        }

        if (request()->ajax()) {
            return response()->json([
                'data' => new EmailResource($email->refresh()),
                'message' => trans('admin::app.mail.update-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.mail.update-success'));

        return redirect()->back();
    }

    /**
     * Send an email and file it in the given folders. Returns why it was not sent, or null.
     *
     * A failed send used to be swallowed, so the screen said "sent" while the email sat in the
     * outbox. Now it stays in the outbox and the person is told why.
     *
     * @param  list<string>  $folders
     */
    protected function deliver($email, array $folders): ?string
    {
        try {
            Mail::send(new Email($email));
        } catch (BusinessMailNotConfigured|UnsafeSmtpHost $exception) {
            return $exception->getMessage();
        } catch (Throwable $exception) {
            report($exception);

            return trans('admin::app.mail.not-sent', [
                'reason' => Str::limit(trim(strtok($exception->getMessage(), "\n") ?: ''), 200),
            ]);
        }

        $this->emailRepository->update(['folders' => $folders], $email->id);

        return null;
    }

    /**
     * Run process inbound parse email.
     */
    public function inboundParse(): JsonResponse
    {
        $validator = Validator::make(request()->all(), [
            'email' => ['required', 'string', 'max:26214400'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid inbound email payload.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (config('mail-receiver.default') !== 'sendgrid') {
            return response()->json([
                'message' => 'Inbound webhook parse is disabled unless MAIL_RECEIVER_DRIVER=sendgrid.',
            ], 503);
        }

        app(InboundEmailProcessor::class)->processMessage($validator->validated()['email']);

        return response()->json([], 200);
    }

    /**
     * Download file from storage
     *
     * @param  int  $id
     * @return View
     */
    public function download($id)
    {
        /**
         * Downloading an attachment requires the mail view permission — the same permission that
         * gates the mailbox it belongs to. This closes the missing function-level authorization that
         * let any authenticated user retrieve an attachment by its id, independent of the ACL route
         * map (defense in depth).
         */
        abort_unless(bouncer()->hasPermission('mail.view'), 401, trans('admin::app.errors.unauthorized'));

        $attachment = $this->attachmentRepository->findOrFail($id);

        /**
         * Bind the download to the acting user's data scope through the attachment's parent mail:
         * an attachment on a lead/person-linked mail outside that scope is refused, so an
         * unauthorized mail id can no longer be turned into an unauthorized file download.
         */
        $this->preventUnauthorizedMailAccess($attachment->email);

        try {
            return Storage::disk(AttachmentRepository::resolveDisk($attachment->path))
                ->download($attachment->path, $attachment->name);
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());

            return redirect()->back();
        }
    }

    /**
     * Mass Update the specified resources.
     */
    public function massUpdate(MassUpdateRequest $massUpdateRequest): JsonResponse
    {
        $emails = $this->filterAuthorizedMails(
            $this->emailRepository->findWhereIn('id', $massUpdateRequest->input('indices'))
        );

        try {
            foreach ($emails as $email) {
                Event::dispatch('email.update.before', $email->id);

                $this->emailRepository->update([
                    'folders' => request('folders'),
                ], $email->id);

                Event::dispatch('email.update.after', $email->id);
            }

            return response()->json([
                'message' => trans('admin::app.mail.mass-update-success'),
            ]);
        } catch (Exception) {
            return response()->json([
                'message' => trans('admin::app.mail.mass-update-success'),
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse|RedirectResponse
    {
        $email = $this->emailRepository->findOrFail($id);

        $this->preventUnauthorizedMailAccess($email);

        try {
            Event::dispatch('email.'.request('type').'.before', $id);

            $parentId = $email->parent_id;

            if (request('type') == SupportedFolderEnum::TRASH->value) {
                $this->emailRepository->update([
                    'folders' => [SupportedFolderEnum::TRASH->value],
                ], $id);
            } else {
                $this->emailRepository->delete($id);
            }

            Event::dispatch('email.'.request('type').'.after', $id);

            if (request()->ajax()) {
                return response()->json([
                    'message' => trans('admin::app.mail.delete-success'),
                ], 200);
            }

            session()->flash('success', trans('admin::app.mail.delete-success'));

            if ($parentId) {
                return redirect()->back();
            }

            return redirect()->route('admin.mail.index', ['route' => SupportedFolderEnum::INBOX->value]);
        } catch (Exception $exception) {
            if (request()->ajax()) {
                return response()->json([
                    'message' => trans('admin::app.mail.delete-failed'),
                ], 400);
            }

            session()->flash('error', trans('admin::app.mail.delete-failed'));

            return redirect()->back();
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $mails = $this->filterAuthorizedMails(
            $this->emailRepository->findWhereIn('id', $massDestroyRequest->input('indices'))
        );

        try {
            foreach ($mails as $email) {
                Event::dispatch('email.'.$massDestroyRequest->input('type').'.before', $email->id);

                if ($massDestroyRequest->input('type') == SupportedFolderEnum::TRASH->value) {
                    $this->emailRepository->update(['folders' => [SupportedFolderEnum::TRASH->value]], $email->id);
                } else {
                    $this->emailRepository->delete($email->id);
                }

                Event::dispatch('email.'.$massDestroyRequest->input('type').'.after', $email->id);
            }

            return response()->json([
                'message' => trans('admin::app.mail.delete-success'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => trans('admin::app.mail.delete-success'),
            ]);
        }
    }
}
