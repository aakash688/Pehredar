<?php
/**
 * Comprehensive Performance Analyzer
 * 
 * This tool identifies performance bottlenecks and suggests optimizations
 * for industrial-level application performance with 3000-4000 active users per hour.
 */

require_once __DIR__ . '/../helpers/ConnectionPool.php';
require_once __DIR__ . '/../helpers/database.php';

class PerformanceAnalyzer {
    private $pdo;
    private $results = [];
    
    public function __construct() {
        $this->pdo = ConnectionPool::getConnection();
    }
    
    /**
     * Run comprehensive performance analysis
     */
    public function runAnalysis() {
        echo "=== COMPREHENSIVE PERFORMANCE ANALYSIS ===\n";
        echo "Analyzing system for 3000-4000 users/hour capacity\n\n";
        
        $this->analyzeDatabasePerformance();
        $this->analyzeSlowQueries();
        $this->analyzeIndexUsage();
        $this->analyzeTableStructure();
        $this->analyzeConnectionPerformance();
        $this->suggestOptimizations();
        
        return $this->results;
    }
    
    /**
     * Analyze database performance metrics
     */
    private function analyzeDatabasePerformance() {
        echo "--- DATABASE PERFORMANCE METRICS ---\n";
        
        try {
            // Get MySQL performance variables
            $queries = [
                'slow_query_log' => "SHOW VARIABLES LIKE 'slow_query_log'",
                'slow_query_time' => "SHOW VARIABLES LIKE 'long_query_time'", 
                'query_cache' => "SHOW VARIABLES LIKE 'query_cache%'",
                'innodb_buffer' => "SHOW VARIABLES LIKE 'innodb_buffer_pool_size'",
                'max_connections' => "SHOW VARIABLES LIKE 'max_connections'",
                'key_buffer' => "SHOW VARIABLES LIKE 'key_buffer_size'"
            ];
            
            foreach ($queries as $name => $query) {
                try {
                    $result = $this->pdo->query($query)->fetchAll();
                    echo "$name:\n";
                    foreach ($result as $row) {
                        echo "  {$row['Variable_name']}: {$row['Value']}\n";
                    }
                } catch (Exception $e) {
                    echo "  ❌ Could not retrieve $name: " . $e->getMessage() . "\n";
                }
            }
            
            // Get status information
            echo "\n--- DATABASE STATUS ---\n";
            $statusQueries = [
                "SHOW STATUS LIKE 'Slow_queries'",
                "SHOW STATUS LIKE 'Questions'", 
                "SHOW STATUS LIKE 'Uptime'",
                "SHOW STATUS LIKE 'Threads_connected'",
                "SHOW STATUS LIKE 'Threads_running'",
                "SHOW STATUS LIKE 'Table_locks_waited'",
                "SHOW STATUS LIKE 'Select_full_join'",
                "SHOW STATUS LIKE 'Select_scan'"
            ];
            
            foreach ($statusQueries as $query) {
                try {
                    $result = $this->pdo->query($query)->fetch();
                    echo "{$result['Variable_name']}: {$result['Value']}\n";
                } catch (Exception $e) {
                    echo "❌ Status query failed: " . $e->getMessage() . "\n";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Database analysis error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Analyze potentially slow queries by examining common patterns
     */
    private function analyzeSlowQueries() {
        echo "\n--- SLOW QUERY ANALYSIS ---\n";
        
        $problematicQueries = [
            'missing_limits' => [
                'description' => 'Queries without LIMIT clauses',
                'files_to_check' => ['actions/', 'mobileappapis/', 'UI/'],
                'pattern' => '/SELECT.*FROM.*(?!.*LIMIT)/i'
            ],
            'n_plus_one' => [
                'description' => 'Potential N+1 query problems', 
                'files_to_check' => ['helpers/', 'actions/'],
                'pattern' => '/foreach.*\$.*query|for.*\$.*SELECT/i'
            ],
            'complex_joins' => [
                'description' => 'Complex multi-table JOINs',
                'files_to_check' => ['actions/', 'UI/', 'mobileappapis/'],
                'pattern' => '/JOIN.*JOIN.*JOIN/i'
            ]
        ];
        
        foreach ($problematicQueries as $type => $config) {
            echo "Checking for: {$config['description']}\n";
            $this->scanForQueryPatterns($type, $config);
        }
    }
    
    /**
     * Analyze database indexes and suggest improvements
     */
    private function analyzeIndexUsage() {
        echo "\n--- INDEX ANALYSIS ---\n";
        
        try {
            // Get all tables
            $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                try {
                    echo "\nTable: $table\n";
                    
                    // Get table info
                    $tableInfo = $this->pdo->query("SHOW TABLE STATUS LIKE '$table'")->fetch();
                    echo "  Rows: " . number_format($tableInfo['Rows']) . "\n";
                    echo "  Data Size: " . $this->formatBytes($tableInfo['Data_length']) . "\n";
                    echo "  Index Size: " . $this->formatBytes($tableInfo['Index_length']) . "\n";
                    
                    // Get indexes
                    $indexes = $this->pdo->query("SHOW INDEX FROM $table")->fetchAll();
                    echo "  Indexes: " . count($indexes) . "\n";
                    
                    foreach ($indexes as $index) {
                        echo "    - {$index['Key_name']} ({$index['Column_name']})\n";
                    }
                    
                    // Suggest missing indexes based on common query patterns
                    $this->suggestMissingIndexes($table);
                    
                } catch (Exception $e) {
                    echo "  ❌ Error analyzing table $table: " . $e->getMessage() . "\n";
                }
            }
        } catch (Exception $e) {
            echo "❌ Index analysis error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Analyze table structure for optimization opportunities
     */
    private function analyzeTableStructure() {
        echo "\n--- TABLE STRUCTURE ANALYSIS ---\n";
        
        $criticalTables = [
            'users', 'attendance', 'salary_records', 'roster', 
            'society_onboarding_data', 'teams', 'advance_payments',
            'tickets', 'audit_logs'
        ];
        
        foreach ($criticalTables as $table) {
            try {
                echo "\nAnalyzing: $table\n";
                
                // Get row count
                $count = $this->pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                echo "  Records: " . number_format($count) . "\n";
                
                // Check for large text/blob columns
                $columns = $this->pdo->query("DESCRIBE $table")->fetchAll();
                $largeColumns = [];
                foreach ($columns as $col) {
                    if (strpos(strtolower($col['Type']), 'text') !== false || 
                        strpos(strtolower($col['Type']), 'blob') !== false) {
                        $largeColumns[] = $col['Field'];
                    }
                }
                
                if (!empty($largeColumns)) {
                    echo "  ⚠️  Large columns: " . implode(', ', $largeColumns) . "\n";
                    echo "     Consider normalizing or optimizing these columns\n";
                }
                
                // Check for missing primary keys or indexes
                $primaryKey = false;
                foreach ($columns as $col) {
                    if ($col['Key'] === 'PRI') {
                        $primaryKey = true;
                        break;
                    }
                }
                
                if (!$primaryKey) {
                    echo "  ❌ Missing primary key - this will severely impact performance\n";
                }
                
            } catch (Exception $e) {
                echo "  ❌ Error analyzing $table: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Test connection performance with load simulation
     */
    private function analyzeConnectionPerformance() {
        echo "\n--- CONNECTION PERFORMANCE TEST ---\n";
        
        $testCases = [
            ['iterations' => 10, 'description' => 'Light load (10 requests)'],
            ['iterations' => 50, 'description' => 'Medium load (50 requests)'],
            ['iterations' => 100, 'description' => 'Heavy load (100 requests)']
        ];
        
        foreach ($testCases as $test) {
            echo "Testing: {$test['description']}\n";
            
            $start = microtime(true);
            $errors = 0;
            
            for ($i = 0; $i < $test['iterations']; $i++) {
                try {
                    $pdo = ConnectionPool::getConnection();
                    $result = $pdo->query("SELECT CONNECTION_ID(), NOW()")->fetch();
                } catch (Exception $e) {
                    $errors++;
                }
            }
            
            $duration = (microtime(true) - $start) * 1000;
            $avgTime = $duration / $test['iterations'];
            
            echo "  Total time: " . round($duration, 2) . "ms\n";
            echo "  Average per request: " . round($avgTime, 2) . "ms\n";
            echo "  Errors: $errors\n";
            
            if ($avgTime < 10) {
                echo "  ✅ EXCELLENT performance\n";
            } elseif ($avgTime < 50) {
                echo "  ✅ GOOD performance\n";
            } else {
                echo "  ⚠️  SLOW performance - needs optimization\n";
            }
            echo "\n";
        }
    }
    
    /**
     * Suggest specific optimizations
     */
    private function suggestOptimizations() {
        echo "\n=== OPTIMIZATION RECOMMENDATIONS ===\n";
        
        $recommendations = [
            'CRITICAL' => [
                'Add Redis/Memcached caching for frequently accessed data',
                'Implement query result caching for dashboard widgets',
                'Add composite indexes on frequently JOINed columns',
                'Optimize salary calculation queries with proper indexing',
                'Use LIMIT clauses on all SELECT queries'
            ],
            'HIGH PRIORITY' => [
                'Enable MySQL query cache if available',
                'Implement API response caching',
                'Add database-level pagination for large result sets',
                'Optimize attendance queries with date range indexes',
                'Use prepared statements consistently'
            ],
            'MEDIUM PRIORITY' => [
                'Compress large text columns or move to separate tables',
                'Implement lazy loading for dashboard components',
                'Add monitoring for slow queries',
                'Optimize image uploads and serving',
                'Use CDN for static assets'
            ]
        ];
        
        foreach ($recommendations as $priority => $items) {
            echo "\n$priority:\n";
            foreach ($items as $item) {
                echo "  • $item\n";
            }
        }
        
        echo "\n=== IMMEDIATE ACTIONS FOR 3000-4000 USERS/HOUR ===\n";
        echo "1. Implement caching layer (Redis/Memcached)\n";
        echo "2. Add missing database indexes\n";
        echo "3. Optimize mobile API response sizes\n";
        echo "4. Enable MySQL query cache\n";
        echo "5. Add proper LIMIT clauses to prevent large result sets\n";
        echo "6. Implement API rate limiting\n";
        echo "7. Use database connection pooling (✅ Already implemented)\n";
    }
    
    /**
     * Helper method to suggest missing indexes
     */
    private function suggestMissingIndexes($table) {
        $commonPatterns = [
            'users' => ['email_id', 'mobile_number', 'user_type', 'created_at'],
            'attendance' => ['user_id', 'attendance_date', 'attendance_master_id'],
            'salary_records' => ['user_id', 'month', 'year', 'created_at'],
            'roster' => ['guard_id', 'society_id', 'team_id', 'shift_id'],
            'tickets' => ['user_id', 'status', 'priority', 'created_at'],
            'advance_payments' => ['employee_id', 'status', 'created_at'],
            'audit_logs' => ['user_id', 'created_at', 'table_name']
        ];
        
        if (isset($commonPatterns[$table])) {
            echo "  📝 Suggested indexes for $table:\n";
            foreach ($commonPatterns[$table] as $column) {
                echo "    - CREATE INDEX idx_{$table}_{$column} ON $table ($column);\n";
            }
        }
    }
    
    /**
     * Scan files for problematic query patterns
     */
    private function scanForQueryPatterns($type, $config) {
        // This is a simplified implementation
        // In practice, you'd scan actual files for these patterns
        echo "  📝 Pattern analysis for {$config['description']} - implement file scanning\n";
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Run the analysis
$analyzer = new PerformanceAnalyzer();
$analyzer->runAnalysis();
?>
