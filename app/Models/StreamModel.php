<?php

namespace App\Models;

use CodeIgniter\Model;

class StreamModel extends Model
{
    protected $table            = 'stream_details';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['Division', 'full_name'];

    public function getAllStreams(): array
    {
        return $this->orderBy('Division', 'ASC')->findAll();
    }

    public function getDistinctDivisions(): array
    {
        return $this->select('Division')
                    ->distinct()
                    ->where('Division IS NOT NULL')
                    ->where('Division !=', '')
                    ->orderBy('Division', 'ASC')
                    ->findAll();
    }

    public function searchStreamsForSelect2(?string $term): array
    {
        $builder = $this->select('Division as stream');
        if (!empty($term)) {
            $builder->like('Division', $term);
        }
        $streams = $builder->distinct()->orderBy('Division', 'ASC')->findAll();

        $results = [];
        foreach ($streams as $s) {
            $name = $s['stream'];
            if (!empty($name)) {
                $results[] = [
                    'id'   => $name,
                    'text' => $name
                ];
            }
        }
        return ['results' => $results];
    }
}
