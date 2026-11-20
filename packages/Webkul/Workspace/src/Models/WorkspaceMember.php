<?php

namespace Webkul\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Workspace\WorkspaceManager;

/**
 * Sign-in directory: which workspace an email address belongs to. It holds no password and
 * no profile; those stay inside the workspace database.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $email
 */
class WorkspaceMember extends Model
{
    protected $connection = WorkspaceManager::LANDLORD;

    protected $table = 'workspace_members';

    protected $fillable = [
        'workspace_id',
        'email',
    ];

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
