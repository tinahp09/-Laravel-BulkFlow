<?php

declare(strict_types=1);

namespace BulkFlow\Progress;

final class NullProgressPublisher implements ProgressPublisher
{
    public function publish(ProgressSnapshot $snapshot): void {}
}
