<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\profession;

class Category extends Model
{
    
    protected $fillable = ['name'];

    public function professions()
    {
        return $this->hasMany(profession::class);
    }
}
