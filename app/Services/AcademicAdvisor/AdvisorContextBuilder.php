<?php

namespace App\Services\AcademicAdvisor;

use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\Nilai;
use App\Models\Krs;
use App\Models\KrsDetail;
use App\Models\JadwalKuliah;
use App\Services\AkademikCalculationService;
use App\Services\PresensiService;

class AdvisorContextBuilder
{
    protected AkademikCalculationService $calculationService;
    protected PresensiService $presensiService;

    public function __construct(
        AkademikCalculationService $calculationService,
        PresensiService $presensiService
    ) {
        $this->calculationService = $calculationService;
        $this->presensiService = $presensiService;
    }

    /**
     * Build comprehensive context for AI Advisor
     */
    public function build(Mahasiswa $mahasiswa): array
    {
        $mahasiswa->load(['user', 'prodi.fakultas', 'dosenPa.user']);

        $prodiKey = $this->getProdiKey($mahasiswa);
        $rules = $this->getProdiRules($prodiKey);

        $academicSummary = $this->buildAcademicSummary($mahasiswa);
        $courseStatuses = $this->buildCourseStatuses($mahasiswa, $prodiKey);
        $curriculum = $this->getCurriculum($prodiKey);
        $schedule = $this->getActiveSchedule($mahasiswa);
        $attendance = $this->getAttendanceData($mahasiswa);

        return [
            'student' => [
                'nama' => $mahasiswa->user->name,
                'nim' => $mahasiswa->nim,
                'prodi' => $mahasiswa->prodi->nama ?? '-',
                'fakultas' => $mahasiswa->prodi->fakultas->nama ?? '-',
                'angkatan' => $mahasiswa->angkatan,
                'semester_aktif' => $this->calculateActiveSemester($mahasiswa),
                'status' => $mahasiswa->status,
                'dosen_pa' => $mahasiswa->dosenPa->user->name ?? '-',
            ],
            'prodi_rules' => $rules,
            'academic_summary' => $academicSummary,
            'course_statuses' => $courseStatuses,
            'curriculum' => $curriculum,
            'schedule' => $schedule,
            'attendance' => $attendance,
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'prodi_key' => $prodiKey,
            ],
        ];
    }

    /**
     * Get prodi key for config lookup
     */
    protected function getProdiKey(Mahasiswa $mahasiswa): string
    {
        $prodiName = strtolower($mahasiswa->prodi->nama ?? '');

        if (str_contains($prodiName, 'sistem informasi')) {
            return 'sistem_informasi_unri';
        }

        return 'default';
    }

    /**
     * Get rules for specific prodi
     */
    protected function getProdiRules(string $prodiKey): array
    {
        $rules = config("academic_rules.prodi.{$prodiKey}");

        if (!$rules) {
            $rules = config('academic_rules.default');
        }

        return [
            'graduation_total_sks' => $rules['graduation_total_sks'] ?? 144,
            'thesis_min_sks' => $rules['thesis_min_sks'] ?? 144,
            'internship_sks' => $rules['internship']['sks'] ?? 3,
            'internship_min_sks' => $rules['internship']['min_sks_required'] ?? 90,
        ];
    }

    /**
     * Build academic summary
     */
    protected function buildAcademicSummary(Mahasiswa $mahasiswa): array
    {
        $ipkData = $this->calculationService->calculateIPK($mahasiswa);
        $ipsHistory = $this->calculationService->getIPSHistory($mahasiswa);

        // Get current semester SKS (enrolled but not graded)
        $sksSedangDiambil = $this->getEnrolledSks($mahasiswa);

        // Determine max SKS for next semester
        $lastIps = $ipsHistory->filter(fn($s) => $s['ips'] > 0)->last()['ips'] ?? 0;
        $maxSks = $this->calculationService->getMaxSKS($lastIps);

        return [
            'total_sks_lulus' => $ipkData['total_sks'],
            'total_sks_sedang_diambil' => $sksSedangDiambil,
            'ipk' => $ipkData['ips'],
            'max_sks_next_semester' => $maxSks,
            'ips_history' => $ipsHistory->map(fn($s) => [
                'semester' => $s['tahun_akademik'],
                'ips' => $s['ips'],
                'sks' => $s['total_sks'],
            ])->values()->toArray(),
        ];
    }

    /**
     * Get enrolled SKS (current semester, not yet graded)
     */
    protected function getEnrolledSks(Mahasiswa $mahasiswa): int
    {
        $activeKrs = Krs::where('mahasiswa_id', $mahasiswa->id)
            ->where('status', 'approved')
            ->whereHas('tahunAkademik', fn($q) => $q->where('is_active', true))
            ->with('krsDetail.kelas.mataKuliah')
            ->first();

        if (!$activeKrs) {
            return 0;
        }

        return $activeKrs->krsDetail->sum(fn($d) => $d->kelas->mataKuliah->sks ?? 0);
    }

    /**
     * Build course statuses (LULUS, SEDANG_DIAMBIL, TERSEDIA_DI_KURIKULUM)
     */
    protected function buildCourseStatuses(Mahasiswa $mahasiswa, string $prodiKey): array
    {
        $statuses = [];

        // 1. LULUS - from Nilai (graded courses)
        $nilaiList = Nilai::where('mahasiswa_id', $mahasiswa->id)
            ->with('kelas.mataKuliah')
            ->get();

        foreach ($nilaiList as $nilai) {
            $mk = $nilai->kelas->mataKuliah;
            if ($mk) {
                $statuses[$mk->kode_mk] = [
                    'kode' => $mk->kode_mk,
                    'nama' => $mk->nama_mk,
                    'sks' => $mk->sks,
                    'status' => 'LULUS',
                    'nilai' => $nilai->nilai_huruf,
                    'semester' => $mk->semester,
                ];
            }
        }

        // 2. SEDANG_DIAMBIL - from active KRS without nilai
        $activeKrsDetails = KrsDetail::whereHas('krs', function ($q) use ($mahasiswa) {
            $q->where('mahasiswa_id', $mahasiswa->id)
              ->where('status', 'approved')
              ->whereHas('tahunAkademik', fn($ta) => $ta->where('is_active', true));
        })
        ->with('kelas.mataKuliah')
        ->get();

        foreach ($activeKrsDetails as $detail) {
            $mk = $detail->kelas->mataKuliah;
            if ($mk && !isset($statuses[$mk->kode_mk])) {
                // Check if grade exists
                $hasGrade = Nilai::where('mahasiswa_id', $mahasiswa->id)
                    ->where('kelas_id', $detail->kelas_id)
                    ->exists();

                if (!$hasGrade) {
                    $statuses[$mk->kode_mk] = [
                        'kode' => $mk->kode_mk,
                        'nama' => $mk->nama_mk,
                        'sks' => $mk->sks,
                        'status' => 'SEDANG_DIAMBIL',
                        'nilai' => null,
                        'semester' => $mk->semester,
                    ];
                }
            }
        }

        // 3. TERSEDIA_DI_KURIKULUM - from curriculum config
        $curriculum = config("academic_rules.kurikulum.{$prodiKey}", []);

        foreach ($curriculum as $semester => $courses) {
            foreach ($courses as $course) {
                if (!isset($statuses[$course['kode']])) {
                    $statuses[$course['kode']] = [
                        'kode' => $course['kode'],
                        'nama' => $course['nama'],
                        'sks' => $course['sks'],
                        'status' => 'TERSEDIA_DI_KURIKULUM',
                        'nilai' => null,
                        'semester' => $semester,
                    ];
                }
            }
        }

        return array_values($statuses);
    }

    /**
     * Get curriculum for specific prodi
     */
    protected function getCurriculum(string $prodiKey): array
    {
        $curriculum = config("academic_rules.kurikulum.{$prodiKey}", []);

        $result = [];
        foreach ($curriculum as $semester => $courses) {
            $result[] = [
                'semester' => $semester,
                'mata_kuliah' => $courses,
            ];
        }

        return $result;
    }

    /**
     * Get active schedule
     */
    protected function getActiveSchedule(Mahasiswa $mahasiswa): array
    {
        $jadwalList = JadwalKuliah::whereHas('kelas.krsDetail.krs', function ($q) use ($mahasiswa) {
            $q->where('mahasiswa_id', $mahasiswa->id)
              ->where('status', 'approved')
              ->whereHas('tahunAkademik', fn($ta) => $ta->where('is_active', true));
        })->with('kelas.mataKuliah')->get();

        return $jadwalList->map(fn($j) => [
            'hari' => $j->hari,
            'jam_mulai' => substr($j->jam_mulai, 0, 5),
            'jam_selesai' => substr($j->jam_selesai, 0, 5),
            'mata_kuliah' => $j->kelas->mataKuliah->nama_mk ?? '-',
            'ruangan' => $j->ruangan ?? '-',
        ])->toArray();
    }

    /**
     * Get attendance data with availability flag
     */
    protected function getAttendanceData(Mahasiswa $mahasiswa): array
    {
        $kelasList = \App\Models\Kelas::whereHas('krsDetail.krs', function ($q) use ($mahasiswa) {
            $q->where('mahasiswa_id', $mahasiswa->id)
              ->where('status', 'approved')
              ->whereHas('tahunAkademik', fn($ta) => $ta->where('is_active', true));
        })->with('mataKuliah')->get();

        $details = [];
        $hasValidData = false;
        $allZeroOrNull = true;

        foreach ($kelasList as $kelas) {
            $rekap = $this->presensiService->getRekapPresensi($mahasiswa->id, $kelas->id);

            // Check if there's actual meeting data
            $hasPertemuan = $rekap['total_pertemuan'] > 0;
            $hasAttendance = ($rekap['hadir'] + $rekap['sakit'] + $rekap['izin'] + $rekap['alpa']) > 0;

            if ($hasPertemuan && $hasAttendance) {
                $hasValidData = true;
                $allZeroOrNull = false;
            }

            $details[] = [
                'mata_kuliah' => $kelas->mataKuliah->nama_mk ?? '-',
                'total_pertemuan' => $rekap['total_pertemuan'],
                'hadir' => $rekap['hadir'],
                'sakit' => $rekap['sakit'],
                'izin' => $rekap['izin'],
                'alpa' => $rekap['alpa'],
                'persentase' => $rekap['persentase'],
                'data_valid' => $hasPertemuan && $hasAttendance,
            ];
        }

        return [
            'data_available' => $hasValidData,
            'all_zero_or_null' => $allZeroOrNull,
            'warning' => $allZeroOrNull ? 'Data presensi belum diinput atau masih default' : null,
            'details' => $details,
        ];
    }

    /**
     * Calculate active semester based on angkatan
     */
    protected function calculateActiveSemester(Mahasiswa $mahasiswa): int
    {
        $angkatan = (int) $mahasiswa->angkatan;
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        // Academic year starts in August
        $yearsEnrolled = $currentYear - $angkatan;
        $semester = $yearsEnrolled * 2;

        // If before August, we're in the even semester of the previous academic year
        if ($currentMonth >= 8) {
            $semester += 1; // Odd semester (Ganjil)
        }

        return max(1, $semester);
    }

    /**
     * Find a course by name in the context
     */
    public function findCourseByName(array $context, string $courseName): ?array
    {
        $courseName = strtolower($courseName);

        // Search in course_statuses
        foreach ($context['course_statuses'] as $course) {
            if (str_contains(strtolower($course['nama']), $courseName)) {
                return $course;
            }
        }

        // Search in curriculum
        foreach ($context['curriculum'] as $semesterData) {
            foreach ($semesterData['mata_kuliah'] as $course) {
                if (str_contains(strtolower($course['nama']), $courseName)) {
                    return [
                        'kode' => $course['kode'],
                        'nama' => $course['nama'],
                        'sks' => $course['sks'],
                        'status' => 'TERSEDIA_DI_KURIKULUM',
                        'nilai' => null,
                        'semester' => $semesterData['semester'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Calculate graduation progress
     */
    public function calculateGraduationProgress(array $context): array
    {
        $sksLulus = $context['academic_summary']['total_sks_lulus'];
        $targetSks = $context['prodi_rules']['graduation_total_sks'];
        $sksSedangDiambil = $context['academic_summary']['total_sks_sedang_diambil'];

        $sksRemaining = max(0, $targetSks - $sksLulus);
        $progressPercent = $targetSks > 0 ? round(($sksLulus / $targetSks) * 100, 1) : 0;

        return [
            'sks_lulus' => $sksLulus,
            'sks_target' => $targetSks,
            'sks_remaining' => $sksRemaining,
            'sks_sedang_diambil' => $sksSedangDiambil,
            'progress_percent' => $progressPercent,
            'eligible_thesis' => $sksLulus >= $context['prodi_rules']['thesis_min_sks'],
            'eligible_internship' => $sksLulus >= $context['prodi_rules']['internship_min_sks'],
        ];
    }
}
