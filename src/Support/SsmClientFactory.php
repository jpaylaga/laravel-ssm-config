<?php

namespace Jpaylaga\SsmConfig\Support;

use Aws\Ssm\SsmClient;
use InvalidArgumentException;

/**
 * Builds the SSM client. Credentials come from the SDK's default chain, which on
 * EC2 means the instance profile; nothing is read from .env for this.
 */
final class SsmClientFactory
{
    public function __invoke(?string $region): SsmClient
    {
        if ($region === null || $region === '') {
            throw new InvalidArgumentException('ssm.region is not set: set SSM_REGION or AWS_DEFAULT_REGION.');
        }

        return new SsmClient([
            'region' => $region,
            'version' => '2014-11-06',
        ]);
    }
}
