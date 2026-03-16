<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CsvImport extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'partner_firebase_id',
        'uploaded_by',
        'filename',
        'total_rows',
        'imported',
        'duplicates',
        'errors',
        'error_details',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'imported' => 'integer',
        'duplicates' => 'integer',
        'errors' => 'integer',
        'error_details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
