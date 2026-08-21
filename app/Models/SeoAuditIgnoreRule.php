<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAuditIgnoreRule extends Model
{
    protected $fillable = ['fingerprint', 'issue_type', 'source_path', 'target_path', 'reason', 'created_by'];
}
