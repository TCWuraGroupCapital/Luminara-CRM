<?php

namespace Webkul\Email\Mails\Concerns;

use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Webkul\Email\Support\BusinessMailer;

/**
 * For email a business sends to its own contacts: deliver it through the business's own account
 * and from its own address. The account is looked up when the message is actually sent, so a
 * queued message uses the settings of the business it was queued from.
 */
trait SendsAsBusiness
{
    protected bool $sendingAsBusiness = false;

    /**
     * @param  Factory|Mailer  $mailer
     */
    public function send($mailer)
    {
        $business = app(BusinessMailer::class)->mailer();

        $this->sendingAsBusiness = $business !== null;

        return parent::send($business ?? $mailer);
    }

    /**
     * The business's account only accepts its own address, so that wins over any sender the
     * message set itself.
     *
     * @param  Message  $message
     * @return $this
     */
    protected function buildFrom($message)
    {
        if (! $this->sendingAsBusiness) {
            return parent::buildFrom($message);
        }

        $business = app(BusinessMailer::class);

        $message->from($business->fromAddress(), $business->fromName());

        return $this;
    }
}
