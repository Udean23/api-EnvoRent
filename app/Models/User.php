<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'google_id',
        'avatar',
    ];

    protected $hidden = ['password'];

    protected $attributes = [
        'role' => 'customer'
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function conversationsAsUser1()
    {
        return $this->hasMany(Conversation::class, 'user_id_1');
    }

    public function conversationsAsUser2()
    {
        return $this->hasMany(Conversation::class, 'user_id_2');
    }

    public function getConversations()
    {
        return Conversation::where('user_id_1', $this->id)
            ->orWhere('user_id_2', $this->id)
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
}
