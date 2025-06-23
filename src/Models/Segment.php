<?php

namespace Monitor\Models;

use Inspector\Models\Segment as BaseSegment;

class Segment extends BaseSegment
{
    protected ?Error $error = null;

    public function getError(): ?Error
    {
        return $this->error;
    }

    public function setError(Error $error): self
    {
        $this->error = $error;

        return $this;
    }
}
