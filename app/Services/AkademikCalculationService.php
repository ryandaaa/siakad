<?php

namespace App\Services;

use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\Krs;
use App\Models\TahunAkademik;
use Illuminate\Support\Collection;

class AkademikCalculationService
{
    /**
     * Calculate IPS (Indeks Prestasi Semester) for a specific semester
     */
    public function calculateIPS(Mahasiswa $mahasiswa, ?int $tahunAkademikId = null): array
    {
        $tahunAkademikId = $tahunAkademikId ?? TahunAkademik::where('is_active', true)->first()?->id;
        
        if (!$tahunAkademikId) {
            return ['ips' => 0, 'total_sks' => 0, 'total_bobot' => 0];
        }

        $nilaiList = Nilai::where('mahasiswa_id', $mahasiswa->id)
            ->whereHas('kelas', function ($q) use ($tahunAkademikId) {
                $q->whereHas('krsDetail.krs', function ($q2) use ($tahunAkademikId) {
                    $q2->where('tahun_akademik_id', $tahunAkademikId);
                });
            })
            ->with('kelas.mataKuliah')
            ->get();

        return $this->calculateIndexFromNilai($nilaiList);
    }

    /**
     * Calculate IPK (Indeks Prestasi Kumulatif) - all semesters
     */
    public function calculateIPK(Mahasiswa $mahasiswa): array
    {
        $nilaiList = Nilai::where('mahasiswa_id', $mahasiswa->id)
            ->with('kelas.mataKuliah')
            ->get();

        return $this->calculateIndexFromNilai($nilaiList);
    }

    /**
     * Get grade distribution for a mahasiswa
     */
    public function getGradeDistribution(Mahasiswa $mahasiswa): array
    {
        // ⚡ Bolt: Optimize by using database-level aggregation instead of hydrating all models in memory
        $distributionCounts = Nilai::where('mahasiswa_id', $mahasiswa->id)
            ->whereNotNull('nilai_huruf')
            ->selectRaw('nilai_huruf, count(*) as count')
            ->groupBy('nilai_huruf')
            ->pluck('count', 'nilai_huruf')
            ->toArray();
        
        $distribution = [
            'A' => 0, 'B+' => 0, 'B' => 0, 'C+' => 0, 
            'C' => 0, 'D' => 0, 'E' => 0
        ];

        foreach ($distributionCounts as $huruf => $count) {
            if (isset($distribution[$huruf])) {
                $distribution[$huruf] = $count;
            }
        }

        return $distribution;
    }

    /**
     * Get semester-wise IPS history
     */
    public function getIPSHistory(Mahasiswa $mahasiswa): Collection
    {
        $krsHistory = Krs::where('mahasiswa_id', $mahasiswa->id)
            ->where('status', 'approved')
            ->with('tahunAkademik')
            ->orderBy('created_at')
            ->get();

        return $krsHistory->map(function ($krs) use ($mahasiswa) {
            $ipsData = $this->calculateIPS($mahasiswa, $krs->tahun_akademik_id);
            return [
                'tahun_akademik' => $krs->tahunAkademik->tahun . ' ' . $krs->tahunAkademik->semester,
                'tahun_akademik_id' => $krs->tahun_akademik_id,
                'ips' => $ipsData['ips'],
                'total_sks' => $ipsData['total_sks'],
            ];
        });
    }

    /**
     * Determine max SKS allowed based on IPS
     */
    public function getMaxSKS(float $ips): int
    {
        $rules = config('siakad.maks_sks.ips_rules', []);
        
        foreach ($rules as $rule) {
            if ($ips >= $rule['min'] && $ips <= $rule['max']) {
                return $rule['sks'];
            }
        }

        return config('siakad.maks_sks.default', 24);
    }

    /**
     * Get transcript data for mahasiswa
     */
    public function getTranscript(Mahasiswa $mahasiswa): array
    {
        $nilaiList = Nilai::where('mahasiswa_id', $mahasiswa->id)
            ->with(['kelas.mataKuliah', 'kelas.krsDetail.krs.tahunAkademik'])
            ->get();

        $grouped = $nilaiList->groupBy(function ($nilai) {
            $krs = $nilai->kelas->krsDetail->first()?->krs;
            return $krs ? $krs->tahunAkademik->tahun . ' - ' . $krs->tahunAkademik->semester : 'Unknown';
        });

        $transcript = [];
        foreach ($grouped as $semester => $nilaiGroup) {
            $semesterData = $this->calculateIndexFromNilai($nilaiGroup);
            $transcript[] = [
                'semester' => $semester,
                'ips' => $semesterData['ips'],
                'total_sks' => $semesterData['total_sks'],
                'courses' => $nilaiGroup->map(fn($n) => [
                    'kode' => $n->kelas->mataKuliah->kode_mk,
                    'nama' => $n->kelas->mataKuliah->nama_mk,
                    'sks' => $n->kelas->mataKuliah->sks,
                    'nilai_angka' => $n->nilai_angka,
                    'nilai_huruf' => $n->nilai_huruf,
                ])
            ];
        }

        $ipkData = $this->calculateIPK($mahasiswa);
        
        return [
            'mahasiswa' => $mahasiswa,
            'semesters' => $transcript,
            'ipk' => $ipkData['ips'],
            'total_sks_lulus' => $ipkData['total_sks'],
        ];
    }

    /**
     * Calculate index from nilai collection
     */
    private function calculateIndexFromNilai(Collection $nilaiList): array
    {
        $totalSks = 0;
        $totalBobot = 0;

        foreach ($nilaiList as $nilai) {
            $sks = $nilai->kelas->mataKuliah->sks;
            $bobot = $this->getBobot($nilai->nilai_huruf);
            
            $totalSks += $sks;
            $totalBobot += ($sks * $bobot);
        }

        $index = $totalSks > 0 ? round($totalBobot / $totalSks, 2) : 0;

        return [
            'ips' => $index,
            'total_sks' => $totalSks,
            'total_bobot' => $totalBobot,
        ];
    }

    /**
     * Get bobot from nilai huruf using config
     */
    private function getBobot(string $nilaiHuruf): float
    {
        $konversi = config('siakad.nilai_konversi', []);
        
        foreach ($konversi as $k) {
            if ($k['huruf'] === $nilaiHuruf) {
                return $k['bobot'];
            }
        }

        return 0;
    }
}
