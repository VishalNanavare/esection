<?php

namespace App\Models;

use CodeIgniter\Model;

class StreamModel extends Model
{
    protected $table            = 'stream_details';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    // stream_details is exactly (id, Name, Division). The previous list was
    // ['Division', 'full_name'] -- 'full_name' has never existed on this
    // table, and 'Name' (the programme-family column that does) was missing.
    // $allowedFields is consulted only on insert/update and nothing writes
    // through this Model today, so the mismatch was latent: the first write
    // would have silently dropped Name and tried to set a phantom column.
    protected $allowedFields    = ['Name', 'Division'];

    /**
     * Fresh builder per call, matching the idiom the other fifteen Models
     * use. Model::builder() returns a SHARED builder that accumulates
     * where-clauses across calls within one request; $db->table() returns a
     * new one every time. Identical SQL, no cross-call leakage.
     */
    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    public function getAllStreams(): array
    {
        return $this->table()->orderBy('Division', 'ASC')->get()->getResultArray();
    }

    public function getDistinctDivisions(): array
    {
        return $this->table()
                    ->select('Division')
                    ->distinct()
                    ->where('Division IS NOT NULL')
                    ->where('Division !=', '')
                    ->orderBy('Division', 'ASC')
                    ->get()->getResultArray();
    }

    public function searchStreamsForSelect2(?string $term): array
    {
        $builder = $this->table()->select('Division as stream');
        if (!empty($term)) {
            $builder->like('Division', like_term($term));
        }
        $streams = $builder->distinct()->orderBy('Division', 'ASC')->get()->getResultArray();

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
