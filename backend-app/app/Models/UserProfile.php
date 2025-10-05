<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = ['user_id','photo_url','address','phone'];
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
