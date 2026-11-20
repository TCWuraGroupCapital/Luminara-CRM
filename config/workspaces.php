<?php

return [
    /**
     * Off by default: a single-business install behaves exactly as before. When on, every
     * business that signs up gets its own database and the session decides which one a
     * request reads and writes.
     */
    'enabled' => (bool) env('LUMINARA_WORKSPACES', false),

    /**
     * Who may create a workspace from the public sign-up page.
     *  - off:    nobody (workspaces are created with `luminara:workspace:create`)
     *  - invite: only people who know LUMINARA_SIGNUP_INVITE_CODE
     *  - open:   anyone
     */
    'signup' => env('LUMINARA_SIGNUP', 'off'),

    'invite_code' => env('LUMINARA_SIGNUP_INVITE_CODE'),

    /**
     * Whether a new owner must open a link emailed to them before the workspace is built.
     * Left unset it is on for `open` sign-up and off for `invite`. It needs working outgoing
     * mail: with MAIL_MAILER=log the link is only written to the log file.
     */
    'verify_email' => env('LUMINARA_SIGNUP_VERIFY_EMAIL'),

    /**
     * Hard ceiling on the number of workspaces. Each one is a full database, so an
     * unbounded public form would let anyone fill the server.
     */
    'limit' => (int) env('LUMINARA_WORKSPACE_LIMIT', 50),

    /**
     * API clients name their workspace in this header. It only chooses which database the
     * bearer token is checked against, so a wrong value can never widen access.
     */
    'header' => 'X-Luminara-Workspace',

    'session_key' => 'luminara.workspace_id',
];
