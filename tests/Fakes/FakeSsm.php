<?php

namespace Jpaylaga\SsmConfig\Tests\Fakes;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\Ssm\SsmClient;

/** An SSM client that answers GetParametersByPath from queued pages, with no network. */
final class FakeSsm
{
    private MockHandler $handler;

    /** @var list<CommandInterface> every command the client sent */
    public array $commands = [];

    public function __construct()
    {
        $this->handler = new MockHandler;
    }

    public function client(): SsmClient
    {
        return new SsmClient([
            'region' => 'us-east-1',
            'version' => '2014-11-06',
            'credentials' => false,
            'handler' => $this->handler,
        ]);
    }

    /**
     * Queue one page per array. A page is name => value, with "/app/tenant-a/"
     * prepended to the names; every page but the last carries a NextToken.
     *
     * @param  array<string, string>  ...$pages
     */
    public function hasParameters(array ...$pages): void
    {
        $pages = $pages === [] ? [[]] : $pages;
        $last = count($pages) - 1;

        foreach ($pages as $index => $page) {
            $this->handler->append(function (CommandInterface $command) use ($page, $index, $last) {
                $this->commands[] = $command;

                $parameters = [];

                foreach ($page as $name => $value) {
                    $parameters[] = ['Name' => '/app/tenant-a/'.$name, 'Type' => 'SecureString', 'Value' => $value];
                }

                return new Result(array_filter([
                    'Parameters' => $parameters,
                    'NextToken' => $index < $last ? 'page-'.($index + 1) : null,
                ]));
            });
        }
    }
}
