<?php

namespace Webkul\Workspace\Mail;

use Illuminate\Mail\Mailable;
use Webkul\Workspace\Models\Workspace;

/**
 * Asks a new owner to confirm their email address. Sent before any database is built, so a
 * mistyped or borrowed address never ends up owning a workspace.
 */
class VerifySignUp extends Mailable
{
    public function __construct(public Workspace $workspace, public string $token) {}

    public function build(): static
    {
        return $this
            ->subject(trans('workspace::app.mail.subject'))
            ->view('workspace::emails.verify', [
                'name' => $this->workspace->owner_name,
                'business' => $this->workspace->name,
                'link' => route('workspace.signup.verify', $this->token),
                'hours' => Workspace::VERIFICATION_HOURS,
            ]);
    }
}
