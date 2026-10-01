<?php
/**
 * Neo4j HTTP API client (no external driver needed).
 *
 * Communicates with Neo4j's transactional HTTP endpoint at port 7474.
 * Connection failures are lazy: errors surface only at execute() time.
 *
 * Requires: Neo4j running with HTTP enabled (default).
 */

function db_neo4j(): Neo4jHttp {
    static $client = null;
    if ($client === null) {
        $host = getenv('NEO4J_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('NEO4J_HTTP_PORT') ?: 7474);
        $user = getenv('NEO4J_USER') ?: 'neo4j';
        $pass = getenv('NEO4J_PASS') ?: 'password';

        $client = new Neo4jHttp("http://{$host}:{$port}", $user, $pass);
    }
    return $client;
}

/**
 * Thin HTTP wrapper for Neo4j transactional Cypher endpoint.
 */
class Neo4jHttp
{
    private string $baseUrl;
    private string $user;
    private string $pass;

    public function __construct(string $baseUrl, string $user, string $pass)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->user = $user;
        $this->pass = $pass;
    }

    /**
     * Run a Cypher statement with parameters. Returns array of records.
     *
     * @param array<string,mixed> $params
     * @return array<int,array<string,mixed>> rows keyed by column name
     * @throws RuntimeException on connection or HTTP error
     */
    public function run(string $cypher, array $params = []): array
    {
        $url = $this->baseUrl . '/db/data/transaction/commit';

        $body = json_encode([
            'statements' => [[
                'statement'  => $cypher,
                'parameters' => (object)$params,
            ]],
        ], JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_USERPWD => "{$this->user}:{$this->pass}",
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        ]);

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException('Neo4j connection failed: ' . $err);
        }
        $data = json_decode($resp, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid Neo4j response: ' . substr($resp, 0, 200));
        }

        if (!empty($data['errors'])) {
            $msg = $data['errors'][0]['message'] ?? 'Unknown Neo4j error';
            throw new RuntimeException('Neo4j: ' . $msg);
        }

        $rows = [];
        foreach ($data['results'] ?? [] as $result) {
            $columns = $result['columns'] ?? [];
            foreach ($result['data'] ?? [] as $row) {
                $assoc = [];
                foreach ($columns as $i => $col) {
                    $v = $row['row'][$i] ?? null;
                    // Neo4j HTTP returns nodes as { properties: {...} }
                    if (is_array($v) && isset($v['properties']) && is_array($v['properties'])) {
                        // keep both - some callers want labels too
                        $assoc[$col] = $v['properties'];
                    } else {
                        $assoc[$col] = $v;
                    }
                }
                $rows[] = $assoc;
            }
        }
        return $rows;
    }
}