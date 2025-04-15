<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\User;
use OwenIt\Auditing\Contracts\Audit as AuditContract;

class Audit extends Model implements AuditContract
{
    use HasFactory;

    /**
     * La tabla asociada con el modelo.
     */
    protected $table = 'audits';

    /**
     * Los atributos que no son asignables masivamente.
     */
    protected $guarded = [];

    /**
     * Los atributos que deben convertirse a tipos nativos.
     */
    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtener el modelo auditable.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Obtener el usuario que realizó la acción.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(bool $json = false, int $options = 0, int $depth = 512)
    {
        $metadata = $this->getAttributes();

        if ($json) {
            return json_encode($metadata, $options, $depth);
        }

        return $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getModified(bool $json = false, int $options = 0, int $depth = 512)
    {
        $modified = [
            'old_values' => $this->old_values ?: [],
            'new_values' => $this->new_values ?: [],
        ];

        if ($json) {
            return json_encode($modified, $options, $depth);
        }

        return $modified;
    }

    /**
     * {@inheritdoc}
     */
    public function setMetadata(array $metadata): AuditContract
    {
        if (empty($metadata)) {
            return $this;
        }

        foreach ($metadata as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setModified(array $modified): AuditContract
    {
        if (array_key_exists('old_values', $modified)) {
            $this->old_values = $modified['old_values'];
        }

        if (array_key_exists('new_values', $modified)) {
            $this->new_values = $modified['new_values'];
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataValue(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveData(): array
    {
        $data = [
            'auditable_id' => $this->auditable_id,
            'auditable_type' => $this->auditable_type,
            'user_id' => $this->user_id,
            'event' => $this->event,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'tags' => $this->tags,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'url' => $this->url,
        ];

        return array_filter($data);
    }
}
