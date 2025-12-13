<?php

namespace Tests\Feature\AcademicAdvisor;

use Tests\TestCase;
use App\Models\User;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Fakultas;
use App\Models\Dosen;
use App\Models\MataKuliah;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KrsDetail;
use App\Models\Nilai;
use App\Models\TahunAkademik;
use App\Services\AcademicAdvisor\AdvisorContextBuilder;
use App\Services\AcademicAdvisor\AdvisorGuards;
use App\Services\AiAdvisorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AcademicAdvisorTest extends TestCase
{
    use RefreshDatabase;

    protected AdvisorContextBuilder $contextBuilder;
    protected AdvisorGuards $guards;
    protected Mahasiswa $mahasiswa;
    protected TahunAkademik $activeTahunAkademik;
    protected Dosen $dosen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contextBuilder = app(AdvisorContextBuilder::class);
        $this->guards = app(AdvisorGuards::class);

        // Create test data
        $this->setupTestData();
    }

    protected function setupTestData(): void
    {
        // Create fakultas and prodi
        $fakultas = Fakultas::create(['nama' => 'Fakultas Teknik']);
        $prodi = Prodi::create([
            'fakultas_id' => $fakultas->id,
            'nama' => 'Sistem Informasi',
        ]);

        // Create dosen
        $dosenUser = User::create([
            'name' => 'Test Dosen',
            'email' => 'dosen@test.com',
            'password' => bcrypt('password'),
            'role' => 'dosen',
        ]);

        $this->dosen = Dosen::create([
            'user_id' => $dosenUser->id,
            'nidn' => '1234567890',
            'prodi_id' => $prodi->id,
        ]);

        // Create user and mahasiswa
        $user = User::create([
            'name' => 'Test Mahasiswa',
            'email' => 'test@mahasiswa.com',
            'password' => bcrypt('password'),
            'role' => 'mahasiswa',
        ]);

        $this->mahasiswa = Mahasiswa::create([
            'user_id' => $user->id,
            'nim' => '2303113649',
            'prodi_id' => $prodi->id,
            'angkatan' => 2023,
            'status' => 'aktif',
        ]);

        // Create active tahun akademik
        $this->activeTahunAkademik = TahunAkademik::create([
            'tahun' => '2024/2025',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        // Create some completed courses (LULUS) - total 87 SKS
        $this->createCompletedCourses(87);
    }

    protected function createCompletedCourses(int $targetSks): void
    {
        $sksCreated = 0;
        $semesterNum = 1;
        $courseNum = 1;

        while ($sksCreated < $targetSks) {
            $sks = min(3, $targetSks - $sksCreated);

            $mk = MataKuliah::create([
                'kode_mk' => 'MK' . str_pad($courseNum, 3, '0', STR_PAD_LEFT),
                'nama_mk' => 'Mata Kuliah ' . $courseNum,
                'sks' => $sks,
                'semester' => $semesterNum,
            ]);

            $tahunAkademik = TahunAkademik::create([
                'tahun' => '2023/2024',
                'semester' => $semesterNum % 2 == 1 ? 'Ganjil' : 'Genap',
                'is_active' => false,
            ]);

            $krs = Krs::create([
                'mahasiswa_id' => $this->mahasiswa->id,
                'tahun_akademik_id' => $tahunAkademik->id,
                'status' => 'approved',
            ]);

            $kelas = Kelas::create([
                'mata_kuliah_id' => $mk->id,
                'dosen_id' => $this->dosen->id,
                'nama_kelas' => 'Kelas A',
                'kapasitas' => 40,
            ]);

            KrsDetail::create([
                'krs_id' => $krs->id,
                'kelas_id' => $kelas->id,
            ]);

            Nilai::create([
                'mahasiswa_id' => $this->mahasiswa->id,
                'kelas_id' => $kelas->id,
                'nilai_angka' => 85,
                'nilai_huruf' => 'A',
                'status' => 'final',
            ]);

            $sksCreated += $sks;
            $courseNum++;

            if ($courseNum % 6 == 0) {
                $semesterNum++;
            }
        }
    }

    /**
     * Test 1: it_returns_total_sks_and_progress_using_144_rule
     * Input: total lulus 87, rule 144
     * Expected: progress ≈ 60% (57 SKS remaining)
     */
    public function test_it_returns_total_sks_and_progress_using_144_rule(): void
    {
        $context = $this->contextBuilder->build($this->mahasiswa);
        $progress = $this->contextBuilder->calculateGraduationProgress($context);

        // Expected: 87 SKS lulus, 144 target, 57 remaining, ~60.4% progress
        $this->assertEquals(87, $progress['sks_lulus']);
        $this->assertEquals(144, $progress['sks_target']);
        $this->assertEquals(57, $progress['sks_remaining']);
        $this->assertEqualsWithDelta(60.4, $progress['progress_percent'], 0.5);

        // Verify prodi rules loaded correctly
        $this->assertEquals(144, $context['prodi_rules']['graduation_total_sks']);
    }

    /**
     * Test 2: it_denies_thesis_if_sks_less_than_144
     * Expected: "TIDAK" + "kurang 57 SKS"
     */
    public function test_it_denies_thesis_if_sks_less_than_144(): void
    {
        $context = $this->contextBuilder->build($this->mahasiswa);
        $progress = $this->contextBuilder->calculateGraduationProgress($context);

        // With 87 SKS, should NOT be eligible for thesis (requires 144)
        $this->assertFalse($progress['eligible_thesis']);
        $this->assertEquals(57, $progress['sks_remaining']);

        // Verify thesis min sks rule
        $this->assertEquals(144, $context['prodi_rules']['thesis_min_sks']);
    }

    /**
     * Test 3: it_finds_big_data_from_curriculum_semester_7
     * Expected: Big Data semester 7, status "TERSEDIA_DI_KURIKULUM"
     */
    public function test_it_finds_big_data_from_curriculum_semester_7(): void
    {
        $context = $this->contextBuilder->build($this->mahasiswa);

        // Search for Big Data in course statuses
        $bigDataCourse = $this->contextBuilder->findCourseByName($context, 'Big Data');

        $this->assertNotNull($bigDataCourse);
        $this->assertEquals('Big Data', $bigDataCourse['nama']);
        $this->assertEquals(7, $bigDataCourse['semester']);
        $this->assertEquals('TERSEDIA_DI_KURIKULUM', $bigDataCourse['status']);

        // Also verify it's in curriculum
        $semester7 = collect($context['curriculum'])->firstWhere('semester', 7);
        $this->assertNotNull($semester7);

        $bigDataInCurriculum = collect($semester7['mata_kuliah'])->firstWhere('nama', 'Big Data');
        $this->assertNotNull($bigDataInCurriculum);
        $this->assertEquals(3, $bigDataInCurriculum['sks']);
    }

    /**
     * Test 4: it_does_not_flag_attendance_low_if_attendance_data_missing
     * When attendance_data_available=false
     * Expected: mention "data presensi belum tersedia", not "presensi rendah"
     */
    public function test_it_does_not_flag_attendance_low_if_attendance_data_missing(): void
    {
        // Create an enrolled course in active semester (no meeting data)
        $enrolledMk = MataKuliah::create([
            'kode_mk' => 'ATTEND01',
            'nama_mk' => 'Course Without Attendance',
            'sks' => 3,
            'semester' => 5,
        ]);

        $enrolledKelas = Kelas::create([
            'mata_kuliah_id' => $enrolledMk->id,
            'dosen_id' => $this->dosen->id,
            'nama_kelas' => 'Kelas No Attendance',
            'kapasitas' => 40,
        ]);

        $activeKrs = Krs::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->activeTahunAkademik->id,
            'status' => 'approved',
        ]);

        KrsDetail::create([
            'krs_id' => $activeKrs->id,
            'kelas_id' => $enrolledKelas->id,
        ]);

        $context = $this->contextBuilder->build($this->mahasiswa);

        // Since we haven't created any pertemuan, attendance should be unavailable
        $this->assertFalse($context['attendance']['data_available']);
        $this->assertTrue($context['attendance']['all_zero_or_null']);
        $this->assertNotNull($context['attendance']['warning']);

        // Test guard with output that mentions low attendance
        $badOutput = 'Berdasarkan data, presensi rendah dan perlu diperbaiki.';
        $guardResult = $this->guards->attendanceGuard($context, $badOutput);

        $this->assertEquals(AdvisorGuards::GUARD_FAIL, $guardResult['status']);
        $this->assertNotNull($guardResult['issue']);
        $this->assertStringContainsString('belum tersedia', $guardResult['recommended_response']);
    }

    /**
     * Test 5: it_rejects_generic_assumption_phrases
     * If output contains "biasanya/umumnya/tergantung"
     * Expected: retry or sanitized output
     */
    public function test_it_rejects_generic_assumption_phrases(): void
    {
        // Test each forbidden phrase
        $forbiddenOutputs = [
            'Biasanya SKS kelulusan adalah 120 SKS.',
            'Umumnya mahasiswa semester 5 sudah bisa mengambil skripsi.',
            'Hal ini tergantung kebijakan prodi masing-masing.',
            'Pada umumnya mata kuliah Big Data ada di semester atas.',
        ];

        foreach ($forbiddenOutputs as $output) {
            $result = $this->guards->preventGenericAssumptions($output);

            $this->assertEquals(
                AdvisorGuards::GUARD_RETRY,
                $result['status'],
                "Expected GUARD_RETRY for output: {$output}"
            );
            $this->assertNotEmpty($result['violations']);
            $this->assertNotNull($result['retry_prompt']);
            $this->assertNotNull($result['sanitized_output']);
        }

        // Test good output (should pass)
        $goodOutput = 'Berdasarkan aturan Prodi Sistem Informasi UNRI, total SKS kelulusan adalah 144 SKS.';
        $result = $this->guards->preventGenericAssumptions($goodOutput);

        $this->assertEquals(AdvisorGuards::GUARD_PASS, $result['status']);
        $this->assertEmpty($result['violations']);
    }

    /**
     * Test 6: it_distinguishes_completed_vs_enrolled_vs_available
     * Completed from KHS, enrolled from KRS, available from curriculum.
     */
    public function test_it_distinguishes_completed_vs_enrolled_vs_available(): void
    {
        // Create an enrolled course (in active KRS, no grade yet)
        $enrolledMk = MataKuliah::create([
            'kode_mk' => 'ENROLLED01',
            'nama_mk' => 'Enrolled Course',
            'sks' => 3,
            'semester' => 5,
        ]);

        $enrolledKelas = Kelas::create([
            'mata_kuliah_id' => $enrolledMk->id,
            'dosen_id' => $this->dosen->id,
            'nama_kelas' => 'Kelas Enrolled',
            'kapasitas' => 40,
        ]);

        $activeKrs = Krs::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->activeTahunAkademik->id,
            'status' => 'approved',
        ]);

        KrsDetail::create([
            'krs_id' => $activeKrs->id,
            'kelas_id' => $enrolledKelas->id,
        ]);

        // Build context and check statuses
        $context = $this->contextBuilder->build($this->mahasiswa);

        $statuses = collect($context['course_statuses']);

        // Check LULUS courses (from our setUp)
        $completedCourses = $statuses->where('status', 'LULUS');
        $this->assertGreaterThan(0, $completedCourses->count());

        // Check SEDANG_DIAMBIL course
        $enrolledCourse = $statuses->firstWhere('kode', 'ENROLLED01');
        $this->assertNotNull($enrolledCourse, 'Enrolled course should exist in statuses');
        $this->assertEquals('SEDANG_DIAMBIL', $enrolledCourse['status']);

        // Check TERSEDIA_DI_KURIKULUM (from config)
        $availableCourses = $statuses->where('status', 'TERSEDIA_DI_KURIKULUM');
        $this->assertGreaterThan(0, $availableCourses->count());

        // Specifically check Big Data is available
        $bigData = $statuses->first(fn($c) => str_contains($c['nama'], 'Big Data'));
        $this->assertNotNull($bigData);
        $this->assertEquals('TERSEDIA_DI_KURIKULUM', $bigData['status']);
    }

    /**
     * Additional test: Guards validation works correctly
     */
    public function test_guards_assert_rules_present(): void
    {
        $context = $this->contextBuilder->build($this->mahasiswa);

        // Should not throw exception with valid context
        $this->guards->assertRulesPresent($context);
        $this->guards->validateContext($context);

        // Should throw with invalid context
        $this->expectException(\InvalidArgumentException::class);
        $this->guards->assertRulesPresent([
            'prodi_rules' => ['graduation_total_sks' => 0]
        ]);
    }

    /**
     * Additional test: Full guard pipeline works
     */
    public function test_full_guard_pipeline(): void
    {
        $context = $this->contextBuilder->build($this->mahasiswa);

        // Good output should pass
        $goodOutput = 'Anda telah menyelesaikan 87 dari 144 SKS (60.4%). Tersisa 57 SKS untuk lulus.';
        $result = $this->guards->runPostGuards($context, $goodOutput);

        $this->assertTrue($result['passed']);
        $this->assertEmpty($result['issues']);

        // Bad output with assumption should fail
        $badOutput = 'Biasanya mahasiswa membutuhkan sekitar 120-140 SKS untuk lulus.';
        $result = $this->guards->runPostGuards($context, $badOutput);

        $this->assertFalse($result['passed']);
        $this->assertNotEmpty($result['issues']);
        $this->assertTrue($result['should_retry']);
    }
}
