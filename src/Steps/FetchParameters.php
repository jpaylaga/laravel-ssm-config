<?php

namespace Jpaylaga\SsmConfig\Steps;

use Aws\Ssm\SsmClient;
use Illuminate\Contracts\Config\Repository;
use Jpaylaga\SsmConfig\Support\ParameterPath;

/**
 * Fetches every parameter under the configured path, following NextToken until
 * the last page. Shared by the boot loader and the ssm:* commands.
 */
final class FetchParameters
{
    public function __construct(
        private SsmClient $ssm,
        private ParameterPath $path,
        private Repository $config,
    ) {}

    /** @return array<string, string> parameter name (relative to the path) => value, sorted by name */
    public function __invoke(): array
    {
        $parameters = [];
        $nextToken = null;

        do {
            $request = [
                'Path' => (string) $this->path,
                'Recursive' => true,
                'WithDecryption' => (bool) $this->config->get('ssm.with_decryption', true),
            ];

            if ($nextToken !== null) {
                $request['NextToken'] = $nextToken;
            }

            $result = $this->ssm->getParametersByPath($request);

            /** @var list<array{Name: string, Value: string}> $page */
            $page = $result['Parameters'] ?? [];

            foreach ($page as $parameter) {
                $parameters[$this->path->nameOf($parameter['Name'])] = $parameter['Value'];
            }

            /** @var string|null $nextToken */
            $nextToken = $result['NextToken'] ?? null;
        } while ($nextToken !== null && $nextToken !== '');

        ksort($parameters);

        return $parameters;
    }
}
