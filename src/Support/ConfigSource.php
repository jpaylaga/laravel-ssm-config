<?php

namespace Jpaylaga\SsmConfig\Support;

/** Where the running application's config came from, recorded at boot. */
enum ConfigSource: string
{
    /** bootstrap/cache/config.php exists; the boot fetch was skipped. */
    case Cache = 'cache';

    /** No cache existed, so parameters were fetched and applied at boot. */
    case Ssm = 'ssm';

    /** The boot fetch is disabled; config came from the files and .env only. */
    case Disabled = 'disabled';
}
