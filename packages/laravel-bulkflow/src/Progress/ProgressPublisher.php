<?php

declare(strict_types=1);

namespace BulkFlow\Progress;

interface ProgressPublisher
{
    public function publish(ProgressSnapshot $snapshot): void;
}
