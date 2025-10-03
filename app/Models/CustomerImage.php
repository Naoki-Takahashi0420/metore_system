<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'file_path',
        'title',
        'image_type',
        'description',
        'display_order',
        'is_visible_to_customer',
        'uploaded_by',
    ];

    protected $casts = [
        'is_visible_to_customer' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
