<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PfOperation extends Model
{
    use HasFactory;

    protected $fillable = ['operation_type', 'operation_date', 'account_id', 'account_title', 'value', 'currency_code',
        'comment', 'operation_dt', 'status'];
}
