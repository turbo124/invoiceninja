<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\MockAccountData;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Report\ARSummaryReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Test suite specifically for AR Summary Report chunking behavior
 * Validates that large client datasets don't break SQL whereIn limits
 */
class ARSummaryReportChunkingTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    /**
     * Test that report handles 2500 clients (2.5x chunk size)
     * This validates chunking logic works correctly
     */
    public function testHandlesLargeClientDataset()
    {
        // Create 2500 clients (will require 3 chunks at 1000/chunk)
        $clients = Client::factory()->count(2500)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        // Add a few invoices to random clients to verify data integrity
        $testClients = $clients->random(10);
        foreach ($testClients as $client) {
            Invoice::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $client->id,
                'status_id' => Invoice::STATUS_SENT,
                'balance' => 1000,
                'due_date' => now()->subDays(5), // Current
            ]);
        }

        $input = [
            'date_range' => 'all',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        $report = new ARSummaryReport($this->company, $input);

        // This should not throw SQL errors about packet size or whereIn limits
        DB::enableQueryLog();
        $output = $report->run();
        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        // Verify output is valid CSV
        $this->assertNotEmpty($output);
        $lines = explode("\n", $output);
        $dataLines = array_filter($lines, fn($line) => !empty(trim($line)));
        
        // Should have header + 2500 client rows + some empty lines
        $this->assertGreaterThan(2500, count($dataLines));

        // Verify no query has more than 1000 client IDs in whereIn
        foreach ($queryLog as $query) {
            $sql = $query['query'];
            // Count occurrences of ? in whereIn clause (rough check)
            if (str_contains($sql, 'client_id')) {
                $placeholderCount = substr_count($sql, '?');
                // Should not have more than ~1010 placeholders (1000 IDs + other params)
                $this->assertLessThan(1100, $placeholderCount, 
                    "Query has too many placeholders, chunking may not be working");
            }
        }
    }

    /**
     * Test that chunking preserves data ordering
     */
    public function testChunkingPreservesOrdering()
    {
        // Create clients with predictable balances
        $clients = [];
        for ($i = 0; $i < 50; $i++) {
            $clients[] = Client::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'balance' => 1000 - ($i * 10), // Descending balance
                'number' => 'CLIENT-' . str_pad($i, 3, '0', STR_PAD_LEFT),
            ]);
        }

        $input = [
            'date_range' => 'all',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        $report = new ARSummaryReport($this->company, $input);
        $output = $report->run();

        // Extract client numbers from output
        $lines = explode("\n", $output);
        $clientNumbers = [];
        foreach ($lines as $line) {
            if (preg_match('/CLIENT-(\d+)/', $line, $matches)) {
                $clientNumbers[] = (int)$matches[1];
            }
        }

        // Verify clients appear in ascending number order (descending balance)
        $this->assertEquals(range(0, 49), $clientNumbers, 
            'Clients should maintain balance DESC ordering across chunks');
    }

    /**
     * Test memory efficiency with large dataset
     */
    public function testMemoryEfficiencyWithChunking()
    {
        // Create 1500 clients (will use 2 chunks)
        Client::factory()->count(1500)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $input = [
            'date_range' => 'all',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        $memoryBefore = memory_get_usage();
        $report = new ARSummaryReport($this->company, $input);
        $output = $report->run();
        $memoryAfter = memory_get_usage();

        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB

        // With chunking, memory usage should be reasonable (<50MB for 1500 clients)
        $this->assertLessThan(50, $memoryUsed, 
            "Memory usage should be < 50MB with chunking, got {$memoryUsed}MB");
        
        // Verify output is valid
        $this->assertNotEmpty($output);
    }
}
