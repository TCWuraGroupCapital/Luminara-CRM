<?php

namespace Webkul\Email\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;
use Webkul\Email\InboundEmailProcessor\WebklexImapEmailProcessor;
use Webkul\Email\Support\BusinessInbox;

class ProcessInboundEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inbound-emails:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will process the incoming emails from the mail server.';

    /**
     * Handle.
     */
    public function handle(BusinessInbox $inbox): int
    {
        /**
         * Runs for the main database and, through luminara:workspace:run, for every business;
         * most have no mailbox to read. The processor is built here, not in the constructor,
         * because building it opens the IMAP connection.
         */
        if (! $inbox->shouldPoll()) {
            $this->line('No mailbox to read.');

            return self::SUCCESS;
        }

        $this->info('Processing the incoming emails.');

        $processor = $inbox->hasOwnAccount()
            ? $this->laravel->make(WebklexImapEmailProcessor::class)
            : $this->laravel->make(InboundEmailProcessor::class);

        $processor->processMessagesFromAllFolders();

        $this->info('Incoming emails processed successfully.');

        return self::SUCCESS;
    }
}
