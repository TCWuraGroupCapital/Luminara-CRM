<?php

namespace Webkul\Email\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email as MimeEmail;
use Webkul\Email\Mails\Concerns\SendsAsBusiness;
use Webkul\Email\Repositories\AttachmentRepository;

class Email extends Mailable
{
    use Queueable, SendsAsBusiness, SerializesModels;

    /**
     * Create a new email instance.
     *
     * @return void
     */
    public function __construct(public $email) {}

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->from($this->email->from)
            ->to($this->email->reply_to)
            ->cc($this->email->cc ?? [])
            ->bcc($this->email->bcc ?? [])
            ->subject($this->email->parent_id ? $this->email->parent->subject : $this->email->subject)
            ->html($this->email->reply);

        /**
         * Through the business's own account, replies go back to its own mailbox, where Settings >
         * Configuration > Email > IMAP picks them up. Through the server's mailer they go to the
         * thread's address for inbound parsing, as before.
         */
        if (! $this->sendingAsBusiness) {
            $this->replyTo($this->email->parent_id ? $this->email->parent->unique_id : $this->email->unique_id);
        }

        $this->withSymfonyMessage(function (MimeEmail $message) {
            $message->getHeaders()->addIdHeader('Message-ID', $this->email->message_id);

            $message->getHeaders()->addTextHeader('References', $this->email->parent_id
                ? implode(' ', $this->email->parent->reference_ids)
                : implode(' ', $this->email->reference_ids)
            );
        });

        foreach ($this->email->attachments as $attachment) {
            $this->attachFromStorageDisk(
                AttachmentRepository::resolveDisk($attachment->path),
                $attachment->path,
                $attachment->name
            );
        }

        return $this;
    }
}
