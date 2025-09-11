<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MedicalRecordImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'medical_record_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'title',
        'description',
        'display_order',
        'is_visible_to_customer',
        'image_type',
    ];

    protected $casts = [
        'is_visible_to_customer' => 'boolean',
        'file_size' => 'integer',
        'display_order' => 'integer',
    ];

    /**
     * 所属するカルテ
     */
    public function medicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    /**
     * 画像URLを取得
     */
    public function getUrlAttribute()
    {
        if (filter_var($this->file_path, FILTER_VALIDATE_URL)) {
            return $this->file_path;
        }
        
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * サムネイルURLを取得（将来的に実装予定）
     */
    public function getThumbnailUrlAttribute()
    {
        // 現時点では通常のURLを返す
        return $this->url;
    }

    /**
     * 画像タイプの日本語表記
     */
    public function getImageTypeTextAttribute()
    {
        return match($this->image_type) {
            'before' => '施術前',
            'after' => '施術後',
            'progress' => '経過',
            'reference' => '参考',
            'other' => 'その他',
            default => $this->image_type,
        };
    }

    /**
     * ファイルサイズを人間が読みやすい形式で取得
     */
    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return $bytes . ' byte';
        } else {
            return '0 bytes';
        }
    }
}