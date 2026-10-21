<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutletSection extends Model
{
    use HasFactory;

    protected $primaryKey = 'section_id';

    protected $guarded = ['section_id'];
}
