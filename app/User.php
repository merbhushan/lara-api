<?php

namespace App;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Model\CommonScope;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, CommonScope;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    // hasOne Relationship of Resume
    public function resume(){
        return $this->attachment();
    }
    // hasOne Relationship of Cover Latter
    public function cover(){
        return $this->attachment();
    }
    // hasOne Relationship of References
    public function reference(){
        return $this->attachment();
    }
}
