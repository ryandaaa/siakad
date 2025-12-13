<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use App\Models\Mahasiswa;
use App\Services\AcademicAdvisor\AdvisorContextBuilder;
use App\Services\AcademicAdvisor\AdvisorGuards;

class AiAdvisorService
{
    protected AdvisorContextBuilder $contextBuilder;
    protected AdvisorGuards $guards;
    protected string $apiKey;
    protected string $model = 'llama-3.3-70b-versatile';

    protected const MAX_RETRIES = 1;

    public function __construct(
        AdvisorContextBuilder $contextBuilder,
        AdvisorGuards $guards
    ) {
        $this->contextBuilder = $contextBuilder;
        $this->guards = $guards;
        $this->apiKey = config('services.groq.api_key', '');
    }

    /**
     * Send a chat message to Groq with grounded student context
     */
    public function chat(Mahasiswa $mahasiswa, string $message, array $history = []): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'API key belum dikonfigurasi. Silakan hubungi administrator.',
            ];
        }

        try {
            // Step 1: Build context
            $context = $this->contextBuilder->build($mahasiswa);

            // Step 2: Run pre-guards
            $this->guards->assertRulesPresent($context);
            $this->guards->validateContext($context);

            // Step 3: Build system prompt with context
            $systemPrompt = $this->buildSystemPrompt($context);

            // Step 4: Call LLM
            $response = $this->callLlm($systemPrompt, $message, $history);

            if (!$response['success']) {
                return $response;
            }

            $output = $response['message'];

            // Step 5: Run post-guards
            $guardResult = $this->guards->runPostGuards($context, $output);

            if (!$guardResult['passed']) {
                // Try retry if allowed
                if ($guardResult['should_retry'] && $guardResult['retry_prompt']) {
                    $retryResponse = $this->retryWithGuardPrompt(
                        $systemPrompt,
                        $message,
                        $output,
                        $guardResult['retry_prompt'],
                        $history
                    );

                    if ($retryResponse['success']) {
                        // Check guards again on retry
                        $retryGuardResult = $this->guards->runPostGuards($context, $retryResponse['message']);
                        if ($retryGuardResult['passed']) {
                            return $retryResponse;
                        }
                    }
                }

                // Use replacement output if guard provides one
                if ($guardResult['replacement_output']) {
                    return [
                        'success' => true,
                        'message' => $guardResult['replacement_output'],
                        'guard_applied' => true,
                    ];
                }
            }

            return [
                'success' => true,
                'message' => $output,
            ];

        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'message' => 'Konfigurasi akademik tidak valid: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build system prompt from template with context
     */
    protected function buildSystemPrompt(array $context): string
    {
        $templatePath = resource_path('prompts/academic_advisor_system.txt');

        if (File::exists($templatePath)) {
            $template = File::get($templatePath);
        } else {
            $template = $this->getDefaultPromptTemplate();
        }

        // Inject context JSON
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $prompt = str_replace('{{CONTEXT_JSON}}', $contextJson, $template);

        return $prompt;
    }

    /**
     * Call LLM API
     */
    protected function callLlm(string $systemPrompt, string $message, array $history = []): array
    {
        $messages = [];

        // Add system prompt
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt
        ];

        // Add conversation history
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['content']
            ];
        }

        // Add current message
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.3, // Lower temperature for more deterministic outputs
                    'max_completion_tokens' => 1024,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? 'Maaf, saya tidak bisa memberikan respons saat ini.';

                return [
                    'success' => true,
                    'message' => $text,
                ];
            }

            $error = $response->json();
            return [
                'success' => false,
                'message' => 'Gagal mendapatkan respons dari AI: ' . ($error['error']['message'] ?? 'Unknown error'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan koneksi: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retry with guard prompt
     */
    protected function retryWithGuardPrompt(
        string $systemPrompt,
        string $originalMessage,
        string $previousOutput,
        string $guardPrompt,
        array $history
    ): array {
        $messages = [];

        // Add system prompt
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt
        ];

        // Add history
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['content']
            ];
        }

        // Add original message
        $messages[] = [
            'role' => 'user',
            'content' => $originalMessage
        ];

        // Add previous (problematic) output
        $messages[] = [
            'role' => 'assistant',
            'content' => $previousOutput
        ];

        // Add guard retry prompt
        $messages[] = [
            'role' => 'user',
            'content' => $guardPrompt
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.1, // Even lower for retry
                    'max_completion_tokens' => 1024,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? '';

                return [
                    'success' => true,
                    'message' => $text,
                    'is_retry' => true,
                ];
            }

            return [
                'success' => false,
                'message' => 'Retry failed',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Retry failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get the context builder instance
     */
    public function getContextBuilder(): AdvisorContextBuilder
    {
        return $this->contextBuilder;
    }

    /**
     * Get the guards instance
     */
    public function getGuards(): AdvisorGuards
    {
        return $this->guards;
    }

    /**
     * Default prompt template if file not found
     */
    protected function getDefaultPromptTemplate(): string
    {
        return <<<'PROMPT'
<SYSTEM_IDENTITY>
Kamu adalah AI Academic Advisor untuk SIAKAD. Jawab HANYA berdasarkan data context JSON.
</SYSTEM_IDENTITY>

<GROUNDING_RULES>
1. HANYA gunakan data dari context JSON
2. JANGAN menggunakan asumsi umum (biasanya, umumnya, tergantung)
3. Jika data tidak ada, katakan "data belum tersedia"
4. Gunakan status: LULUS, SEDANG_DIAMBIL, TERSEDIA_DI_KURIKULUM
5. Jangan simpulkan presensi rendah jika attendance.data_available = false
</GROUNDING_RULES>

<DATA_CONTEXT>
{{CONTEXT_JSON}}
</DATA_CONTEXT>
PROMPT;
    }

    /**
     * Build context for external use (e.g., testing)
     */
    public function buildContext(Mahasiswa $mahasiswa): array
    {
        return $this->contextBuilder->build($mahasiswa);
    }

    /**
     * Calculate graduation progress
     */
    public function calculateGraduationProgress(Mahasiswa $mahasiswa): array
    {
        $context = $this->contextBuilder->build($mahasiswa);
        return $this->contextBuilder->calculateGraduationProgress($context);
    }

    /**
     * Find course by name
     */
    public function findCourse(Mahasiswa $mahasiswa, string $courseName): ?array
    {
        $context = $this->contextBuilder->build($mahasiswa);
        return $this->contextBuilder->findCourseByName($context, $courseName);
    }
}
